# Franscoffee Shop — Deployment Checklist

Before deploying online:

1. **Backup** the local database and uploaded assets.
2. Change `config.php`:
   - `base_url` to the HTTPS production URL.
   - database host/name/user/password to production credentials.
   - `environment` to `production`.
   - `debug` to `false`.
3. Use a dedicated database user with only the permissions the application needs; do not use production `root`.
4. Enable HTTPS and verify PHP detects HTTPS so the session cookie receives the `Secure` flag.
5. Replace all demo GCash/Maya receiver numbers and QR placeholders in Admin → Settings.
6. Replace the local password-reset debug-link workflow with configured transactional email before relying on self-service recovery publicly.
7. Confirm `uploads/` subfolders are writable by PHP but do not allow script execution.
8. Confirm `.htaccess` rules are enabled (`AllowOverride` as needed) or reproduce equivalent restrictions in the production web-server configuration.
9. Set appropriate PHP limits (`upload_max_filesize`, `post_max_size`, `session.gc_maxlifetime`) consistent with the application limits.
10. Re-run `FINAL_TESTING.md` on the production-like/staging environment.
11. Verify Admin → Settings contact details, store address, hours, delivery fee, minimum order, payment methods, and shop logo.
12. Create real Admin/Staff accounts and deactivate/remove any demo accounts you do not want publicly accessible.
13. Review Activity Logs and database backups regularly after launch.
