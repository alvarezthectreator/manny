# Admin Console Setup

## Database

Apply the migrations in order after `database/schema.sql`:

1. `database/migrations/20261103_003_demo_workflows.sql`
2. `database/migrations/20261103_004_order_draft_amount.sql`
3. `database/migrations/20261103_005_investment_simulations.sql`
4. `database/migrations/20261103_006_admin_access.sql`
5. `database/migrations/20261103_007_cash_review.sql`
6. `database/migrations/20261104_008_payment_channels.sql`
7. `database/migrations/20261104_009_recurring_semiannual.sql`
8. `database/migrations/20261104_010_daily_frequency.sql`
9. `database/migrations/20261106_011_demo_payment_instructions.sql`
10. `database/migrations/20261106_012_payment_instruction_labels.sql`
11. `database/migrations/20261106_013_cash_app_copy_identifier.sql`
12. `database/migrations/20261106_014_structured_payment_channel_details.sql`
13. `database/migrations/20261106_015_withdrawal_wallet.sql`
14. `database/migrations/20261106_016_withdrawal_details.sql`

## Provision Admin Access

Create or use an active application account, then grant the admin and support-agent roles from the CLI:

```sh
php database/grant_admin.php admin@example.com
```

Sign in through `login.html` with that account, then open `admin.html`. Admin API requests require the authenticated session, admin role, and CSRF token. To revoke access, delete the account's row from both `admin_users` and `support_agents`.

The current local MAMP workspace provisions `demo@cladvest.local` for development. Do not use the demo account as a production administrator.

## Financial Boundaries

Balance edits are recorded as double-entry ledger adjustments with an audit reason. Deposit approvals require a settlement reference; withdrawal approvals require an external payout reference and recheck available cash under lock. This app does not initiate bank or wallet transfers, so staff must verify and perform external settlement separately.

Loan approval is an internal decision, not a lender offer. Approval regenerates a schedule for the approved amount. The user must explicitly accept disbursement, which posts the amount to the app cash ledger. No lender or funding provider is connected.

Portfolio profit/loss is derived from quantity, cost basis, and stored asset quotes. Portfolio edits and manual asset quotes are audited; a manually entered quote is not represented as a live feed.