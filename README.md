# ThredIt - Modern Developer Discussion Forum

A production-ready technical community discussion platform with a Reddit-authentic flat design, dual database engine (MySQL & TiDB Cloud), voting, nested categories, and responsive layout.

---

## 🚀 Quick Start

### 1. Requirements
- PHP 8.0 or higher (with `pdo`, `pdo_mysql`, `mbstring`, `openssl` extensions enabled)
- MySQL / MariaDB (e.g. XAMPP) or TiDB Cloud Serverless cluster

### 2. Database Setup
Import the database schema and seed data into your MySQL database:
```bash
mysql -u root -p < database.sql
```

### 3. Configuration
1. Copy the environment configuration template:
   ```bash
   cp config/.env.example config/.env
   ```
2. Configure your database credentials in `config/.env`:
   - Set `APP_ENV=local` for local MySQL (default port 3306)
   - Or set `APP_ENV=tidb` for TiDB Cloud Serverless (port 4000 with TLS)

### 4. Running the Development Server
Run PHP's built-in web server from the project root directory:
```bash
php -S 127.0.0.1:8000
```
Open your browser and navigate to:
**`http://127.0.0.1:8000/`** (or `http://127.0.0.1:8000/public/index.php`)

---

## 🌟 Key Features
- **Reddit-Authentic Architecture**: 3-column desktop layout with collapsible sidebars, custom Electric Azure & Cyan palette, and dark/light mode toggle.
- **Dual Database Engine**: Seamlessly switch between local MySQL and TiDB Cloud distributed SQL via `.env`.
- **RBAC (Role-Based Access Control)**: Granular permissions for Admin, Moderator, User, and Guest roles.
- **Discussion Feed & Voting**: Real-time AJAX upvoting and downvoting with database-enforced single vote per user.
- **Markdown & Code Highlighting**: Automatic detection and syntax-styled rendering for headings (`#`, `##`, `###`), inline backticks, and triple-backtick code blocks.
- **Enterprise Security**: Prepared statements (PDO), strict CSRF protection, XSS prevention, and session fixation safeguards.

---

## 📁 Repository Structure
```text
thredit/
├── admin/            # Administrator dashboard & management views
│   ├── categories.php# Category CRUD management
│   ├── dashboard.php # Platform statistics & recent activity
│   └── users.php     # User role management & ban controls
├── assets/           # Stylesheets (CSS variables, dark/light themes), JS, logos
│   ├── css/style.css
│   └── js/main.js
├── config/           # Database connection manager (PDO) & .env template
│   ├── .env.example
│   └── db.php
├── includes/         # Shared components, CSRF, auth guards, utility functions
│   ├── auth.php
│   ├── csrf.php
│   ├── footer.php
│   ├── functions.php
│   └── header.php
├── public/           # Frontend pages (feed, thread, category, profile, auth)
│   ├── index.php     # Main discussion feed
│   ├── thread.php    # Single discussion & replies view
│   ├── category.php  # Category-filtered feed
│   ├── login.php     # User authentication
│   └── register.php  # Account registration
├── database.sql      # Database schema and seed data
├── index.php         # Root entry point
└── README.md
```
