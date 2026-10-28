---
name: hostname-in-mail-links
description: HOSTNAME (request Host header) for redirects back to the visitor, HOSTNAME_SETTING for links that leave the request (e-mails, documents); the mails that used the request host
---

## What is the difference between HOSTNAME and HOSTNAME_SETTING?
- `HOSTNAME` (`init.php`) is the Host header of the request, verbatim; on the command line it falls back to the setting. The client chooses it, and on a default or catch-all virtual host any value reaches the site.
- `HOSTNAME_SETTING` is the `config.hostname` setting, the address the operator gave the site (taken from the Host header once, at installation).

## Which one does a link use?
- Back to the visitor in the same request (a `Location` header, a `go()` target, a link printed on the page they are looking at): `HOSTNAME`, so the visitor stays on the host their session cookie belongs to. The OAuth sign-in return address (`pg_signin_redirect_uri()`) is in this group on purpose.
- Anything that leaves the request — a link in an e-mail, a stored document, a feed, a canonical URL: `HOSTNAME_SETTING`, never `HOSTNAME` or `$_SERVER['HTTP_HOST']`. Otherwise whoever sends the request decides where the recipient is sent.
- The ERP sales invoice's `web_address` (`includes/erp/order_bridge.php` `erp_order_internet_sale()`) is in this group: with `ERP_WEB_ADDRESS` empty it falls back to `URL_SCHEME . HOSTNAME_SETTING`. It took the checkout request's Host header and stored it on the invoice before PR #2748; the same test scans the file.
- The Workspace's scheduled actions are in this group (`includes/workspace/scheduled.php`): the address a new web check starts from is stored and run later by the job, and a webhook's payload names the site to another service. Both took the operator's request host (an IP address or an alias) before PR #701.

## Which mails used the request host, and what was the symptom?
- An affiliate sign-up by any signed-in member (`affiliate_sign_up_form.php`) and a comment, anonymous unless the page requires sign-in (`add_comment.php`: attachment, edit and view links), mailed the administrators links on whatever host the request named, so a forged Host header turned the "approve" or "edit comment" link into a phishing link sent by the site's own mail. A manager's password reset (`reset_password.php`) and user import (`import_users.php`) mailed the login link on the manager's request host (an alias or IP address instead of the site address). All now use `HOSTNAME_SETTING`, as `add_user.php`, `forgot_password.php`, `email_a_friend.php` and `includes/fn/mail.php` already did. Fixed in #558; `tests/mail_link_hostname_test.php` scans those files (and `includes/fn/widgets_comments.php`, whose comment e-mails link on `HOSTNAME_SETTING`) for a request host after `URL_SCHEME` outside a `Location` header.

## What still uses the request host outside a redirect?
- The "retrieve order" link (`get_shopping_cart.php`, `get_express_order.php`) is printed on the visitor's own screen: fine.
- The registration and membership staff notices (`includes/fn/auth.php` `pg_member_register()` / `pg_member_activate()`) draw `get_registration_confirmation_screen_content.php` / `get_membership_confirmation_screen_content.php`, whose "Continue" link is on `HOSTNAME`: a forged Host header on the anonymous sign-up post points the staff e-mail's link anywhere. Open; to be fixed like #558.
