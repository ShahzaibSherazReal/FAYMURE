    <footer class="main-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-section">
                    <h3>Contact</h3>
                    <?php
                    $conn = getDBConnection();
                    $email = 'contact@faymure.com';
                    $phone = '+1 (555) 123-4567';
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
                    ?>
                    <p><i class="fas fa-envelope"></i> <a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a></p>
                    <p><i class="fas fa-phone"></i> <a href="tel:<?php echo $phone; ?>"><?php echo $phone; ?></a></p>
                    <div class="social-links">
                        <a href="<?php echo $fb; ?>" target="_blank"><i class="fab fa-facebook"></i></a>
                        <a href="<?php echo $ig; ?>" target="_blank"><i class="fab fa-instagram"></i></a>
                        <a href="<?php echo $tw; ?>" target="_blank"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h3>Our Story</h3>
                    <p>FAYMURE is dedicated to crafting premium leather goods that combine traditional craftsmanship with modern design.</p>
                    <a href="about.php">Learn More</a>
                </div>
                <div class="footer-section">
                    <h3>FAQ</h3>
                    <ul>
                        <li><a href="faq.php">Frequently Asked Questions</a></li>
                        <li><a href="shipping.php">Shipping Information</a></li>
                        <li><a href="returns.php">Return Policy</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Legal</h3>
                    <ul>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                        <li><a href="terms.php">Terms & Conditions</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
    <script defer src="assets/js/motion.js"></script>
</body>
</html>

