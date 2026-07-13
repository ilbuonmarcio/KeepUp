# KeepUp

KeepUp is a self-hosted, agentless dashboard for monitoring Linux servers over SSH. It collects a concise operational snapshot from each server and keeps health, pending updates and resource information in one place.

## Features

### Server monitoring

- Add, edit and delete monitored servers.
- Connect over SSH using a password or private key.
- Reuse an encrypted private key across multiple monitors.
- Scan every monitor on demand or request a scan for one specific monitor.
- Run scheduled scans daily at `08:00` in the application's configured timezone.
- Automatically refresh the dashboard every five minutes.

For each supported server, KeepUp collects:

- Distribution name and full operating-system version.
- Uptime in days.
- Number of available package updates.
- IPv4 addresses reported by the server.
- CPU load averages.
- Disk usage and available space.
- Whether the Docker daemon is running and how many containers are active.
- UFW status and rules when UFW is installed and accessible to the SSH user.

### Dashboard

- At-a-glance totals for healthy monitors, unreachable monitors, available updates and all monitors.
- Per-monitor warning thresholds for uptime and available updates.
- Clear indicators when a server reports a public IP and whether UFW appears active.
- Expandable technical details for network addresses, firewall rules and disk usage.
- Alphabetical monitor ordering.
- Multiple labels per monitor, with deterministic label colors and toggleable dashboard filters.
- Last scan time plus successful-check snapshots of uptime, available updates and scan duration.

### Credential protection

- SSH passwords are encrypted at rest with Laravel's application encryption key.
- Uploaded private keys are encrypted before being written to private storage.
- A private key is decrypted into a temporary permission-restricted file only while its scan runs, then removed.
- Docker keeps the database and encrypted private-key storage in persistent named volumes.

Back up `APP_KEY` securely. Losing or changing it makes previously encrypted passwords and private keys unreadable.

## Supported operating systems

- Debian
- Ubuntu
- Arch Linux
- Proxmox VE

KeepUp currently targets these Linux distributions and expects SSH on the standard port `22`.

## Monitored-server requirements

The KeepUp host or containers must be able to reach each monitored server over SSH. The configured SSH user must be allowed to run the commands used for the selected distribution, including:

- Standard system tools such as `cat`, `awk`, `ip`, `uptime` and `df`.
- `apt` on Debian, Ubuntu and Proxmox VE, or `pacman` on Arch Linux.
- `docker` when Docker status should be collected.
- `ufw status` when firewall status should be collected.

These optional Docker and UFW values depend on their commands being installed and usable by the configured account.

## Run with Docker Compose

The included stack runs the web application, MySQL 8.4, a queue worker and the Laravel scheduler. Database migrations run automatically when the application starts.

### Requirements

- Docker Desktop, or Docker Engine with the Compose v2 plugin.
- A clone of this repository.

### First start

1. Copy the Docker environment template:

    ```bash
    cp .env.docker.example .env.docker
    ```

2. Configure `.env.docker`:

    - Set `APP_URL` to the URL used to access KeepUp.
    - Give `DB_PASSWORD` and `MYSQL_PASSWORD` the same strong value.
    - Set a different strong value for `MYSQL_ROOT_PASSWORD`.

3. Build the image and generate the Laravel application key:

    ```bash
    docker compose build
    docker compose run --rm --no-deps app php artisan key:generate --show
    ```

4. Copy the generated value, including its `base64:` prefix, into `APP_KEY` in `.env.docker`.

5. Start the stack and wait for its services:

    ```bash
    docker compose up -d --wait
    ```

6. Create the first login account:

    ```bash
    docker compose exec app php artisan app:create-user
    ```

7. Open [http://localhost:8000](http://localhost:8000) and sign in.

### Custom port

KeepUp is published on port `8000` by default. To use another port, update `APP_URL` and set `KEEPUP_PORT` when starting the stack:

```bash
KEEPUP_PORT=8080 docker compose up -d --wait
```

### Operations

```bash
# Show service status
docker compose ps

# Follow logs from every service
docker compose logs -f

# Rebuild and restart after updating the project
docker compose up -d --build --wait

# Stop without deleting persistent data
docker compose down
```

The database and encrypted SSH keys survive a normal `docker compose down`. Running `docker compose down --volumes` permanently deletes both named volumes and their data.

## License

KeepUp is licensed under the [MIT License](LICENSE).

KeepUp is built with the [Laravel Framework](https://laravel.com); Laravel remains the property of its respective copyright holders.
