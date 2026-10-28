# Custom Docker environment

This deployment keeps local settings and secrets in a required environment
file. Docker Compose will not start if the file or a required value is missing.

## Configure

From the repository root, create the local environment file:

```powershell
Copy-Item custom/docker/.env.example custom/docker/.env
notepad custom/docker/.env
```

Change both password values before starting the application. The real `.env`
file is ignored by Git and must not be committed.

When updating an existing checkout, add the following value to its local `.env`
if it is not already present:

```env
BOOTSTRAP_CACHE_VOLUME_NAME=invoiceshelf_bootstrap_cache
```

## Start

```powershell
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml up -d --build
```

Before the web application starts, a one-time `permissions` service assigns the
`storage` and `bootstrap/cache` volumes to `www-data` with `775` permissions.
The permission service then exits successfully, while the web application runs
as its normal non-root user. An exited permission service with exit code `0` is
expected.

Open `http://localhost:8090`. Use the values from `custom/docker/.env` when the
installation wizard asks for the database settings. The database host is
`database`, not `127.0.0.1`.

## Stop or restart

```powershell
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml stop
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml up -d
```

The database and application storage use named Docker volumes, so they persist
across restarts and `docker compose down`. Do not add `--volumes` unless you
intend to delete the stored data.
