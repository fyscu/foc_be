# Backup and recovery strategy

This strategy is designed for the current single-server FOC deployment. Backups are only useful after a restore has been tested.

## Recovery objectives

- Database recovery point: no more than one hour of data loss.
- Media recovery point: no more than one day after the new owned object store is active.
- Service recovery target: restore the current release and database on a clean host without relying on the failed host.

## Backup layers

### 1. Tencent Cloud system-disk snapshots

Use the two free snapshot slots as a rotating pair:

- Snapshot A: latest verified baseline.
- Snapshot B: immediately before a high-risk runtime, OS, Docker or database migration.

Do not treat snapshots as the only database backup. They are crash-consistent system-disk copies and remain in the same cloud account and region.

### 2. MySQL logical backups

Recommended retention after the new off-site target is verified:

| Frequency | Retention |
| --- | ---: |
| Hourly | 48 to 168 copies |
| Daily | 30 to 90 copies |
| Weekly | 12 copies |
| Monthly | 12 copies |

Use `--single-transaction`, include routines/triggers/events, compress the output, write a SHA256 manifest and restrict files to mode 0600. Keep one local copy set for fast restore and one encrypted copy set in an object-storage account controlled by the club.

The historical 1Panel policy retains 1000 hourly and 500 daily copies both locally and in the legacy KODO account. Do not delete those copies until the replacement off-site target and a restore test are complete. After that, reduce retention gradually and verify disk usage after each cleanup.

### 3. Media and uploaded files

- Primary: object storage account owned by the club, with HTTPS, versioning and lifecycle rules.
- Secondary: daily replication to the physical server's MinIO after its network and recovery path are stable.
- Preserve object keys during migration so existing database URLs can continue to resolve through the chosen image domain.
- Keep database backups and user media in separate buckets or prefixes with separate credentials and lifecycle policies.

### 4. Source and configuration

- Source, migrations and deployment documentation belong in the private or appropriately scoped Git repository.
- Production secrets do not belong in Git. Store an example config containing placeholders and keep the real config outside the document root.
- Back up encrypted secrets separately from the source repository and document who can recover them.

## Automated verification

For every backup job, record the start time, finish time, size, SHA256 and destination result. Alert when an hourly backup is missing for more than two hours or when either local or off-site upload fails.

At least monthly:

1. Restore the latest logical dump into an isolated MySQL instance.
2. Run table counts and core relationship checks.
3. Start the current application release against the restored database in a private test network.
4. Verify login lookup, user/ticket reads, queue counts and administrator statistics without sending notifications.
5. Record the measured restore time and any manual steps.

## Recovery order

1. Provision a clean host and restrict management access.
2. Restore the versioned application release and production-compatible runtime image.
3. Restore secrets from the controlled encrypted store.
4. Restore MySQL and run migrations up to the selected release.
5. Restore or reconnect media storage.
6. Run read-only smoke tests before directing public traffic to the host.

Never test recovery by overwriting the production database or bucket.
