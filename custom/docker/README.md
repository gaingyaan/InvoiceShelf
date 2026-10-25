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

## Start

```powershell
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml up -d --build
```

Open `http://localhost:8090`. Use the values from `custom/docker/.env` when the
installation wizard asks for the database settings. The database host is
`database`, not `127.0.0.1`.

## Stop or restart

```powershell
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml stop
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml start
```

The database and application storage use named Docker volumes, so they persist
across restarts and `docker compose down`. Do not add `--volumes` unless you
intend to delete the stored data.
