# Clientele PHP Setup

## 1. Create MySQL Database

In GoDaddy cPanel, create:

- one MySQL database
- one MySQL user
- assign the user to the database with full permissions

Update `includes/config.php` with those database values.

## 2. Import SQL

Open phpMyAdmin and import these files in order:

1. `database/schema.sql`
2. `database/seed.sql`

Default admin login:

```text
Username: admin
Password: admin123
```

Change this password after first login by replacing the password hash in the database with a new `password_hash()` value.

## 3. Upload Files

Upload the project folder contents to the hosting directory.

Use:

- Public site: `index.php`
- Admin login: `admin/login.php`

The older static files are kept only as backups:

- `index-static-backup.html`
- `admin-static-prototype.html`

## 4. Next Production Hardening

- Change `APP_SECRET` in `includes/config.php`
- Change the default admin password
- Use HTTPS
- Remove backup prototype files from the hosted public folder if not needed
