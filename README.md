# MediAgenda - Medical Booking System

![PHP](https://img.shields.io/badge/php-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/mysql-%2300f.svg?style=for-the-badge&logo=mysql&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/postgresql-%23316192.svg?style=for-the-badge&logo=postgresql&logoColor=white)
![JavaScript](https://img.shields.io/badge/javascript-%23323330.svg?style=for-the-badge&logo=javascript&logoColor=%23F7DF1E)
![Bootstrap](https://img.shields.io/badge/bootstrap-%238511FA.svg?style=for-the-badge&logo=bootstrap&logoColor=white)
![Composer](https://img.shields.io/badge/composer-%23885630.svg?style=for-the-badge&logo=composer&logoColor=white)
![Vercel](https://img.shields.io/badge/vercel-%23000000.svg?style=for-the-badge&logo=vercel&logoColor=white)
![Supabase](https://img.shields.io/badge/supabase-%233ECF8E.svg?style=for-the-badge&logo=supabase&logoColor=white)

**MediAgenda** is a medical appointment platform built with plain PHP. It brings patients, doctors and the clinic's front desk together: patients book and track their appointments and medication, doctors see their weekly schedule and issue prescriptions, and the admin team runs the whole clinic from one panel.

## 🌐 Live Demo

**[mediagenda-demo.vercel.app](https://mediagenda-demo.vercel.app)** — all data is fictitious and resets every day.

| Role | User | Password |
|------|------|----------|
| Admin | `demo.admin` | `Demo2026!` |
| Doctor | `demo.doctor` | `Demo2026!` |
| Patient | `paciente@example.com` | `Demo2026!` |

Hosted on Vercel (PHP serverless runtime) with a PostgreSQL database on Supabase. See [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md) (Spanish) for the full deployment guide.

> The doctor account, prescriptions and medication reminders are complete on the `developer` branch; they reach the live demo when that branch is merged into `main`.

## ✨ Key Features

### Patients
* **Appointment booking:** per-doctor availability calendar; book, reschedule and cancel appointments, and browse the appointment history.
* **Prescriptions:** every prescription a doctor issues becomes a treatment in the patient's week, with a downloadable PDF.
* **Medication reminders:** a weekly "pill organizer" view of every dose, personal reminders (medication, every N hours, for N days), marking each dose as taken or skipped, and browser push notifications at the exact time of each dose (installable as an app on the phone).

### Doctors
* **Weekly schedule:** the week laid out as a pill organizer (day × morning / afternoon / night) with every appointment, plus the day-by-day list.
* **Prescription issuing:** diagnosis, general instructions and one or more medications with dose, frequency, duration and first dose. A live preview shows how many doses each medication will have and at what times; issuing the prescription schedules every dose for the patient automatically.
* **Prescription history:** search by patient and date range, with each patient's adherence (doses taken vs. skipped) and a PDF of every prescription.

### Clinic admin
* **Appointment panel and analytics dashboard** with charts for operational and performance analysis.
* **Staff management:** doctors, nurses, specialties and offices, including creating, resetting and revoking each doctor's login.
* **Patient directory** with account status.
* **Audit log (read-only):** every relevant action is recorded and can only be queried, never edited. Export to CSV and PDF.

### Across the platform
* **Email notifications** (PHPMailer): booking, confirmation and cancellation, password recovery and password-change confirmation.
* **PDF documents** (Dompdf): appointment reports, prescriptions and the patient's personal data export.
* **Design system:** a "weekly pill organizer" visual language (rounded compartments with a colored lid per time of day), smooth page transitions, animated dialogs and notifications, loading skeletons, and full support for `prefers-reduced-motion`.

## 🏗 Architecture & Design Patterns

* **Layered structure:** pages in `public/` and JSON endpoints in `api/` delegate to models (`src/Models`), a controller for appointment business rules (`src/Controllers`), helpers and services. Classes are loaded with Composer PSR-4 autoload (`App\` → `src/`).
* **Role-based access:** three roles (admin, doctor, patient) enforced on every page and endpoint; a doctor's login is linked to an active doctor record.
* **Exact dose scheduling:** when a treatment is created, every dose is calculated from the first dose every N hours for the treatment's duration (e.g. every 8 h for 7 days = 21 doses) and stored with its exact time, so reminders and adherence tracking are simple queries.
* **Single bootstrap:** every entry point loads `config/config.php`, which reads `.env`, configures error handling and starts a hardened session (HttpOnly, SameSite). Sessions can be stored in the database for serverless hosting.
* **Portable SQL:** the same code runs on MySQL/MariaDB and PostgreSQL; every schema change ships as a migration for both engines.
* **JSON API:** endpoints return `{ "status", "message" }` with proper HTTP status codes; state-changing requests require a CSRF token.
* **Security:** prepared statements everywhere, CSRF protection, progressive login rate limiting, honeypot fields, hashed one-time password tokens, an audit log, and a whitelist router on Vercel that never serves source files.

## 🛠 Technical Stack

* **Backend:** PHP 8.2+ (OOP), PDO.
* **Database:** MySQL / MariaDB (XAMPP) or PostgreSQL (Supabase).
* **Frontend:** HTML5, CSS3 (design tokens over Bootstrap 5), vanilla JavaScript, Chart.js, Bootstrap Icons.
* **Dependencies:** Composer (Dompdf, PHPMailer).
* **Hosting:** Vercel (vercel-php runtime) + Supabase.

## 🚀 Installation & Setup (XAMPP / WAMP)

Requirements: PHP 8.2+, MySQL or MariaDB 10.4+, Composer, and Apache with `mod_rewrite` and `AllowOverride All`.

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
4. Create the database with [`database/schema_mysql.sql`](database/schema_mysql.sql). To fill it with fictitious data (including the three demo accounts), set `DEMO_MODE=true` in `.env` and run `php database/sembrar_demo.php --confirmar` (it **erases** the configured database). Existing databases must apply the migrations listed in [`database/README.md`](database/README.md), including migration 16 (doctor accounts, prescriptions and reminders).
5. Open `http://localhost/<project-folder>/` in your browser.

> The original database dump is not versioned because it contains real data.

## ✅ Automated Tests

[`tests/prueba_integracion.py`](tests/prueba_integracion.py) walks through the whole app (registration, booking, rescheduling, admin panel, password recovery, protected files and demo mode). GitHub Actions ([`.github/workflows/pruebas.yml`](.github/workflows/pruebas.yml)) runs it on every pull request to `main` against MariaDB 10.4 and PostgreSQL 15, using the same entry point as Vercel.

To run it locally against a **throwaway** database seeded with demo data (it creates and deletes records):

```bash
python tests/prueba_integracion.py --base http://localhost/<project-folder> --log <path-to-php-error-log>
```
