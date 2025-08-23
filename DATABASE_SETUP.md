# ShopSmart E-commerce Database Setup Guide

This guide will help you set up the MySQL database for your ShopSmart e-commerce website.

## 📋 Prerequisites

Before setting up the database, ensure you have:

- **MySQL Server** (version 5.7 or higher) installed and running
- **PHP** (version 7.4 or higher) with PDO MySQL extension
- **Web Server** (Apache/Nginx) configured to serve PHP files
- **MySQL User** with CREATE DATABASE privileges

## 🚀 Quick Setup

### 1. Database Installation

Run the setup script to automatically create the database and tables:

```bash
php setup_database.php
```

This script will:
- Connect to your MySQL server
- Create the `shopsmart_db` database
- Create all necessary tables
- Insert sample data
- Test the configuration

### 2. Manual Setup (Alternative)

If you prefer to set up manually:

1. **Create Database:**
   ```sql
   CREATE DATABASE shopsmart_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import Schema:**
   ```bash
   mysql -u your_username -p shopsmart_db < database_schema.sql
   ```

## ⚙️ Configuration

### Database Connection

Update the database credentials in `config/database.php`:

```php
private $host = 'localhost';
private $db_name = 'shopsmart_db';
private $username = 'your_mysql_username';
private $password = 'your_mysql_password';
```

### Security Considerations

1. **Create a dedicated MySQL user:**
   ```sql
   CREATE USER 'shopsmart_user'@'localhost' IDENTIFIED BY 'strong_password';
   GRANT ALL PRIVILEGES ON shopsmart_db.* TO 'shopsmart_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. **Set proper file permissions:**
   ```bash
   chmod 644 config/database.php
   chmod 755 api/
   ```

## 📊 Database Schema Overview

### Core Tables

| Table | Purpose | Key Features |
|-------|---------|--------------|
| `users` | User accounts and authentication | Password hashing, email verification |
| `products` | Product catalog | Pricing, inventory, categories |
| `categories` | Product organization | Hierarchical categories |
| `orders` | Customer orders | Status tracking, payment info |
| `shopping_cart` | Shopping cart items | Session-based for guests |

### Supporting Tables

| Table | Purpose |
|-------|---------|
| `product_images` | Multiple images per product |
| `product_specifications` | Detailed product attributes |
| `order_items` | Individual items in orders |
| `addresses` | Shipping/billing addresses |
| `reviews` | Product reviews and ratings |
| `wishlist` | User wishlists |
| `coupons` | Discount codes |
| `user_sessions` | Session tracking |

## 🔌 API Endpoints

### Authentication
- `POST /api/auth/register.php` - User registration
- `POST /api/auth/login.php` - User login

### Products
- `GET /api/products/get_products.php` - Get products with filtering

### Shopping Cart
- `GET /api/cart/cart_operations.php` - Get cart items
- `POST /api/cart/cart_operations.php` - Add item to cart
- `PUT /api/cart/cart_operations.php` - Update cart item
- `DELETE /api/cart/cart_operations.php` - Remove item from cart

## 📝 Sample API Usage

### Register a User
```javascript
fetch('/api/auth/register.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        full_name: 'John Doe',
        email: 'john@example.com',
        password: 'SecurePass123',
        confirm_password: 'SecurePass123'
    })
})
.then(response => response.json())
.then(data => console.log(data));
```

### Get Products
```javascript
fetch('/api/products/get_products.php?category=Audio&sort=price_low_high&page=1')
.then(response => response.json())
.then(data => console.log(data.products));
```

### Add to Cart
```javascript
fetch('/api/cart/cart_operations.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        product_id: 1,
        quantity: 2
    })
})
.then(response => response.json())
.then(data => console.log(data));
```

## 🔍 Database Views

The schema includes useful views for common queries:

### `product_catalog`
Complete product information with category and pricing:
```sql
SELECT * FROM product_catalog WHERE is_featured = TRUE;
```

### `order_summary`
Order information with customer details:
```sql
SELECT * FROM order_summary WHERE order_status = 'pending';
```

## 📈 Performance Optimization

### Indexes
The database includes optimized indexes for:
- User email lookups
- Product category filtering
- Order status queries
- Shopping cart operations

### Query Optimization Tips
1. Use the provided views for complex queries
2. Implement pagination for large result sets
3. Cache frequently accessed data
4. Use prepared statements (already implemented in API)

## 🔒 Security Features

### Data Protection
- Password hashing with `password_hash()`
- Input sanitization and validation
- SQL injection prevention with prepared statements
- XSS protection with `htmlspecialchars()`

### Session Management
- Secure session handling
- Session tracking in database
- Automatic session cleanup

## 🛠️ Maintenance

### Regular Tasks
1. **Backup Database:**
   ```bash
   mysqldump -u username -p shopsmart_db > backup_$(date +%Y%m%d).sql
   ```

2. **Clean Old Sessions:**
   ```sql
   DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY);
   ```

3. **Update Product Stock:**
   ```sql
   UPDATE products SET stock_quantity = stock_quantity - 1 WHERE product_id = ?;
   ```

### Monitoring
- Check error logs for database issues
- Monitor slow queries
- Track user activity patterns

## 🐛 Troubleshooting

### Common Issues

1. **Connection Failed**
   - Verify MySQL is running
   - Check credentials in `config/database.php`
   - Ensure user has proper privileges

2. **Permission Denied**
   - Check file permissions
   - Verify web server can access PHP files
   - Ensure MySQL user has required privileges

3. **Tables Not Created**
   - Run setup script again
   - Check MySQL error logs
   - Verify schema file exists

### Debug Mode
Enable error reporting in PHP:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📚 Additional Resources

- [MySQL Documentation](https://dev.mysql.com/doc/)
- [PHP PDO Documentation](https://www.php.net/manual/en/book.pdo.php)
- [E-commerce Best Practices](https://www.mysql.com/why-mysql/white-papers/)

## 🤝 Support

For database-related issues:
1. Check the error logs
2. Verify your MySQL configuration
3. Test with the setup script
4. Review the API documentation

---

**Note:** This database setup is designed for development and small to medium-scale e-commerce operations. For production use, consider additional security measures, backup strategies, and performance optimizations.
