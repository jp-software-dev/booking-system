# MediAgenda - Medical Booking System

![PHP](https://img.shields.io/badge/php-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/mysql-%2300f.svg?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/javascript-%23323330.svg?style=for-the-badge&logo=javascript&logoColor=%23F7DF1E)
![Composer](https://img.shields.io/badge/composer-%23885630.svg?style=for-the-badge&logo=composer&logoColor=white)
![Bootstrap](https://img.shields.io/badge/bootstrap-%238511FA.svg?style=for-the-badge&logo=bootstrap&logoColor=white)
![Windows](https://img.shields.io/badge/Windows-0078D6?style=for-the-badge&logo=windows&logoColor=white)

**MediAgenda** is a professional medical appointment management system built with PHP. This project implements a robust architecture focused on scalability, security, and efficient data handling for healthcare environments, providing total administrative control over the clinic's daily operations.

## ✨ Key Features

* **Advanced Admin Panel:** A centralized, secure administrative dashboard providing complete control over patient records, medical shifts, and staff information.
* **Interactive Analytics Dashboard:** Real-time data visualization featuring dynamic charts and graphs for comprehensive operational and performance analysis.
* **Appointment Management:** Full CRUD (Create, Read, Update, Delete) system for scheduling, updating, and cancelling medical appointments seamlessly.
* **Automated Notifications:** Integrated SMTP Mailer service for automated, real-time email notifications and reminders.
* **Document Generation:** Feature for generating professional PDF reports and tickets for appointments.

## 🏗 Architecture & Design Patterns

* **MVC Pattern:** Full separation of concerns using Model-View-Controller architecture to ensure code maintainability.
* **Singleton Pattern:** Optimized database connectivity through a Singleton PDO wrapper, preventing multiple redundant connections.
* **RESTful API:** Dedicated API layer for handling asynchronous operations and real-time data updates.
* **Security:** Implementation of environment variables (`.env`) for sensitive credential management and SMTP configurations.

## 🛠 Technical Stack

* **Backend:** PHP (OOP), PDO.
* **Database:** MySQL with a structured relational schema.
* **Frontend:** HTML5, CSS3, JavaScript, Bootstrap Modals, Chart.js (for analytics).
* **Dependencies:** Managed via Composer.

## 🚀 Installation & Setup (WAMP Environment)

Follow these steps to deploy the project locally using your Windows WAMP server:

1. Clone the repository into your WAMP `www` directory (e.g., `C:\wamp64\www`):
   ```bash
   git clone https://github.com/jp-software-dev/booking-system.git
