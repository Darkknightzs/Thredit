# ThredIt - Modern Developer Discussion Forum

A production-ready technical community discussion platform inspired by **Reddit** and **Stack Overflow**. Built for developers to discuss code, debug issues, share web development insights, and explore AI/Database innovations.

---

## 🌟 Key Features

* **Dual Database Engine**: Seamlessly switch between **local XAMPP MySQL** (port 3306) and **TiDB Cloud** (port 4000 with TLS) by changing a single line in `config/.env`.
* **Role-Based Access Control (RBAC)**:
  * **Guest**: Read discussions, search keywords, browse categories.
  * **User**: Create threads/replies, edit/delete own posts, upvote/downvote.
  * **Moderator**: Pin/unpin threads, lock/unlock discussions, delete any inappropriate post.
  * **Admin**: Everything + manage categories (CRUD), manage users (ban/unban, promote/demote roles), view system metrics.
* **Stack Overflow-style "Best Answer"**: Thread authors can accept an answer as the accepted solution, highlighted with an emerald badge at the top.
* **Reddit-style Voting**: Upvote and downvote counters with smooth vanilla JavaScript AJAX and database-enforced single vote per user (`UNIQUE(user_id, target_type, target_id)`).
* **Technical Formatting**: Automatic detection and syntax-styled rendering for triple-backtick code blocks (` ```php ... ``` `).
* **Search & Pagination**: Keyword search matching titles and post bodies using SQL `LIKE`, paginated at 10 results per page.
* **Modern UI with Dark & Light Themes**: Responsive mobile-first design with smooth instant theme switching persisted in `localStorage`.
* **Strict Security**: Prepared statements everywhere, complete XSS sanitization via `htmlspecialchars()`, cryptographic CSRF token validation on all POST requests, and session fixation defense with `session_regenerate_id()`.

---

## 📁 Project Structure

```text
forum/
├── admin/                     # Administrator dashboard & management views
│   ├── categories.php         # Full Category CRUD management
│   ├── dashboard.php          # Platform statistics & recent activity
│   └── users.php              # User role promotion/demotion & ban controls
├── assets/
│   ├── css/
│   │   └── style.css          # Vanilla CSS3 with CSS variables & dark/light theme
│   └── js/
│       └── main.js            # Vanilla JS: theme switcher & AJAX voting
├── config/
│   ├── .env                   # Active environment credentials (gitignored)
│   ├── .env.example           # Environment template for local & TiDB setup
│   └── db.php                 # Dual database PDO connection manager (Singleton)
├── includes/
│   ├── auth.php               # Authentication, secure session & role guards
│   ├── csrf.php               # CSRF token generator & strict validator
│   ├── footer.php             # Shared layout footer & JavaScript bundle
│   ├── functions.php          # Base URL generator, XSS escaping (e), time_ago, etc.
│   └── header.php             # HTML head, responsive navigation, flash alerts
├── public/
│   ├── category.php           # Filtered discussions by technical category
│   ├── delete_thread.php      # Secure thread deletion handler
│   ├── edit_thread.php        # Discussion edit form
│   ├── index.php              # Forum feed, sorting (Latest / Top Voted), sidebar
│   ├── login.php              # User authentication & demo credentials helper
│   ├── logout.php             # Session destruction & cookie cleanup
│   ├── moderate.php           # Pin/unpin and lock/unlock moderation actions
│   ├── new_thread.php         # Discussion authoring form with category selection
│   ├── profile.php            # User profile with thread and reply activity tabs
│   ├── register.php           # Account creation with validation & auto-login
│   ├── reply.php              # Add reply, toggle Best Answer, delete reply
│   ├── search.php             # Search discussions by keyword (10 per page)
│   ├── thread.php             # Single thread view, view counter, answers list
│   └── vote.php               # AJAX & fallback upvote/downvote handler
├── .gitignore                 # Excludes .env and sensitive credentials
├── database.sql               # Complete schema with foreign keys and seed data
├── index.php                  # Root entry redirect to public/index.php
└── README.md                  # Comprehensive setup, test checklist & viva guide
```

---

## 🚀 Setup Instructions

### Option A: Local Setup with XAMPP (Default)

1. **Start Apache & MySQL**:
   * Open the **XAMPP Control Panel**.
   * Click **Start** for **Apache** and **MySQL**.
2. **Place the Files**:
   * Ensure the project is located in `C:\xampp\htdocs\forum` (or keep in your workspace and configure a virtual host or symlink).
3. **Import Database Schema & Seed Data**:
   * Open phpMyAdmin in your browser: `http://localhost/phpmyadmin/`.
   * Click **New** or click the **Import** tab.
   * Choose the file `forum/database.sql` and click **Import** (or **Go**).
   * This creates `forum_db` and seeds users, categories, sample threads, replies, and votes.
