---
name: referral-account-part
description: Referral programme in My Account (RF3): section referral, flags has_referral and can_refer, __referral_url, why a customer cannot refer, referrals as initials
module: widgets_referral
---

## Where does My Account show it?
- Section `referral` of the my account widget, built in `_pg_my_account_extras()` (shop sites, signed in) by `pg_referral_account()` in `includes/fn/widgets_referral.php`, loaded there, not by `functions.php`. Nothing in it writes.
- Flags: `has_referral` (the part is shown), `can_refer` (the link is shown). Link token `^^__referral_url^^` (the referral link; empty, so its node drops, while the customer cannot refer).
- The section is in no starter and "Convert to design elements" does not offer it; there is no list source for the referrals yet.

## When is it hidden, and what is a customer told?
- `pg_referral_account_reason()` (pure): `hidden` while the programme is off (`pg_referral_settings()['on']`), the 11.0.245 step has not run (`pg_referral_ready()`), there is no code (no site key or contact) or the contact has no account. Hidden draws nothing, past referrals included.
- `affiliate`: an approved affiliate reads "As an affiliate, share your affiliate link instead: it earns you commission." and gets no link.
- `no_order`: no complete or exported order (`pg_referral_referrer()['orders']`): "You can refer friends once you have a completed order."
- `ready`: the reward sentence (and the smallest order when one is set), the link in a read-only box with Copy (inline script: selects the box, then the clipboard API, else `execCommand('copy')`), and the share links.

## Which share links?
- `pg_referral_share_links()` (pure), built by the site with no third-party script, `target="_blank" rel="noopener nofollow"`: WhatsApp (`wa.me/?text=`), E-mail (`mailto:`), X (`twitter.com/intent/tweet`), Facebook (`facebook.com/sharer`). The words: "Take a look at this shop: your first order gets an offer." with a friend's offer set, else "Take a look at this shop."

## What does the referrals list show?
- `pg_referral_account_rows()`: the referral `commissions` rows of the customer's code, newest first, 50 at most, with the order's billing name. Shown for `ready`, `no_order` and `affiliate` alike once there is one; `ready` with none says "No referrals yet."
- `pg_referral_account_view()` (pure): the friend only as initials (`pg_referral_initials()`, "A. K.", else "A friend"), the row's date, `paid` Rewarded (green), any other state Taken back (grey), the reward. Above it "Rewards earned" (kept rewards) and "Store credit earned" (their amounts; a taken-back reward counts in neither).
- Money is minor units through `pg_money_text()` with the base currency, escaped once by the section.

## What does the designer draw?
- The section in the my account section list "Refer a friend (shop sites; hidden while the referral programme is off)"; its canvas sample `_sdAccountReferralHtml()` (a member who can refer, one reward kept and one taken back) with its note; the two flags; `__referral_url` under Account Pages (href).

## What was not checked?
- Built without a browser or database run (pictures stopped, `workflow/look-pictures-none.md`): the Copy button, the share links on a phone and the part on a live site.

## Tests?
- `tests/referral_test.php` (`test_referral_account_*`).
