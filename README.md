# TAASCOR HRIS

TAASCOR's PHP/MySQL HRIS and payroll application.

## Local setup

1. Copy `config/mysql-config.example.php` to `config/mysql-config.php`.
2. Set `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_DATABASE`, or edit the local config copy.
3. Import the required database schema into a local MySQL instance.
4. Serve this directory with PHP and open `/login/`.

Example:

```powershell
php -S 127.0.0.1:8797 -t .
```

The local credential file, database exports, logs, uploaded payroll data, and local authentication helpers are intentionally excluded from source control.

## Deployment

GitHub is the source of truth. Production releases are built from a reviewed commit and uploaded to Hostinger manually. Pushing this repository does not deploy production.
