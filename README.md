# KeepUp

![KeepUp Logo](/public/images/logo.png)

Your personal, *agentless* assistant for keeping your Linux systems monitored and up-to-date!

## Features

- Add, remove and monitor systems remotely via SSH password-based authentication and private-key-based authentication, and get automatically:
    - Operating System
    - Uptime
    - Updates available
    - IP addresses of the remote machine
    - Disk usage
    - CPU usage
    - Docker system support and container list
    - Weekly emails about monitor status

## Operating Systems supported

For now, we integrated with:

- Debian
- Ubuntu
- Arch Linux
- Proxmox PVE

Feel free to make a PR to integrate with other operating systems, like SUSE and RHEL!

## Screenshots

![Dashboard view](/screenshots/dashboard.png)
![Monitor creation view](/screenshots/monitor_creation.png)

## Roadmap

For now, everything needed from my side is currently implemented, but you're very welcome to contribute to the project by opening a PR!

Ideas worth mentioning, that would be good to implement:

- [ ] Update mail view template with newly integrated data
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
- [x] Send reports via e-mail on a weekly basis
- [x] Get cumulative volatile memory status, disk(s) space left and system load
- [x] Add manual refresh button on dashboard + last refresh + auto refresh page on 5 minutes on dashboard

# License

This software is licensed under MIT license. See `LICENSE` file for more information.

This software is based on the [Laravel Framework](https://laravel.com), all rights reserved to the respective license owners.