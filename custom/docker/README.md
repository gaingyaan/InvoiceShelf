# Custom Docker Deployment

This is an isolated, local Docker deployment for this fork. It does not modify
the upstream `docker/production` configuration.

This deployment intentionally uses **MariaDB**. SQLite remains an optional
native/local setup and is not used by this Docker configuration.

## Requirements

- Docker Engine with Docker Compose v2
- At least 4 GB of memory available to Docker during the first image build

## Configure

From the repository root, create the required environment file:

```sh
cp custom/docker/.env.example custom/docker/.env
```

On Windows PowerShell, use:

```powershell
Copy-Item custom/docker/.env.example custom/docker/.env
```

Edit `custom/docker/.env` before starting the containers. Set strong, unique
values for `DB_PASSWORD`, `MARIADB_PASSWORD`, and `MARIADB_ROOT_PASSWORD`.
`DB_PASSWORD` and `MARIADB_PASSWORD` must be identical.

Generate an application key and set it as `APP_KEY` in that same file:

```sh
docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

The `custom/docker/.env` file is required. Docker Compose reads it for both
services and stops with an error if it is missing. Do not add secrets to the
Compose file or commit the `.env` file.

## Start

```sh
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml up --build -d
```

Open [http://localhost:8090](http://localhost:8090).

To watch startup logs:

```sh
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml logs -f
```

## Stop and restart

Stop the containers without deleting data:

```sh
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml down
```

Start them again with the same `up` command. The MariaDB database and
InvoiceShelf uploads remain in Docker named volumes.

## Persistent data and backup

The deployment stores data in these named volumes:

- `custom_invoiceshelf_mysql` — MariaDB database
- `custom_invoiceshelf_storage` — InvoiceShelf uploads, logs, and generated files

Do not run `docker compose down --volumes` unless you intentionally want to
delete all application data. Back up the MariaDB volume before upgrades or
destructive Docker operations.

## Update

Pull the desired source changes, then rebuild and recreate the application:

```sh
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml up --build -d
```

The named volumes are reused, so existing data remains available.
