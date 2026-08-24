# Student Dormitory System

PHP + Firebase Student Dormitory Management System.

## User Roles

- Admin
- House Master
- Senior Houseparent
- Security
- Nurse
- Student

## Technology

- PHP
- Firebase Authentication
- Cloud Firestore
- HTML
- CSS
- JavaScript

## Installation

1. Install PHP.
2. Install Composer.
3. Create a Firebase project.
4. Enable Firebase Authentication.
5. Create a Cloud Firestore database.
6. Download the Firebase service account credentials.
7. Store the credentials outside the public folder.
8. Configure .env.
9. Run composer install.

For the Render deployment, set the application URL in `.env`:

```dotenv
APP_URL=https://student-dormitory-system.onrender.com
```

### Email delivery

PHPMailer is installed through Composer and supports authenticated SMTP delivery.
Copy the `MAIL_*` settings from `.env.example` into `.env`, then replace the
placeholder values with the credentials from your mail provider. Keep
`MAIL_ENABLED=false` until the host, port, sender address, username, and
password are configured.

Supported encryption values are `tls`, `ssl`, or an empty value for providers
that do not require encryption. The application never logs the SMTP password.

## Development

Use the PHP development server:

php -S localhost:8000 -t public
php -S 127.0.0.1:8000 -t public

Then open:

http://localhost:8000

## Deployment

The deployed application is available at:

https://student-dormitory-system.onrender.com

Set the Firebase credentials and other `.env` values as environment variables
in the hosting provider. Do not commit `.env` or the Firebase service-account
JSON file.
