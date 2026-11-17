---
name: connections-merges
description: Plan (owner 2026-11-17): an outside service set up in several places gets one place, each merge asked of the owner with its sheet before its step; steps MG1 to MG5
priority: 4
---

## What is asked?
- Some outside services are still set up in several places (`integrations-census-screens.md`). The Connections screen shows each one's place (`ui/connections-screen-rules.md`); moving a setting is a step of its own, asked of the owner one by one with its sheet (architect 2026-11-17, agent channel message 7524).
- Owner (2026-11-17, `workflow/decisions-made-product-69.md`): the integration plans at priority 4.

## Which steps are there?
- MG1 (M, the owner's answer first) GitHub's five places: the OAuth application on Connections, the "GitHub repositories" card, the GitHub MCP connector's personal access token, the "GitHub" application that appears on Application Access, the developer route (a key, repository secrets, a workflow file).

- MG2 (M, the owner's answer first) The AI keys' six places: the subscription key (Pinegrap AI), "Claude in the channels", a key for each assistant on "Workspace agents", "Voice message transcripts", "Translation Engines", the AI programs that come in on Application Access.

- MG3 (M, the owner's answer first) Mail's four places: SMTP Settings outside Site Settings, the Google or Microsoft application on Connections, Mailboxes, Communication for campaigns and Mailchimp.

- MG4 (M, the owner's answer first) A provider's client typed in one place: Google's is typed in Accounts and sign-in and reused by Connections, Microsoft's the other way round, Apple's in Accounts and sign-in.

- MG5 (S, the owner's answer first) One meaning each for "Connections", "Connected accounts" and "Connected applications" (the System tools group, an OAuth application's tab, a dashboard button, help texts, the sign-in list of "Sessions and connected accounts", a card and a screen).

## What waits for what?
- Each step waits only for the owner's answer to its sheet; none waits for another. Two are open together only when they change different files.

## How is each step made?
- First a sheet: the setting's one place and what leaves the others, through the architect to the owner as one question; his answer goes to the decisions list, then the step is claimed as `[plan:connections-merges] <step> — <topic>`.
- A stored setting keeps working on a running site. A card's address and old anchors stay: the step adds its `pg_settings_legacy_anchors()` rows (owner 2026-10-30, `workflow/decisions-made-product-14.md`). No schema change without asking.
- The registry row's `place` follows the move (`api/connections-registry-rows.md`); the Connections screen changes only through the registry.
- When the last step lands, its lasting rules go to `ui/connections-screen-rules.md` and this file is deleted.
