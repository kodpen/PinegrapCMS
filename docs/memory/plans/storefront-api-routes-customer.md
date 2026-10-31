---
name: storefront-api-routes-customer
description: Storefront API design note: customer routes (sign-in, sign-up, refresh, sign-out, password reset, profile, orders, addresses, returns) with scopes, limits and the website code each one calls
---

## Which customer routes?
| Id | Method, path | Customer token | Idem. | Dry-run |
|---|---|---|---|---|
| `storefront.customer.sign_in` | POST /storefront/customer/sign-in | - | yes | no |
| `storefront.customer.sign_up` | POST /storefront/customer/sign-up | - | yes | no |
| `storefront.customer.refresh` | POST /storefront/customer/refresh | - (refresh token in body) | - | no |
| `storefront.customer.sign_out` | POST /storefront/customer/sign-out | yes | - | no |
| `storefront.customer.password_reset` | POST /storefront/customer/password-reset | - | yes | no |
| `storefront.customer.me` | GET /storefront/customer | yes | - | - |
| `storefront.customer.update` | POST /storefront/customer (also PATCH) | yes | yes | yes |
| `storefront.customer.orders` | GET /storefront/customer/orders | yes | - | - |
| `storefront.customer.order` | GET /storefront/customer/orders/{number} | yes | - | - |
| `storefront.customer.addresses` | GET /storefront/customer/addresses | yes | - | - |
| `storefront.customer.addresses.save` | POST /storefront/customer/addresses[/{id}] | yes | yes | yes |
| `storefront.customer.addresses.delete_post` | POST /storefront/customer/addresses/{id}/delete | yes | yes | yes |
| `storefront.customer.returns.create` | POST /storefront/customer/orders/{number}/returns | yes | yes | yes |

- Scope `storefront:customer` on all; sign-in, sign-up, refresh, reset are NOT `'public' => true`: unlike `/auth/login` they are called by a known application and count in its bucket and in the shopper limits (`storefront-api-callers.md`).
- A route with "yes" in the token column answers 401 `customer_token_required` without one.

## Which website code does each call?
- Sign-in: `pg_mfa_gate()` and the sign-in throttle (`core/two-step-sign-in.md`); `otp` as on `/auth/login`, 401 `mfa_required`.
- Sign-up: the registration form's checks (blocked e-mail, password rule, consent and KVKK, IYS opt-in through `pg_consent_log_record()`); the welcome mail as from the website.
- Password reset: the website's reset mail; always 202 whatever the address (no account enumeration).
- Orders: the customer's own rows as `order_history` shows them, completed statuses only, presented through the existing `Order` presenter's subset (no internal notes, no commission, no staff fields).
- Addresses: the address book of `express-order-notes-address-book.md`.
- Returns: the customer return request path of `commerce/order-returns-customer.md` (window, reasons, `order-withdrawal-deadline.md`).

## What is left out?
- Wishlist, reviews, back-in-stock requests, subscriptions management, saved cards: later, each its own small step once the core slices land.
- Changing the e-mail address (needs the confirmation round trip in a browser).

Related: plans/storefront-api-customer-token.md, plans/storefront-api-routes-checkout.md
