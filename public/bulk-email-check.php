<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$page_title = "Bulk Email Check - " . SITE_NAME;

$results = [];
$error = null;

// Hàm check email đơn giản (nhanh hơn)
function quickEmailCheck($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'reason' => 'Invalid format'];
    }
    
    $domain = explode('@', $email)[1];
    
    // Check domain exists
    if (!checkdnsrr($domain, 'ANY') && gethostbyname($domain) == $domain) {
        return ['valid' => false, 'reason' => 'Domain not found'];
    }
    
    // Check MX records
    if (!checkdnsrr($domain, 'MX')) {
        return ['valid' => false, 'reason' => 'No MX records'];
    }
    
    return ['valid' => true, 'reason' => 'Looks valid'];
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['emails'])) {
        $emails_text = trim($_POST['emails']);
        $emails = array_map('trim', explode("\n", $emails_text));
        $emails = array_filter($emails);
        $emails = array_slice($emails, 0, 50); // Giới hạn 50 email/lần
        
        if (empty($emails)) {
            $error = "Vui lòng nhập ít nhất một email!";
        } elseif (count($emails) > 50) {
            $error = "Chỉ có thể check tối đa 50 email mỗi lần!";
        } else {
            foreach ($emails as $email) {
                $result = quickEmailCheck($email);
                $results[] = [
                    'email' => $email,
                    'valid' => $result['valid'],
                    'reason' => $result['reason']
                ];
            }
        }
    } elseif (isset($_FILES['csv_file'])) {
        // Xử lý upload file CSV
        $file = $_FILES['csv_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = "Lỗi upload file: " . $file['error'];
        } else {
            $csv_content = file_get_contents($file['tmp_name']);
            $lines = explode("\n", $csv_content);
            
            foreach ($lines as $line) {
                $email = trim($line);
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $result = quickEmailCheck($email);
                    $results[] = [
                        'email' => $email,
                        'valid' => $result['valid'],
                        'reason' => $result['reason']
                    ];
                }
                
                if (count($results) >= 100) break; // Giới hạn 100 email
            }
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
                    <h3 class="mb-0"><i class="fas fa-list-alt me-2"></i> Check Nhiều Email Cùng Lúc</h3>
                </div>
                
                <div class="card-body p-4">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Chú ý:</strong> Tool này check nhanh nhiều email cùng lúc, chỉ kiểm tra định dạng, domain và MX records.
                    </div>
                    
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs mb-4" id="bulkTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="text-tab" data-bs-toggle="tab" data-bs-target="#text-panel" type="button">
                                <i class="fas fa-align-left me-1"></i> Nhập danh sách
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="csv-tab" data-bs-toggle="tab" data-bs-target="#csv-panel" type="button">
                                <i class="fas fa-file-csv me-1"></i> Upload CSV
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content" id="bulkTabContent">
                        <!-- Text Input Panel -->
                        <div class="tab-pane fade show active" id="text-panel" role="tabpanel">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="emails" class="form-label">Danh sách Email (mỗi email một dòng)</label>
                                    <textarea class="form-control" id="emails" name="emails" rows="8" 
                                              placeholder="example1@gmail.com&#10;example2@yahoo.com&#10;example3@company.com"
                                              required></textarea>
                                    <div class="form-text">Tối đa 50 email mỗi lần check. Mỗi email trên một dòng riêng.</div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-play me-2"></i> Bắt đầu check
                                </button>
                            </form>
                        </div>
                        
                        <!-- CSV Upload Panel -->
                        <div class="tab-pane fade" id="csv-panel" role="tabpanel">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="csv_file" class="form-label">Upload file CSV</label>
                                    <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv,.txt">
                                    <div class="form-text">
                                        File CSV chỉ chứa cột email, mỗi email một dòng. Tối đa 100 email.
                                        <a href="sample-emails.csv" class="ms-2"><i class="fas fa-download me-1"></i>Download mẫu</a>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i> Upload và Check
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <?php if (!empty($results)): ?>
                    <div class="card mt-4 border-success">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Kết quả check</h5>
                            <div>
                                <span class="badge bg-primary me-2">
                                    Tổng: <?php echo count($results); ?> email
                                </span>
                                <span class="badge bg-success me-2">
                                    Hợp lệ: <?php echo count(array_filter($results, fn($r) => $r['valid'])); ?>
                                </span>
                                <span class="badge bg-danger">
                                    Không hợp lệ: <?php echo count(array_filter($results, fn($r) => !$r['valid'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Email</th>
                                            <th>Trạng thái</th>
                                            <th>Lý do</th>
                                            <th>Check chi tiết</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $index => $result): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($result['email']); ?></td>
                                            <td>
                                                <?php if ($result['valid']): ?>
                                                <span class="badge bg-success">Hợp lệ</span>
                                                <?php else: ?>
                                                <span class="badge bg-danger">Không hợp lệ</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($result['reason']); ?></td>
                                            <td>
                                                <a href="email-check.php?email=<?php echo urlencode($result['email']); ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-search"></i> Chi tiết
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <button class="btn btn-outline-primary me-2" onclick="exportBulkResults()">
                                    <i class="fas fa-download me-1"></i> Export CSV
                                </button>
                                <button class="btn btn-outline-secondary" onclick="printBulkResults()">
                                    <i class="fas fa-print me-1"></i> In kết quả
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Export bulk results
    function exportBulkResults() {
        <?php if (!empty($results)): ?>
        const data = [
            ['STT', 'Email', 'Trạng thái', 'Lý do'],
            <?php foreach ($results as $index => $result): ?>
            [
                '<?php echo $index + 1; ?>',
                '<?php echo addslashes($result['email']); ?>',
                '<?php echo $result['valid'] ? 'Hợp lệ' : 'Không hợp lệ'; ?>',
                '<?php echo addslashes($result['reason']); ?>'
            ],
            <?php endforeach; ?>
        ];
        
        exportToCSV('bulk_email_check.csv', data);
        <?php endif; ?>
    }
    
    // Print bulk results
    function printBulkResults() {
        window.print();
    }
    
    // Auto-count emails
    document.getElementById('emails').addEventListener('input', function() {
        const emails = this.value.split('\n').filter(email => email.trim().length > 0);
        const count = emails.length;
        
        let counter = document.getElementById('emailCounter');
        if (!counter) {
            counter = document.createElement('div');
            counter.id = 'emailCounter';
            counter.className = 'form-text';
            this.parentNode.appendChild(counter);
        }
        
        counter.textContent = `Số email: ${count}`;
        
        if (count > 50) {
            counter.className = 'form-text text-danger';
        } else {
            counter.className = 'form-text text-success';
        }
    });
</script>

<?php include '../templates/footer.php'; ?>