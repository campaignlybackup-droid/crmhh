# Lightweight CRM Web App

A clean, modern, and lightweight CRM web application built with PHP, MySQL, HTML, and Bootstrap 5 (Vanilla JS). Designed specifically to be easily deployable on shared hosting environments like Hostinger.

## Features

- **Dashboard:** Real-time widgets and upcoming task/shoot tables.
- **Roles:** Superadmin (full control) and Users (assigned tasks only).
- **Leads Management:** Track leads, deal values, statuses, upload via CSV, and view full edit history.
- **Client Management:** Track clients, billing, and drive folders.
- **Project Management:** Track production projects, shoot dates, and payments.
- **Task Management:** Assign tasks to users. Superadmins get a full view, users see only their assignments.
- **Invoices:** Track paid/unpaid invoices.
- **Notifications System:** Get notified on the platform when tasks are assigned or deadlines are missed.
- **Lead Master Sheet:** Global search for leads and comprehensive history logging.

## Deployment Setup (Shared Hosting / Hostinger)

This app doesn't require complex routing (like Laravel/Symfony), so you can just upload the files directly to your `public_html`.

1. **Upload Files:**
   - Zip the `crm` folder contents and upload it to your Hostinger `public_html` directory via the File Manager or FTP.
   - Unzip the files.

2. **Database Setup:**
   - In your Hostinger control panel, go to **MySQL Databases** and create a new database and user.
   - Open **phpMyAdmin**.
   - Select your new database.
   - Go to the **Import** tab and upload the `schema.sql` file provided in this folder.
   - This will create all necessary tables (`users`, `leads`, `clients`, `projects`, `tasks`, `invoices`, `lead_history`, `notifications`) and insert a default superadmin account.

3. **Configure Database Connection:**
   - Open `config.php` in the file manager.
   - Update the placeholder values with your actual database credentials:
     ```php
     define('DB_HOST', 'localhost'); // usually 'localhost' on shared hosting
     define('DB_USER', 'your_db_username');
     define('DB_PASS', 'your_db_password');
     define('DB_NAME', 'your_db_name');
     ```

4. **Login:**
   - Navigate to your domain (e.g. `https://yourdomain.com/login.php`).
   - Use the default superadmin credentials:
     - **Username:** `admin`
     - **Password:** `admin123`
   - *Important:* Go to the "Admin -> Manage Users" section immediately and change the superadmin password for security!

## File Structure

- `index.php` -> redirects to login.
- `login.php` / `logout.php` -> Authentication handling.
- `dashboard.php` -> Main dashboard with widgets.
- `*.php` -> Modules for Leads, Clients, Projects, Tasks, Invoices.
- `functions.php` -> Helper functions for auth, notifications, history tracking.
- `config.php` -> Database configuration.
- `css/style.css` -> Custom UI overrides.
- `schema.sql` -> Database schema and seed data.
