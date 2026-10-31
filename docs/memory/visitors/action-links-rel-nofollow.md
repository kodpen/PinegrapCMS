---
name: action-links-rel-nofollow
description: Every action link (login, register, reset, subscribe, remove from cart) must carry rel="nofollow"; crawl-budget reason and server-stamped rel.
---

## Which links must carry `rel="nofollow"`?
- Every link that is an ACTION: login, register, password reset, subscription, remove from cart. It is a job to do, not a document to index.
- Any visitor link carrying `send_to` (or a similar return address) produces a DIFFERENT URL per page; crawlers take them for separate pages and walk all of them, each crawler separately.
- Measured: the comment login link had no `rel`; the login page became the most requested page on the whole site with 1.7 million views while real search traffic was 100-200/day. Crawl budget went to the login form instead of posts, the visitor counter became meaningless, and `visitors` and `submitted_form_views` bloated.

## Why can the WAF not solve this?
- It must not: Googlebot is a good bot and exempt from the rate limit, rightly. The problem was not that we failed to block it but that we invited it.

## Who stamps `rel` in system widgets?
- The SERVER stamps `rel` (e.g. the `remove_from_cart` action binding); it is not left to the designer or the templates. Whether a crawler may follow a "remove this product" link is not a design choice.
- One stamp, `pg_sw_stamp_nofollow($tree, $tokens)` (`includes/fn/widgets.php`): every node whose `href` binding is in `$tokens` gets `props.rel` = its own words + an Attributes panel `rel` + `nofollow`, each once (`pg_sw_rel_with_nofollow()`). The comments and wishlist widgets call it for `__login_url`.
- `_pg_member_drop_empty_links()`, which every member widget (login region, login, registration, set/forgot/change password, account pages) runs over its links, stamps each kept link bound to `pg_sw_action_link_tokens()`: `__login_url`, `__register_url`, `__forgot_password_url`, `__logout_url`, `__google_signin_url`, `__google_signup_url`. The login widget's section-drawn forgot and sign-up links print `rel="nofollow"` themselves (#4036: the header's Log In / Sign Up links carried a per-page `send_to` without it; #4190).

## What else keeps crawlers off those addresses?
- Turning on `page.noindex` for the login page also writes a `Disallow` to `robots.txt`, so crawlers never request the address. Trade-off documented in docs/memory/visitors/page-noindex-switch.md ("Known trade-off").

Related: docs/memory/visitors/robots-txt-disallow-rules.md
