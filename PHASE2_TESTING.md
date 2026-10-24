# Phase 2 Test Checklist

## Before testing

1. Start Apache and MySQL in XAMPP.
2. If this is an existing Phase 1 database, import `database/migrations/002_authentication.sql` once in phpMyAdmin.
3. If this is a fresh installation, import `database/franscoffee.sql` instead.
4. Confirm `config.php` uses the correct MySQL credentials and `base_url` is `http://localhost/franscoffee` when the folder is named `franscoffee`.

## Test 1 — Admin login

Open `http://localhost/franscoffee/login.php` and sign in with:

- Email: `francis.admin@franscoffee.com`
- Password: `Admin123!`

Expected: redirect to `/admin/dashboard.php` and show **Admin access verified**.

## Test 2 — Staff login

- Email: `francis.staff@franscoffee.com`
- Password: `Staff123!`

Expected: redirect to `/staff/dashboard.php` and show **Staff access verified**.

## Test 3 — Customer login

- Email: `francis.customer@franscoffee.com`
- Password: `Customer123!`

Expected: redirect to `/customer/dashboard.php` and show **Customer access verified**.

## Test 4 — Role protection

While signed in as Customer, manually open `/admin/dashboard.php`.

Expected: HTTP 403-style **Access denied** page. The customer must not see the admin dashboard.

## Test 5 — Customer registration

Open `/register.php`, create a new account, then check `users` in phpMyAdmin.

Expected:

- one new `users` row
- role is `CUSTOMER`
- password is hashed
- one matching `customer_profiles` row

## Test 6 — Duplicate registration

Try registering the same email again.

Expected: validation message that the email already exists.

## Test 7 — Invalid login

Enter the correct Francis email with a wrong password.

Expected: generic email/password error. After repeated failed attempts in the same session, temporary throttling activates.

## Test 8 — Forgot/reset password

1. Open `/forgot-password.php`.
2. Enter `francis.customer@franscoffee.com`.
3. In local debug mode, open the displayed local reset link.
4. Set a new password that meets the password rule.
5. Sign in using the new password.

Expected: reset succeeds and the reset URL cannot be reused.

## Test 9 — Logout

Use **Sign out** in the portal sidebar.

Expected: return to login page. Opening the protected dashboard afterward redirects back to login.

## Common problems

### `Table 'franscoffee.password_reset_tokens' doesn't exist`
Import `database/migrations/002_authentication.sql` if you upgraded from Phase 1.

### `Access denied for user 'root'@'localhost'`
Update `config.php` with your actual MySQL username/password.

### `404 Not Found`
Make sure the project is at `C:\\xampp\\htdocs\\franscoffee` and `base_url` is `http://localhost/franscoffee`.

### CSS/Bootstrap does not load
Internet access is needed for the Bootstrap and Google Fonts CDN resources. The custom `assets/css/app.css` remains local.
