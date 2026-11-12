---
name: workspace-channel-sound
description: The tone on the Workspace channel screen when something for the person arrives out of their sight: which inbox lines sound, the menu switch, why a hidden tab stays silent
---

## What does it do?
- Since 2026.11.0 (PR #8792): a short tone made in the browser (880 Hz then 660 Hz, the panel chat's `playTone()` way, no sound file) when a new inbox line for the person arrives and its conversation is not in front of them. The owner chose it (#8720, `workflow/decisions-made-workspace-14.md`).
- Several lines in one look sound once (`soundBatch()`). A page not clicked yet stays silent and shows no error: the audio is unlocked on the first pointerdown or keydown (the browser's rule).

## Which arrivals sound?
- The person's `ws_inbox` lines of kinds mention, agent_answer, question_asked, thread (a discussion started on their message), guest_message and mail_customer (the customer writing again on a mail conversation they hold): `SOUND_KINDS` in `assets/js/workspace.js`.
- Never a line whose actor is the person, other kinds (a task or mail conversation given, flows, notes, reminders), or channel traffic that writes no inbox line.
- In front of them (`soundFor()`): the line's channel is the open channel on its Messages tab (not an older era) or the discussion open beside it, with the tab visible and the window focused. Another channel, another tab of the channel, an unfocused window: it sounds.
- A direct message is the panel chat's (`pgChatOpenWith`), which has its own tone and switch (`pg_chat_sound`); the channel screen adds none.

## Where does it read from?
- No new request: the channel screen's 4 s look (`ws_sync`) sends `sound=1&sound_after=<mark>` only while the switch is on and only from the main screen, not a discussion beside it. Its answer's `sound` is `mark` and at most 50 `lines` from `ws_sound_lines()` (`includes/workspace/notify.php`): the person's unread lines newer than the mark (`idx_user`).
- The first look (`sound_after=-1`) gets only the mark, the newest unread line, so what waited before the page opened stays silent. A line read in the same look (the conversation in front, `ws_channel_mark_read()`) is not given.

## Why does a hidden tab stay silent?
- The screen asks nothing while the tab is hidden (`schedule()` stops on `document.hidden`; a look marks the person online, which holds push back). A line that came while hidden is not heard: the look on return (`soundQuiet`) only moves the mark, as the person is looking again and the marks show it.
- Chrome also reports a window fully covered by another app as hidden; only a window still partly visible keeps looking and sounds. A look while hidden would be a request only for the sound, which the plan ruled out: the question went to the architect with the PR.

## Where is the switch?
- The channel menu (⋯): "Play a sound when something comes for me", ticked when on, on by default. Kept per person and site in the browser: localStorage `ws-sound:<api url>:<user id>`, `off` when off, removed when on (`soundOn()`, `soundSet()`). Storage refused: on, and a choice lasts the page. Turning it off or on restarts the mark.
- The menu holds it as a reader's own choice in this browser, like Read answers aloud (`tests/workspace_channel_menu_test.php`).

## Which tests cover it?
- `tests/workspace_channel_sound_test.php`: which lines sound, once a look, the quiet first look and look after a return, the switch and its storage, the `ws_sync` wiring (node runs the functions taken from `workspace.js`).
