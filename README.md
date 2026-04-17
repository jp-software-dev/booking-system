# Medical Booking System

![PHP](https://img.shields.io/badge/php-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/mysql-%2300f.svg?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/javascript-%23323330.svg?style=for-the-badge&logo=javascript&logoColor=%23F7DF1E)
![Composer](https://img.shields.io/badge/composer-%23885630.svg?style=for-the-badge&logo=composer&logoColor=white)

Professional medical appointment management system built with PHP. This project implements a robust architecture focused on scalability, security, and efficient data handling for healthcare environments.

## Architecture & Design Patterns

* **MVC Pattern:** Full separation of concerns using Model-View-Controller architecture to ensure code maintainability.
* **Singleton Pattern:** Optimized database connectivity through a Singleton PDO wrapper, preventing multiple redundant connections.
* **RESTful API:** Dedicated API layer for handling asynchronous operations and real-time data updates.
* **Security:** Implementation of environment variables (`.env`) for sensitive credential management and SMTP configurations.

## Key Features

* **Appointment Management:** Full CRUD system for creating, updating, and cancelling medical shifts.
* **Patient & Doctor Modules:** Distinct interfaces and logic for different user roles.
* **Automated Notifications:** Integrated SMTP Mailer service for real-time email notifications.
* **Document Generation:** Feature for generating PDF reports/tickets for appointments.

## Technical Stack

* **Backend:** PHP (OOP), PDO.
* **Database:** MySQL with structured relational schema.
* **Frontend:** HTML5, CSS3, JavaScript, Bootstrap Modals.
* **Dependencies:** Managed via Composer.

## Installation & Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/jp-software-dev/booking-system.git
