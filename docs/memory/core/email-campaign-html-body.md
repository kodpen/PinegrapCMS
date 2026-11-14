---
name: email-campaign-html-body
description: How an HTML campaign body is drawn from a page: an e-mail render with no session token and signed-out account widgets, the token cut as a guard, and no hard wrap of mail bodies
---

## How is an HTML campaign's body drawn?
- `pg_email_campaign_page_body($page_id)` (`includes/fn/email_campaign_create.php`), called by `add_email_campaign.php` and Edit Campaign's "Take the design again": `get_page_content(..., 'preview', $email = true)`, then `pg_email_campaign_without_token()` cuts every copy of the sender's session token (a long random hex string) from the HTML.
- The page is drawn inside the sender's panel request, but the e-mail goes to the recipients. Before, the stored body held the admin's address book rows, `remove_recipient.php?...&token=<admin token>` links and `var software_token = "<token>"`, and every recipient got them.

- Then `pg_email_campaign_prepare_html_body($body, $tracking_name, $with_footer)` (since MA5b #9426): base and absolute addresses, the `t=` tracking code on links to this site, the fixed footer before `</body>`. Pasted HTML goes through it too.

## What does an e-mail render leave out?
- `get_page_content()` writes `software_token` blank when `$email` is true (every e-mail render: campaigns, comment and form mails, campaign profiles).
- `_pg_acct_signed_in()` (`includes/fn/widgets_account.php`) answers false while `pg_sw_render_context()['email']` is set, so the account widgets (my account, address book, profile, and the comments audience) draw their signed-out state, as the wishlist widget already did (`pg_wishlist_widget_background()`).
- Widgets that read `USER_ID` or `$_SESSION` without these helpers are not covered; a new widget with personal data checks the render context's `email`.

## Why are mail bodies no longer wrapped?
- `wordwrap($body, 900, "\n", true)` cut every run over 900 bytes: inline scripts broke (SyntaxError in the edit screen's preview), words, URLs and JSON were split, and a multibyte character could be cut.
- `email()` sends through PHPMailer, which switches a part to quoted-printable when a line is over 998 characters (`hasLineLongerThanMax()`), so the wrap was never needed. Removed in `pg_email_campaign_insert()`, the automatic campaigns (`includes/fn/mail.php`), the form mail HTML (`includes/fn/forms.php`) and the order campaigns (`includes/fn/order_completion.php`). Campaigns stored before keep their wrapped body.

## What else does create check?
- A page id with no page row is refused with "Please select a page for the body of the campaign." (it stored a ready campaign with an empty body and a PHP warning).
- `from_name`, `body` (and on edit `send_to`) go through `pg_email_campaign_posted_text()` (`includes/forms/email_campaign_form.php`): an array posted is `''` (it was stored as 'Array' or ended in a fatal error); the sender name is trimmed.
- Pinned by `tests/email_campaign_panel_fixes_test.php`.

Related: docs/memory/ui/email-campaign-shared-form.md, docs/memory/core/email-campaign-send.md
