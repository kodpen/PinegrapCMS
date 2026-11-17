---
name: workspace-command-notify-invite-leave
description: /notify (/bildirim), /invite (/davet) and /leave (/ayrıl) in a Workspace channel (CC6a, #10250): what each reads, the function it writes through, its gate, live line and answer
---

## What does /notify (/bildirim) do?
- `/bildirim anma`: the writer's notifications of this channel through `ws_channel_set_notify()`, as the channel menu's three choices set them. The answer, to the writer alone (`ephemeral`), says "Now set: Only when I am mentioned".
- Words (`ws_command_notify_word()`): hepsi, tümü, all, every → all; anma, anmalar, mentions, mention → mentions; hiç, hiçbiri, never, none → none (folded: hic, HİÇ). The popup offers `ws_command_suggestions()['notify']` (`ws_command_notify_values()`): hepsi, anma, hiç on a Turkish screen, all, mentions, never on any other, each with the menu's label (argument kind and source `notify`).
- No word: the choice in force and the three words with their labels (`ws_command_notify_html()`), each pair in a `text-nowrap` span so a narrow screen breaks only between pairs; to the writer alone, nothing is written. A word it does not read: "Write one of: …".
- Live line: "Will set your notifications here: Never", or that refusal.

## What does /invite (/davet) do?
- `/davet @ayse @mehmet`: the people tagged or typed as a team member's username (`ws_command_approval_read()`, as /approval reads them) go through `ws_channel_add_members()`, as "Add people" adds them: its line in the channel, the notice to each, the folder.
- "Add people"'s rule (in the `ws_channel_members_add` action) is kept by `ws_command_gate_invite()`: anybody in a public channel, only a member in any other; refused with "Access denied." when run anyway.
- The answer to the writer alone (`ws_command_invite_html()`, `ws_command_invite_split()`): "Added: …", "Already in the channel: …", "Could not be added: …" (somebody the function skips: not on the team, a non-staff person in a guest room, a discussion's outsider). Nobody named: "Name the people to add with @.".
- Live line: "Will add: …", a warning per person in already and per unknown username. Runs in a discussion too.

## What does /leave (/ayrıl) do?
- First a card to the writer alone (answer `leave_card`, `private` for a channel that is not public), drawn in `send()` of workspace.js: the menu's question ("Leave this channel? …" or the private one), `pg-button pg-button-danger pg-button-sm` "Leave the channel" and a ghost Cancel. Nothing is written before its button.
- The button sends the line again with `confirm`; then `ws_channel_leave()` with its refusals (a mailbox room). Answer `left`: the channel list again and an empty centre, as the menu item does.
- `ws_command_leave_step()` (pure) decides: not in the channel → "You are not in this channel."; no confirm → the card; confirm → leave. API class `preview` (only the card); `leave_card` is one of `ws_api_command_preview_types()`.
- Live line: "Will ask before you leave #name".

## Where are they offered?
- Group The channel (`ws_command_registry_channel()`, orders 950 to 970, before /help). /notify and /leave are `where` channel; their gate `ws_command_gate_member()` offers them to a member only, as the menu shows its notification choices and "Leave the channel" only when joined. A discussion is left with its own Leave (`ws_thread_leave`).

## Tests?
- tests/workspace_command_notify_invite_leave_test.php: names, gates by channel, the words in both languages, the answer without a word, /invite's split with somebody in already, /leave's steps and the card's confirm; the writing calls read from the source.
