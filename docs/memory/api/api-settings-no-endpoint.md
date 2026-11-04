---
name: api-settings-no-endpoint
description: Why settings, firewall, sessions, backups and updates have no external API endpoint (#5238), and the two doors that stay open
---

## Why do the settings screens get no endpoint? (#5238)
- Settings, firewall, sessions, backups and updates stay panel-only. What an integration needs from the settings is already read: `GET /meta` (currency, tax, timezone, the modules switched on, firewall mode, security headers, jobs, upload limits), `GET /system/status` for health, `GET /site-log` and `GET /audit-log` for who did what.
- Every write on those screens hangs on the screen's own guards (sealed secrets, the self-ban guard, files on the server), and a leaked key holding such a scope opens the whole site; a firewall write over the API can lock the owner out. A read-only settings tree would need a per-field secret check across sixteen screens, and no client has asked for it.

## What stays open?
- A single setting a client must read is added as a field of `/meta` under `meta:read` as free work.
- A real need for a settings group read or written (the fields named, the guard that holds) is a new architect issue, not a step claimed from a plan.
