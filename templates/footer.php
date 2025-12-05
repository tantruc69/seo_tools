    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h4 class="mb-3">SEO Tools Pro</h4>
                    <p class="text-light">Cung cấp công cụ SEO và Email Marketing miễn phí cho cộng đồng người Việt.</p>
                    <div class="mt-3">
                        <a href="#" class="text-light me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-github fa-lg"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-4 mb-4">
                    <h5 class="mb-3">Công cụ</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="email-check.php" class="text-light text-decoration-none">Check Email</a></li>
                        <li class="mb-2"><a href="seo-check.php" class="text-light text-decoration-none">Check SEO On-Page</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Check Backlink (Sắp ra mắt)</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Check Tốc độ Website</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-4 mb-4">
                    <h5 class="mb-3">Liên hệ</h5>
                    <p class="text-light mb-2">
                        <i class="fas fa-envelope me-2"></i> contact@seotools.com
                    </p>
                    <p class="text-light mb-2">
                        <i class="fas fa-globe me-2"></i> seotools.vn
                    </p>
                    <p class="text-light">
                        <i class="fas fa-map-marker-alt me-2"></i> Hà Nội, Việt Nam
                    </p>
                </div>
            </div>
            
            <hr class="bg-light my-4">
            
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">© <?php echo date('Y'); ?> SEO Tools Pro. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-light text-decoration-none me-3">Privacy Policy</a>
                    <a href="#" class="text-light text-decoration-none">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <script>
        // Hiển thị loading spinner khi submit form
        function showLoading(buttonId, spinnerId) {
            document.getElementById(buttonId).style.display = 'none';
            document.getElementById(spinnerId).style.display = 'inline-block';
        }
        
        // Copy text to clipboard
        function copyToClipboard(text, buttonId) {
            navigator.clipboard.writeText(text).then(function() {
                const btn = document.getElementById(buttonId);
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Đã copy';
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-success');
                
                setTimeout(function() {
                    btn.innerHTML = originalText;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-primary');
                }, 2000);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
            });
        }
        
        // Export to CSV
        function exportToCSV(filename, rows) {
            const csvContent = "data:text/csv;charset=utf-8," 
                + rows.map(e => e.join(",")).join("\n");
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Auto-resize textarea
        function autoResize(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }
    </script>
    
    <?php if (isset($page_js)): ?>
    <script src="<?php echo $page_js; ?>"></script>
    <?php endif; ?>
</body>
</html>