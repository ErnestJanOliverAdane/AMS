# Konektado IT Solutions — Multi-Auth System

A web-based authentication system developed using Laravel. The application provides a centralized sign-in page for Super Admins, Admins, and standard Users, along with a user registration page.

## Screenshots

### Sign In Page

![Konektado IT Solutions Sign In Page](screenshots/login.png)![Uploading Screenshot 2026-10-24 011110.png…]()


The sign-in page allows registered Super Admins, Admins, and Users to access the system using their email address and password.

### Create Account Page

![Konektado IT Solutions Create Account Page](screenshots/register.png)

The registration page allows new users to create an account. Newly registered accounts are assigned the standard User role by default.

## Features

- Centralized authentication for Super Admins, Admins, and Users
- User registration
- Password confirmation during registration
- Password validation
- Optional "Keep me signed in" functionality
- Role-based access control
- Laravel session-based authentication
- Responsive, dark-themed interface

> Role permissions and access restrictions must be enforced by the application's backend and route middleware.

## Technology Stack

- **Backend:** PHP and Laravel
- **Frontend:** HTML, CSS, and JavaScript
- **Database:** MySQL or another Laravel-supported database
- **Authentication:** Laravel authentication and session management
- **Version Control:** Git and GitHub

## Requirements

Before installing the project, make sure you have:

- PHP compatible with your Laravel version
- Composer
- Node.js and npm
- MySQL or another supported database
- Git

## Installation

### 1. Clone the repository

```bash
git clone YOUR_GITHUB_REPOSITORY_URL
cd YOUR_PROJECT_FOLDER
```

Replace the repository URL and project folder with your actual values.

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install frontend dependencies

```bash
npm install
```

### 4. Configure the environment

```bash
cp .env.example .env
php artisan key:generate
```

On Windows Command Prompt, copy the environment file using:

```cmd
copy .env.example .env
```

Update your `.env` file with your database credentials.

### 5. Run database migrations

```bash
php artisan migrate
```

If your project includes seeders for initial roles and accounts, run them using the appropriate command for your project:

```bash
php artisan db:seed
```

Only run seeders after verifying what data they create. Do not use production credentials or default demo passwords for real accounts.

### 6. Build frontend assets

```bash
npm run build
```

### 7. Start the development server

```bash
php artisan serve
```

Open the local address shown in your terminal, typically:

http://127.0.0.1:8000

## Demo Accounts

For local development only, use the demo accounts configured by your database seeders.

| Role | Email |
|---|---|
| Super Admin | `superadmin@konektado.test` |
| Admin | `admin@konektado.test` |
| User | `user@konektado.test` |

The screenshots show `password` as the demo password. This credential should only be used in a local development environment. Replace demo credentials and remove or disable demo accounts before deployment.

## Role-Based Access

The system is intended to support three account roles:

- **Super Admin:** Access to authorized system-wide administration functions.
- **Admin:** Access to administrative functions assigned to the role.
- **User:** Access to standard user functions.

Actual permissions depend on the roles, policies, middleware, and controllers implemented in the application.

## Development Commands

Clear Laravel's application caches:

```bash
php artisan optimize:clear
```

Check available routes:

```bash
php artisan route:list
```

Run automated tests:

```bash
php artisan test
```

## Security Notes

- Store passwords using Laravel's supported password hashing mechanisms.
- Validate user input on the server.
- Protect restricted routes using authentication and authorization middleware.
- Never commit `.env` files, API keys, or real credentials to GitHub.
- Disable public demo accounts and change default passwords before production deployment.
- Use HTTPS in production.

## License

This project is developed for Konektado IT Solutions. Specify the applicable license and distribution terms before making the project publicly available.
