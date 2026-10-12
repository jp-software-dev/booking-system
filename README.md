# MediAgenda - Medical Booking System

![PHP](https://img.shields.io/badge/php-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/mysql-%2300f.svg?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/javascript-%23323330.svg?style=for-the-badge&logo=javascript&logoColor=%23F7DF1E)
![Composer](https://img.shields.io/badge/composer-%23885630.svg?style=for-the-badge&logo=composer&logoColor=white)
![Bootstrap](https://img.shields.io/badge/bootstrap-%238511FA.svg?style=for-the-badge&logo=bootstrap&logoColor=white)
![Windows](https://img.shields.io/badge/Windows-0078D6?style=for-the-badge&logo=windows&logoColor=white)

**MediAgenda** is a professional medical appointment management system built with PHP. This project implements a robust architecture focused on scalability, security, and efficient data handling for healthcare environments, providing total administrative control over the clinic's daily operations.

## 🌐 Live Demo

**[mediagenda.vanguarddevsec.com](https://mediagenda.vanguarddevsec.com)** — all data is fictitious and resets every day.

| Role | User | Password |
|------|------|----------|
| Admin | `demo.admin` | `Demo2026!` |
| Patient | `paciente@example.com` | `Demo2026!` |

Hosted on Vercel (PHP serverless runtime) with a PostgreSQL database on Supabase. See [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md) (Spanish) for the full deployment guide.

## ✨ Key Features

* **Advanced Admin Panel:** A centralized, secure administrative dashboard providing complete control over patient records, medical shifts, and staff information.
* **Interactive Analytics Dashboard:** Real-time data visualization featuring dynamic charts and graphs for comprehensive operational and performance analysis.
* **Appointment Management:** Per-doctor availability calendar; patients can book, reschedule and cancel their own appointments, and staff manage the full appointment lifecycle.
* **Email Notifications:** SMTP (PHPMailer) emails when an appointment is booked and when staff confirm or cancel it, plus password recovery and password-change confirmation links.
* **Document Generation:** Feature for generating professional PDF reports and tickets for appointments.

## 🏗 Architecture & Design Patterns

* **Layered structure:** pages in `public/` and JSON endpoints in `api/` delegate to models (`src/Models`), a controller for appointment business rules (`src/Controllers`), helpers and services. Classes are loaded with Composer PSR-4 autoload (`App\` → `src/`).
* **Single bootstrap:** every entry point loads `config/config.php`, which reads `.env`, configures error handling and starts a hardened session (HttpOnly, SameSite).
* **Singleton Pattern:** one shared PDO connection per request (`App\Core\Database`).
* **JSON API:** endpoints return `{ "status", "message" }` with proper HTTP status codes; state-changing requests require a CSRF token.
* **Security:** prepared statements everywhere, CSRF protection, progressive login rate limiting, honeypot fields, hashed one-time password tokens and an audit log.

## 🛠 Technical Stack

* **Backend:** PHP (OOP), PDO.
* **Database:** MySQL / MariaDB (XAMPP) or PostgreSQL (Supabase); the SQL is portable between both.
* **Frontend:** HTML5, CSS3, JavaScript, Bootstrap Modals, Chart.js (for analytics).
* **Dependencies:** Managed via Composer.

## 🚀 Installation & Setup (XAMPP / WAMP)

Requirements: PHP 8.1+, MySQL or MariaDB 10.4+, Composer, and Apache with `mod_rewrite` and `AllowOverride All`.

1. Clone the repository into your web root (e.g. `C:\xampp\htdocs` or `C:\wamp64\www`):
   ```bash
   git clone https://github.com/jp-software-dev/booking-system.git
   ```
2. Install the dependencies:
   ```bash
   composer install
   ```
3. Create the configuration file and fill in your database and SMTP values:
   ```bash
   cp .env.example .env
   ```
   Set `MAIL_DRIVER=log` during development to write emails to the PHP error log instead of sending them.
4. Create the database with [`database/schema_mysql.sql`](database/schema_mysql.sql). To fill it with fictitious data, set `DEMO_MODE=true` in `.env` and run `php database/sembrar_demo.php --confirmar` (it **erases** the configured database). See [`database/README.md`](database/README.md) for details and for the migrations of existing databases.
5. Open `http://localhost/<project-folder>/` in your browser.

> The original database dump is not versioned because it contains real data.
