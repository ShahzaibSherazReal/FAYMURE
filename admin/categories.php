<?php
require_once 'check-auth.php';

$conn = getDBConnection();

$upload_dir = '../assets/images/categories/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = sanitize($_POST['name'] ?? '');
                $slug = sanitize($_POST['slug'] ?? '');
                $description = sanitize($_POST['description'] ?? '');
                $image = '';
                
                // Handle image upload
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $file_name = uniqid() . '.' . $file_ext;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file_name)) {
                        $image = 'assets/images/categories/' . $file_name;
                    }
                }
                
                if ($name && $slug) {
                    $stmt = $conn->prepare("INSERT INTO categories (name, slug, description, image) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $name, $slug, $description, $image);
                    $stmt->execute();
                    $stmt->close();
                }
                break;
            case 'edit':
                $id = intval($_POST['id']);
                $name = sanitize($_POST['name'] ?? '');
                $slug = sanitize($_POST['slug'] ?? '');
                $description = sanitize($_POST['description'] ?? '');
                
                // Get current category
                $current = $conn->query("SELECT image FROM categories WHERE id = $id")->fetch_assoc();
                $image = $current['image'] ?? '';
                
                // Handle remove image
                if (isset($_POST['remove_image'])) {
                    if (!empty($image) && file_exists('../' . $image)) {
                        unlink('../' . $image);
                    }
                    $image = '';
                }
                
                // Handle image upload
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    // Remove old image if exists
                    if (!empty($current['image']) && file_exists('../' . $current['image'])) {
                        unlink('../' . $current['image']);
                    }
                    $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $file_name = uniqid() . '.' . $file_ext;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file_name)) {
                        $image = 'assets/images/categories/' . $file_name;
                    }
                }
                
                if ($name && $slug) {
                    $stmt = $conn->prepare("UPDATE categories SET name=?, slug=?, description=?, image=? WHERE id=?");
                    $stmt->bind_param("ssssi", $name, $slug, $description, $image, $id);
                    $stmt->execute();
                    $stmt->close();
                }
                break;
            case 'delete':
                $id = intval($_POST['id']);
                // Get image before deleting
                $cat = $conn->query("SELECT image FROM categories WHERE id = $id")->fetch_assoc();
                if ($cat && !empty($cat['image']) && file_exists('../' . $cat['image'])) {
                    unlink('../' . $cat['image']);
                }
                $conn->query("UPDATE categories SET deleted_at = NOW() WHERE id = $id");
                break;
        }
    }
}

$categories = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order, name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Admin - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/admin-header.php'; ?>
    
    <main class="admin-main">
        <div class="admin-container">
            <div class="page-header">
                <h1>Categories</h1>
                <button onclick="showAddForm()" class="btn-primary"><i class="fas fa-plus"></i> Add Category</button>
            </div>
            
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cover Image</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="6">No categories found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><?php echo $cat['id']; ?></td>
                                    <td>
                                        <?php if (!empty($cat['image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($cat['image']); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border-color);">
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 12px;">No image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                    <td><?php echo htmlspecialchars($cat['slug']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($cat['description'] ?? '', 0, 50)); ?>...</td>
                                    <td class="actions">
                                        <button onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)" class="btn-edit"><i class="fas fa-edit"></i></button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                            <button type="submit" class="btn-delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <!-- Add/Edit Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Add Category</h2>
            <form method="POST" id="categoryForm" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="categoryId">
                
                <div class="form-group">
                    <label for="cat_name">Name *</label>
                    <input type="text" id="cat_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="cat_slug">Slug *</label>
                    <input type="text" id="cat_slug" name="slug" required>
                </div>
                
                <div class="form-group">
                    <label for="cat_description">Description</label>
                    <textarea id="cat_description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group" id="imageGroup">
                    <label for="cat_image">Cover Image</label>
                    <div id="currentImageContainer" style="margin-bottom: 10px; display: none;">
                        <img id="currentImagePreview" src="" alt="Current Cover Image" style="max-width: 300px; max-height: 200px; display: block; margin-bottom: 10px; border: 1px solid var(--border-color); border-radius: 4px; object-fit: cover;">
                        <button type="button" id="removeImageBtn" class="btn-delete" onclick="removeCategoryImage()">Remove Cover Image</button>
                    </div>
                    <input type="file" id="cat_image" name="image" accept="image/*">
                    <small>Upload a cover image for this category. This will be displayed on the category listing page.</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Save</button>
                    <button type="button" onclick="closeModal()" class="btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function editCategory(cat) {
            document.getElementById('modalTitle').textContent = 'Edit Category';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('categoryId').value = cat.id;
            document.getElementById('cat_name').value = cat.name;
            document.getElementById('cat_slug').value = cat.slug;
            document.getElementById('cat_description').value = cat.description || '';
            
            // Handle image display
            const imageContainer = document.getElementById('currentImageContainer');
            const imagePreview = document.getElementById('currentImagePreview');
            if (cat.image) {
                imagePreview.src = '../' + cat.image;
                imageContainer.style.display = 'block';
            } else {
                imageContainer.style.display = 'none';
            }
            
            document.getElementById('categoryModal').style.display = 'block';
        }
        
        function removeCategoryImage() {
            if (confirm('Remove this image?')) {
                const form = document.getElementById('categoryForm');
                const removeInput = document.createElement('input');
                removeInput.type = 'hidden';
                removeInput.name = 'remove_image';
                removeInput.value = '1';
                form.appendChild(removeInput);
                form.submit();
            }
        }
        
        function showAddForm() {
            document.getElementById('modalTitle').textContent = 'Add Category';
            document.getElementById('formAction').value = 'add';
            document.getElementById('categoryForm').reset();
            document.getElementById('categoryId').value = '';
            document.getElementById('currentImageContainer').style.display = 'none';
            document.getElementById('categoryModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }
        
        // Auto-generate slug
        document.getElementById('cat_name').addEventListener('input', function() {
            const slug = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            document.getElementById('cat_slug').value = slug;
        });
    </script>
</body>
</html>

