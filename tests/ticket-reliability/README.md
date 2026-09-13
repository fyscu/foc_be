# FOC ticket reliability integration tests

These scripts exercise the actual new business service and HTTP endpoints against a disposable MySQL 8.4 database using synthetic identities, ticket IDs above 2^31, and the production token verifier. No framework or package installation is required.

Required PHP modules: PDO + pdo_mysql. Concurrent tests and the HTTP suite use proc_open to start independent PHP processes. No real transport worker is started; notification tests inject synthetic delivery callbacks, never ticketDeliverNotification or provider APIs.

## Required environment

- FOC_TEST_DSN=mysql:host=<isolated mysql>;dbname=foc_reliability_test;charset=utf8mb4
- FOC_TEST_DB_USER and FOC_TEST_DB_PASSWORD: disposable database credentials
- FOC_TEST_ALLOW_SCHEMA_RESET=yes
- FOC_TEST_CORE=<candidate root>/utils/ticket_actions.php
- FOC_TEST_MIGRATION=<candidate root>/migrations/20260913_ticket_reliability.sql
- FOC_TEST_TOKEN=<copied unmodified production token.php>
- Optional FOC_TEST_NOTIFICATIONS=<candidate root>/utils/ticket_notifications.php (defaults to core sibling)
- FOC_TEST_WEB_ROOT=<isolated webroot with the real candidate endpoints/utils>
- Optional FOC_TEST_HTTP_PORT; otherwise a localhost ephemeral port is selected.

Only the exact database name foc_reliability_test or foc_reliability_test_<suffix> is allowed. The harness checks SELECT DATABASE() and refuses unexpected table names. It truncates fixture tables between tests. The database must be in the independently created throwaway MySQL container, not the production MySQL container.

The isolated HTTP webroot must contain utils/ticket_actions.php, utils/ticket_http.php, utils/token.php, v1/ticket/{give,set,complete}.php, v1/admin/setTicket.php, and a synthetic config.php. The latter returns db.host/db.dbname/db.username/db.password and info.weeklyset=5. The HTTP wrapper uses that config to connect to the same isolated schema.

## Run order

1. Run php -l on all PHP scripts, candidate helpers, and endpoints.
2. Run php <tests>/run.php (schema + migration setup, 32 business/token groups).
3. Run php <tests>/http.php (9 groups using real PHP HTTP endpoints).
4. Run php <tests>/notifications.php (8 worker groups with injected in-process delivery stubs).
5. Save stdout/stderr as test evidence; all suites exit nonzero on any failed group.
6. After verification, the owning task removes its isolated containers and fixture schema.

The run.php suite covers code/int normalization, assignment/transfer, safe retry receipts, optional request_id for delayed static-hash retries and explicit subsequent claims, competing claimants, authorization, terminal/archived state protection, capacity calculation, concurrent quota refunds, two-party confirmation (technician submits UserConfirming; customer submits TechConfirming), editable fields, admin reassignment, invalid types/lengths, three token stores, and notification persistence fault rollback.

The HTTP suite covers all three token stores through actual give/edit/complete endpoints, header case independence, missing/expired tokens, invalid JSON, request IDs, unauthenticated preflight, the admin id parameter contract, clean JSON on database failure, and method/body-size boundaries.
