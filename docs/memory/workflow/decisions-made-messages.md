---
name: decisions-made-messages
description: Decisions list, messages part: which text messages are transactional (no İYS consent), the back-in-stock SMS, the cash-on-delivery code's orders, the member's SMS sign-in code.
---

## Is the back-in-stock text message a marketing message?
- No. Owner decision (2026-10-26, #1864): "sadece işlem mesajları yer alacak". The customer asks for this one notice about one product and it is sent once, so it is transactional: kind `stock`, not commercial, sent without İYS consent. It carries no campaign, discount or other product text, and the form says one message comes (`commerce/back-in-stock-sms.md`).

## Which orders ask for the cash-on-delivery code?
- Decided (architect, 2026-10-28, #2670): the checkout has no cash-on-delivery method, so the code attaches to `Offline Payment` orders not put on the ERP account (`orders.erp_on_account`), behind the `cod_code` event of the SMS Messages card (off by default, kind `otp`), sent to the billing phone and checked before the order is written; running sites change nothing until the event is switched on. A real "Cash on Delivery" payment method (ENUM value, checkout pages, ERP `KAPIDAODEME`) is a plan of its own; the check moves to it then.

## What is the member's SMS sign-in code?
- Decided (architect, 2026-10-28, #2672): an alternative at the second step only: a role-3 account that already has a second factor (TOTP or passkey) may ask for a code texted to its linked contact's mobile phone instead of the app code, behind the `signin_code` event (off by default); the password stays the first factor; staff roles 0-2 keep the app code or passkey; `/auth/login`, ShipWorks and the account-page proofs are unchanged. Not a passwordless sign-in by phone: an unverified, non-unique, staff-editable number is no credential. SMS as a factor of its own (a confirmed number stored in `user_mfa`) would be a plan of its own.
