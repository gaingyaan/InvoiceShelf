# InvoiceShelf `stable-2.4.2` — Windows Local Installation Guide

This guide installs the repository at `https://github.com/gaingyaan/InvoiceShelf`, branch `stable-2.4.2`, directly on Windows with a local SQLite database. It does not use Docker, MySQL, or PostgreSQL.

> Important: this branch is newer than InvoiceShelf 2.x. Do **not** use the old PHP 8.2/8.3, Node 18/20, or npm instructions. This branch requires PHP 8.4, Node 24 or later, and pnpm 11.6.0.

## 1. System prerequisites

Install Git for Windows and Node.js 24 or later. Node 24 includes Corepack, which provides the required pnpm version.

Install PHP 8.4 with Windows Package Manager:

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

Install Composer using the official Windows installer from [getcomposer.org/download](https://getcomposer.org/download/), then reopen PowerShell and verify it:

```powershell
composer -V
```

If Composer is deliberately kept local to the project instead of installed globally, use `php composer.phar` anywhere this guide says `composer`.

### Required PHP extensions

Create `php.ini` from `php.ini-development` in PHP's installation folder. Set `extension_dir` to that folder's `ext` directory, then enable these extensions:

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

The standard PHP extensions `ctype`, `dom`, `filter`, `iconv`, `json`, `session`, `tokenizer`, and `xml` should also appear in `php -m`. Verify the essential modules before continuing:

```powershell
php -m
```

`sqlite3`, `pdo_sqlite`, `curl`, `exif`, `gd`, `intl`, `mbstring`, and `zip` must be listed. If PHP reports that it cannot load an extension from `C:\php\ext`, correct `extension_dir` in `php.ini` to the actual PHP `ext` path.

## 2. Clone the correct repository and branch

```powershell
git clone --branch stable-2.4.2 https://github.com/gaingyaan/InvoiceShelf.git InvoiceShelf
cd InvoiceShelf
```

## 3. Create and configure the environment file

```powershell
copy .env.example .env
```

Edit `.env` and set the following values. Replace the database path if the project is not located at `C:\uoi\InvoiceShelf`.

```ini
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_KEY=

DB_CONNECTION=sqlite
DB_DATABASE=C:/uoi/InvoiceShelf/database/database.sqlite
```

Do this **before** generating the application key. The example file may mark the app as production; using `APP_ENV=local` avoids a production confirmation prompt.

## 4. Install the PHP dependencies

```powershell
composer install
```

For a project-local Composer PHAR, run:

```powershell
php composer.phar install
```

Do not use `--ignore-platform-reqs`; enable any missing PHP extension instead.

## 5. Install and build the frontend

This branch pins pnpm 11.6.0 through `packageManager` in `package.json`. Use Corepack/pnpm, not npm:

```powershell
corepack pnpm install
corepack pnpm run build
```

For active frontend development, run `corepack pnpm dev` in a separate terminal. For a normal local installation, `corepack pnpm run build` is sufficient.

## 6. Create the SQLite database and initialize the app

```powershell
New-Item -Path "database\database.sqlite" -ItemType File
php artisan key:generate
php artisan migrate
```

If `database.sqlite` already exists, do not recreate it. If `key:generate` still says the application is in production, recheck `APP_ENV=local`; only use `php artisan key:generate --force` when that is intentional.

## 7. Start InvoiceShelf

```powershell
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000) and complete the installation wizard (administrator account and company details).

## 8. Quick verification

```powershell
php artisan about --only=environment
php artisan migrate:status
```

The app should report PHP 8.4, the `local` environment, and all migrations as `Ran`.


## Optional Docker route

Use the repository's own Docker instructions only if you intentionally want Docker. Do not combine Docker setup with this native Windows/SQLite setup.
