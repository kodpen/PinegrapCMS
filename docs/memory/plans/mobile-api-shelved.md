---
name: mobile-api-shelved
description: Team-device API work for the closed-source Pinegrap Mobile app (restarted 2026-10-24 outside this repo): what exists (M1–M6), what M7 (native push) and M8 add, decisions, how its needs arrive
---

## What is the status?
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
- M7 native push (needs answers 1, 3, 4): `POST /push/devices` {provider fcm | apns, token, bundle_id}, delete; the push queue sends one signed request to a Kodpen push broker (`push.pinegrap.com`) with token, provider, badge, no text; the app fetches `GET /notifications?unread=true` itself. New table (`user_id`, `device_id`, `provider`, `token`, `bundle_id`, timestamps, last error), a free label of the open version.
- M8 when a real client needs it: batch requests, tombstone list, `sort=`, CORS allow-list for a web shell.

## How were the open questions answered? (owner, 2026-10-24)
1. Which app: one Kodpen store app for every site (not a PWA, not branded per customer).
2. Sign-in: a Team devices session (`/auth/login`) and QR pairing (#1640; its `api_device_pairings` table approved in #2470, `workflow/decisions-made-schema.md`), no OAuth in the browser.
3. Native push: a Kodpen broker; the push shape (M7) is handed to this repository as an issue.
4. Broker access for free and licensed sites: still open in the app repository.
- Questions 5–12 concerned Claude in channels: answered by docs/memory/plans/agent-channel.md (the Claude Code door, step A8)

## What must the team-device work still check?
- Prefer verified `https` links (Universal / App Links) over a custom scheme; tokens in Keychain / Keystore.
