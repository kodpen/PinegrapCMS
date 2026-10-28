---
name: workspace-voice-read-aloud
description: Assistants' answers read aloud (V5): the per-person browser switch in the channel menu, off by default, no schema; which answers are read, what is left out, when it stops
---

## What is read aloud? (V5, #2861)
- An answer of Claude, Pinegrap AI or an MCP agent (`sender_kind` `app` with `sender.claude`, `.ai` or `.mcp`) that answers one of the viewer's own messages (`parent_id` to a message with `mine`), arrived by `sync()` while the conversation is open and the tab is in sight (`speakAnswer()` in workspace.js). Never on the first load or a scroll back, never someone else's answer.
- The words are the drawn `.ws-msg-body` without code blocks, buttons, audio, change cards (`.ws-drafts`) and hidden text; white space folded, cut at a sentence end before 1500 characters (`speakText()`), read in `CFG.locale` by the browser's `speechSynthesis` (`PGWsVoice.speak()`). Several answers queue.

## Where is the switch and why no schema?
- Channel menu "Read assistant answers to me aloud" (✓ when on), only where `speechSynthesis` exists and an assistant is set up (`BOOT.claude`, `.ai` or `.mcp`). It is the viewer's choice, not the channel's: localStorage `ws-voice-speak:<api_url>:<viewer>` = `on`, removed when off, like the voice speed and the "Aa" box (workspace-composer-expanded.md). Off by default; each person on a shared browser keeps their own. Storage refused: the choice lasts the page.
- Chosen this way so V5 needs no column and no server text: speech is made in the browser, nothing leaves it, and a person on another browser hears nothing until they switch it on there.

## When does it stop?
- Switching it off, opening another channel (`open()`), and playing any sound (the player's `play` calls `speakStop()`); a voice player that sounds is paused when reading starts.

## How was it checked?
- `tests/workspace_voice_test.php`: the switch, key, text cut and stop under Node with a stub `speechSynthesis`; the wiring by source. Not heard in a real browser (voices differ per system) nor on a running site.
