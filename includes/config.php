<?php
// Cấu hình database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'seo_tools');

// Cấu hình website
define('SITE_NAME', 'SEO Tools Pro');
define('SITE_URL', 'http://localhost/seo_tools/public');

// Giới hạn sử dụng (số lần check/ngày)
define('DAILY_LIMIT', 50);

// Khởi động session
session_start();
?>