4. **Configure Environment File**:
   * In `forum/config/`, verify that `.env` exists (copied from `.env.example`).
   * Default local settings:
     ```ini
     APP_ENV=local
     LOCAL_DB_HOST=127.0.0.1
     LOCAL_DB_PORT=3306
     LOCAL_DB_DATABASE=forum_db
     LOCAL_DB_USERNAME=root
     LOCAL_DB_PASSWORD=
     ```
5. **Access the Forum**:
   * Open your browser and navigate to:
     ```text
     http://localhost/forum/
     ```
   * It will automatically direct you to `public/index.php`.

---

### Option B: TiDB Cloud Setup (Serverless Distributed SQL)

TiDB Cloud is a MySQL-compatible distributed HTAP database that scales horizontally.

1. **Create Free TiDB Cloud Cluster**:
   * Go to [https://tidbcloud.com](https://tidbcloud.com) and create a free Serverless tier cluster.
2. **Get Connection Credentials**:
   * In your TiDB Cloud Console, click **Connect**.
   * Select **Connect Type**: `General` or `MySQL CLI`.
   * Copy the host (e.g., `gateway01.ap-southeast-1.prod.aws.tidbcloud.com`), port (`4000`), user (e.g., `xxxxxx.root`), and cluster password.
3. **Import `database.sql` to TiDB Cloud**:
   * In the TiDB Cloud web console, open the **SQL Editor** tab.
   * Paste the entire contents of `forum/database.sql` and click **Run All**.
   * All tables and seed records will be created cleanly.
4. **Configure `forum/config/.env` for TiDB**:
   * Switch the active environment:
     ```ini
     APP_ENV=tidb

     TIDB_DB_HOST=gateway01.ap-southeast-1.prod.aws.tidbcloud.com
     TIDB_DB_PORT=4000
     TIDB_DB_DATABASE=forum_db
     TIDB_DB_USERNAME=your_cluster_username.root
     TIDB_DB_PASSWORD=your_cluster_password

     # TLS CA path (Default in XAMPP on Windows):
     TIDB_DB_SSL_CA=C:/xampp/apache/bin/curl-ca-bundle.crt
     ```
5. **Refresh your browser**:
   * The footer badge will now display **Active DB: TIDB MODE**. All threads and queries now run against the distributed cloud database!

---

## 🔑 Demo Login Credentials (Seed Data)

| Role | Name | Email Address | Password | Permissions |
| :--- | :--- | :--- | :--- | :--- |
| **Admin** | Admin User | `admin@example.com` | `Admin@123` | Full system access, category CRUD, user ban/role controls |
| **Moderator** | Moderator Dan | `mod@example.com` | `Mod@123` | Pin/unpin threads, lock/unlock threads, delete any post |
| **User 1** | Alice Johnson | `alice@example.com` | `User@123` | Create threads/replies, edit/delete own, vote, mark best answer |
| **User 2** | Bob Smith | `bob@example.com` | `User@123` | Create threads/replies, edit/delete own, vote |

---

## 🧪 Comprehensive Testing Checklist

### 1. Authentication & Security
- [x] **Registration**: Register a new user with valid inputs &rarr; logs in automatically with user role.
- [x] **Validation**: Try registering with password &lt; 6 chars or mismatched confirmation &rarr; displays error alerts.
- [x] **Duplicate Email**: Try registering with `admin@example.com` &rarr; error message preventing duplicate entry.
- [x] **Password Verification**: Log in with wrong password &rarr; friendly error message.
- [x] **Session Fixation Defense**: Log in &rarr; `session_regenerate_id(true)` generates fresh session ID.
- [x] **CSRF Protection**: Inspect any form &rarr; hidden `<input name="csrf_token">` is included. Alter token &rarr; 403 Forbidden.
- [x] **XSS Prevention**: Create a thread with title `<script>alert('xss')</script>` &rarr; cleanly escaped via `e()` (`htmlspecialchars`).
- [x] **Ban Enforcement**: As Admin, ban Alice Johnson &rarr; Alice's active session is terminated, login attempts are blocked.

### 2. Discussion Threads & Categories
- [x] **Create Thread**: Log in as User &rarr; click "+ New Thread" &rarr; select category, write title & body with ```php code &rarr; redirects to created thread.
- [x] **Code Block Formatting**: Inspect thread with triple backticks &rarr; renders clean `<pre><code>` block with language badge.
- [x] **View Counter**: Visit thread &rarr; view count increments by 1. Refreshing in the same session does not artificially inflate views.
- [x] **Edit Own Thread**: As author, click "Edit Thread" &rarr; modify title &rarr; changes persist.
- [x] **Authorization Guard**: Log in as Bob and try to access `/public/edit_thread.php?id=2` (Alice's thread) &rarr; 403 Forbidden.
- [x] **Delete Own Thread**: As author, delete thread &rarr; removed from database, child replies cascade-deleted.

### 3. Replies & Stack Overflow-style Best Answer
- [x] **Post Reply**: Post an answer to a thread &rarr; appears at the bottom of the reply list.
- [x] **Mark Best Answer**: As thread author, click "☆ Mark as Best Answer" &rarr; reply is highlighted with green border and moves to the top of the answers list.
- [x] **Toggle Best Answer**: Click "★ Unmark Best Answer" &rarr; unmarks accepted solution.
- [x] **Non-Author Protection**: Another user viewing the thread does not see the "Mark as Best Answer" button.

### 4. Upvote & Downvote System
- [x] **Upvote**: Click ▲ upvote button &rarr; counter increases by +1, button highlights orange without page refresh (AJAX).
- [x] **Toggle Off**: Click ▲ again &rarr; vote is removed, counter decreases back to original.
- [x] **Switch Vote**: Click ▼ downvote while upvoted &rarr; transitions cleanly from +1 to -1 (net change of -2).
- [x] **Single Vote Constraint**: Database table `votes` enforces `UNIQUE(user_id, target_type, target_id)`.
- [x] **Guest Guard**: Click vote as guest &rarr; redirected to login page.

### 5. Moderation Controls
- [x] **Pin Thread**: Log in as Moderator &rarr; click "📌 Pin" on a thread &rarr; thread gains pinned badge and moves to top of index feed.
- [x] **Lock Thread**: Click "🔒 Lock" &rarr; thread gains locked badge.
- [x] **Lock Enforcement**: When a thread is locked, the reply form is replaced with a warning banner; direct POST submissions are rejected.
- [x] **Moderator Delete**: Moderator or Admin can delete any inappropriate thread or reply.

### 6. Admin Panel
- [x] **Dashboard Metrics**: Shows live counts of users, threads, replies, and categories.
- [x] **Manage Users**: Change user role from `user` to `moderator` &rarr; permissions update immediately.
- [x] **Self-Lockout Prevention**: Admin cannot ban themselves or demote their own admin role.
- [x] **Category CRUD**: Create a new category &rarr; appears in sidebar and thread creation dropdown. Edit and delete categories.

### 7. Search & Pagination
- [x] **Keyword Search**: Type "TiDB" or "PHP" in navbar search &rarr; finds matches in both title and body.
- [x] **Pagination**: Result set shows 10 items per page with Previous / Next navigation controls.
- [x] **Empty State**: Search for a nonexistent term &rarr; renders friendly empty state illustration.

### 8. Dual Database Switching
- [x] **Local Mode**: Set `APP_ENV=local` in `config/.env` &rarr; reads from XAMPP MySQL (3306).
- [x] **TiDB Mode**: Set `APP_ENV=tidb` in `config/.env` &rarr; connects with TLS over port 4000 to TiDB Cloud.
- [x] **Zero Code Changes**: The application code remains 100% unchanged between local and cloud databases.

---

## 🎓 College Viva Defense (Q&A Cheat Sheet)

1. **Q: Why did you use PDO instead of mysqli?**
   * **A**: PDO supports multiple database drivers with a unified object-oriented API. This enables switching between local MySQL and cloud databases like TiDB simply by changing connection credentials in `.env` without modifying query code. Furthermore, PDO supports named parameters (`:id`, `:email`) and enforces prepared statements to eliminate SQL Injection.

2. **Q: How does the application prevent SQL Injection?**
   * **A**: Every dynamic query uses PDO prepared statements (`$pdo->prepare()`) with parameterized inputs (`:title`, `:body`). User inputs are transmitted separately from the SQL query syntax, preventing SQL injection attacks.

3. **Q: How does CSRF protection work?**
   * **A**: On session start, a cryptographically secure 64-character token (`bin2hex(random_bytes(32))`) is generated and stored in `$_SESSION['csrf_token']`. Every POST form includes this token in a hidden input. Before executing state-changing logic, `validate_csrf()` validates the incoming token using `hash_equals()` to prevent timing attacks.

4. **Q: How is XSS (Cross-Site Scripting) prevented?**
   * **A**: All output rendered to the browser is escaped using the `e()` helper function, which applies `htmlspecialchars($string, ENT_QUOTES, 'UTF-8')`. This converts `<script>`, quotes, and special characters into HTML entities.

5. **Q: How does TiDB differ from traditional MySQL?**
   * **A**: TiDB separates stateless SQL compute nodes from distributed transactional key-value storage (TiKV). While MySQL typically relies on single-primary replication for writes, TiDB uses the Raft consensus protocol across distributed regions to provide horizontal scalability while remaining 100% wire-compatible with MySQL PDO drivers.
