# Purchases, refunds, and hearts

Admin Revenue & Orders and Hearts refresh every 10 seconds while visible, and on tab focus. Player purchase history refreshes while open. A player-wide sync updates heart balances and creates notification-bell entries for refund decisions and admin heart grants. Notifications link to purchase history or the heart store, preserve admin notes, and are deduplicated per signed-in account.

Purchase and heart-activity responses include customer identity only when that relation is loaded. Player endpoints remain scoped to the authenticated user. Revenue customer totals exclude refunded orders. Both historical `hearts` and current `heart` product values work in the admin filter.

## Refund processing

- `pending`: awaiting admin review.
- `approved` with `pending` or `processing` provider status: waiting for the payment provider. Hearts/access and paid-order totals remain unchanged.
- `refunded` with `succeeded`: provider confirmation received; purchase, payment records, hearts/access, and heart activity are updated once.
- `approved` with `unknown` or `failed`: needs admin attention. The admin can retry; uncertain requests retain the same persisted idempotency key. An uncertain request older than 23 hours requires provider investigation instead of another automated refund.
- `rejected`: declined by the admin; the player can see the reason.

No simulated refunds are generated. Missing configuration, missing payment references, and provider rejection produce explicit errors. A provider timeout is treated as unconfirmed rather than falsely reported as refunded. Per-request locks prevent concurrent admin processing, and the attempt key is committed before contacting the provider.

Provider statuses are reconciled when purchase/refund lists refresh, on verified refund webhooks, and by Laravel's `reconcile-refunds` task every minute. This task retrieves existing refund status; it never initiates a new refund.

## Running and deployment

1. Apply migrations with `php artisan migrate`.
2. Run Laravel's scheduler (`php artisan schedule:work` locally, or a production scheduler calling `schedule:run` every minute). This keeps pending refunds up to date when no browser is open.
3. Keep the existing signed payment webhook endpoint configured with the provider's refund events.
4. Verify the intended payment mode and mail transport before a real transaction.

The local migration was applied on 2026-10-14. Local inspection found payment **test mode**, a configured payment key, SMTP transport, 31 orders from 3 customers, and no legacy simulated-refund records. No real payment or customer email was sent during verification.

Admin feedback distinguishes mail-service acceptance from failure or a log-only transport. `sent` means the mail transport accepted the email; it does not confirm inbox delivery. Purchase history and notification-bell updates remain available when email fails.

Admin heart grants fill available lives and place overflow in the reserve, retaining the normal life cap. The activity record includes the allocation.

## Checks

- Backend: `php artisan test` (full suite); `php artisan test --filter='CommerceIntegrationTest|HeartInventoryTest|RefundSystemTest'` for focused checks.
- Frontend: `node --test tests/playerRefund.test.mjs tests/commerceSync.test.mjs` and `npm run build`.
- Provider calls and mail are mocked in tests; those tests do not validate external delivery or actual fund settlement.

Provider references: [Refund resource and statuses](https://docs.paymongo.com/reference/refund-resource), [idempotent requests](https://docs.paymongo.com/reference/idempotent-requests).
