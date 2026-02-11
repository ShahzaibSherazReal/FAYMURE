# FAYMURE - Leather Goods E-Commerce Website

A comprehensive PHP-based e-commerce website for leather goods including jackets, wallets, purses, travel bags, gloves, and more.

## Features

### Frontend
- **Homepage** with hero video, brand logo, tagline, and explore button
- **Carousel section** displaying vision, mission, and services
- **Customer reviews** with continuous loop animation
- **Categories page** with product browsing
- **Product listing** with gender filters (Male/Female/Unisex)
- **Product detail pages** with images, description, MOQ, and contact form
- **Contact/Quote form** that sends emails to admin
- **Language detection and toggle** (English/Spanish)
- **Responsive design** for all devices

### Admin Panel
- **Dashboard** with statistics and recent orders
- **Product Management**
  - Add, edit, and delete products
  - Upload multiple images
  - Set MOQ, price, category, subcategory
  - Filter by category, status, date, search
  - Bulk delete (selected or all)
  - Soft delete functionality
- **Category Management** - Add, edit, delete categories
- **Order Management** - View and update order status
- **User Management** - View and manage users
- **Site Content Management** - Edit site content without code changes

## Installation

1. **Database Setup**
   - Create a database named `faymure` in phpMyAdmin
   - Run `setup-database.php` in your browser to create tables and default data
   - Or import `database/schema.sql` manually

2. **Configuration**
   - Update `config/database.php` with your database credentials if needed
   - Update `ADMIN_EMAIL` in `config/config.php` for receiving contact form emails

3. **File Structure**
   ```
   FAYMURE/
   ├── admin/
   ├── assets/
   │   ├── css/
   │   ├── js/
   │   ├── images/
   │   └── videos/
   ├── config/
   ├── database/
   ├── includes/
   └── [PHP files]
   ```

4. **Required Directories**
   - Create `assets/images/products/` directory with write permissions for image uploads
   - Add your `hero.mp4` video to `assets/videos/`

## Default Admin Login

- **Username:** admin
- **Password:** admin123

## Usage

1. **Homepage**: Visit `index.php` to see the main website
2. **Admin Panel**: Login at `login.php` and access dashboard at `admin/dashboard.php`
3. **Add Products**: Go to Admin > Products > Add New Product
4. **Manage Content**: Admin > Site Content to edit text without code changes

## Database Tables

- `users` - User accounts (admin and regular users)
- `categories` - Product categories
- `products` - Product information
- `orders` / `quote_requests` - Customer inquiries/orders
- `site_content` - Editable site content
- `reviews` - Customer reviews

## Notes

- The website detects user's browser language and offers language toggle
- All deletes are soft deletes (deleted_at timestamp)
- Contact forms send emails to the admin email address
- Product images are stored in `assets/images/products/`
- The site is fully responsive and works on mobile devices

## Support

For issues or questions, please contact the development team.

