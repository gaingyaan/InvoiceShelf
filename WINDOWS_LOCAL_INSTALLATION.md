# Windows Local Installation

This guide installs the `stable-2.4.2` branch for local use on one Windows computer. Internet access is needed only to clone the repository and download dependencies. After setup, the application runs locally at `http://127.0.0.1:8090`.

## Prerequisites

Install the following and ensure each command works in PowerShell:

- Git
- PHP 8.4 or newer, with `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_sqlite`, `sqlite3`, and `zip` enabled
- Composer 2
- Node.js 22 or newer, including Corepack
- SQLite command-line tools, with `sqlite3` available on `PATH` for native database backups

Check the tools:

```powershell
git --version
php -v
composer --version
node -v
corepack --version
sqlite3 --version
```

## Clone the Stable Branch

```powershell
cd C:\
git clone --branch stable-2.4.2 --single-branch https://github.com/gaingyaan/InvoiceShelf.git InvoiceShelf
cd C:\InvoiceShelf
git remote add upstream https://github.com/InvoiceShelf/InvoiceShelf.git
```

The `origin` remote is the maintained fork. The `upstream` remote points to the official InvoiceShelf project and is used only when reviewing future updates.

## Install Dependencies

```powershell
Copy-Item .env.example .env
New-Item -ItemType File -Force .\storage\app\database.sqlite

composer install
corepack enable
pnpm install --frozen-lockfile
pnpm build
```

## Configure Local SQLite Storage

Open the environment file:

```powershell
notepad .env
```

Set these values. Update `DB_DATABASE` if the project is installed in another folder.

```env
APP_NAME="Simple Billing"
APP_TIMEZONE=Asia/Kolkata
APP_URL=http://127.0.0.1:8090

DB_CONNECTION=sqlite
DB_DATABASE=C:/InvoiceShelf/storage/app/database.sqlite

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
SESSION_DOMAIN=127.0.0.1
SANCTUM_STATEFUL_DOMAIN=127.0.0.1:8090
```

Do not commit `.env`, database files, backups, or passwords to Git.

## Start the Application

```powershell
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8090
```

Open `http://127.0.0.1:8090` and complete the first-run organization setup in the browser. Use India, INR, and an April to March financial year if those settings apply to the local business.

To stop the server, press `Ctrl+C` in the PowerShell window that is running `php artisan serve`.

## Backups

Use **Settings > Backup** inside InvoiceShelf to create a database backup. The SQLite command-line tool must remain available on `PATH` for this feature to work.

Store backup ZIP files outside the project folder as an additional safety copy.

## Updating From Upstream

Do not update blindly. First inspect available updates and take a backup:

```powershell
git fetch upstream
git log --oneline stable-2.4.2..upstream/2.x
```

Review and test updates locally before merging or deploying them.
