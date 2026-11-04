---
name: encryption-key-reseal
description: Plan: Reset Encryption Key re-seals every value sealed with the key in the same save (owner, 2026-11-03, #6392, after the warn-and-confirm fix); claim as [plan:encryption-key-reseal] RK<n>
---

## What is this plan?
- Owner (2026-11-03, #6392, option C; `workflow/decisions-made-product-25.md`): first the warn-and-confirm fix (the `bug` work item of #6392, not a step here), then this plan: Reset Encryption Key (`includes/settings/security.save.php`) opens every value sealed with the old key and seals it with the new one in the same save, as it does today for `orders.card_number` and the gateway secrets (`commerce/payment-gateway-secrets.md`).
- Today only those two are re-sealed; everything else sealed with `encrypt_string_with_iv()` or `pg_connect_seal()` (`core/security-helpers-and-secret-storage.md`) stays under the old key and reads empty afterwards (`platform/sms-provider-layer.md`).

## What is sealed with the key?
- `encrypt_string_with_iv()` values: the Google sign-in client secret (`security.save.php`), e-document credentials (`includes/erp/edoc/registry.php`), Paraşüt (`includes/fn/parasut.php`), backup storage (`includes/fn/backup.php`), product feed tokens (`includes/fn/product_feeds.php`), two-step keys (`includes/fn/mfa.php`), subscription card references (`pg_subscription_card_ref_seal()`, `commerce/subscriptions.md`).
- `pg_connect_seal()` values: connected accounts and applications (`includes/fn/connect.php`: Apple key, CAPTCHA secrets, SMS and mail transport passwords, object storage, carriers, service rows such as Google Analytics), marketplace connector credentials (`includes/api/outbound/connectors/base.php`).
- HMACs keyed with the key cannot be re-sealed and are re-issued by nature: device tokens and pairings (`api/api-device-refresh-rotation.md`, `api/api-device-pairing.md`), form-issued tokens, e-mail preference links, idempotency fingerprints. The card's warning keeps naming them: connected devices pair again.

## Steps
- RK1 (S) The registry and the key-parameter helpers. Done (#6487): `pg_reseal_registry()`, `pg_reseal_run()`, the `$key` parameters; `platform/encryption-key-reseal-registry.md`.

- RK2 (M) The module entries. Done (#6501): every module's entries, JSON `fields`, the key reset moved last in `security.save.php`.

- RK3 (S) The card. The warn-and-confirm list becomes "will be re-sealed", the confirmation box stays only for the HMAC-keyed items, the success message gives the counts; counts per area: `pg_encryption_key_reset_counts()` (#6392); `pg_reseal_run()` areas add subscriptions, translation, activitypub, workspace (labels needed). Memory: `ui/settings-security-cards.md`.

## Open questions
- Whether a site with thousands of two-step keys needs the general job (`release/migration-long-steps.md` pattern) instead of the save request: RK1 measures with its counts and asks only if a real site exceeds the request budget.

## Where does it start?
- RK1 and RK2 are done; RK3 is next. Read `platform/encryption-key-reseal-registry.md` first. Read `core/README.md` key rules, `core/security-helpers-and-secret-storage.md`, `commerce/payment-gateway-secrets.md`, `ui/settings-security-cards.md`.
