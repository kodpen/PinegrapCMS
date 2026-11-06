---
name: affiliate-welcome-mail-link
description: The affiliate welcome mail's link is built on the configured address (pg_affiliate_link()), never the request's Host header; what it fixed
module: contacts
---

## Where is the affiliate welcome content shown?
- Only in a mail: `get_affiliate_welcome_screen()` (`includes/fn/forms.php`) is called by `affiliate_sign_up_form.php` when automatic approval is on and by `pg_affiliate_approve()` (panel approve link and the API). The `affiliate welcome` page type renders the same content (`get_affiliate_welcome_screen_content()`) inside the mailed page.
- The code comes from `$_SESSION['software']['affiliate_welcome']['affiliate_code']`, which both callers set just before the mail.

## How is the link built?
- `pg_affiliate_link(URL_SCHEME, HOSTNAME_SETTING, PATH, $code)` in `includes/fn/contacts.php`: the configured address plus `?a=` and the URL-encoded code, the same address the My Account page (`get_my_account.php`) shows.

## What symptom did it fix?
- The link was `URL_SCHEME . $_SERVER['HTTP_HOST'] . '/?a=…'`: an anonymous sign-up with a forged Host header got the shop to mail a link to another host (a sign-up can name any address), an approval through the API or another panel host mailed that host, and a site in a folder lost its path, so the link opened the domain's root instead of the site.
