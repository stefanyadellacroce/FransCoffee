# InfinityFree deployment

This folder prepares a production copy of the Franscoffee PHP/MySQL app. The database import is intended for a **new, empty** InfinityFree database.

1. Create a hosting account, domain, and MySQL database in the InfinityFree dashboard. Enable HTTPS for the domain.
2. Copy `production-settings.example.php` to `production-settings.php`. Enter the HTTPS site URL, the exact MySQL details from the dashboard, and a unique admin email/password. Keep this file private. It is excluded from the output. Set `app_path` to `/franscoffee` when the app is visited at `/franscoffee/`; set it to an empty string when the domain's document directory is a dedicated `htdocs` and the app is visited at the domain root. Set `ftp_remote_dir` to the domain's actual document directory shown in the panel.
3. Run `C:\xampp\php\php.exe deployment\infinityfree\build.php` from the project root. It creates a dated folder under `deployment/infinityfree/output/` containing `htdocs/` and `import.sql`. `FRANSCOFFEE_BUILD_SITE_URL` can override the site root before the build.
4. Open the new database in InfinityFree phpMyAdmin. Import `import.sql` there. Do not upload the SQL file into `htdocs`.
5. Upload the **contents** of the generated `htdocs/` folder to the domain's `htdocs/` directory using FTP with explicit TLS. Keep the `.htaccess` files. Do not upload the project `database/`, `tests/`, documentation, or `production-settings.php`.
6. Visit the HTTPS site. Check storefront, registration, admin login, checkout, and image uploads. Verify that `/config.php` and `/includes/bootstrap.php` return access denied.
7. In Admin → Settings, enter the real contact details and payment receivers. The generated import disables GCash and Maya until they are configured. Check delivery settings, fees, hours, logo, inventory, and product availability before accepting orders.

The build creates a unique admin account and omits all demo accounts, sample operations, and fake e-wallet receiver details. The admin password is hashed in the SQL; the plain password is not copied to `htdocs/`. Delete the local `production-settings.php` or store it securely after launch. Online password reset is unavailable until an email delivery service is implemented.

InfinityFree does not allow direct MySQL connections from a local computer, so the database import must run through its phpMyAdmin. The source `database/franscoffee.sql` has `CREATE DATABASE` and `USE` statements that are inappropriate for InfinityFree; import only the generated `import.sql`.
