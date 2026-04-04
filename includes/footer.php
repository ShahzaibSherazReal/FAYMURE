    <footer class="main-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-section">
                    <h3>Contact</h3>
                    <?php
                    $conn = getDBConnection();
                    $email = 'info@faymure.com';
                    $phone = '+92 345 0300861';
                    $fb = '#';
                    $ig = '#';
                    $tw = '#';
                    
                    // Check if content_value column exists
                    $columns_check = $conn->query("SHOW COLUMNS FROM site_content LIKE 'content_value'");
                    if ($columns_check && $columns_check->num_rows > 0) {
                        $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='footer_email'");
                        if ($result && $row = $result->fetch_assoc()) {
                            $email = $row['content_value'] ?? $email;
                        }
                        
                        $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='footer_phone'");
                        if ($result && $row = $result->fetch_assoc()) {
                            $phone = $row['content_value'] ?? $phone;
                        }
                        // DB still had the old template phone; migrate once so footer matches live contact
                        $legacy_footer_phone = '+1 (555) 123-4567';
                        if (trim((string) $phone) === $legacy_footer_phone) {
                            $new_phone = '+92 345 0300861';
                            $conn->query("UPDATE site_content SET content_value = '" . $conn->real_escape_string($new_phone) . "' WHERE content_key = 'footer_phone'");
                            $phone = $new_phone;
                        }
                        
                        $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='footer_facebook'");
                        if ($result && $row = $result->fetch_assoc()) {
                            $fb = $row['content_value'] ?? $fb;
                        }
                        
                        $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='footer_instagram'");
                        if ($result && $row = $result->fetch_assoc()) {
                            $ig = $row['content_value'] ?? $ig;
                        }
                        
                        $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='footer_twitter'");
                        if ($result && $row = $result->fetch_assoc()) {
                            $tw = $row['content_value'] ?? $tw;
                        }
                    }
                    $conn->close();
                    if (trim(strtolower($email)) === 'contact@faymure.com') {
                        $email = 'info@faymure.com';
                    }
                    $phone_tel = preg_replace('/\s+/', '', $phone);
                    ?>
                    <p><i class="fas fa-envelope"></i> <a href="mailto:<?php echo htmlspecialchars($email); ?>"><?php echo htmlspecialchars($email); ?></a></p>
                    <p><i class="fas fa-phone"></i> <a href="tel:<?php echo htmlspecialchars($phone_tel); ?>"><?php echo htmlspecialchars($phone); ?></a></p>
                    <div class="social-links">
                        <a href="<?php echo $fb; ?>" target="_blank"><i class="fab fa-facebook"></i></a>
                        <a href="<?php echo $ig; ?>" target="_blank"><i class="fab fa-instagram"></i></a>
                        <a href="<?php echo $tw; ?>" target="_blank"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                <div class="footer-section footer-story">
                    <h3>Our Story</h3>
                    <p>FAYMURE is dedicated to crafting premium leather goods that combine traditional craftsmanship with modern design.</p>
                    <a href="<?php echo (defined('BASE_PATH') ? BASE_PATH : ''); ?>/about">Learn More</a>
                </div>
                <div class="footer-section">
                    <h3>FAQ</h3>
                    <ul>
                        <li><a href="<?php echo (defined('BASE_PATH') ? BASE_PATH : ''); ?>/faq">Frequently Asked Questions</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="<?php echo (defined('BASE_PATH') ? BASE_PATH : ''); ?>/assets/js/main.js"></script>
    <script defer src="<?php echo (defined('BASE_PATH') ? BASE_PATH : ''); ?>/assets/js/visitor-tracker.js?v=<?php echo time(); ?>"></script>
    <script defer src="<?php echo (defined('BASE_PATH') ? BASE_PATH : ''); ?>/assets/js/motion.js"></script>
</body>
</html>

