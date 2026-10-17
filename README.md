# ThredIt - Modern Developer Discussion Forum

A production-ready technical community discussion platform with a Reddit-authentic flat design, dual database engine (MySQL & TiDB Cloud), voting, nested categories, and responsive layout.

---

## 🚀 Quick Start

### 1. Requirements
- PHP 8.0 or higher (with `pdo`, `pdo_mysql`, `mbstring`, `openssl` extensions enabled)
- MySQL / MariaDB (e.g. XAMPP) or TiDB Cloud Serverless cluster

### 2. Database Setup
1. Import the database schema and seed data into your MySQL database:
   ```bash
   mysql -u root -p < forum/database.sql
   ```

### 3. Configuration
1. Copy the environment configuration template:
   ```bash
   cp forum/config/.env.example forum/config/.env
   ```
2. Configure your database credentials in `forum/config/.env`:
   - Set `APP_ENV=local` for local MySQL (default port 3306)
   - Or set `APP_ENV=tidb` for TiDB Cloud Serverless (port 4000 with TLS)

### 4. Running the Development Server
Run PHP's built-in web server from the project directory:
```bash
php -S 127.0.0.1:8000 -t forum
```
Open your browser and navigate to:
**`http://127.0.0.1:8000/public/index.php`**

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
├── forum/
│   ├── admin/         # Admin management (users, categories, system stats)
│   ├── assets/        # Stylesheets (CSS variables, themes), JavaScript, icons
│   ├── config/        # Database connection manager (PDO) & .env template
│   ├── includes/      # Shared components, CSRF, auth guards, functions
│   ├── public/        # Frontend pages (feed, thread, category, profile, auth)
│   └── database.sql   # Complete schema and seed data
├── index.php          # Workspace root redirect to forum/public/index.php
└── README.md
```
