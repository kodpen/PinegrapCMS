---
name: mobile-api-shelved
description: Team-device API work for the closed-source Pinegrap Mobile app (restarted 2026-10-24 outside this repo): what exists (M1–M6), what M7 (native push) and M8 add, decisions, how its needs arrive
---

## What is the status?
- Owner (2026-11-15): the issues are opened on his word and are given out as work; M7 is moved forward (`workflow/decisions-made-product-53.md`). Given out: #9605, #9606, M7 as #9607. M8 waits for a real client.
- Restarted (owner, 2026-10-24; shelved from 2026-10-14): Kodpen develops Pinegrap Mobile, a closed-source app that is not part of this open-source repository, in the private repository `kodpen/PinegrapMobile`. Its plans, questions and store work live there.
- The endpoints built for it stay general team-device capabilities: no app-specific endpoints, and route descriptions and user-facing texts name a team device, not the app.
- What the app needs from this repository arrives as an issue (`[iş] <topic>`, or `[plan:mobile-api-shelved.md] M7 — <topic>` for native push) and is done as an ordinary task here. The file keeps its name because those issues refer to it. The Claude Code channel is a door of docs/memory/plans/agent-channel.md (step A8).

## Which decisions were made before? (2026-09-27)
- Users are the team / panel users, not shop customers. Sign-in is a panel login + device token.
- No app-specific endpoints ("mobile home summary"); every client gets general capabilities: a person session, "who am I / what can I see", notifications + push registration, ETag / 304, `fields=`, consistent `updated_since`.
- Notifications: the existing web push + API endpoints.

## What exists for team devices?
- Done: M1-M6 (#369 to #387): device sessions, notifications and the rest, in `api/api-device-sessions.md`, `workspace/workspace-api-callers.md`, `workspace/workspace-api-sync-inbox.md`.

## What would M7 and M8 add?
- M7 native push, the shape of #9607: the device gives its token to the site, the site trades it at the Kodpen broker (`push.pinegrap.com`) for an https endpoint kept as a `push_subscriptions` row. Sites hold no device token; the sender, clean-up and revoke are reused.
- M7a (M, schema; #9703, done) `push_subscriptions.provider` (VARCHAR(8), empty a browser, `fcm`, `apns`); `POST /push/devices` and `/push/devices/delete` (`account:write`, team devices, `X-Dry-Run`); the broker call in one function, signed with the site's VAPID key, with the licence key when a valid one is set; the `GET /meta` feature key. The broker's address is a constant, empty until the broker is live: 503. As built: `api/api-native-push.md`.
- M7b (S, after M7a) A broker row gets the body `{"v":1,"badge":n}`, no text; 404/410 delete the row, 429 honours `Retry-After`; a revoke tells the broker to forget.
- M8 when a real client needs it: batch requests, tombstone list, `sort=`, CORS allow-list for a web shell.

## How were the open questions answered? (owner, 2026-10-24)
1. Which app: one Kodpen store app for every site (not a PWA, not branded per customer).
2. Sign-in: a Team devices session (`/auth/login`) and QR pairing (#1640; its `api_device_pairings` table approved in #2470, `workflow/decisions-made-schema.md`), no OAuth in the browser.
3. Native push: a Kodpen broker; the push shape (M7) is handed to this repository as an issue.
4. Broker access for free and licensed sites: quota sizes and who may use it are the broker's own settings, decided when it is built. The site step does not wait (architect, 2026-11-15): it sends what it has and shows the broker's refusal.
- Questions 5–12 concerned Claude in channels: answered by docs/memory/plans/agent-channel.md (the Claude Code door, step A8)

## What must the team-device work still check?
- Prefer verified `https` links (Universal / App Links) over a custom scheme; tokens in Keychain / Keystore.
