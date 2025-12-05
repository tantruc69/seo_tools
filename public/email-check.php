<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$page_title = "Check Email - " . SITE_NAME;

$result = null;
$error = null;
$history = getRecentHistory('email', 10);

// Hàm check email thực tế bằng SMTP verification (không gửi email)
function verifyEmailSMTP($email, $sender_email = 'test@example.com') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'error' => 'Email không đúng định dạng'];
    }
    
    list($user, $domain) = explode('@', $email);
    
    // 1. Kiểm tra domain có tồn tại không
    if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
        return ['valid' => false, 'error' => 'Domain không tồn tại'];
    }
    
    // 2. Lấy MX records
    getmxrr($domain, $mxhosts, $mxweight);
    
    if (empty($mxhosts)) {
        // Nếu không có MX, thử dùng A record
        $mxhosts = [$domain];
    }
    
    // 3. Thử kết nối đến mail server qua port 25, 465, 587
    $ports = [25, 465, 587, 2525];
    $timeout = 10;
    $connected = false;
    $smtp_response = '';
    
    foreach ($mxhosts as $mxhost) {
        foreach ($ports as $port) {
            $sock = @fsockopen($mxhost, $port, $errno, $errstr, $timeout);
            
            if ($sock) {
                $connected = true;
                
                // Đọc banner
                fread($sock, 1024);
                
                // Gửi HELO
                fwrite($sock, "HELO example.com\r\n");
                fread($sock, 1024);
                
                // Gửi MAIL FROM
                fwrite($sock, "MAIL FROM: <$sender_email>\r\n");
                fread($sock, 1024);
                
                // Gửi RCPT TO (check email)
                fwrite($sock, "RCPT TO: <$email>\r\n");
                $response = fread($sock, 1024);
                $smtp_response = trim($response);
                
                // Gửi QUIT
                fwrite($sock, "QUIT\r\n");
                fclose($sock);
                
                // Phân tích response
                if (strpos($response, '250') === 0 || strpos($response, '550') !== false) {
                    // 250 = Accepted, 550 = Mailbox not found
                    return [
                        'valid' => strpos($response, '250') === 0,
                        'error' => strpos($response, '550') !== false ? 'Mailbox không tồn tại' : '',
                        'smtp_response' => $smtp_response,
                        'mx_host' => $mxhost,
                        'port' => $port
                    ];
                }
                break 2; // Thoát cả hai vòng lặp
            }
        }
    }
    
    // 4. Fallback: Kiểm tra thông qua API (nếu có)
    if (!$connected) {
        return checkEmailViaAPI($email);
    }
    
    return ['valid' => false, 'error' => 'Không thể kết nối đến mail server'];
}

// Hàm check email qua API bên thứ 3 (free tier)
function checkEmailViaAPI($email) {
    $apis = [
        // Hunter.io API (cần API key)
        // 'https://api.hunter.io/v2/email-verifier?email=' . urlencode($email) . '&api_key=YOUR_API_KEY',
        
        // Temp API endpoint (example)
        // 'https://api.eva.pingutil.com/email?email=' . urlencode($email)
    ];
    
    foreach ($apis as $api) {
        $response = @file_get_contents($api);
        if ($response !== false) {
            $data = json_decode($response, true);
            if (isset($data['data']['status'])) {
                return [
                    'valid' => $data['data']['status'] === 'valid',
                    'error' => $data['data']['status'] !== 'valid' ? 'Email không hợp lệ' : '',
                    'source' => 'API'
                ];
            }
        }
    }
    
    return ['valid' => false, 'error' => 'Không thể xác minh qua API'];
}

// Hàm check disposable/temporary email
function isDisposableEmail($email) {
    $disposable_domains = [
        'tempmail.com', '10minutemail.com', 'guerrillamail.com', 'mailinator.com',
        'yopmail.com', 'temp-mail.org', 'sharklasers.com', 'grr.la', 'spam4.me',
        'fakeinbox.com', 'trashmail.com', 'dispostable.com', 'mailmetrash.com'
    ];
    
    $domain = explode('@', $email)[1] ?? '';
    return in_array($domain, $disposable_domains);
}

