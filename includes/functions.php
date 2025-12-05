<?php
require_once 'db.php';

/**
 * Lấy IP address của người dùng
 */
function getUserIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if (isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if (isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if (isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    
    return $ipaddress;
}

/**
 * Kiểm tra số lần sử dụng tool trong ngày
 */
function checkDailyLimit($tool_type) {
    $ip = getUserIP();
    $today = date('Y-m-d');
    
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($tool_type == 'email') {
        $table = 'email_checks';
    } else {
        $table = 'seo_checks';
    }
    
    $sql = "SELECT COUNT(*) as count FROM $table 
            WHERE ip_address = ? AND DATE(check_date) = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $ip, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] < DAILY_LIMIT;
}

/**
 * Lưu kết quả check email
 */
/**
 * Lưu kết quả check email (phiên bản nâng cao)
 */
function saveEmailCheck($email, $is_valid, $has_mx, $domain_exists, $is_disposable = false, $is_role_based = false, $mailbox_exists = false, $confidence = 0, $smtp_response = '') {
    $db = new Database();
    $data = [
        'email' => $email,
        'is_valid' => $is_valid ? 1 : 0,
        'has_mx_record' => $has_mx ? 1 : 0,
        'domain_exists' => $domain_exists ? 1 : 0,
        'is_disposable' => $is_disposable ? 1 : 0,
        'is_role_based' => $is_role_based ? 1 : 0,
        'mailbox_exists' => $mailbox_exists ? 1 : 0,
        'confidence' => $confidence,
        'smtp_response' => $smtp_response,
        'check_method' => $smtp_response ? 'smtp' : 'basic',
        'ip_address' => getUserIP()
    ];
    
    return $db->insert('email_checks', $data);
}
/**
 * Lưu kết quả check SEO
 */
function saveSEOCheck($data) {
    $db = new Database();
    $data['ip_address'] = getUserIP();
    return $db->insert('seo_checks', $data);
}

/**
 * Lấy lịch sử check gần đây
 */
function getRecentHistory($tool_type, $limit = 5) {
    $db = new Database();
    $conn = $db->getConnection();
    $ip = getUserIP();
    
    if ($tool_type == 'email') {
        $table = 'email_checks';
        $sql = "SELECT email, is_valid, check_date FROM $table 
                WHERE ip_address = ? ORDER BY check_date DESC LIMIT ?";
    } else {
        $table = 'seo_checks';
        $sql = "SELECT url, title, check_date FROM $table 
                WHERE ip_address = ? ORDER BY check_date DESC LIMIT ?";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $ip, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    return $history;
}

/**
 * Validate URL
 */
function validateUrl($url) {
    if (!preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i", $url)) {
        return false;
    }
    
    return filter_var($url, FILTER_VALIDATE_URL);
}

/**
 * Format thời gian
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return "Vừa xong";
    } elseif ($diff < 3600) {
        return floor($diff / 60) . " phút trước";
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . " giờ trước";
    } else {
        return floor($diff / 86400) . " ngày trước";
    }
}
?>