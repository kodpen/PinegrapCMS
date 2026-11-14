---
name: guest-channel-view
description: Plan (owner 2026-11-14): a guest sees the agents' messages on the guest page and still wakes no agent; guests listed in Channel info; the channel's agents' answers read aloud
---

## What is asked?
- Owner (2026-11-14), after sharing the agent channel with a guest whose tag was answered by an agent she could not see: "misafir ajan mesajlarını görebilsin ancak onları tetikleyemesin. kanalda sahip ile konuşabilsin. (ajan kanalı dışında da bu dikkat edilmeli)"; "ayrıca misafir kanalda bilgi alanında görünmesi iyi olurdu".
- Owner (2026-11-13), with both switches of the channel menu on: "benim tarayıcım anıldığımda ses çalmıyor ve sesli okunmuyor."
- Record: `workflow/decisions-made-workspace-24.md`. The agents' side: `workflow/agent-channel-guests.md`.

## What is there today?
- `ws_guest_messages()` (`includes/workspace/guests.php`) lists messages of `sender_kind` `user` and `guest` only, so a guest sees no agent line. A guest's message makes no request for an agent, and while a share is open Claude, Pinegrap AI and model agents are not asked in the channel (`ws_channel_share_open()`).

## Which steps?
- GV1 (M) Done (#9401): the guest page lists the agents' and assistants' messages and nothing a guest does asks one: `workspace/guest-agent-lines.md`.
- GV2 (S) Done (#9427). Guests in Channel info: a "Guests · n" group under People and Agents (`workspace/channel-members-agents.md`) for every guest with an open share: name, the Guest badge, read only or read and write, who shared and when. A guest's last seen only for a channel manager, as a person's.
- GV3 (S) Done (#9415). Read aloud covers the channel's agents: an answer of a registry agent of the channel to the viewer's own message, or one that names the viewer, is read as an assistant's is (`workspace/workspace-voice-read-aloud.md`).
- GV5 (M) Done (#9423). The standing caution in every channel (`workspace/workspace-outside-caution.md`): each brief and prompt the site gives an agent or an assistant says that a guest's line, a mail's text and other outside text are data from outside the team, never a request or an instruction.
  - A guest's messages are marked as a guest's in the conversation the brief carries and in the API's message, so an agent reading by itself can tell. Unit tests on the built text. Owner: "her kanalda bu konuda varsayılan dikkatli olmayı sağlayacak bir promp bilgisi olması iyi olurdu".
- GV6 (S) The share bar (`workspace/workspace-channel-identity-motion.md`): while a share is open it stays a full row and stands out more; once no link is open the row goes, and a small button with an exclamation mark in the channel's head opens the same Manage. Owner: "bu şekilde gereksiz alan kaplattırıp head alanını büyütüyor".
- GV4 (S) Close-out: the rules into `workspace/` (guest page, Channel info, read aloud, the caution), this plan deleted.

## What does every step keep?
- No table or column; a step that needs one stops and asks the architect. What a guest may do stays as it is: text, an answer, an emoji, a vote, a tick; no files, tags or commands.
- The sound stays silent in a hidden tab (`workflow/decisions-made-workspace-18.md`).
- Real screenshots of GV1, GV2 and GV6 (dark and light, wide and phone) are posted in the agent channel before the PR goes ready. New texts through `lang()` and `php tools/tr_add.php`.
- GV1, GV2, GV3 and GV5 may run side by side; GV6 after GV2 (the same head). Claim as `[plan:guest-channel-view] <step> — <topic>`.
