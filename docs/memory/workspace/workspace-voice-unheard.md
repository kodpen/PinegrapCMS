---
name: workspace-voice-unheard
description: Why nobody heard a voice message (VA1): the recorder's hint while transcripts are off or the channel keeps its recordings, ws_voice_unheard_reason() in the assistants' briefings, Claude's instruction
---

## What does the recorder say? (VA1)
- `channel.voice_unheard` in `ws_channel_detail()`: `ws_voice_channel_unheard()` → '' when a recording made now is written out, `off` while transcripts are switched off on the site (or the schema step has not run), `skipped` where the channel keeps its recordings (`ws_voice_channel_allowed()` false: a private channel without the switch, a guest room, a channel shared with guests, a discussion of such). Pure rule `ws_voice_channel_unheard_rule($enabled, $allowed)`.
- `voiceUnheard()` (workspace.js) makes the hint only where an assistant could be asked (`channel.claude`, `channel.ai` or an MCP server): the words (`voice_unheard_off` / `voice_unheard_skipped`), for role 0 on `off` a link to Workspace Settings › Voice message transcripts (`CFG.urls.settings + '#ws-voice'`), for a manager of a switchable channel on `skipped` the menu entry's note. It goes to `PGWsVoice.start()` as `options.unheard`.
- `PGWsVoice.unheardHint(unheard, h)` draws it as `.ws-voice-rec-hint`, a full-width line of the recording row (the row wraps; a node outside the row is hidden while recording). The link opens in a new tab, so the recording goes on. VA2 reuses it for a browser without the model.

## What does the assistant read?
- `ws_voice_unheard_rule($status, $enabled, $allowed)`: '' for `done`; `off` whatever the row says (the site's switch is where it is put right); `failed`; `pending` for queued/running; `skipped` for the rest ('' never asked, `skipped`, or posted before transcripts were allowed).
- `ws_voice_unheard_reason($channel, $row, $state)` applies it to a message: '' for no recording or one written out; `ws_voice_state_of($row)` reads status, transcript and error of the row once.
- `ws_ai_heard_text()` passes the reason to `ws_voice_heard()`, whose note becomes `[voice message, not transcribed: <ws_voice_unheard_words()>]` (English, like the prompt). The MCP ask reads the same.
- After the request block, `ws_ai_briefing()` and `ws_mcp_ask_start()` add `ws_voice_unheard_line($reason)` outside the data blocks, as the site's own line: that it could not hear it, why, where the setting is (the settings card for `off`, the channel menu for `skipped`, record again or write for `failed`), and not to guess. Pinegrap AI takes it in its parts, Claude's brief in its text.
- Claude's routine instructions (`ws_claude_routine_prompt()`, step 2b) name the case; the bootstrap version is unchanged (the routine text is served by the site).
- The row's `error` is site-language text and stays out of the prompt.

## What was checked?
- tests/workspace_voice_unheard_test.php: the rule, the words and the line, the note with a reason, the channel rule, the wiring, and the hint under Node with stub elements. Not seen in a browser nor with a live assistant.