// Hàm check role-based email
function isRoleBasedEmail($email) {
    $role_prefixes = [
        'admin', 'administrator', 'webmaster', 'info', 'support', 'contact',
        'sales', 'help', 'service', 'postmaster', 'hostmaster', 'abuse',
        'noc', 'security', 'billing', 'feedback', 'hello', 'noreply',
        'no-reply', 'newsletter', 'marketing', 'media', 'press', 'jobs'
    ];
    
    $user = explode('@', $email)[0] ?? '';
    $user_lower = strtolower($user);
    
    foreach ($role_prefixes as $prefix) {
        if (strpos($user_lower, $prefix) === 0) {
            return true;
        }
    }
    
    return false;
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    // Kiểm tra giới hạn sử dụng
    if (!checkDailyLimit('email')) {
        $error = "Bạn đã đạt giới hạn " . DAILY_LIMIT . " lần check email trong ngày hôm nay. Vui lòng quay lại vào ngày mai!";
    } else {
        // Validate email
        if (empty($email)) {
            $error = "Vui lòng nhập địa chỉ email!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result = [
                'email' => $email,
                'is_valid_format' => false,
                'has_mx_record' => false,
                'domain_exists' => false,
                'is_disposable' => false,
                'is_role_based' => false,
                'message' => 'Địa chỉ email không đúng định dạng!'
            ];
        } else {
            // Tách domain từ email
            $domain = explode('@', $email)[1];
            
            // Kiểm tra định dạng cơ bản
            $is_valid_format = true;
            
            // Kiểm tra MX records
            $has_mx = checkdnsrr($domain, "MX");
            
            // Kiểm tra domain tồn tại
            $domain_exists = checkdnsrr($domain, "ANY") || gethostbyname($domain) != $domain;
            
            // Kiểm tra disposable email
            $is_disposable = isDisposableEmail($email);
            
            // Kiểm tra role-based email
            $is_role_based = isRoleBasedEmail($email);
            
            // Kiểm tra sâu hơn bằng SMTP (chỉ khi domain tồn tại)
            $smtp_check = null;
            $mailbox_exists = false;
            
            if ($domain_exists && $has_mx && !$is_disposable) {
                // Chỉ check SMTP cho các email nghiêm túc
                $smtp_check = verifyEmailSMTP($email);
                $mailbox_exists = $smtp_check['valid'] ?? false;
            }
            
            // Tạo thông điệp kết quả
            $messages = [];
            
            if (!$is_valid_format) {
                $messages[] = 'Email không đúng định dạng';
            }
            
            if (!$domain_exists) {
                $messages[] = 'Domain không tồn tại';
            } elseif (!$has_mx) {
                $messages[] = 'Domain không có MX records (không thể nhận email)';
            }
            
            if ($is_disposable) {
                $messages[] = 'Email tạm thời (disposable) - Không nên dùng cho mục đích quan trọng';
            }
            
            if ($is_role_based) {
                $messages[] = 'Email theo vai trò (role-based) - Có thể không phải cá nhân';
            }
            
            if ($mailbox_exists) {
                $messages[] = 'Hộp thư tồn tại (đã xác minh qua SMTP)';
            } elseif ($domain_exists && $has_mx && !$is_disposable) {
                $messages[] = 'Hộp thư có thể không tồn tại (không xác minh được qua SMTP)';
            }
            
            // Xác định độ tin cậy
            $confidence = 0;
            if ($is_valid_format) $confidence += 20;
            if ($domain_exists) $confidence += 20;
            if ($has_mx) $confidence += 20;
            if (!$is_disposable) $confidence += 20;
            if ($mailbox_exists) $confidence += 20;
            
            $result = [
                'email' => $email,
                'is_valid_format' => $is_valid_format,
                'has_mx_record' => $has_mx,
                'domain_exists' => $domain_exists,
                'is_disposable' => $is_disposable,
                'is_role_based' => $is_role_based,
                'mailbox_exists' => $mailbox_exists,
                'smtp_check' => $smtp_check,
                'confidence' => $confidence,
                'domain' => $domain,
                'messages' => $messages,
                'summary' => $confidence >= 80 ? 'Email có khả năng tồn tại cao' : 
                            ($confidence >= 60 ? 'Email có thể tồn tại' : 
                            'Email có thể không tồn tại')
            ];
            
            // Lưu vào database
            saveEmailCheck($email, $result['is_valid_format'], $result['has_mx_record'], $result['domain_exists']);
        }
    }
}
?>
<?php include '../templates/header.php'; ?>

    <div class="container py-5">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card">
                    <div class="card-header bg-white">
                        <h3 class="mb-0"><i class="fas fa-envelope me-2"></i> Kiểm tra Email Tồn Tại - Thực Tế</h3>
                        <p class="text-muted mb-0 mt-2"><small>Sử dụng phương pháp check MX records, domain và xác minh SMTP</small></p>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Lưu ý:</strong> Check email thực tế phụ thuộc vào cấu hình mail server. Một số server chặn check SMTP để phòng chống spam.
                        </div>
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="emailForm">
                            <div class="mb-3">
                                <label for="email" class="form-label">Địa chỉ Email</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-at"></i>
                                    </span>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           placeholder="example@gmail.com" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                           required>
                                    <button type="submit" class="btn btn-primary" id="checkEmailBtn">
                                        <i class="fas fa-search me-2"></i> Kiểm tra chi tiết
                                    </button>
                                </div>
                                <div class="form-text">Nhập địa chỉ email cần kiểm tra. Hệ thống sẽ check domain, MX records và thử xác minh qua SMTP.</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="checkSMTP" name="checkSMTP" checked>
                                        <label class="form-check-label" for="checkSMTP">
                                            Kiểm tra SMTP (xác minh hộp thư)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="checkDisposable" name="checkDisposable" checked>
                                        <label class="form-check-label" for="checkDisposable">
                                            Phát hiện email tạm thời
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center my-4">
                                <div class="spinner-border text-primary loading-spinner" id="emailSpinner" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p id="checkingStatus" class="text-muted mt-2"></p>
                            </div>
                        </form>
                        
                        <?php if ($result): ?>
                        <div class="card mt-4 border-primary">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Kết quả kiểm tra chi tiết</h5>
                                <div>
                                    <span class="badge bg-primary me-2">Độ tin cậy: <?php echo $result['confidence']; ?>%</span>
                                    <button class="btn btn-outline-primary btn-sm" onclick="copyToClipboard('<?php echo $result['email']; ?>', 'copyEmailBtn')" id="copyEmailBtn">
                                        <i class="fas fa-copy me-1"></i> Copy Email
                                    </button>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <div class="row mb-4">
                                    <div class="col-md-8">
                                        <h6>Email được kiểm tra:</h6>
                                        <p class="lead">
                                            <i class="fas fa-envelope me-2"></i>
                                            <?php echo htmlspecialchars($result['email']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <?php if ($result['confidence'] >= 80): ?>
                                        <span class="badge bg-success p-2">
                                            <i class="fas fa-check-circle me-1"></i> TỐT
                                        </span>
                                        <?php elseif ($result['confidence'] >= 60): ?>
                                        <span class="badge bg-warning p-2">
                                            <i class="fas fa-exclamation-circle me-1"></i> KHÁ
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-danger p-2">
                                            <i class="fas fa-times-circle me-1"></i> KÉM
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Độ tin cậy -->
                                <div class="mb-4">
                                    <h6>Độ tin cậy:</h6>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar 
                                            <?php 
                                            if ($result['confidence'] >= 80) echo 'bg-success';
                                            elseif ($result['confidence'] >= 60) echo 'bg-warning';
                                            else echo 'bg-danger';
                                            ?>" 
                                            role="progressbar" 
                                            style="width: <?php echo $result['confidence']; ?>%;"
                                            aria-valuenow="<?php echo $result['confidence']; ?>" 
                                            aria-valuemin="0" 
                                            aria-valuemax="100">
                                            <?php echo $result['confidence']; ?>%
                                        </div>
                                    </div>
                                    <div class="text-center mt-1">
                                        <small class="text-muted"><?php echo $result['summary']; ?></small>
                                    </div>
                                </div>
                                
                                <!-- Chi tiết kiểm tra -->
                                <h6 class="border-bottom pb-2 mb-3">Chi tiết kiểm tra:</h6>
                                
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="list-group mb-4">
                                            <!-- Định dạng email -->
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fas fa-check-circle text-<?php echo $result['is_valid_format'] ? 'success' : 'danger'; ?> me-2"></i>
                                                    <span>Định dạng email</span>
                                                </div>
                                                <span class="badge bg-<?php echo $result['is_valid_format'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $result['is_valid_format'] ? 'Hợp lệ' : 'Không hợp lệ'; ?>
                                                </span>
                                            </div>
                                            
                                            <!-- Domain tồn tại -->
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fas fa-globe text-<?php echo $result['domain_exists'] ? 'success' : 'danger'; ?> me-2"></i>
                                                    <span>Domain tồn tại</span>
                                                </div>
                                                <span class="badge bg-<?php echo $result['domain_exists'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $result['domain_exists'] ? 'Có' : 'Không'; ?>
                                                </span>
                                            </div>
                                            
                                            <!-- MX Records -->
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fas fa-server text-<?php echo $result['has_mx_record'] ? 'success' : 'warning'; ?> me-2"></i>
                                                    <span>MX Records</span>
                                                </div>
                                                <span class="badge bg-<?php echo $result['has_mx_record'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $result['has_mx_record'] ? 'Có' : 'Không có'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-6">
                                        <div class="list-group mb-4">
                                            <!-- Email tạm thời -->
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fas fa-clock text-<?php echo $result['is_disposable'] ? 'warning' : 'success'; ?> me-2"></i>
                                                    <span>Email tạm thời</span>
                                                </div>
                                                <span class="badge bg-<?php echo $result['is_disposable'] ? 'warning' : 'success'; ?>">
                                                    <?php echo $result['is_disposable'] ? 'Có' : 'Không'; ?>
                                                </span>
                                            </div>
                                            
                                            <!-- Role-based email -->
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fas fa-user-tag text-<?php echo $result['is_role_based'] ? 'info' : 'success'; ?> me-2"></i>
                                                    <span>Email vai trò</span>
                                                </div>
                                                <span class="badge bg-<?php echo $result['is_role_based'] ? 'info' : 'success'; ?>">
                                                    <?php echo $result['is_role_based'] ? 'Có' : 'Không'; ?>
                                                </span>
                                            </div>
                                            
                                            <!-- Hộp thư tồn tại -->
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fas fa-inbox text-<?php echo $result['mailbox_exists'] ? 'success' : ($result['domain_exists'] && $result['has_mx_record'] ? 'warning' : 'secondary'); ?> me-2"></i>
                                                    <span>Hộp thư tồn tại</span>
                                                </div>
                                                <span class="badge bg-<?php echo $result['mailbox_exists'] ? 'success' : ($result['domain_exists'] && $result['has_mx_record'] ? 'warning' : 'secondary'); ?>">
                                                    <?php 
                                                    if ($result['mailbox_exists']) {
                                                        echo 'Đã xác minh';
                                                    } elseif ($result['domain_exists'] && $result['has_mx_record']) {
                                                        echo 'Chưa xác minh được';
                                                    } else {
                                                        echo 'Chưa kiểm tra';
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Thông tin domain -->
                                <?php if ($result['domain_exists']): ?>
                                <div class="alert alert-light border">
                                    <h6><i class="fas fa-info-circle me-2"></i> Thông tin Domain:</h6>
                                    <p class="mb-1"><strong>Domain:</strong> <?php echo htmlspecialchars($result['domain']); ?></p>
                                    <p class="mb-1"><strong>IP Address:</strong> <?php echo gethostbyname($result['domain']); ?></p>
                                    
                                    <?php if ($result['smtp_check'] && isset($result['smtp_check']['mx_host'])): ?>
                                    <p class="mb-1"><strong>Mail Server:</strong> <?php echo htmlspecialchars($result['smtp_check']['mx_host']); ?></p>
                                    <p class="mb-0"><strong>SMTP Response:</strong> <code><?php echo htmlspecialchars($result['smtp_check']['smtp_response'] ?? 'Không có phản hồi'); ?></code></p>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Thông báo -->
                                <div class="alert 
                                    <?php 
                                    if ($result['confidence'] >= 80) echo 'alert-success';
                                    elseif ($result['confidence'] >= 60) echo 'alert-warning';
                                    else echo 'alert-danger';
                                    ?>">
                                    <i class="fas fa-comment-dots me-2"></i>
                                    <strong>Kết luận:</strong>
                                    <ul class="mb-0 mt-2">
                                        <?php foreach ($result['messages'] as $message): ?>
                                        <li><?php echo $message; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                
                                <!-- Lời khuyên -->
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-lightbulb me-2"></i> Lời khuyên:</h6>
                                    <ul class="mb-0">
                                        <?php if ($result['is_disposable']): ?>
                                        <li>Không nên sử dụng email tạm thời cho tài khoản quan trọng</li>
                                        <?php endif; ?>
                                        
                                        <?php if ($result['is_role_based']): ?>
                                        <li>Email vai trò thường được nhiều người quản lý, ít cá nhân hóa</li>
                                        <?php endif; ?>
                                        
                                        <?php if (!$result['domain_exists']): ?>
                                        <li>Domain không tồn tại - email chắc chắn không hợp lệ</li>
                                        <?php endif; ?>
                                        
                                        <?php if ($result['domain_exists'] && !$result['has_mx_record']): ?>
                                        <li>Domain không có MX records - không thể nhận email</li>
                                        <?php endif; ?>
                                        
                                        <?php if ($result['confidence'] >= 80): ?>
                                        <li>Email này có độ tin cậy cao, có thể sử dụng</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                
                                <div class="mt-4">
                                    <button class="btn btn-outline-primary me-2" onclick="exportEmailResult()">
                                        <i class="fas fa-download me-1"></i> Export CSV
                                    </button>
                                    <button class="btn btn-outline-secondary me-2" onclick="printEmailResult()">
                                        <i class="fas fa-print me-1"></i> In kết quả
                                    </button>
                                    <button class="btn btn-outline-success" onclick="checkAnotherEmail()">
                                        <i class="fas fa-redo me-1"></i> Kiểm tra email khác
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Hướng dẫn -->
                <div class="card mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i> Cách thức hoạt động</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Các bước kiểm tra:</h6>
                                <ol>
                                    <li><strong>Kiểm tra định dạng:</strong> Đảm bảo email đúng cấu trúc</li>
                                    <li><strong>Kiểm tra domain:</strong> Xác minh domain có tồn tại</li>
                                    <li><strong>Kiểm tra MX records:</strong> Xác định server nhận email</li>
                                    <li><strong>Phát hiện email tạm thời:</strong> Nhận diện disposable email</li>
                                    <li><strong>Kiểm tra SMTP:</strong> Thử kết nối đến mail server</li>
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <h6>Giới hạn:</h6>
                                <ul>
                                    <li>Một số mail server chặn check SMTP (anti-spam)</li>
                                    <li>Không thể xác minh 100% email có thực sự được sử dụng</li>
                                    <li>Kết quả chỉ mang tính chất tham khảo</li>
                                    <li>Giới hạn <?php echo DAILY_LIMIT; ?> lần check/ngày</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- History Section -->
                <div class="card mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i> Lịch sử kiểm tra gần đây</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($history)): ?>
                            <p class="text-muted text-center mb-0">Chưa có lịch sử kiểm tra.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Email</th>
                                            <th>Domain</th>
                                            <th>Kết quả</th>
                                            <th>Thời gian</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($history as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['email']); ?></td>
                                            <td><?php echo explode('@', $item['email'])[1] ?? ''; ?></td>
                                            <td>
                                                <?php if ($item['is_valid']): ?>
                                                <span class="badge bg-success">Hợp lệ</span>
                                                <?php else: ?>
                                                <span class="badge bg-danger">Không hợp lệ</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo timeAgo($item['check_date']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="recheckEmail('<?php echo htmlspecialchars($item['email']); ?>')">
                                                    <i class="fas fa-redo"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Hiển thị loading khi submit form
        document.getElementById('emailForm').addEventListener('submit', function() {
            document.getElementById('checkEmailBtn').style.display = 'none';
            document.getElementById('emailSpinner').style.display = 'inline-block';
            document.getElementById('checkingStatus').textContent = 'Đang kiểm tra domain và MX records...';
            
            // Simulate progress updates
            setTimeout(() => {
                document.getElementById('checkingStatus').textContent = 'Đang kiểm tra email tạm thời...';
            }, 1500);
            
            setTimeout(() => {
                document.getElementById('checkingStatus').textContent = 'Đang thử kết nối SMTP...';
            }, 3000);
        });
        
        // Export kết quả ra CSV
        function exportEmailResult() {
            <?php if ($result): ?>
            const data = [
                ['Email', 'Định dạng', 'Domain tồn tại', 'MX Records', 'Email tạm thời', 'Hộp thư tồn tại', 'Độ tin cậy', 'Kết luận'],
                [
                    '<?php echo $result['email']; ?>',
                    '<?php echo $result['is_valid_format'] ? 'Hợp lệ' : 'Không hợp lệ'; ?>',
                    '<?php echo $result['domain_exists'] ? 'Có' : 'Không'; ?>',
                    '<?php echo $result['has_mx_record'] ? 'Có' : 'Không có'; ?>',
                    '<?php echo $result['is_disposable'] ? 'Có' : 'Không'; ?>',
                    '<?php echo $result['mailbox_exists'] ? 'Đã xác minh' : 'Chưa xác minh'; ?>',
                    '<?php echo $result['confidence']; ?>%',
                    '<?php echo addslashes($result['summary']); ?>'
                ]
            ];
            
            exportToCSV('email_check_advanced.csv', data);
            <?php endif; ?>
        }
        
        // In kết quả
        function printEmailResult() {
            window.print();
        }
        
        // Kiểm tra email khác
        function checkAnotherEmail() {
            document.getElementById('email').value = '';
            document.getElementById('email').focus();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        // Kiểm tra lại email từ lịch sử
        function recheckEmail(email) {
            document.getElementById('email').value = email;
            document.getElementById('emailForm').submit();
        }
        
        // Kiểm tra nhanh khi paste
        document.getElementById('email').addEventListener('paste', function(e) {
            setTimeout(() => {
                const email = this.value.trim();
                if (email.includes('@') && email.includes('.')) {
                    // Tự động validate cơ bản
                    const domain = email.split('@')[1];
                    const domainSpan = document.getElementById('domainPreview');
                    if (!domainSpan) {
                        const preview = document.createElement('div');
                        preview.id = 'domainPreview';
                        preview.className = 'form-text text-info';
                        preview.innerHTML = `<i class="fas fa-search me-1"></i>Domain: ${domain}`;
                        this.parentNode.parentNode.appendChild(preview);
                    } else {
                        domainSpan.textContent = `Domain: ${domain}`;
                    }
                }
            }, 100);
        });
    </script>

<?php include '../templates/footer.php'; ?>