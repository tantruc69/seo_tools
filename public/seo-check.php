<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$page_title = "Check SEO - " . SITE_NAME;

$result = null;
$error = null;
$history = getRecentHistory('seo', 10);

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['url'])) {
    $url = trim($_POST['url']);
    
    // Kiểm tra giới hạn sử dụng
    if (!checkDailyLimit('seo')) {
        $error = "Bạn đã đạt giới hạn " . DAILY_LIMIT . " lần check SEO trong ngày hôm nay. Vui lòng quay lại vào ngày mai!";
    } else {
        // Validate URL
        if (empty($url)) {
            $error = "Vui lòng nhập URL!";
        } elseif (!validateUrl($url)) {
            $error = "URL không hợp lệ! Vui lòng nhập URL đầy đủ (bao gồm http:// hoặc https://)";
        } else {
            // Thêm http:// nếu không có
            if (!preg_match("/^https?:\/\//i", $url)) {
                $url = "http://" . $url;
            }
            
            // Bắt đầu đo thời gian
            $start_time = microtime(true);
            
            // Lấy nội dung trang web
            $options = [
                'http' => [
                    'method' => "GET",
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
                ]
            ];
            
            $context = stream_context_create($options);
            $html = @file_get_contents($url, false, $context);
            
            if ($html === FALSE) {
                $error = "Không thể truy cập URL. Vui lòng kiểm tra lại URL hoặc kết nối mạng.";
            } else {
                // Kết thúc đo thời gian
                $load_time = round(microtime(true) - $start_time, 2);
                
                // Lấy thông tin cơ bản
                $title = '';
                $meta_description = '';
                $h1_tags = [];
                $internal_links = 0;
                $external_links = 0;
                
                // Lấy title
                if (preg_match("/<title>(.*?)<\/title>/si", $html, $matches)) {
                    $title = trim($matches[1]);
                }
                
                // Lấy meta description
                if (preg_match('/<meta\s+name="description"\s+content="(.*?)"/si', $html, $matches)) {
                    $meta_description = trim($matches[1]);
                } elseif (preg_match("/<meta\s+content=\"(.*?)\"\s+name=\"description\"/si", $html, $matches)) {
                    $meta_description = trim($matches[1]);
                }
                
                // Lấy tất cả H1 tags
                if (preg_match_all("/<h1[^>]*>(.*?)<\/h1>/si", $html, $matches)) {
                    $h1_tags = array_map('trim', $matches[1]);
                }
                
                // Lấy tất cả links
                if (preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>/si', $html, $matches)) {
                    $all_links = $matches[1];
                    
                    // Parse base URL
                    $parsed_url = parse_url($url);
                    $base_domain = $parsed_url['host'];
                    
                    foreach ($all_links as $link) {
                        // Bỏ qua anchor links
                        if (strpos($link, '#') === 0) continue;
                        
                        // Parse link
                        $parsed_link = parse_url($link);
                        
                        if (!isset($parsed_link['host'])) {
                            // Internal link (relative)
                            $internal_links++;
                        } else {
                            // Check if same domain
                            $link_domain = $parsed_link['host'];
                            if ($link_domain == $base_domain || strpos($link_domain, '.' . $base_domain) !== false) {
                                $internal_links++;
                            } else {
                                $external_links++;
                            }
                        }
                    }
                }
                
                // Kiểm tra robots.txt
                $robots_url = rtrim($url, '/') . '/robots.txt';
                $robots_content = @file_get_contents($robots_url, false, $context);
                $has_robots_txt = ($robots_content !== FALSE);
                
                // Kiểm tra sitemap.xml
                $sitemap_url = rtrim($url, '/') . '/sitemap.xml';
                $sitemap_content = @file_get_contents($sitemap_url, false, $context);
                $has_sitemap = ($sitemap_content !== FALSE);
                
                // Tính điểm SEO
                $seo_score = 0;
                if (!empty($title)) $seo_score += 25;
                if (!empty($meta_description)) $seo_score += 25;
                if (!empty($h1_tags)) $seo_score += 25;
                if ($has_robots_txt) $seo_score += 12.5;
                if ($has_sitemap) $seo_score += 12.5;
                
                // Chuẩn bị kết quả
                $result = [
                    'url' => $url,
                    'title' => $title,
                    'meta_description' => $meta_description,
                    'h1_count' => count($h1_tags),
                    'h1_list' => $h1_tags,
                    'internal_links' => $internal_links,
                    'external_links' => $external_links,
                    'has_robots_txt' => $has_robots_txt,
                    'has_sitemap' => $has_sitemap,
                    'load_time' => $load_time,
                    'seo_score' => $seo_score,
                    'html_size' => round(strlen($html) / 1024, 2) // KB
                ];
                
                // Lưu vào database
                $db_data = [
                    'url' => $url,
                    'title' => $title,
                    'meta_description' => $meta_description,
                    'h1_count' => count($h1_tags),
                    'h1_list' => json_encode($h1_tags),
                    'internal_links' => $internal_links,
                    'external_links' => $external_links,
                    'has_robots_txt' => $has_robots_txt ? 1 : 0,
                    'has_sitemap' => $has_sitemap ? 1 : 0,
                    'load_time' => $load_time
                ];
                
                saveSEOCheck($db_data);
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
                        <h3 class="mb-0"><i class="fas fa-chart-line me-2"></i> Kiểm tra SEO On-Page</h3>
                    </div>
                    
                    <div class="card-body p-4">
                        <p class="text-muted mb-4">
                            Nhập URL website để phân tích SEO on-page: title, meta description, heading, links và tốc độ tải trang.
                        </p>
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="seoForm">
                            <div class="mb-3">
                                <label for="url" class="form-label">URL Website</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-globe"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="url" 
                                           name="url" 
                                           placeholder="https://example.com" 
                                           value="<?php echo isset($_POST['url']) ? htmlspecialchars($_POST['url']) : ''; ?>"
                                           required>
                                    <button type="submit" class="btn btn-primary" id="checkSeoBtn">
                                        <i class="fas fa-search me-2"></i> Phân tích
                                    </button>
                                </div>
                                <div class="form-text">Nhập URL đầy đủ (bao gồm http:// hoặc https://).</div>
                            </div>
                            
                            <div class="text-center mb-4">
                                <div class="spinner-border text-primary loading-spinner" id="seoSpinner" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </form>
                        
                        <?php if ($result): ?>
                        <div class="card mt-4 border-primary">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Kết quả phân tích SEO</h5>
                                <span class="badge bg-primary">Điểm: <?php echo $result['seo_score']; ?>/100</span>
                            </div>
                            <div class="card-body">
                                <div class="row mb-4">
                                    <div class="col-md-8">
                                        <h6>URL:</h6>
                                        <p class="lead"><?php echo htmlspecialchars($result['url']); ?></p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <button class="btn btn-outline-primary btn-sm" onclick="copyToClipboard('<?php echo $result['url']; ?>', 'copyUrlBtn')" id="copyUrlBtn">
                                            <i class="fas fa-copy me-1"></i> Copy URL
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- SEO Score Progress -->
                                <div class="mb-4">
                                    <h6>Điểm SEO:</h6>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar 
                                            <?php 
                                            if ($result['seo_score'] >= 80) echo 'bg-success';
                                            elseif ($result['seo_score'] >= 50) echo 'bg-warning';
                                            else echo 'bg-danger';
                                            ?>" 
                                            role="progressbar" 
                                            style="width: <?php echo $result['seo_score']; ?>%;"
                                            aria-valuenow="<?php echo $result['seo_score']; ?>" 
                                            aria-valuemin="0" 
                                            aria-valuemax="100">
                                            <?php echo $result['seo_score']; ?>%
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <!-- Thông tin cơ bản -->
                                    <div class="col-lg-6 mb-4">
                                        <h6 class="border-bottom pb-2">Thông tin cơ bản</h6>
                                        
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span>Title:</span>
                                                <?php if (!empty($result['title'])): ?>
                                                <span class="badge bg-success">Có</span>
                                                <?php else: ?>
                                                <span class="badge bg-danger">Không có</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($result['title'])): ?>
                                            <div class="alert alert-light border">
                                                <?php echo htmlspecialchars($result['title']); ?>
                                                <br>
                                                <small class="text-muted">Độ dài: <?php echo mb_strlen($result['title']); ?> ký tự</small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span>Meta Description:</span>
                                                <?php if (!empty($result['meta_description'])): ?>
                                                <span class="badge bg-success">Có</span>
                                                <?php else: ?>
                                                <span class="badge bg-danger">Không có</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($result['meta_description'])): ?>
                                            <div class="alert alert-light border">
                                                <?php echo htmlspecialchars($result['meta_description']); ?>
                                                <br>
                                                <small class="text-muted">Độ dài: <?php echo mb_strlen($result['meta_description']); ?> ký tự</small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span>H1 Tags:</span>
                                                <span class="badge <?php echo ($result['h1_count'] > 0) ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $result['h1_count']; ?> tag(s)
                                                </span>
                                            </div>
                                            <?php if ($result['h1_count'] > 0): ?>
                                            <div class="alert alert-light border">
                                                <?php foreach ($result['h1_list'] as $index => $h1): ?>
                                                <div class="mb-1">
                                                    <strong>H1 #<?php echo $index + 1; ?>:</strong> <?php echo htmlspecialchars($h1); ?>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Thông số kỹ thuật -->
                                    <div class="col-lg-6 mb-4">
                                        <h6 class="border-bottom pb-2">Thông số kỹ thuật</h6>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6 mb-2">
                                                <div class="card bg-light h-100">
                                                    <div class="card-body text-center">
                                                        <div class="stats-number"><?php echo $result['internal_links']; ?></div>
                                                        <div class="stats-label">Internal Links</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <div class="card bg-light h-100">
                                                    <div class="card-body text-center">
                                                        <div class="stats-number"><?php echo $result['external_links']; ?></div>
                                                        <div class="stats-label">External Links</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Tốc độ tải trang:</span>
                                                <span class="badge 
                                                    <?php 
                                                    if ($result['load_time'] < 1) echo 'bg-success';
                                                    elseif ($result['load_time'] < 3) echo 'bg-warning';
                                                    else echo 'bg-danger';
                                                    ?>">
                                                    <?php echo $result['load_time']; ?> giây
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Kích thước HTML:</span>
                                                <span class="badge 
                                                    <?php 
                                                    if ($result['html_size'] < 100) echo 'bg-success';
                                                    elseif ($result['html_size'] < 300) echo 'bg-warning';
                                                    else echo 'bg-danger';
                                                    ?>">
                                                    <?php echo $result['html_size']; ?> KB
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span>Robots.txt:</span>
                                                <?php if ($result['has_robots_txt']): ?>
                                                <span class="badge bg-success">Có</span>
                                                <?php else: ?>
                                                <span class="badge bg-danger">Không có</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Sitemap.xml:</span>
                                                <?php if ($result['has_sitemap']): ?>
                                                <span class="badge bg-success">Có</span>
                                                <?php else: ?>
                                                <span class="badge bg-danger">Không có</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <h6>Đề xuất cải thiện:</h6>
                                        <div class="alert 
                                            <?php 
                                            if ($result['seo_score'] >= 80) echo 'alert-success';
                                            elseif ($result['seo_score'] >= 50) echo 'alert-warning';
                                            else echo 'alert-danger';
                                            ?>">
                                            <?php
                                            $suggestions = [];
                                            
                                            if (empty($result['title'])) {
                                                $suggestions[] = "Thêm thẻ title cho trang";
                                            } elseif (mb_strlen($result['title']) > 60) {
                                                $suggestions[] = "Title quá dài (>60 ký tự)";
                                            }
                                            
                                            if (empty($result['meta_description'])) {
                                                $suggestions[] = "Thêm meta description";
                                            } elseif (mb_strlen($result['meta_description']) > 160) {
                                                $suggestions[] = "Meta description quá dài (>160 ký tự)";
                                            }
                                            
                                            if ($result['h1_count'] == 0) {
                                                $suggestions[] = "Thêm ít nhất một thẻ H1";
                                            } elseif ($result['h1_count'] > 1) {
                                                $suggestions[] = "Nên chỉ có một thẻ H1 trên mỗi trang";
                                            }
                                            
                                            if (!$result['has_robots_txt']) {
                                                $suggestions[] = "Thêm file robots.txt";
                                            }
                                            
                                            if (!$result['has_sitemap']) {
                                                $suggestions[] = "Thêm sitemap.xml";
                                            }
                                            
                                            if ($result['load_time'] > 3) {
                                                $suggestions[] = "Tối ưu tốc độ tải trang (>3 giây)";
                                            }
                                            
                                            if (empty($suggestions)) {
                                                echo '<i class="fas fa-check-circle me-2"></i>Trang web của bạn đã được tối ưu tốt!';
                                            } else {
                                                echo '<i class="fas fa-lightbulb me-2"></i><strong>Cần cải thiện:</strong><br>';
                                                foreach ($suggestions as $suggestion) {
                                                    echo '- ' . $suggestion . '<br>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <button class="btn btn-outline-primary me-2" onclick="exportSEOResult()">
                                        <i class="fas fa-download me-1"></i> Export CSV
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="printSEOResult()">
                                        <i class="fas fa-print me-1"></i> In kết quả
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
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
                                            <th>URL</th>
                                            <th>Title</th>
                                            <th>Thời gian</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($history as $item): ?>
                                        <tr>
                                            <td class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($item['url']); ?>">
                                                <?php echo htmlspecialchars($item['url']); ?>
                                            </td>
                                            <td class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($item['title']); ?>">
                                                <?php echo !empty($item['title']) ? htmlspecialchars($item['title']) : '<span class="text-muted">Không có title</span>'; ?>
                                            </td>
                                            <td><?php echo timeAgo($item['check_date']); ?></td>
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
        document.getElementById('seoForm').addEventListener('submit', function() {
            document.getElementById('checkSeoBtn').style.display = 'none';
            document.getElementById('seoSpinner').style.display = 'inline-block';
        });
        
        // Export kết quả ra CSV
        function exportSEOResult() {
            <?php if ($result): ?>
            const data = [
                ['URL', 'Title', 'Meta Description', 'H1 Count', 'Internal Links', 'External Links', 'Robots.txt', 'Sitemap.xml', 'Load Time', 'SEO Score'],
                [
                    '<?php echo $result['url']; ?>',
                    '<?php echo addslashes($result['title']); ?>',
                    '<?php echo addslashes($result['meta_description']); ?>',
                    '<?php echo $result['h1_count']; ?>',
                    '<?php echo $result['internal_links']; ?>',
                    '<?php echo $result['external_links']; ?>',
                    '<?php echo $result['has_robots_txt'] ? 'Có' : 'Không'; ?>',
                    '<?php echo $result['has_sitemap'] ? 'Có' : 'Không'; ?>',
                    '<?php echo $result['load_time']; ?> giây',
                    '<?php echo $result['seo_score']; ?>/100'
                ]
            ];
            
            exportToCSV('seo_check_result.csv', data);
            <?php endif; ?>
        }
        
        // In kết quả
        function printSEOResult() {
            window.print();
        }
    </script>

<?php include '../templates/footer.php'; ?>