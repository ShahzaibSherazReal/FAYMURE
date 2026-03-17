<?php
require_once 'config/config.php';
require_once 'includes/header.php';

$conn = getDBConnection();
$categories = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order, name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
    <main class="categories-page">
        <div class="container">
            <h1 class="page-title reveal">Browse Our Categories</h1>
            <div class="categories-grid stagger">
                <?php foreach ($categories as $category): ?>
                    <a href="<?php echo (defined('BASE_PATH') ? BASE_PATH : ''); ?>/products?category=<?php echo $category['slug']; ?>" class="category-card hover-lift reveal">
                        <div class="category-image">
                            <?php
                            $imgs = [];
                            if (!empty($category['images'])) {
                                $tmp = json_decode($category['images'], true);
                                if (is_array($tmp)) $imgs = $tmp;
                            }
                            if (!empty($category['image']) && !in_array($category['image'], $imgs, true)) {
                                array_unshift($imgs, $category['image']);
                            }
                            $imgs = array_values(array_filter($imgs));
                            ?>
                            <?php if (!empty($imgs)): ?>
                                <div class="cat-thumb-carousel" data-interval="1760">
                                    <?php foreach ($imgs as $i => $p): ?>
                                        <img class="<?php echo $i === 0 ? 'is-active' : ''; ?>" src="<?php echo htmlspecialchars($p); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>">
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="placeholder-image">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="category-info">
                            <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                            <?php if ($category['description']): ?>
                                <p><?php echo htmlspecialchars(substr($category['description'], 0, 100)); ?>...</p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

<style>
.cat-thumb-carousel { position:absolute; inset:0; overflow:hidden; }
.cat-thumb-carousel img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; opacity:0; transition: opacity 450ms ease; }
.cat-thumb-carousel img.is-active { opacity:1; }
</style>
<script>
(function() {
  function init(el) {
    var imgs = el.querySelectorAll('img');
    if (!imgs || imgs.length <= 1) return;
    var idx = 0;
    var interval = parseInt(el.getAttribute('data-interval') || '1760', 10);
    setInterval(function() {
      imgs[idx].classList.remove('is-active');
      idx = (idx + 1) % imgs.length;
      imgs[idx].classList.add('is-active');
    }, interval);
  }
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.cat-thumb-carousel').forEach(init);
  });
})();
</script>

<?php require_once 'includes/footer.php'; ?>

