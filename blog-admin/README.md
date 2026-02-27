# Blog Admin Panel (Blog CMS)

Separate admin panel for managing the FAYMURE blog. It does **not** share login, session, or code with the main site admin.

## URL

- **Local:** `http://localhost/FAYMURE/blog-admin`
- **Production:** `https://yourdomain.com/blog-admin` (or with your `BASE_PATH`)

## First-time setup

1. **Run the installer**  
   Open in browser:  
   `http://localhost/FAYMURE/blog-admin/install`  
   This creates:
   - `blog_admin_users`
   - `blog_admin_audit_log`
   - `blog_media`
   - `blog_post_revisions`
   - `blog_post_views`
   - `blog_admin_settings`
   - New columns on `blog_posts` (e.g. `scheduled_at`, `og_title`, `robots_noindex`, …)

2. **Delete or protect `install.php`**  
   After the first run, remove `blog-admin/install.php` or block access (e.g. via .htaccess or moving it outside the web root) so it cannot be run again.

## Login

- **Username:** `faymureblogadmin`
- **Password:** `BlogAdmin123`

Use these only for initial setup. Change the password as soon as possible (see below).

## How to change credentials

### Change password

1. Generate a new hash in PHP (e.g. run once in a temp file and then delete it):

```php
<?php
require_once 'config/database.php';
$conn = getDBConnection();
$new_password = 'YourNewSecurePassword';
$hash = password_hash($new_password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE blog_admin_users SET password_hash = ? WHERE username = 'faymureblogadmin'");
$stmt->bind_param('s', $hash);
$stmt->execute();
echo "Password updated.";
```

2. Or in MySQL (replace `YOUR_NEW_HASH` with the output of `password_hash('YourNewPassword', PASSWORD_DEFAULT)` in PHP):

```sql
UPDATE blog_admin_users SET password_hash = 'YOUR_NEW_HASH' WHERE username = 'faymureblogadmin';
```

### Add another user

```sql
INSERT INTO blog_admin_users (username, password_hash, role)
VALUES ('editor1', 'HASH_FROM_PHP', 'editor');
```

Use PHP to generate the hash; do not store plain-text passwords.

## Database migrations (SQL)

If you prefer to run SQL by hand instead of `install.php`, use:

- **Schema:** `blog-admin/migrations/001_blog_admin_schema.sql`

That file defines:

- `blog_admin_users`
- `blog_admin_audit_log`
- `blog_media`
- `blog_post_revisions`
- `blog_post_views`
- `blog_admin_settings`
- Optional `ALTER TABLE blog_posts` for new columns (e.g. `scheduled_at`, `og_title`, …)

After running it, create the default user and set the password hash in PHP as in “How to change credentials” above.

## Deployment steps

1. Upload the whole project (including the `blog-admin/` folder).
2. Ensure `config/database.php` (or your DB config) has the correct credentials for the server.
3. Set `BASE_PATH` in `blog-admin/config.php` if needed (e.g. `''` when the site is at the domain root).
4. Run the installer once: `https://yourdomain.com/blog-admin/install` (or with your base path).
5. Remove or protect `blog-admin/install.php`.
6. Change the default password and (optionally) create editor users.
7. Ensure the `blog-admin` rewrite rules are present in the root `.htaccess` (see below).
8. Create and make writable `uploads/blog/` (or the path set in `BLOG_ADMIN_UPLOAD_DIR`) for media uploads.

## .htaccess (Blog Admin routes)

These rules should exist in the **site root** `.htaccess`:

```apache
RewriteRule ^blog-admin/?$ blog-admin/index.php [L,QSA]
RewriteRule ^blog-admin/(.*)$ blog-admin/index.php?p=$1 [L,QSA]
```

So that:

- `/blog-admin` → dashboard (after login)
- `/blog-admin/login` → login
- `/blog-admin/logout` → logout
- `/blog-admin/posts` → posts list
- `/blog-admin/post-new` → new post
- `/blog-admin/post-edit/123` → edit post 123
- `/blog-admin/categories`, `/blog-admin/tags`, `/blog-admin/media`, `/blog-admin/settings`, `/blog-admin/audit` → respective pages

## Security summary

- **Sessions:** Isolated session name (`FAYMURE_BLOG_ADMIN_SESSION`), HttpOnly, SameSite=Lax.
- **Passwords:** `password_hash()` / `password_verify()` (bcrypt).
- **CSRF:** Token on all forms; validated on POST.
- **Rate limiting:** Login attempts limited (e.g. 5 per 15 minutes per IP) via audit log.
- **SQL:** Prepared statements for all user input.
- **Uploads:** Allowed MIME types (images only), max size, random filenames; stored under `/uploads/blog/`.

## Features

- **Posts:** Create, edit, delete; draft / published / scheduled; featured toggle; publish date, scheduled date.
- **Content:** Block-based (paragraph, heading, image, quote, list, embed); order saved on save.
- **SEO:** Meta title/description, slug, OG title/description/image, canonical URL, noindex.
- **Media:** Upload images; thumb/medium/large generated; list and delete; path reusable in posts.
- **Categories & tags:** Add, edit (categories), delete; assign to posts.
- **Settings:** Featured post IDs for homepage (comma-separated).
- **Audit log:** Who did what and when.
- **Revisions:** Snapshot saved on each post update (in `blog_post_revisions`).

The frontend blog (e.g. `/blog`) reads from the same `blog_posts`, `blog_categories`, `blog_tags`, etc., so content managed here appears on the site.
