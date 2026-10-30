---
name: cloudflare-dns-dialog
description: Cloudflare Tools Add/Edit DNS Record dialogs (#3628): hidden type groups are disabled so only the visible priority box is posted, MX priority is carried into Edit, Content is required where shown
---

## Why are hidden fields disabled in the DNS dialogs?
- The MX box (`add_priority` / `edit_priority`) and the SRV box (`add_priority2` / `edit_priority2`) are both named `priority`. A field hidden with `d-none` is still posted and PHP keeps the last one, the empty SRV box, so an MX record reached Cloudflare with priority 0.
- `toggleExtraFields()` in `cloudflare.php` now shows/hides each `.type-extra`, `.common-content` and `.proxied-only` group and sets `disabled` on its fields to match. Keep both ids: `tests/cloudflare_names_test.php` pins them.

## Why did Edit reset an MX priority?
- The row's Edit button only carried SRV and CAA `data` keys. Cloudflare returns an MX priority at the top level of the record, so the MX row now gets `data-priority` from `$r['priority']` (SRV falls back to it when `data.priority` is missing), and the Edit show handler fills `edit_priority` before the toggle runs.

## When is Content required?
- Whenever it is shown: A, AAAA, CNAME, TXT, MX and NS. SRV and CAA hide it, and the toggle clears `required` with it, so the browser stops a blank value instead of Cloudflare refusing it in English after the redirect.
- Number boxes carry `min`/`max`: 0–65535 for priority, weight and port, 0–255 for CAA flags.

Pinned by `tests/cloudflare_dns_dialog_test.php`.

Related: docs/memory/ui/panel-accessible-names.md
