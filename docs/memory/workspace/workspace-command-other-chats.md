---
name: workspace-command-other-chats
description: What other team chats do with / commands (read 2026-11-16 for plan channel-commands): their built-ins, application commands, no-code commands, record lookups, and what goes wrong there
---

## Where was it read?
- The vendors' help and developer pages on 2026-11-16: Slack, Microsoft Teams, Discord, Mattermost, Rocket.Chat, Zulip, Google Chat, Telegram. Discord's own built-in list could not be opened.

## Which built-ins does nearly every chat have?
- The channel: invite, leave, remove a member, mute, topic, rename, archive, create, join. The person: a status, away, do not disturb. A reminder (Slack, Flock), a direct message, search, help, a call (/huddle, /meetnow, /call), helpers (shrug, /me, a GIF).
- A poll and a to-do list only in Zulip and Flock. None has commands for tasks with dates and people, leave, meetings, decisions or a product's price: that part is Pinegrap's own.

## What can an application's command do there?
- Typed arguments with offered values: only Discord (typed options, choices, names in its languages, who may use it by role, person or channel). The others hand over free text.
- An answer only the writer sees: all of them (Slack `ephemeral`). A form and buttons on the answer: Slack, Teams, Discord, Mattermost, Google Chat. The first answer is due in about three seconds in Slack and Discord.
- Names: Slack lets two applications hold one name, the last installed wins; Mattermost refuses a duplicate and a built-in name; Telegram takes lower-case English letters only.

## How does a team make a command without code?
- Slack lists a Workflow Builder workflow by name in the "/" menu; Teams' /workflows opens its app; Mattermost: an administrator's form (a trigger word and an address). Text shortcuts: Rocket.Chat canned responses ("!"), Zulip snippets, Telegram quick replies.

## How do they reach business records?
- A lookup answered to the writer as a card with a button to post it (HubSpot, Jira's issue key); buttons on the card changing one state; a dialog for a create (/jira create); "create from this message"; a channel subscribed to events (/github subscribe).

## What goes wrong there?
- One word, two meanings: /status is presence in Slack (here it moves a task); /mute silences a person in Rocket.Chat and a conversation elsewhere.
- A mistyped command posted as a message; a destructive command without a confirmation; commands missing on phones or in threads. Microsoft: "Keep the command set small and focused". No vendor counts command use.
