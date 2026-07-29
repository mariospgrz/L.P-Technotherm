# L.P-Technotherm

University project — L.P Technotherm workforce / project management system.

## Setup

1. Copy `Backend/config.example.php` → `Backend/config.php` and fill in DB, mail, AWS, `base_path`, and `cron_secret`.
2. `composer install`
3. Generate VAPID keys (CLI): `php Backend/Notifications/generate_vapid.php`
4. Point the web root at this project (or set `base_path` if deployed in a subdirectory).

## Security notes

- Never commit `Backend/config.php` or `Backend/Notifications/vapid.json`.
- Invoice files on S3 are private; access goes through `Backend/invoice_file.php`.
- Cron/notification scripts require CLI or `?key=<cron_secret>`.
