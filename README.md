# KeepUp

![KeepUp Logo](/public/images/logo.png)

Your personal, *agentless* assistant for keeping your Linux systems monitored and up-to-date!

## Features

- Add, edit, remove and monitor systems remotely via SSH password-based authentication and private-key-based authentication. Passwords and private keys are encrypted at rest, and stored private keys can be reused across multiple monitors. KeepUp automatically collects:
    - Operating System
    - Uptime
    - Updates available
    - IP addresses of the remote machine
    - Disk usage
    - CPU usage
    - Docker system support and container list
- Organize monitors with multiple color-coded labels and quick dashboard filters.

## Operating Systems supported

For now, we integrated with:

- Debian
- Ubuntu
- Arch Linux
- Proxmox PVE

Feel free to make a PR to integrate with other operating systems, like SUSE and RHEL!

## Docker deployment

The included Docker Compose stack runs KeepUp with MySQL 8.4 LTS, a queue worker, the scheduler, persistent SSH-key storage, health checks and automatic database migrations. You need Docker Engine with the Compose v2 plugin, or Docker Desktop.

1. From the project directory, copy the container environment template:

    ```bash
    cp .env.docker.example .env.docker
    ```

2. Open `.env.docker` and configure the deployment:

    - Set `APP_URL` to the URL used to access KeepUp.
    - Replace `DB_PASSWORD` and `MYSQL_PASSWORD` with the same strong password.
    - Set `MYSQL_ROOT_PASSWORD` to a different strong password.

3. Build the application image and generate an application key:

    ```bash
    docker compose build
    docker compose run --rm --no-deps app php artisan key:generate --show
    ```

4. Paste the generated value, including its `base64:` prefix, into `APP_KEY` in `.env.docker`.

5. Start the stack and wait for its services to become healthy:

    ```bash
    docker compose up -d --wait
    ```

6. Create the first login account using the secure interactive command:

    ```bash
    docker compose exec app php artisan app:create-user
    ```

7. Open `http://localhost:8000` and sign in with the account you created. You can confirm that every service is running with:

    ```bash
    docker compose ps
    ```

### Custom port

KeepUp is published on port `8000` by default. To use another port, update `APP_URL` accordingly and set `KEEPUP_PORT` when starting the stack:

```bash
KEEPUP_PORT=8080 docker compose up -d --wait
```

### Managing the stack

```bash
# Follow logs from every service
docker compose logs -f

# Rebuild and apply a project update
docker compose up -d --build --wait

# Stop the stack without deleting its data
docker compose down
```

The database and encrypted SSH keys are stored in named Docker volumes and survive a normal `docker compose down`. Running `docker compose down --volumes` permanently deletes those volumes, including the database and stored SSH keys.

## Screenshots

![Dashboard view](/screenshots/dashboard.png)
![Monitor creation view](/screenshots/monitor_creation.png)

## Roadmap

For now, everything needed from my side is currently implemented, but you're very welcome to contribute to the project by opening a PR!

Ideas worth mentioning, that would be good to implement:

- [x] Add an official Docker Compose stack for easier deployment
- [ ] Add LDAP/SSO login support/integration
- [x] Add multiple labels and quick label filters for monitored servers
- [x] Reuse a securely stored private key across multiple monitored machines
- [ ] Add Windows clients/servers support
- [x] Add Proxmox PVE support
- [x] Operating system specific version, so we can check if systems are in EOL or not
- [x] Docker service installed, and number of containers active (number should be enough) 
- [x] Show if IP shown is a public IP or not, clearly
- [x] History of changes between a monitor check and the next one, via a separate versioned data table (uptime, number of updates)
- [x] Summary status of Good, Bad on the top side of Dashboard section
- [x] Add single Monitor alert for number of update threshold, uptime threshold
- [x] Add cumulative stats for updates available
- [x] Add password encryption
- [x] Add ssh private key encryption
- [x] Get cumulative volatile memory status, disk(s) space left and system load
- [x] Add manual refresh button on dashboard + last refresh + auto refresh page on 5 minutes on dashboard
- [x] Add a manual refresh request button for each monitor in the main table

# License

This software is licensed under MIT license. See `LICENSE` file for more information.

This software is based on the [Laravel Framework](https://laravel.com), all rights reserved to the respective license owners.
