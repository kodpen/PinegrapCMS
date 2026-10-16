---
name: mobile-api-shelved
description: SHELVED team-app API work: what M1–M6 built, what M7 (native push) and M8 would add, earlier decisions, restart point; docs never say "mobile app"
---

## What is the status?
- The mobile app is shelved (owner decision 2026-10-14). The endpoints built for it stay: they are general team-device capabilities. Docs never say "mobile app"; the twelve app and Claude-channel questions are not asked again unless the owner restarts the work. Restart point: this file and docs/memory/plans/claude-code-channel.md

## Which decisions were made before? (2026-09-27)
- Users are the team / panel users, not shop customers. Sign-in is a panel login + device token.
- No app-specific endpoints ("mobile home summary"); every client gets general capabilities: a person session, "who am I / what can I see", notifications + push registration, ETag / 304, `fields=`, consistent `updated_since`.
- Notifications: the existing web push + API endpoints.

## What exists for team devices?
- Device session ("Team devices" app): `/auth/login` (password, `otp`, login lock), `/auth/refresh`, `/auth/logout`, `/auth/me`, `/auth/devices`; token 1 h, refresh 30 days; devices close on password change and "sign out everywhere" (`includes/api/devices.php`). OAuth 2.1 with PKCE also gives device rows.
- Notifications `GET /notifications`, unread count, read / unread; web push carries no text.
- M1 #369: writes from a device session are the person's own (rate limit, auto-join); OAuth connections still write as the application. Follow-up #373: `@Claude` / `@ai` / MCP aliases work from devices.
- M2 #371, #375: edit, delete, hide, mark read, `changed_since`; own-words endpoints only from a Team devices session.
- M3 #374, #376, #380, #385: `GET /workspace/sync` (one call, ETag, marks the person as on screen), notifications count, channel state, pins.
- M4 #377, #379, #383: `GET /workspace/inbox`, read marks, deletions (schema 10.88).
- M5 #378, #382: upload to a channel, download by token with `?size=` previews and `Range`.
- M6 #381, #386, #387: channel notify level, polls, thread join / leave, personal pin / order.

## What would M7 and M8 add?
- M7 native push (needs answers 1, 3, 4): `POST /push/devices` {provider fcm | apns, token, bundle_id}, delete; the push queue sends one signed request to a Kodpen push broker (`push.pinegrap.com`) with token, provider, badge, no text; the app fetches `GET /notifications?unread=true` itself. New table (`user_id`, `device_id`, `provider`, `token`, `bundle_id`, timestamps, last error), a free label of the open version.
- M8 when a real client needs it: batch requests, tombstone list, `sort=`, CORS allow-list for a web shell.

## Which questions were open? (recommendation)
1. Which app: (a) one Kodpen store app for every site, (b) better PWA, (c) branded per customer → a.
2. Sign-in: (a) OAuth in the browser (2FA and Google / Microsoft / Apple for free), (b) `/auth/login` → a first, b fallback.
3. Native push: (a) Kodpen broker, (b) each site's own Firebase / APNs keys, (c) web push only → a.
4. Broker open to: (a) licensed sites, (b) everyone → a.
- Questions 5–12 concern Claude in channels: docs/memory/plans/claude-code-channel.md

## What must a restart check?
- Apple's current rule on offering an extra sign-in option when external sign-in is used; prefer verified `https` links (Universal / App Links) over a custom scheme; tokens in Keychain / Keystore.
