<?php
$post = $post ?? null;
$is_new = !$post;
$base = BLOG_ADMIN_BASE;
?>
<div class="blog-admin-page">
    <h1><?php echo $is_new ? 'New post' : 'Edit post'; ?></h1>
    <?php if ($flash_error): ?>
    <div class="blog-admin-alert blog-admin-alert-error"><?php echo htmlspecialchars($flash_error); ?></div>
    <?php endif; ?>
    <form method="post" id="post-edit-form">
        <?php echo blog_admin_csrf_field(); ?>
        <div class="form-group">
            <label for="title">Title *</label>
            <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($post['title'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="slug">Slug</label>
            <input type="text" id="slug" name="slug" value="<?php echo htmlspecialchars($post['slug'] ?? ''); ?>" placeholder="auto from title">
        </div>
        <div class="form-group">
            <label for="excerpt">Excerpt</label>
            <textarea id="excerpt" name="excerpt" rows="3"><?php echo htmlspecialchars($post['excerpt'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label>Author</label>
            <select name="author_id">
                <option value="">—</option>
                <?php foreach ($authors as $a): ?>
                <option value="<?php echo (int)$a['id']; ?>" <?php echo (isset($post['author_id']) && (int)$post['author_id'] === (int)$a['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Categories</label>
            <select name="category_ids[]" multiple size="4">
                <?php foreach ($categories as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>" <?php echo in_array((int)$c['id'], $post_cats ?? []) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Tags</label>
            <select name="tag_ids[]" multiple size="4">
                <?php foreach ($tags as $t): ?>
                <option value="<?php echo (int)$t['id']; ?>" <?php echo in_array((int)$t['id'], $post_tags ?? []) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="draft" <?php echo ($post['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="published" <?php echo ($post['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                <option value="scheduled" <?php echo ($post['status'] ?? '') === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
            </select>
        </div>
        <div class="form-group">
            <label for="published_at">Published at</label>
            <input type="datetime-local" id="published_at" name="published_at" value="<?php echo !empty($post['published_at']) ? date('Y-m-d\TH:i', strtotime($post['published_at'])) : ''; ?>">
        </div>
        <div class="form-group">
            <label for="scheduled_at">Scheduled at</label>
            <input type="datetime-local" id="scheduled_at" name="scheduled_at" value="<?php echo !empty($post['scheduled_at']) ? date('Y-m-d\TH:i', strtotime($post['scheduled_at'])) : ''; ?>">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_featured" value="1" <?php echo !empty($post['is_featured']) ? 'checked' : ''; ?>> Featured post</label>
        </div>
        <div class="form-group">
            <label for="featured_image">Featured image URL</label>
            <input type="text" id="featured_image" name="featured_image" value="<?php echo htmlspecialchars($post['featured_image'] ?? ''); ?>" placeholder="/uploads/blog/...">
        </div>

        <h2>Content blocks</h2>
        <p><small>Add blocks below. Order is saved on submit.</small></p>
        <input type="hidden" name="content_blocks" id="content_blocks_input" value="<?php echo htmlspecialchars(json_encode($content_blocks)); ?>">
        <div class="blog-editor-blocks" id="blocks-container">
            <?php foreach ($content_blocks as $i => $b): ?>
            <div class="blog-editor-block" data-index="<?php echo $i; ?>">
                <span class="blog-editor-block-handle"><i class="fas fa-grip-vertical"></i></span>
                <div class="blog-editor-block-content">
                    <?php
                    $type = $b['type'] ?? 'paragraph';
                    if ($type === 'paragraph'): ?>
                    <p><?php echo htmlspecialchars($b['content'] ?? ''); ?></p>
                    <?php elseif ($type === 'heading'): ?>
                    <strong>H<?php echo (int)($b['level'] ?? 2); ?>:</strong> <?php echo htmlspecialchars($b['content'] ?? ''); ?>
                    <?php elseif ($type === 'image'): ?>
                    <em>Image:</em> <?php echo htmlspecialchars($b['url'] ?? ''); ?>
                    <?php elseif ($type === 'quote'): ?>
                    <blockquote><?php echo htmlspecialchars($b['content'] ?? ''); ?></blockquote>
                    <?php elseif ($type === 'list'): ?>
                    <em>List:</em> <?php echo htmlspecialchars(implode(', ', $b['items'] ?? [])); ?>
                    <?php elseif ($type === 'embed'): ?>
                    <em>Embed:</em> <?php echo htmlspecialchars($b['url'] ?? ''); ?>
                    <?php else: ?>
                    <span><?php echo htmlspecialchars($type); ?></span>
                    <?php endif; ?>
                </div>
                <div class="blog-editor-block-actions">
                    <button type="button" class="edit-block" data-index="<?php echo $i; ?>">Edit</button>
                    <button type="button" class="remove-block" data-index="<?php echo $i; ?>">Remove</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="blog-editor-add-block">
            <select id="new-block-type">
                <option value="paragraph">Paragraph</option>
                <option value="heading">Heading</option>
                <option value="image">Image</option>
                <option value="quote">Quote</option>
                <option value="list">List</option>
                <option value="embed">Embed (YouTube)</option>
            </select>
            <button type="button" id="add-block-btn">Add block</button>
        </div>

        <h2>SEO</h2>
        <div class="form-group">
            <label for="meta_title">Meta title</label>
            <input type="text" id="meta_title" name="meta_title" value="<?php echo htmlspecialchars($post['meta_title'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="meta_description">Meta description</label>
            <textarea id="meta_description" name="meta_description" rows="2"><?php echo htmlspecialchars($post['meta_description'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label for="og_title">OG title</label>
            <input type="text" id="og_title" name="og_title" value="<?php echo htmlspecialchars($post['og_title'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="og_description">OG description</label>
            <textarea id="og_description" name="og_description" rows="2"><?php echo htmlspecialchars($post['og_description'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label for="og_image">OG image URL</label>
            <input type="text" id="og_image" name="og_image" value="<?php echo htmlspecialchars($post['og_image'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="canonical_url">Canonical URL</label>
            <input type="url" id="canonical_url" name="canonical_url" value="<?php echo htmlspecialchars($post['canonical_url'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="robots_noindex" value="1" <?php echo !empty($post['robots_noindex']) ? 'checked' : ''; ?>> Noindex (hide from search engines)</label>
        </div>

        <div class="form-actions">
            <button type="submit" class="blog-admin-btn blog-admin-btn-primary">Save</button>
            <a href="<?php echo $base; ?>/posts" class="blog-admin-btn blog-admin-btn-secondary">Cancel</a>
            <?php if (!$is_new): ?>
            <a href="<?php echo (defined('BASE_PATH') ? BASE_PATH : '') . '/blog/post/' . htmlspecialchars($post['slug']); ?>" target="_blank" class="blog-admin-btn blog-admin-btn-secondary">Preview</a>
            <?php endif; ?>
        </div>
    </form>
</div>
<script>
(function() {
    var blocks = <?php echo json_encode($content_blocks); ?>;
    var input = document.getElementById('content_blocks_input');
    function syncInput() { input.value = JSON.stringify(blocks); }
    function addBlock(type) {
        var b = { type: type };
        if (type === 'paragraph' || type === 'heading' || type === 'quote') b.content = '';
        if (type === 'heading') b.level = 2;
        if (type === 'image') b.url = ''; b.caption = '';
        if (type === 'list') b.items = [];
        if (type === 'embed') b.url = '';
        blocks.push(b);
        syncInput();
        location.reload();
    }
    document.getElementById('add-block-btn') && document.getElementById('add-block-btn').addEventListener('click', function() {
        addBlock(document.getElementById('new-block-type').value);
    });
    document.querySelectorAll('.remove-block').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var i = parseInt(btn.dataset.index, 10);
            blocks.splice(i, 1);
            syncInput();
            location.reload();
        });
    });
})();
</script>
