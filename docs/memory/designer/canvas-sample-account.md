---
name: canvas-sample-account
description: The sample account (Alex Morgan) the designer canvas draws in every account and member widget, and the site words it reads (CS4b, #9280)
---

## What does the account family draw on the canvas?
- One member (`_sdAccountSample()` in `style_designer.src.js`) for widgets whose `regionType` is in `_SD_ACCOUNT_SAMPLE_REGIONS`: `my_account`, `login_form`, `forgot_password`, `registration`, `membership`, `logout`, `change_password`, `set_password`, `account_profile`, `email_preferences`, `address_book`, `account_security`, `account_invoices`, `account_email`, `login_region`, `error_page`, `affiliate_sign_up_form`.
- `_sdSampleRecord(node)` returns it after the order and the product; `_displayValueForBinding()` reads it after the HTML previews, so it beats the standard field previews and the short label map in those widgets only.
- Canvas only: never in a tree, never on the site or in an e-mail.

## Which values agree?
- The member is the sample order's billing person: Alex Morgan, user name `alex`, `alex@example.com`, initials AM, company Example Ltd. (`_sdOrderAddress('billing')`). The avatar (`_sdAvatarSample()`) draws AM too; it drew Jane Cooper's initials before.
- The address book's card is the order's shipping address (Sam Carter, Cooper Studio, Portland), so `__recipient_address_html` (an HTML preview) and the entity values tell one address; two saved recipients.
- Invoices: two documents; the card is INV-2026-0042 for the order's total, still open and 12 days overdue, so balance = overdue total = open = total (`_sdAccountAmounts()`).
- `tests/designer_canvas_account_sample_test.php` checks these and that no value is empty.

## Where do the site's own words come from?
- `sdDesign.siteWords` (`includes/designer_screen.php`, beside `siteFlags`): `site_name` (ORGANIZATION_NAME), `opt_in_label` (OPT_IN_LABEL), `member_id_label` (MEMBER_ID_LABEL), `password_rules` (the block `get_strong_password_requirements()` prints, wrapped as the widgets wrap it). Read by `_sdSiteWord(key)`; when empty the canvas draws Sample Store, the order's consent sentence, Member ID.
- `__password_rules` is an HTML preview (`_SD_HTML_PREVIEW_TOKENS`), drawn while the strong password setting is off too, so the block can be placed; the page leaves it empty then.

## Which value is drawn though the page leaves it empty?
- `__password_rules` (setting off) and `__passkey_notice` (written only when no passkey can be added), as `__out_of_stock_message` on the product.

## What is left?
- `canvas-sample-gaps-account.md`.
