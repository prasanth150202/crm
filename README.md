# Lead Management CRM

A comprehensive, database-driven CRM application for managing leads, customers, and sales pipelines. This system is built with a PHP backend, a dynamic JavaScript frontend, and a MySQL database.

## Architecture

The application follows a traditional web application model:

-   **Backend:** PHP. The `/api` directory contains all the backend logic, with specific endpoints for each resource (leads, users, plans, etc.). It connects to a MySQL database using PDO.
-   **Frontend:** The frontend is composed of PHP files that render HTML, with a heavy emphasis on client-side logic handled by JavaScript. The `/js/modules` directory contains a modularized JavaScript structure for different parts of the application. Tailwind CSS is used for styling.
-   **Database:** MySQL/MariaDB. The database schema is managed through a series of migration files located in the `/database/migrations` directory.

## Key Features

-   **User & Organization Management:** Multi-tenant support where each organization has its own set of users and data. Includes registration, login, and user invitation system.
-   **Plan & Subscription Management:** Users select a plan upon registration. The system supports different plans (e.g., Free, Paid) with trial periods.
-   **Lead Management:** Create, update, and delete leads. View leads in a list view or a Kanban-style pipeline.
-   **Custom Fields:** Add custom fields to leads to store additional information.
-   **Pipeline Management:** A visual Kanban board to track leads through different stages of the sales process.
-   **Reporting:** A dashboard with customizable charts to visualize sales data.
-   **API:** A REST-like API for all core functionalities.

## Database

The database schema is managed via migrations. To set up or update the database, run the migration scripts in the `/database/migrations` directory. The core tables include:

-   `organizations`
-   `users`
-   `plans`
-   `subscriptions`
-   `leads`
-   `pipelines`
-   `custom_fields`

## Local Development Setup

To run this application locally, you will need a web server with PHP and a MySQL database (like XAMPP, WAMP, or MAMP).

1.  **Web Server:** Place the project files in the web server's root directory (e.g., `htdocs` for XAMPP).
2.  **Database:**
    -   Create a new database in your MySQL server.
    -   Configure the database connection by creating a `.env` file in the project root. You can use `.env.example` as a template. The minimum required variables are:
        ```
        DB_HOST=localhost
        DB_NAME=your_db_name
        DB_USER=your_db_user
        DB_PASS=your_db_password
        ```
3.  **Run Migrations:** Execute the PHP scripts in the `database/migrations` directory to create and populate the necessary tables. You can use a script like `run_migrations.php` if available, or run them manually.
4.  **Access the Application:** Open your web browser and navigate to the project's URL (e.g., `http://localhost/leads2`).

You should now be able to register a new account and use the CRM.
