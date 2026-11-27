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

### Redis-backed Firestore cache

Start the project-owned Redis service from the repository root:

```powershell
docker compose up -d redis
```

The Compose service uses container name `student-dormitory-redis` and host port
`6380`, so it does not conflict with other local Redis containers. Set the
`REDIS_*` values from `.env.example` in `.env`. The development defaults connect
PHP on the host to Redis at `127.0.0.1:6380`. If PHP runs in a Docker container
on the same Compose network, set `REDIS_HOST=redis` and `REDIS_PORT=6379`. The
application caches Firestore documents, query results, and aggregates for up to
60 seconds and invalidates affected collection entries after writes. If Redis is
unavailable, the request logs the cache failure and continues with Firestore.

Then open:

https://student-dormitory-system-5hxq.onrender.com

http://localhost:8000

http://127.0.0.1:8000

http://127.0.0.1:8000/login.php

## Deployment

The deployed application is available at:

https://student-dormitory-system.onrender.com

Set the Firebase credentials and other `.env` values as environment variables
in the hosting provider. Do not commit `.env` or the Firebase service-account
JSON file.
