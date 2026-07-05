# Houra Web System

Houra Web System is a PHP-based community platform where users can exchange services using time credits instead of money. Members can offer services, request help, chat with each other, leave reviews, and manage time-wallet transactions.

## Features

- User registration and login
- Service browsing, posting, editing, and detail views
- Service request and approval workflow
- Time wallet and transaction tracking
- Reviews and ratings
- Messaging/chat between users
- Identity verification and dispute handling
- Admin dashboard for managing users, services, requests, disputes, and reports
- Basic AI chatbot integration

## Tech Stack

- PHP
- MySQL / MariaDB
- PDO database access
- Bootstrap-based frontend
- Apache via XAMPP/WAMP

## Project Structure

- `admin/` - Administrative dashboard and management pages
- `ajax/` - AJAX endpoints for messaging, reviews, and updates
- `assets/` - CSS, JS, and images
- `config/` - Database and migration configuration
- `includes/` - Shared PHP includes and helpers
- `models/` - Model files used by AI/verification features
- `uploads/` - Uploaded profile images, proofs, and chat files

## Prerequisites

Make sure you have the following installed:

- XAMPP, WAMP, or a similar local PHP + MySQL environment
- PHP 7.4+ (or compatible version)
- MySQL / MariaDB
- A web browser

## Installation

1. Place the project folder in your web server root.
   - For XAMPP, copy it to `C:\xampp\htdocs\hourawebsystem`

2. Start Apache and MySQL from XAMPP.

3. Create a MySQL database.
   - Example database name: `hourawebsystemdb`

4. Update the database connection settings in `config/database.php` if needed.
   - Default values are currently configured for a local XAMPP setup:
     - host: `localhost`
     - database: `hourawebsystemdb`
     - username: `root`
     - password: `` (empty)

5. Import your database schema if you have an SQL dump.

6. Run the database migrations once.
   - Open: `http://localhost/hourawebsystem/config/migrations.php`
   - Click the migration button to apply pending updates.

## Running the Application

Open the following URL in your browser:

- `http://localhost/hourawebsystem/`

You can then register a new account or sign in with an existing user/admin account depending on your database setup.

## Admin Panel

The administrative area is located in the `admin/` directory.

- Access it via: `http://localhost/hourawebsystem/admin/`

## Notes

- This application depends on a working MySQL database.
- Uploaded files are stored under the `uploads/` directory.
- If you change the database name or credentials, update `config/database.php` accordingly.

## License

This project does not currently include a license file. Please contact the project owner for usage terms.
