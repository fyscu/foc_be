# PHP 8.3 runtime

This image is the validated runtime for the FOC PHP backend. It keeps the extension surface used by the PHP 8.0 deployment and adds OPcache.

## Version and inputs

- PHP: 8.3.33
- Base image: `php:8.3-fpm-alpine`
- Base image digest: `sha256:bf90236449d333cef008b1f01c72a3d4f11a6470a74629665e4c6b6158f03fc8`
- Extension installer: `install-php-extensions` 2.2.14, MIT license, downloaded from the upstream release with SHA256 verification
- Runtime user/group ID: 1000

Enabled extensions include bcmath, curl, GD, gettext, Intl, mysqli, PDO MySQL, PDO SQLite, pcntl, shmop, SOAP, sockets, sysvsem, Zip and OPcache.

## Build

```bash
docker build --progress=plain \
  -t 1panel-php:8.3.33-foc \
  --build-arg TZ=Asia/Shanghai \
  --build-arg 'PHP_EXTENSIONS=bcmath curl gd gettext intl mysqli mbstring pcntl pdo_mysql pdo_sqlite shmop soap sockets sysvsem zip opcache' \
  deploy/php83
```

The base image is pinned by digest. Update the digest deliberately when moving to another PHP patch release, then rerun the validation below.

## Validation before deployment

1. Compare `php -m` with the running production image.
2. Lint application-owned PHP files. The legacy `utils/PHPExcel` library contains code paths that already fail lint on PHP 8.0; test the active XLSX read/write flows separately.
3. Load the Qiniu, AWS and PHPMailer SDKs.
4. Connect to MySQL through the application configuration and run `SELECT 1`.
5. Run the current public and authenticated read-only endpoints against a sidecar container and compare status, JSON shape and record counts.
6. Start PHP-FPM on an unused port and verify it reaches `ready to handle connections`.
7. Keep the previous container stopped but intact until application and cron checks pass.

The first PHP 8.3 rollout should not also introduce a new `php.ini`. The historical production container did not mount 1Panel's stored ini file, and enabling it changes timezone, upload limits and error handling. Apply those changes as a separate tested deployment.

## Rollback model

During a runtime switch, rename the stopped PHP 8.0 container instead of deleting it. If validation fails, remove the new container, rename the old container back to its original name and start it. Preserve the old image until the rollback window closes.

Do not commit production configuration, database dumps, access tokens, logs, generated spreadsheets or uploaded files.
