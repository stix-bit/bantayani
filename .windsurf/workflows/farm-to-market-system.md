---
description: Create a complete farm-to-market management system
---

# Farm-to-Market System Workflow

## Overview
BANTAY-ANI is a comprehensive farm-to-market management system connecting farmers, buyers, and administrators in an agricultural marketplace.

## System Architecture

### User Roles
- **Farmers**: List crops, manage inventory, track orders, set pricing
- **Buyers**: Browse marketplace, place orders, track purchases
- **Admins**: Manage users, monitor system, generate reports

### Core Features
- User authentication and role-based access
- Crop inventory management
- Order processing and tracking
- Marketplace functionality
- Pricing benchmarking
- Search and discovery

## Setup Instructions

### 1. Database Setup
```bash
# Import database schema
mysql -u root -p bantayani_db < backend_sql/bantayani_db.sql
```

### 2. Configure Database
Edit `includes/config.php` with your database credentials:
- Host: localhost:3306
- Username: root
- Password: (your password)
- Database: bantayani_db

### 3. Web Server Setup
- Place files in web root (htdocs/bantayani)
- Ensure PHP 7.4+ and MySQL are running
- Configure Apache/Nginx to point to project directory

### 4. File Permissions
Set write permissions for upload directories:
```bash
chmod 755 uploads/
chmod 755 images/uploads/
```

## User Workflows

### Farmer Workflow
1. Register as farmer
2. Complete profile verification
3. Add crops to inventory
4. Set harvest dates and pricing
5. Monitor incoming orders
6. Update order status
7. View performance analytics

### Buyer Workflow
1. Register as buyer
2. Browse marketplace
3. Search for specific crops
4. Add items to cart
5. Place orders
6. Track delivery status
7. Rate farmers/products

### Admin Workflow
1. Login to admin dashboard
2. Verify farmer registrations
3. Monitor system activity
4. Generate reports
5. Manage user accounts
6. Resolve disputes

## Key File Structure
- `index.php` - Main dashboard
- `user/` - Authentication and profiles
- `farmer/` - Farmer-specific features
- `buyer/` - Buyer marketplace
- `admin/` - Administrative functions
- `includes/` - Shared components and configuration
- `backend_sql/` - Database schemas

## Development Notes
- Uses PHP with MySQL backend
- Responsive design with custom CSS
- Session-based authentication
- Role-based access control
- File upload support for profiles and documents
