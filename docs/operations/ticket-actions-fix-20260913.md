# Ticket Completion and Cancellation Fix 2026-09-13

Production paths:

- `v1/ticket/complete.php`
- `v1/ticket/set.php`

## Problem

Both actions performed third-party notifications inside the primary request path. Cancellation could release technician capacity and send SMS before writing the canceled status. Completion wrote `Done` and then synchronously waited for SMS and SMTP. A warning, exception or timeout could therefore corrupt the JSON response or leave partial side effects.

The mini program also treats an expired one-hour token by starting a login request without waiting for it or replaying the original action. The companion client fix is on `fyscu/foc_fe` branch `codex/retry-ticket-action-after-login-20260913`.

## Changes

- Disable response error display and enable server-side error logging for both endpoints.
- Validate and normalize ticket IDs and actor IDs.
- Make completion idempotent and use a row lock plus transaction for status/capacity updates.
- Make cancellation status, user quota and technician availability one transaction.
- Do not repeat quota or notification side effects for an already canceled ticket.
- Return the successful JSON response before best-effort SMS/email work under FPM.
- Catch notification `Throwable` values and log them without reversing committed business state.
- Record `completion_time` when the two-party confirmation transition reaches `Done`.

## Verification

- Dedicated pre-change database dump and code backup: `/opt/1panel/backups/manual/foc-ticket-actions-20260913-1215/`
- PHP 8.3.33 lint: passed before and after deployment.
- Ten isolated MockPDO HTTP scenarios: passed.
- Authenticated missing-ticket probes traversed TLS, OpenResty, FPM, PDO and token validation without writes.
- Expected probes: complete 200/ticket-not-found, set 200/ticket-not-found, unauthenticated complete 401.
- Production SHA256 after deployment:
  - `complete.php`: `caf1f08026f22349c7d311c89c2b54a96ac3f49fc1d4fb8eede5778b0308390c`
  - `set.php`: `ee2119c4977640ff6a3fa34fe3d61581d24ab1476be663a62d0165c6a4214121`

