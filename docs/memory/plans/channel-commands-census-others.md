---
name: channel-commands-census-others
description: Census (2026-11-16) of slash commands in other team chats, from the vendors' pages: the usual built-ins, what an application's command can do, commands without code, records, pitfalls
---

## Where was it read?
- On the vendors' help and developer pages on 2026-11-16: Slack ("Slash commands in Slack", "Implementing slash commands"), Microsoft Teams ("Use commands in Microsoft Teams", "Agent slash commands"), Discord ("Application commands"), Mattermost ("Built-in slash commands", "Custom slash commands"), Rocket.Chat, Zulip, Google Chat ("Commands"), Telegram ("Bot features"). Discord's own built-in list could not be opened.

## Which built-in commands does nearly every team chat have?
- The channel: invite, leave, remove a member, mute, topic or description, rename, archive, create, join or go to. The person: a status, away or online, do not disturb. A reminder (Slack, Flock). A direct message. Search. Help and shortcuts. A call (Slack /huddle, Teams /meetnow, Mattermost /call). Helpers: shrug, /me, a GIF, fold and unfold previews.
- A poll and a to-do list are built in only in Zulip and Flock. None of them has built-in commands for tasks with dates and people, leave, meetings, decisions or a product's price: that part is this product's own.

## What can an application's command do there?
- Typed arguments with offered values: only Discord (typed options, subcommands, fixed choices or values offered while typing, names and descriptions in its languages, Turkish among them, and who may use a command set by role, person or channel). Slack, Mattermost, Teams, Google Chat and Telegram hand over free text.
- An answer only the writer sees: all of them (Slack `ephemeral`, Teams targeted messages, Discord, Mattermost, Rocket.Chat, Google Chat, Telegram since 2026). A form or dialog and buttons on the answer: Slack, Teams, Discord, Mattermost, Google Chat.
- The first answer is due within about three seconds in Slack and Discord, later ones through an address valid for minutes.
- Names: Slack lets two applications hold one name and the last installed wins; Mattermost refuses a duplicate and a built-in name; Telegram takes lower-case English letters only.

## How does a team make a command without code?
- Slack: a workflow built in Workflow Builder is listed by its name in the "/" menu beside the built-in commands. Teams: /workflows opens the Workflows app. Mattermost: an administrator fills in a form (a trigger word and the address it calls).
- Text shortcuts: Rocket.Chat's canned responses typed as "!" and a shortcut, Zulip's saved snippets, Telegram Business quick replies typed with "/".

## How do they reach business records from a chat?
- A lookup answered to the writer alone as a card, with a button to post it to the channel (HubSpot's /hs-search-contact and its kin, Jira's issue key).
- Buttons on the card that change one state (a deal's stage, a ticket's status); a dialog for a create (/jira create, /zendesk); a message's action "create from this message"; a channel subscribed to events with filters (/github subscribe).

## What goes wrong there?
- One word, two meanings: /status is presence in Slack, a status text elsewhere; /mute silences a person in Rocket.Chat and a conversation elsewhere. Here /status moves a task.
- A mistyped command posted as a message; a destructive command without a confirmation (Slack lets administrators restrict /archive, /remove, /rename); commands missing on phones or in threads (Slack's applications' commands do not run in threads; Teams' built-ins are desktop only).
- Microsoft's advice for agents: "Keep the command set small and focused". No vendor page describes counts of how often a command is used.
