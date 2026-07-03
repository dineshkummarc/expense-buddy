# ExpenseBuddy

**Your Personal Finance Companion**

<div align="center">

## ⭐ Star this repo if you find ExpenseBuddy useful

#### It takes one second — and it helps others discover the project, keeps development going, and shows that self-hosted finance tools matter.

[![⭐ Star on GitHub](https://img.shields.io/badge/⭐_Star_ExpenseBuddy-FFD700?style=for-the-badge&logo=github&logoColor=000000&labelColor=fff8dc)](https://github.com/Mohit-Hasan/expense-buddy/stargazers)

**Like what you see?** Tap **Star** above ↑ before you install — every star genuinely helps.

</div>

---

ExpenseBuddy is a self-hosted finance app for tracking income, expenses, accounts, lending, and invoices — with multi-currency support, role-based access, and a mobile-friendly installable web app.

No subscription. Upload to your server, run the web installer, and start managing money in minutes.

---

## Desktop Previews

#### 1. Dashboard & Financial Analytics
<img src="https://raw.githubusercontent.com/Mohit-Hasan/expense-buddy/refs/heads/main/public/preview/screenshot_1.png" width="100%" alt="Dashboard Analytics" />

#### 2. Transaction Ledger
<img src="https://raw.githubusercontent.com/Mohit-Hasan/expense-buddy/refs/heads/main/public/preview/screenshot_2.png" width="100%" alt="Transaction Ledger" />

#### 3. Accounts & Transfers
<img src="https://raw.githubusercontent.com/Mohit-Hasan/expense-buddy/refs/heads/main/public/preview/screenshot_3.png" width="100%" alt="Accounts and Transfers" />

#### 4. Lending & Borrowing Ledger
<img src="https://raw.githubusercontent.com/Mohit-Hasan/expense-buddy/refs/heads/main/public/preview/screenshot_4.png" width="100%" alt="Lending and Borrowing" />

#### 5. Administrative Settings & Updater
<img src="https://raw.githubusercontent.com/Mohit-Hasan/expense-buddy/refs/heads/main/public/preview/screenshot_5.png" width="100%" alt="System Updater" />

#### 6. Interactive Multi-Currency & Invoicing
<img src="https://raw.githubusercontent.com/Mohit-Hasan/expense-buddy/refs/heads/main/public/preview/screenshot_6.png" width="100%" alt="Multi-Currency and Invoicing" />

---

## Features

- **Dashboard & Ledger**: Record income and expense (date, category, payment method, notes). Search, filter, and view net balances live.
- **Accounts & Transfers**: Manage Bank, Cash, or Wallet accounts in multiple currencies. Log transfers with optional fee/loss rate calculations and optional negative balance blocking.
- **Categories, Reports & Trend Curves**: Separate income and expense categories. Generate detailed analytics, categorized breakdowns, income vs. expense cash flow, balance trends, and custom-period net asset performance curves via dynamic charts.
- **PDF Invoices**: Generate custom PDF invoices for any transaction with shareable public links.
- **Multi-Currency Support**: Set a base currency and define exchange rates. Log native currency transactions while automatically converting analytics.
- **Lending & Debts**: Track lending and borrowing balances and payback ledgers grouped by contact.
- **Administration & Updater**: Manage users/permissions, SMTP settings, database backups, and run self-hosted updates directly from GitHub with a live terminal console.
- **Security & PWA**: Protect accounts with Google 2FA (TOTP) and 30-day sessions. Install the application directly on mobile/Chrome as an offline PWA.

---

## Requirements

| Requirement | Notes |
|-------------|--------|
| **PHP 8.3+** | With PDO, OpenSSL, Mbstring, Tokenizer, XML, Fileinfo, GD, Zip |
| **MySQL / MariaDB** | Recommended for live hosting |
| **SQLite** | Fine for local testing |
| **Web server** | Apache (mod_rewrite) or Nginx — document root must be `/public` |
| **Writable folders** | `storage/`, `bootstrap/cache/`, project root (for `.env`) |

The release package includes **`vendor/`** and **`public/build/`** — no Composer or npm is required on the server.

---

## Install on a live server

### 1. Upload the files
1. Upload the full project folder to your hosting (cPanel, FTP, etc.)
2. Set the domain **document root** to the **`public`** folder  
   *(Not the project root — point directly at `/public`.)*

If your host only allows the project root as document root, a root `.htaccess` is included that forwards requests to `public/`.

### 2. Set permissions
Make these writable by PHP (typically `755` or `775`):

```
storage/
bootstrap/cache/
```

The project root must also allow creating `.env` during install.

### 3. Create a MySQL database
In your hosting panel, create:
- A database
- A database user with full privileges on that database

### 4. Run the web installer
Open in your browser:

```
https://yourdomain.com/install/
```

| Step | What happens |
|------|----------------|
| **1. Requirements** | PHP version, extensions, vendor folder, built assets, writable paths |
| **2. Database** | MySQL credentials + site URL → `.env` is created automatically |
| **3. Application** | Admin account, app name, optional logo, base currency, demo data on/off |
| **4. Finish** | Fresh migration, storage link, admin user, optional demo data |

**Install options:**
- **Logo** — optional; skip to use the built-in wallet icon
- **Demo data** — sample accounts, categories, payment methods, and contacts (your admin login is still the one you enter)
- **Fresh reinstall** — checking “confirm reinstall” wipes the database and starts over

> Every install runs **`migrate:fresh`** for a clean database.

### 5. After install
1. Log in at `https://yourdomain.com/login`
2. Configure **Administration → Settings** (email, branding, currency)
3. **Delete the `/public/install` folder** from your server

---

## Install locally

### Option A — Web installer (easiest)

```bash
php artisan serve
```

Open `http://127.0.0.1:8000/install/`, choose **SQLite** on the database step, and complete the wizard.

### Option B — Developer seed (no installer)

```bash
cp .env.example .env
php artisan key:generate
```

Add to `.env`:

```env
SEED_DEV_ADMIN=true
SEED_ADMIN_EMAIL=admin@expensebuddy.test
SEED_ADMIN_PASSWORD=password
```

Then:

```bash
php artisan db:seed
```

Default login: **admin@expensebuddy.test** / **password**

Optional demo data:

```env
SEED_DEMO_DATA=true
```

---

## First-time setup inside the app

| Task | Where |
|------|--------|
| App name, logo, base currency | **Administration → Settings** |
| SMTP / PHP Mail for emails | **Administration → Settings → Email Configuration** |
| Add currencies | **Administration → Currencies** |
| Users & roles | **Administration → Users / Roles** |
| Enable 2FA | **Account → Security** (navbar) |
| Reset password | **Forgot password?** on login page |

### Email (password reset)
1. Go to **Administration → Settings → Email Configuration**
2. Choose **SMTP** (Gmail, Outlook, Mailtrap, etc.) or **PHP Mail**
3. Fill in host, port, credentials, and “From” address
4. Save, then test with **Forgot password** on the login page

---

## Configuration reference

Most day-to-day settings are in the admin panel. The installer creates `.env` with these common variables:

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Full site URL (no trailing slash) — must match how users access the site |
| `APP_DEBUG` | `false` on live servers |
| `APP_ENV` | `production` on live servers |
| `DB_*` | Database connection |
| `SESSION_LIFETIME` | Session length in minutes (default `43200` = 30 days) |
| `LEDGER_ALLOW_NEGATIVE_BALANCES` | Allow accounts to go below zero |

Email is configured in the admin UI (stored in the database), not in `.env`.

---

## Troubleshooting

### Logo or favicon shows a black box
- Upload a real logo under **Administration → Settings**, or leave logo empty to use the built-in wallet SVG
- Ensure the storage symlink exists:

```bash
php artisan storage:link
```

- Hard-refresh the browser (`Cmd+Shift+R` / `Ctrl+Shift+R`) — favicons are heavily cached

### `/storage/...` images return 404
```bash
php artisan storage:link
```

### App works but installer still shows “not installed”
If you created the admin via `db:seed` instead of the web installer:

```bash
php artisan expensebuddy:repair-install-lock
php artisan storage:link
```

### 404 on all pages except `/install/`
- Document root must be **`public/`**
- On Apache, enable **mod_rewrite**
- On Nginx, use `try_files $uri $uri/ /index.php?$query_string;`

### Styles missing / blank UI
Ensure `public/build/manifest.json` exists. If you cloned from Git without assets:

```bash
npm install && npm run build
```

---

## Testing (developers)

```bash
# PHPUnit (unit + feature + route smoke)
composer test

# Playwright end-to-end (app flows)
npm run test:e2e

# Playwright install wizard
npm run test:e2e:install

# Full local gate
composer test:all
```

First-time Playwright setup:

```bash
npm install
npx playwright install chromium
```

CI runs automatically on push/PR via GitHub Actions (`.github/workflows/tests.yml`).

E2E tests use port **8765** with `artisan serve --no-reload`.

---

## Security checklist

- [ ] Delete **`/public/install`** after setup
- [ ] Use **HTTPS** on live sites (required for PWA install on mobile)
- [ ] Set **`APP_DEBUG=false`** in production
- [ ] Use a strong administrator password
- [ ] Enable **2FA** for admin accounts on shared or production servers
- [ ] Configure **SMTP** so password reset emails work reliably

---

## Project structure (quick reference)

```
public/           ← Web root (point your domain here)
public/install/   ← Standalone web installer (delete after setup)
public/build/     ← Compiled CSS/JS (included in release)
storage/          ← Logs, uploads, sessions (must be writable)
database/         ← Migrations & seeders
```

**Admin is not in `DatabaseSeeder` by default** — it is created by the web installer or `SEED_DEV_ADMIN=true php artisan db:seed`.

Demo sample data only: `php artisan db:seed --class=DemoDataSeeder` *(requires an existing install with admin + currency)*.

---

## Live Demo Access

You can test ExpenseBuddy online using the credentials below:

> **Note**
> - The demo database is **automatically reset every hour**.
> - Any data you create, edit, or delete will be removed during the next scheduled reset.
> - Feel free to explore all features without worrying about affecting other users permanently.

* **Demo URL**: [https://buddy.mohithasan.com](https://buddy.mohithasan.com)

| Account Role | Email Address | Password |
|:---|:---|:---|
| **Administrator** | `admin@expensebuddy.test` | `password` |

---

## License

MIT
