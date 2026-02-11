<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();
$about_content = '';

$columns_check = $conn->query("SHOW COLUMNS FROM site_content LIKE 'content_value'");
if ($columns_check && $columns_check->num_rows > 0) {
    $result = $conn->query("SELECT content_value FROM site_content WHERE content_key='about_content'");
    if ($result && $row = $result->fetch_assoc()) {
        $about_content = $row['content_value'] ?? '';
    }
}
$conn->close();
?>
    <main class="page-content">
        <div class="container">
            <h1 class="page-title">About Us</h1>
            
            <?php if (!empty($about_content)): ?>
                <div class="content-section">
                    <?php echo $about_content; ?>
                </div>
            <?php else: ?>
                <div class="content-section">
                    <p class="intro-text">At FAYMURE, we take pride in offering a comprehensive range of premium leather services designed to meet the diverse needs of our customers. Our expertise spans manufacturing, designing, and customization, ensuring that every leather product we deliver embodies quality, style, and durability.</p>
                </div>

            <div class="content-section">
                <h2 class="section-heading">Our Expertise</h2>
                
                <div class="service-item">
                    <h3 class="service-title">1. Leather Goods Manufacturing</h3>
                    <p>We specialize in crafting high-quality leather products using the finest materials and skilled craftsmanship. Our manufacturing capabilities include a wide variety of items such as belts, shoes, bags, wallets, gloves, jackets, rugs, leather hides for furniture, and keychains. Each product is meticulously made to ensure lasting durability, refined style, and exceptional quality that reflects our commitment to excellence.</p>
                </div>

                <div class="service-item">
                    <h3 class="service-title">2. Our Designs</h3>
                    <p>Beyond manufacturing, we are passionate about design. Our in-house team creates elegant and innovative leather designs that combine timeless style with contemporary trends. Whether you are seeking classic sophistication or modern flair, our designs are thoughtfully curated to inspire and delight.</p>
                </div>

                <div class="service-item">
                    <h3 class="service-title">3. Customization</h3>
                    <p>We understand that every customer has unique preferences. That's why we offer personalized customization services, allowing you to adapt our designs to suit your style and needs. From selecting materials, colors, and finishes to adding personal touches, we ensure that each customized product is truly one-of-a-kind—crafted to reflect your personality while maintaining the highest standards of quality and durability.</p>
                </div>
            </div>

            <div class="content-section">
                <h2 class="section-heading">Our Vision</h2>
                <p class="intro-text">We aspire to set the pinnacle of excellence in the leather industry, crafting products that are not only admired for their artistry but also cherished and trusted by our customers. Our vision is anchored in five unwavering commitments:</p>
                
                <div class="commitment-item">
                    <h3 class="commitment-title">1. Commitment to Quality</h3>
                    <p>We are devoted to delivering leather goods of unparalleled quality. From meticulously selecting the finest materials to executing every detail with precision, our craftsmanship reflects a relentless pursuit of perfection. Each product is a testament to our dedication to superior standards, ensuring enduring excellence that surpasses expectations.</p>
                </div>

                <div class="commitment-item">
                    <h3 class="commitment-title">2. Commitment to Style</h3>
                    <p>We endeavor to create leather products that exude timeless elegance and sophisticated style. Every design harmoniously blends classic aesthetics with contemporary sensibilities, empowering our customers to express their individuality with grace and confidence.</p>
                </div>

                <div class="commitment-item">
                    <h3 class="commitment-title">3. Commitment to Durability</h3>
                    <p>Durability is at the core of our creations. We craft each piece to withstand the rigors of daily life, maintaining its form, function, and beauty over time. Our focus on longevity celebrates the enduring value of true craftsmanship.</p>
                </div>

                <div class="commitment-item">
                    <h3 class="commitment-title">4. Commitment to Sustainability</h3>
                    <p>We champion responsible and sustainable practices at every stage of our process. From ethically sourced materials to environmentally conscious production, our mission is to craft products that honor both people and the planet, ensuring a positive legacy for future generations.</p>
                </div>

                <div class="commitment-item">
                    <h3 class="commitment-title">5. Commitment to Customer Trust</h3>
                    <p>Earning our customers' trust is our highest achievement. We strive to create leather goods that inspire confidence, devotion, and pride. Through integrity, transparency, and unwavering service, we aim to foster enduring relationships that extend far beyond a single purchase.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <style>
        .page-content {
            padding: 100px 0;
        }

        .content-section {
            margin-bottom: 60px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }

        .content-section:last-child {
            margin-bottom: 0;
        }

        .intro-text {
            font-size: 18px;
            line-height: 1.8;
            color: var(--text-color);
            font-weight: 300;
            margin-bottom: 40px;
        }

        .section-heading {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            color: var(--primary-color);
            margin-bottom: 40px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .service-item,
        .commitment-item {
            margin-bottom: 40px;
            padding-bottom: 40px;
            border-bottom: 1px solid var(--border-color);
        }

        .service-item:last-child,
        .commitment-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .service-title,
        .commitment-title {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 16px;
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .service-item p,
        .commitment-item p {
            font-size: 16px;
            line-height: 1.8;
            color: var(--text-color);
            font-weight: 300;
            letter-spacing: 0.2px;
        }

        @media (max-width: 768px) {
            .page-content {
                padding: 50px 0;
            }

            .content-section {
                margin-bottom: 40px;
            }

            .section-heading {
                font-size: 28px;
                margin-bottom: 30px;
            }

            .service-title,
            .commitment-title {
                font-size: 20px;
                margin-bottom: 12px;
            }

            .service-item p,
            .commitment-item p {
                font-size: 14px;
                line-height: 1.7;
            }

            .intro-text {
                font-size: 16px;
                margin-bottom: 30px;
            }

            .service-item,
            .commitment-item {
                margin-bottom: 30px;
                padding-bottom: 30px;
            }
        }
    </style>

<?php require_once 'includes/footer.php'; ?>
