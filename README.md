# manny

## Local database

The MySQL 8 schema is in `database/schema.sql`. MAMP's local database is named `cladvest_app` and listens on port `8889`.

Local credentials belong in the ignored `config/database.local.php` file. Environment variables `CLADVEST_DB_HOST`, `CLADVEST_DB_PORT`, `CLADVEST_DB_DATABASE`, `CLADVEST_DB_USERNAME`, and `CLADVEST_DB_PASSWORD` override those settings.

## Email notifications

Email notifications are sent after successful registration, sign-in, password-reset requests, pending deposit or withdrawal requests, and admin completion or rejection. Mail delivery is non-blocking: a mail failure is written to the PHP error log and does not undo the account or ledger operation.

Copy `config/mail.local.php.example` to the ignored `config/mail.local.php` and set `enabled`, `from`, and `from_name`, or use `CLADVEST_MAIL_ENABLED`, `CLADVEST_MAIL_FROM`, and `CLADVEST_MAIL_FROM_NAME`. Set `CLADVEST_APP_URL` to the public app URL used in password-reset links. PHP's configured `mail()` transport must be available in MAMP; for production, configure the PHP mail transport through a real SMTP relay.

With MAMP running, open `http://localhost:8888/manuel/api/health.php` to verify that PHP can connect to MySQL and report the schema table count.

For an existing database, apply `database/migrations/20261103_003_demo_workflows.sql` through `database/migrations/20261106_016_withdrawal_details.sql` in order after the initial schema. The migrations are safe to rerun.

## Local authentication

Open `http://localhost:8888/manuel/login.html` through MAMP. Registration and sign-in use the PHP endpoints in `api/auth/`, password hashes are stored in MySQL, and sessions are revocable server-side records. `auth-guard.js` redirects unsigned users away from account pages.

The one-time demo account was created with `database/create_demo_account.php`. That script generates and prints a random password only when it creates the account; it refuses to overwrite an existing account. Keep the generated password private. New users can also register from `login.html?mode=signup`.

`admin.html` is a protected admin console. Sign in with an existing user account, then provision it from the command line with `php database/grant_admin.php admin@example.com`. Admin endpoints require a valid session and an `admin_users` role. Financial balance adjustments are posted to the ledger and recorded in `audit_events`; support access is provisioned alongside the admin role. Revoke access by deleting the account from `admin_users` and `support_agents`.

Profile details, email and loan-reminder preferences, ledger-derived balances and activity, simulated cash movements, goals, recurring-plan previews, and loan-request previews use authenticated MySQL APIs. Asset catalog and quote history, per-user watchlists, order drafts, swap previews, and customer support conversations are also database-backed. Asset prices are read from `asset_prices`; demo-seeded quotes are identified as demo data and are not live market feeds.

Investment starts and crypto swaps are saved as simulations; order requests can also be saved as unsubmitted drafts. These workflows do not create ledger entries, change balances, submit trades, nor move tokens. A trusted quote provider and a separately reviewed execution service are required before enabling live investment or swap execution. Cash entries and loan requests likewise remain simulations; they do not move external funds or constitute credit offers.

Support staff can use `api/support/agent.php` to view the queue, read conversations, reply, and update statuses. Agent access is denied unless an operator provisions the account in `support_agents`, for example with `INSERT INTO support_agents (user_id) SELECT id FROM users WHERE email = 'staff@example.com';`. Customer APIs cannot grant this access. No email, push, or support-agent notification is sent by these previews.

Before public deployment, add HTTPS, verified-email/password-reset flows, abuse monitoring, a trusted market-data provider, audited trade/payment/lending providers, support staffing and notification delivery, and operational procedures for provisioning support agents.
