# KeepUp

![KeepUp Logo](/public/images/logo.png)

Your personal assistant for keeping your Linux systems up-to-date!

## Features

- Add, remove and monitor systems remotely via SSH password-based authentication and private-key-based authentication, and get automatically:
    - Operating System
    - Uptime
    - Updates available
    - IP addresses of the remote machine

## Screenshots

![Dashboard view](/screenshots/dashboard.png)
![Monitor creation view](/screenshots/monitor_creation.png)

## Roadmap

For now, everything needed from my side is currently implemented, but you're very welcome to contribute to the project by opening a PR!

Ideas worth mentioning:

- [x] History of changes between a monitor check and the next one, via a separate versioned data table (uptime, number of updates)
- [x] Summary status of Good, Bad on the top side of Dashboard section
- [x] Add single Monitor alert for number of update threshold, uptime threshold
- [x] Add cumulative stats for updates available
- [x] Add password encryption
- [x] Add ssh private key encryption
- [ ] Send reports via e-mail on a weekly basis
- [x] Get cumulative volatile memory status, disk(s) space left and system load
- [x] Add manual refresh button on dashboard + last refresh + auto refresh page on 5 minutes on dashboard

# License

This software is licensed under MIT license. See `LICENSE` file for more information.

This software is based on the [Laravel Framework](https://laravel.com), all rights reserved to the respective license owners.