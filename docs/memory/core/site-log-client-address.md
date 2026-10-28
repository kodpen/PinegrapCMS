---
name: site-log-client-address
description: Which address a site log row (log.log_ip) records: pg_auth_request_address(), the visitor behind a trusted proxy as the firewall resolves it, in both log_activity() copies and the e-mail-a-friend limit (#2044)
---

## Which address does the site log record?
- `log_activity()` (`includes/fn/forms.php`) and its copy in `get_file.php` write `pg_auth_request_address()` (`includes/authentication.php`) into `log.log_ip`: the address the session rows carry, which is the visitor the firewall resolves behind a trusted proxy (`waf_client_ip()`, Cloudflare and the operator's Trusted Proxies list) and the socket peer otherwise. Outside a request (the scheduled jobs on the command line) it is empty.
- Before (#2044) both copies wrote `REMOTE_ADDR`: behind cloudflared or Cloudflare every panel action was logged as the proxy (`IP ::1` on dev.pinegrap.com) while the sessions screen and the firewall log showed the real address, so the log could not say who acted from where.

## Why does e-mail-a-friend read the same helper?
- `email_a_friend.php` counts the rows of the last day by `log_ip` to apply its daily limit for visitors without edit rights: it compares against the same address the rows now carry, or the limit would never match behind a proxy.

## Does a job row print an "IP" label?
- No: `pg_dashboard_log_meta()` (`includes/dashboard/widgets.php`) prints "· IP <address>" only with an address, so a job's row never reads "… · IP".

Test: `tests/log_activity_ip_test.php` (no address on the command line, the peer without the firewall, both copies and the limit read the helper, no label without an address).
