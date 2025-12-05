<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$page_title = "Trang chủ - " . SITE_NAME;
?>
<?php include '../templates/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-3">Công cụ SEO & Email Marketing</h1>
                    <p class="lead mb-4">Kiểm tra email tồn tại, phân tích SEO on-page, và tối ưu hóa website của bạn với bộ công cụ miễn phí và mạnh mẽ.</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="email-check.php" class="btn btn-light btn-lg">
                            <i class="fas fa-envelope me-2"></i> Check Email
                        </a>
                        <a href="seo-check.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-chart-line me-2"></i> Check SEO
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <img src="https://cdn-icons-png.flaticon.com/512/2165/2165004.png" alt="SEO Tools" class="img-fluid" style="max-height: 300px;">
                </div>
            </div>
        </div>
    </section>

    <!-- Tools Section -->
    <div class="container">
        <div class="row mb-5">
            <div class="col-lg-8 mx-auto text-center">
                <h2 class="fw-bold mb-3">Công cụ của chúng tôi</h2>
                <p class="text-muted">Chọn công cụ phù hợp với nhu cầu của bạn</p>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Email Check Tool -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="tool-icon mx-auto">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h4 class="card-title text-center mb-3">Check Email Tồn Tại</h4>
                        <p class="card-text text-center text-muted mb-4">
                            Kiểm tra tính hợp lệ của địa chỉ email, xác minh domain và MX records.
                        </p>
                        <ul class="list-unstyled mb-4">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Kiểm tra định dạng email</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Xác minh domain tồn tại</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Check MX records</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Lưu lịch sử kiểm tra</li>
                        </ul>
                        <div class="text-center">
                            <a href="email-check.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-rocket me-2"></i> Sử dụng công cụ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SEO Check Tool -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="tool-icon mx-auto">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4 class="card-title text-center mb-3">Check SEO On-Page</h4>
                        <p class="card-text text-center text-muted mb-4">
                            Phân tích website, kiểm tra thẻ meta, heading, links và tốc độ tải trang.
                        </p>
                        <ul class="list-unstyled mb-4">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Kiểm tra thẻ Title & Meta</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Phân tích cấu trúc Heading</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Đếm Internal/External links</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Kiểm tra tốc độ tải trang</li>
                        </ul>
                        <div class="text-center">
                            <a href="seo-check.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-rocket me-2"></i> Sử dụng công cụ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Section -->
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="fw-bold mb-4 text-center">Thống kê sử dụng</h3>
            </div>
            
            <?php
            $db = new Database();
            $conn = $db->getConnection();
            
            // Lấy số liệu thống kê
            $email_checks = $conn->query("SELECT COUNT(*) as total FROM email_checks")->fetch_assoc()['total'];
            $seo_checks = $conn->query("SELECT COUNT(*) as total FROM seo_checks")->fetch_assoc()['total'];
            $today_checks = $conn->query("SELECT COUNT(*) as total FROM (SELECT id FROM email_checks WHERE DATE(check_date) = CURDATE() UNION ALL SELECT id FROM seo_checks WHERE DATE(check_date) = CURDATE()) as today")->fetch_assoc()['total'];
            ?>
            
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="stats-number"><?php echo number_format($email_checks); ?></div>
                    <div class="stats-label">Lần check Email</div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="stats-number"><?php echo number_format($seo_checks); ?></div>
                    <div class="stats-label">Lần check SEO</div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="stats-number"><?php echo number_format($today_checks); ?></div>
                    <div class="stats-label">Check hôm nay</div>
                </div>
            </div>
        </div>
        
        <!-- About Section -->
        <div class="row mt-5" id="about">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-body p-4">
                        <h3 class="fw-bold mb-3">Giới thiệu</h3>
                        <p>SEO Tools Pro là nền tảng cung cấp công cụ SEO và Email Marketing miễn phí cho cộng đồng người dùng Việt Nam. Chúng tôi tập trung vào việc cung cấp các công cụ đơn giản, dễ sử dụng nhưng mạnh mẽ.</p>
                        
                        <h5 class="mt-4 mb-3">Tính năng chính:</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <ul>
                                    <li>Check email tồn tại và hợp lệ</li>
                                    <li>Phân tích SEO on-page chi tiết</li>
                                    <li>Kiểm tra tốc độ tải trang</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul>
                                    <li>Lưu lịch sử kiểm tra</li>
                                    <li>Export kết quả ra CSV</li>
                                    <li>Giao diện thân thiện, dễ sử dụng</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Lưu ý:</strong> Mỗi người dùng có thể sử dụng tối đa <?php echo DAILY_LIMIT; ?> lần check mỗi ngày cho mỗi công cụ.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include '../templates/footer.php'; ?>