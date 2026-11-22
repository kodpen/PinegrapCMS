---
name: security-settings-tidy-screens
description: Plan security-settings-tidy, its first screen: the Firewall and HTTPS pane as folded rows, HTTPS first, Limits, Addresses with the bans drawer, Logs; the words and what must be true
---

## By which rules?
- `ui/screen-composition-rules.md`; parts copied from the Design examples screen, its section in brackets (`workflow/screen-described-in-plan.md`). "Value rows" comes with DX2b (`plans/design-examples-patterns-mail.md`) before SE4; the rest is there today. No part is new: nothing to ask the architect for a drawing.

## How is the pane laid out?
- Head: "Firewall and HTTPS" and one purpose line [Type and text]: how pages are sent, what reaches the site and who is refused.
- Folded rows [Settings pane], one open, each line saying its state ("On, Monitor", "2 addresses banned now"), in this order: "HTTPS", "Firewall", "Limits", "Bots", "Addresses", "Countries", "Trusted proxies", "Logs".
- HTTPS: "Secure Mode" [Fields, switch] with its warning and the trusted-proxy offer; "Send security headers" and under it, always drawn, "Refuse framing by other sites", "Always HTTPS (HSTS)", "Content security policy" [Segmented control and status] Off, Report only, Enforce, the policy box, "Policy reports" quiet.
- Firewall: the switch, Mode [Segmented control and status] Monitor, Block with its line, Sensitivity, the signature scan, attack tools and crawler check switches, the AI crawlers' state line with "Change under AI Crawlers", Excluded paths.
- Limits: "Rate limiting" with its four boxes, "Automatic temporary bans" with its three, all drawn while off; from SE4 one inner card [Value rows]: "Sign-in attempts" and "Form submissions", today's values and Change opening their Accounts rows.
- Bots: "Block unknown bots", Allowed bots, Blocked bots; one line saying whether AI training crawlers pass, by SEO's "Allow AI training" (S8, step SE1b).
- Addresses: Allowed IP addresses, Banned IP addresses, then a state line ("No address is banned by the firewall now", "2 addresses banned now") with "See the bans" quiet opening a drawer [Drawer] of today's table and its Release.
- Countries: Restriction [Segmented control and status] Off, Refuse the listed, Let in only the listed; Countries; the exemptions as switches; the "you are in" line.
- Trusted proxies: the list and its line. Logs: Log retention, Log size limit, "Open the firewall log" quiet; "Plain-text block log" and, under a `details` "Log file and fail2ban", the files.
- Under 576 px: rows full width, a segmented control wraps under its label, the bans drawer full width. Foot: Save the fill, one only.

## Which words?
- "HTTPS", "Secure Mode", "Limits", "Addresses", "See the bans", "Logs", "Open the firewall log", "Policy reports"; a row's or switch's name never starts with "Enable"; sentence case.

## What must be true?
- Every field posts under today's name; a Save stores what it stored; the bans' Release still posts to `view_waf_log.php`.
- `#settings/firewall/pgset-waf`, `…/pgset-proxies`, `…/pgset-bots`, `…/pgset-iplists`, `…/pgset-countries`, `…/pgset-headers` open their rows; `waf_log_retention_days` opens "Logs".
- With the firewall off, Rate limiting off or headers off, their fields are drawn; no popover is left, nothing more than one fold deep.
- Secure Mode is the first field of the pane.
- The settings search finds "rate limit", "ban", "fail2ban" and "HSTS".

Related: docs/memory/plans/security-settings-tidy.md, docs/memory/plans/security-settings-tidy-steps.md, docs/memory/plans/security-settings-tidy-screens-accounts.md
