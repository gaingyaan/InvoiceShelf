# InvoiceShelf — Windows Installation Guide

**Branch:** `stable-2.4.2`
**Repository:** `https://github.com/gaingyaan/InvoiceShelf`
**Database:** Local SQLite (no Docker, no MySQL, no PostgreSQL)
**Platform:** Windows

---

## Table of Contents

- [Important](#important)
- [1. System Prerequisites](#1-system-prerequisites)
  - [Required PHP Extensions](#required-php-extensions)
- [2. Clone the Repository](#2-clone-the-correct-repository-and-branch)
- [3. Environment Configuration](#3-create-and-configure-the-environment-file)
- [4. Install PHP Dependencies](#4-install-the-php-dependencies)
- [5. Install and Build the Frontend](#5-install-and-build-the-frontend)
- [6. Database Setup & App Initialization](#6-create-the-sqlite-database-and-initialize-the-app)
- [7. Run the App](#7-start-invoiceshelf)
- [8. Verify the Installation](#8-quick-verification)
- [Optional: Docker Route](#optional-docker-route)

---

## Important

> This branch is newer than InvoiceShelf 2.x. Do **not** use the old PHP 8.2/8.3, Node 18/20, or npm instructions.
>
> **Requirements for this branch:**
> | Component | Version |
> |---|---|
> | PHP | 8.4 |
> | Node.js | 24 or later |
> | Package manager | pnpm 11.6.0 (via Corepack) |
> | Database | SQLite (local file) |

---

## 1. System Prerequisites

Install Git for Windows and Node.js 24 or later. Node 24 includes Corepack, which provides the required pnpm version.

**Install PHP 8.4 with Windows Package Manager:**

```powershell
winget install --exact --id PHP.PHP.8.4 --accept-source-agreements --accept-package-agreements
```

Close and reopen PowerShell after the install, then verify the tools:

```powershell
php -v
node -v
git --version
corepack pnpm --version
```

**Install Composer** using the official Windows installer from [getcomposer.org/download](https://getcomposer.org/download/), then reopen PowerShell and verify it:

```powershell
composer -V
```

> If Composer is deliberately kept local to the project instead of installed globally, use `php composer.phar` anywhere this guide says `composer`.

### Required PHP Extensions

The `PHP.PHP.8.4` Winget package normally enables InvoiceShelf's required extensions already. Validate them first instead of editing `php.ini` manually:

```powershell
$required = 'bcmath','curl','exif','fileinfo','gd','intl','mbstring','openssl','pdo_sqlite','sqlite3','zip','ctype','dom','filter','iconv','json','session','tokenizer','xml'
$loaded = php -m | ForEach-Object { $_.Trim() }
$missing = $required | Where-Object { $_ -notin $loaded }

if ($missing) {
  throw "Missing PHP extensions: $($missing -join ', '). Configure php.ini, then rerun this check."
}

'All InvoiceShelf PHP extensions are available.'
```

If the command reports missing extensions, locate the loaded configuration file:

```powershell
php --ini
```

Only then create `php.ini` from `php.ini-development` in PHP's installation folder. Set `extension_dir` to that installation's actual `ext` directory, then enable the missing extensions:

```ini
extension=bcmath
extension=curl
extension=exif
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=zip
```

> Do not assume the PHP folder is `C:\php`; use the path reported by `php --ini`. Rerun the validation command after changing `php.ini`.

---

## 2. Clone the Correct Repository and Branch

```powershell
git clone --branch stable-2.4.2 https://github.com/gaingyaan/InvoiceShelf.git InvoiceShelf
cd InvoiceShelf
```

---

## 3. Create and Configure the Environment File

```powershell
copy .env.example .env
```

Edit `.env` and set the following values. Replace the database path if the project is not located .

```ini
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_KEY=

DB_CONNECTION=sqlite
DB_DATABASE=C:/uoi/InvoiceShelf/database/database.sqlite
```

> Do this **before** generating the application key. The example file may mark the app as production; using `APP_ENV=local` avoids a production confirmation prompt.

---

## 4. Install the PHP Dependencies

```powershell
composer install
```

For a project-local Composer PHAR, run:

```powershell
php composer.phar install
```

> Do not use `--ignore-platform-reqs`; enable any missing PHP extension instead.

---

## 5. Install and Build the Frontend

This branch pins pnpm 11.6.0 through `packageManager` in `package.json`. Use Corepack/pnpm, not npm:

```powershell
corepack pnpm install
corepack pnpm run build
```

> For active frontend development, run `corepack pnpm dev` in a separate terminal. For a normal local installation, `corepack pnpm run build` is sufficient.

---

## 6. Create the SQLite Database and Initialize the App

```powershell
New-Item -Path "database\database.sqlite" -ItemType File
php artisan key:generate
php artisan migrate
```

> If `database.sqlite` already exists, do not recreate it. If `key:generate` still says the application is in production, recheck `APP_ENV=local`; only use `php artisan key:generate --force` when that is intentional.

---

## 7. Start InvoiceShelf

```powershell
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000) and complete the installation wizard (administrator account and company details).

---

## 8. Quick Verification

```powershell
php artisan about --only=environment
php artisan migrate:status
```

The app should report PHP 8.4, the `local` environment, and all migrations as `Ran`.

---

## Optional: Docker Route

Use the repository's own Docker instructions only if you intentionally want Docker. Do not combine Docker setup with this native Windows/SQLite setup.
