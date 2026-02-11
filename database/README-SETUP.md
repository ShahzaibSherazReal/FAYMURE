# Database Setup Instructions

## Quick Setup (Recommended)

1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Select the `faymure` database from the left sidebar
3. Click on the **SQL** tab at the top
4. Copy the entire contents of `complete-setup.sql`
5. Paste it into the SQL query box
6. Click **Go** or press **Ctrl+Enter**

This will:
- ✅ Drop all old tables
- ✅ Create all new tables with proper structure
- ✅ Insert default admin user (username: `admin`, password: `admin123`)
- ✅ Insert default categories
- ✅ Insert default site content
- ✅ Insert sample reviews

## What Gets Created

### Tables:
1. **users** - Admin and regular user accounts
2. **categories** - Product categories
3. **products** - Product information
4. **orders** - Customer orders/quote requests
5. **site_content** - Editable site content
6. **reviews** - Customer reviews

### Default Data:
- **Admin User**: username `admin`, password `admin123`
- **6 Categories**: Jackets, Wallets, Purses, Travel Bags, Gloves, Bags
- **Site Content**: Hero tagline, Vision, Mission, Services, Footer info
- **6 Sample Reviews**: For homepage display

## After Running the Script

1. Your website should work immediately
2. Login to admin panel: `http://localhost/FAYMURE/login.php`
   - Username: `admin`
   - Password: `admin123`
3. Start adding products through the admin panel

## Troubleshooting

If you get foreign key errors:
- The script disables foreign key checks at the start
- If issues persist, run the DROP statements manually first

If tables already exist:
- The script uses `DROP TABLE IF EXISTS` so it's safe to run multiple times

## Manual Setup Alternative

If you prefer to run the setup script instead:
- Visit: `http://localhost/FAYMURE/setup-database.php`
- This will check and add missing columns/tables without dropping existing data

