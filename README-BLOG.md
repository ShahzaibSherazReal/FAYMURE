# FAYMURE Blog Module

This document describes the blog feature: URLs, database schema, how to create posts (via database for now), deployment, and cache.

## URLs (clean, no .php)

| URL | Description |
|-----|-------------|
| `/blog` | Blog index: featured posts + latest with pagination |
| `/blog/post/{slug}` | Single article (e.g. `/blog/post/how-to-care-for-leather`) |
| `/blog/category/{slug}` | Category archive (e.g. `/blog/category/leather-care`) |
| `/blog/tag/{slug}` | Tag archive (e.g. `/blog/tag/aniline`) |
| `/blog/search?q=keyword` | Search by keyword (title + content) |
| `/blog/sitemap.xml` | Blog sitemap for search engines |

Base path: if the site uses `BASE_PATH` (e.g. `/FAYMURE`), all URLs are prefixed (e.g. `/FAYMURE/blog`).

## Database tables

Created by **setup-database.php** (run once):

- **blog_authors** – id, name, slug, bio, avatar_url
- **blog_categories** – id, slug, name, description
- **blog_tags** – id, slug, name
- **blog_posts** – id, slug, title, excerpt, featured_image, author_id, reading_time_minutes, is_featured, status (draft/published), meta_title, meta_description, content_blocks (JSON), published_at, updated_at, created_at
- **blog_post_categories** – post_id, category_id (many-to-many)
- **blog_post_tags** – post_id, tag_id (many-to-many)
- **blog_post_images** – id, post_id, image_url, caption, sort_order (gallery)

## How to create a post (via database)

1. **Author** (if needed):
   ```sql
   INSERT INTO blog_authors (name, slug, bio) VALUES ('Your Name', 'your-name', 'Short bio.');
   ```

2. **Category** (if needed):
   ```sql
   INSERT INTO blog_categories (slug, name, description) VALUES ('leather-care', 'Leather Care', 'Tips for leather care.');
   ```

3. **Tag** (if needed):
   ```sql
   INSERT INTO blog_tags (slug, name) VALUES ('aniline', 'Aniline');
   ```

4. **Post** (slug must be unique, use lowercase and hyphens):
   ```sql
   INSERT INTO blog_posts (
     slug, title, excerpt, author_id, featured_image,
     reading_time_minutes, is_featured, status, meta_title, meta_description,
     content_blocks, published_at
   ) VALUES (
     'my-post-slug',
     'My Post Title',
     'Short excerpt for cards and SEO.',
     1,
     '/assets/images/your-image.jpg',
     4,
     0,
     'published',
     'My Post Title | FAYMURE Blog',
     'Short meta description for search results.',
     '[{"type":"paragraph","content":"First paragraph."},{"type":"heading","level":2,"content":"Section"},{"type":"paragraph","content":"More text."},{"type":"quote","content":"A quote."},{"type":"list","items":["Item one","Item two"]}]',
     NOW()
   );
   ```
   Get the new post `id` (e.g. `LAST_INSERT_ID()` or `SELECT id FROM blog_posts WHERE slug = 'my-post-slug'`).

5. **Link category and tag**:
   ```sql
   INSERT INTO blog_post_categories (post_id, category_id) VALUES (2, 1);
   INSERT INTO blog_post_tags (post_id, tag_id) VALUES (2, 1);
   ```

**content_blocks** JSON format:

- `{"type":"paragraph","content":"Text"}`
- `{"type":"heading","level":2,"content":"Heading text"}` (level 1–6)
- `{"type":"image","url":"/path/to/image.jpg","caption":"Optional caption"}`
- `{"type":"quote","content":"Quote text"}`
- `{"type":"list","items":["Item 1","Item 2"]}`
- `{"type":"gallery","images":[{"url":"/path/1.jpg","caption":""},{"url":"/path/2.jpg","caption":""}]}`

## How to add a new category or tag

**Category:**
```sql
INSERT INTO blog_categories (slug, name, description) VALUES ('new-category', 'New Category', 'Optional description.');
```

**Tag:**
```sql
INSERT INTO blog_tags (slug, name) VALUES ('new-tag', 'New Tag');
```

Slugs should be lowercase, alphanumeric and hyphens only (e.g. `leather-care`). They appear in URLs: `/blog/category/new-category`, `/blog/tag/new-tag`.

## Deployment

1. **Database**  
   Run `setup-database.php` on the server once (or ensure blog tables exist). This creates blog tables and, if the blog is empty, one sample post.

2. **Config**  
   Set `SITE_URL` and `BASE_PATH` in `config/config.php` (e.g. `BASE_PATH = ''` at domain root).

3. **.htaccess**  
   If the site is at domain root, update `RewriteBase` and the redirect rule as in **HOSTINGER-SETUP.txt**. The blog rules are already in `.htaccess`:
   - `^blog/?$` → `blog/index.php`
   - `^blog/post/([a-z0-9\-]+)/?$` → `blog/post.php?slug=$1`
   - `^blog/category/([a-z0-9\-]+)/?$` → `blog/category.php?slug=$1`
   - `^blog/tag/([a-z0-9\-]+)/?$` → `blog/tag.php?slug=$1`
   - `^blog/search/?$` → `blog/search.php`
   - `^blog/sitemap\.xml$` → `blog/sitemap.php`

4. **Cache**  
   Blog index and single post pages use a simple file cache under `cache/blog/`. TTL is 5 minutes for index, 10 minutes for posts. To clear after adding/editing content via SQL, delete files in `cache/blog/` or call `blog_cache_clear()` (e.g. from a future admin “Clear blog cache” action).

5. **Sitemap**  
   Submit `https://yourdomain.com/blog/sitemap.xml` (or with `BASE_PATH`) in Google Search Console / other tools. The main site sitemap can link to it if you have one.

## SEO

- **Meta title / description** – From `blog_posts.meta_title` and `meta_description`, or fallbacks.
- **Canonical** – Set on index, post, category, tag, and search.
- **Open Graph & Twitter** – Title, description, image (featured image), type (article for posts).
- **JSON-LD** – BreadcrumbList on all blog pages; Article on single post (headline, dates, author, publisher).

## Security

- **Prepared statements** – All blog queries use bound parameters where user/slug input is used.
- **Output** – All dynamic output is escaped with `htmlspecialchars()` in templates.
- **Search rate limit** – Blog search is limited to 30 requests per minute per session/IP to reduce abuse.

## Caching

- **Index** – Cached for 5 minutes (key includes page number).
- **Post** – Cached for 10 minutes per slug.
- **Category / tag / search** – Not cached by default (can be added similarly if needed).

Cache directory: `cache/blog/`. Ensure the web server can create and write files there, or disable cache by not creating the directory.

## No breaking changes

The blog is additive: new tables, new files under `blog/`, new `.htaccess` rules, and one new nav item “Blog” in the main header. Existing pages and behaviour are unchanged.
