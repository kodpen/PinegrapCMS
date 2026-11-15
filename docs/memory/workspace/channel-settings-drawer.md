---
name: channel-settings-drawer
description: The Channel settings drawer (channelForm(), #9616): four tabs, icon and colour popovers by the name, switches at the row end, one versions list; what stays in Channel info
---

## What did the owner ask?
- Owner (2026-11-15, in the architect's window, with a picture of the flows screen's Canvas / List switch): "Kanal ayarları tepesine şu tablardan koyarsan ve alanları geliştirirsen iyi olur".
- On the icon and colour: "bu alanlarıda modernize edelim. switchler sağda ayarlardaki gibi olabilir. renkler ve ikonlar genişlemiş olmasına gerek yok. Kanal adı solunda ikonu seçicisi ve altında veya aynı sırada renk seçicisi daha bütün olurdu. ikonlar ve renkler popover gibi gelebilir".
- On the versions: "bu alanda biraz daha mantıklı tasarlanabilir". On Channel info: "kanal bilgisi ve kanal ayarları 2 ayrı menu ama kanal bilgisi kısmında ayar sızmış".
- The look: owner's poll, channel 4098 (result 4103).

## Which tabs?
- The sheet's segmented control (`.pg-seg .pg-seg-icons`, `role="tablist"`) at the top; `channelSettingsTabs()` gives those that have parts, General always: General (name, icon, colour, topic, customer, department), People and agents (members, Add people, "Agents in this channel", `channel-agents-panel.md`), Conversation (versions, memory), Record events (the linked records' events switch).
- A pane is only hidden, so Save reads every tab and changing tab loses nothing typed. People and agents and Conversation act at once: there Save is hidden and Cancel reads Close. A new channel is the General pane alone.

## How are icon, colour and switches drawn?
- `channelLook()`: an icon button at the left of the name (the icon in the channel's colour) and a colour button at its right (a dot, the crossed ring for none). Each opens its list in a floating box (`floatBox()`): `iconPicker()` with its search, `colorPicker()` with "no colour". Neither stands open. Lists, who may choose and what is stored: `workspace-channel-icon.md`.
- Every switch sits at the end of its row, title and help line at the left, as in Site Settings (`watchField()` in `workspace_events.js`; the memory row likewise with its button).

## How are the versions listed?
- `eraRows()` (pure), `eraSection()`: the current conversation first (since when, how many messages) with Clear at its end, then each earlier version newest first with Rename and Read; one help line: clearing starts again, deletes nothing and keeps what was written as a version. `ws_channel_eras` sends the current `count`.

## What stays in Channel info?
- It tells: the purpose, people and agents with their state (their cards open there), the reader's own notifications, one way into Channel settings.
- Owner (2026-11-15), to a "My settings" tab for every member: "bildirim ayarları kanal bilgisinde kalsın o zaman aksi taktirde daha büyük karışıklık olur". The notification level and "Read assistant answers aloud to me" stay there; Channel settings stays a manager's.
- A manager's Add people, remove and Manage moved to the People and agents tab (`channelPeople()`); a member who may add people without managing keeps Add people in Channel info.
- Nothing stored, no right and no route changed. Tests: `tests/workspace_channel_settings_test.php`.
