# Production Source Sync 2026-09-11

This branch records the application source observed at:

`/www/sites/focapi.feiyang.ac.cn/index`

The snapshot was layered on commit `c5764583e36d2d32b193b7a958bf3d38c2a8c1b3` after the PHP 8.3 runtime and core-index changes.

## Normalized Comparison

- 185 tracked application text files inspected
- 114 matched production after CRLF/LF normalization
- 41 differed from production
- 30 tracked files were absent from production; `config.php` and `db.php` were retained as repository-safe files, while the other 28 were removed
- 74 production application/build files were not previously tracked

## Deliberate Exclusions

The export excluded production configuration, database files, archives, logs, caches, uploads, user data, certificates and private keys. Third-party SDK directories were not overlaid from production.

The following high-risk production-specific files were deliberately not used to overwrite repository copies or add new content:

- `public/maa/index.php`
- `public/verify_email.php`
- `public/repairticket/api/send_sms.php`
- `public/newrepair/deploy/seed-admin.php`
- `utils/token.php`
- `utils/webdav.php`

## Verification

- Trivy secret scan: no findings
- Custom private-key, cloud-key and literal-secret scan: no new findings
- PHP 8.3.33 application lint: 193 files passed
- The branch is a traceability snapshot, not an automatic deployment source

