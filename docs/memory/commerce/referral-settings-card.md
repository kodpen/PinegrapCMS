---
name: referral-settings-card
description: Referral programme settings card (RF2): Settings › Ecommerce › Referral programme, its six config_text rows, what the save refuses, the referrer's reward mail and its texts
module: referral
---

## Where is the card?
- Settings › Ecommerce, card `pgset-referral` "Referral programme" after Affiliate Program (`registry.php` section and keywords; no legacy anchor, it is a new card). Markup in `includes/settings/commerce.php`, values in `prep.php` (`pg_referral_settings_for_input()`, `pg_referral_ready()`), the save in `commerce.save.php`.
- Rows: the switch "Turn on the referral programme" with its note, "Offer for the friend" (`select_offer()`, none allowed), "Reward" and "Smallest order that counts" (amounts in the base currency), "Reward lasts" (days, blank never expires), "Rewards a month per customer" (blank no limit). Every row is always drawn; a note says when Ecommerce is off or the 11.0.245 step has not run.

## What does the save write?
- The six `config_text` rows RF1's `pg_referral_settings()` reads (`referral-rules.md`): `referral_on` ('1' or ''), `referral_offer_id`, `referral_reward` and `referral_smallest` in minor units (`round()`), `referral_reward_days`, `referral_month_cap`; 0 is stored as ''. Off keeps the other values, so turning it on again finds them (E3: off changes nothing).
- Written only when the card was posted (`referral_reward` in the post), after the category's `check_form_errors()`.

## What does the save refuse?
- `pg_referral_settings_check()` (pure, the offer asked of a callback) checks every box first; one refusal writes none of the six. Codes and sentences: `pg_referral_settings_errors()`.
- reward: not an amount (letters, minus, above 4294967295 minor units, the gift card column); `reward_needed`: switch on with 0 or blank. days: not a whole number 0-3650 (`PG_REFERRAL_DAYS_MAX`). smallest: not an amount. month_cap: not a whole number 0-1000 (`PG_REFERRAL_MONTH_CAP_MAX`). offer: an id of no offer, or not an id.

## Which mail does the referrer get? (E10)
- After a reward, once the referrer's named lock is released (a mail sent inline when the queue is down must not hold the lock): `pg_referral_mail_reward()` → `email()` with `queue` true, type `system`, source `account`, to the account's e-mail (else the contact's). A failure is logged; the reward stands.
- Subject "You earned {var:1} in store credit"; body `pg_referral_mail_body()` (pure): the amount, the whole balance (`pg_credit_balance()`), the last day when the reward expires, a link to My Account (http(s) only), the sender. The friend is never named. Texts are `lang()` strings with their Turkish; there is no editable mail text.
- A restore that gives the reward again sends no mail. A shop with a `credit_issued` campaign profile also sends that mail (`store-credit-mails.md`).

## Tests?
- `tests/referral_test.php`: the save's values, off keeping values, every refusal, the mail's placeholders and escaping, the mail after the lock.
