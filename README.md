# Smart Hub — PHP Backend Starter

## Stack
- PHP 8+
- MySQL 8+
- PDO with prepared statements
- Session-based authentication

## Setup
1. Put the `smart_hub` folder in your web server directory (for XAMPP: `htdocs`).
2. Start Apache and MySQL.
3. Open phpMyAdmin and import `database.sql`.
4. Edit `config/database.php` if your MySQL username/password differs.
5. Run `php config/seed_admin.php` once, or create an admin manually.
6. Open `/smart_hub/auth/login.php`.

Demo admin credentials created by the seed script:
- Email: admin@smarthub.local
- Password: ChangeMe123!

Change the demo password before real deployment.

## Current backend pieces
- Database schema
- PDO connection
- Login/logout
- Password hashing/verification
- Session authentication
- Role checking
- Student result retrieval
- Basic dashboard statistics

## Next build stage
Add complete admin CRUD, lecturer result entry, resource uploads, announcements, notifications, messaging, API endpoints, validation, CSRF protection, and the polished UI from the Smart Hub mockups.
