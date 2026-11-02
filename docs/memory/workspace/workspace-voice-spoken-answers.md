---
name: workspace-voice-spoken-answers
description: An assistant's answer to a recording is heard back (VA4, #5390): ws_voice_answer_spoken() on the three answer routes, the words read, the language, the browser fallback
---

## When is an answer spoken? (owner, 2026-11-02, #5390)
- An ask that was a recording is answered by voice; a text ask gets text. `ws_voice_answer_spoken($channel, $message_id, $parent_id, $text, $user_id)` (voice.php) runs after the answer message is written, on all three routes: Claude's answer route (`ws_api_claude_answer()`, api_claude.php), Pinegrap AI (`ws_ai_finish_answer()`, ai.php) and an MCP agent (`ws_mcp_agent_post()`, mcp_agent.php, with the neutral answer, not the "answered through" head). Channels and discussions alike; a run on a task has no message and is not spoken.
- It does nothing unless the message answered is a recording still there (`ws_voice_answer_to_recording()`: a `voice-…` file name, so a spoken message counts too, and not deleted) and the speech service is ready (`ws_voice_speech_ready()`, workspace-voice-speech.md). Then `ws_voice_speak()` and `ws_voice_speak_keep()` on the same message: the answer's text stays as it is, the sound is its file with a `done` `ws_voice` row. The service refusing leaves the text alone and the refusal on the settings card.
- The file's owner (`files.user`) is the person who asked, on every route.

## What does the voice read, and in which language?
- `ws_voice_answer_text()` (pure): fenced code blocks out, tags out (the mention of the asker, record tags), Markdown marks off (`**`, `__`, backticks, `*`/`_` emphasis, headings, bullets, numbered items, quotes), white space within a line folded, empty lines folded. '' means nothing is spoken.
- `ws_voice_answer_locale_rule($text, $site)` (pure): Turkish letters mean `tr` whatever the site speaks; else the site's language code (`lang(array('info' => true))`), `en` for a code that is no code. The row's `language` keeps the code.

## What does the browser do? (speakAnswer(), workspace.js)
- The switch "Read assistant answers to me aloud" still decides answers to the viewer's words. An answer to the viewer's own recording (`answersRecording(parent)`: an audio file named by `PGWsVoice.isVoice()`) is heard whatever the switch says: when the answer carries a sound (the site's speech service made one), its player is played (`audio.play()`, a browser that allows no sound without a gesture refuses quietly and the player stays); else the words are read by `speechSynthesis` as before. Never while the tab is hidden, never somebody else's answer, never on a scroll back.
- Nothing new is stored; no new text. `ws_message_touch()` in the keep makes the screen draw the answer with its sound.

## What was checked?
- tests/workspace_voice_answers_test.php: the words, the language rule, which message is a recording, the three call sites, and `speakAnswer()` under Node with stubs (read with the switch off, the sound played instead, the switch kept for words, a hidden tab). Not heard in a browser nor run against a speech service.
- Later: a line in the briefing telling the assistant that its answer will be spoken (keep it short and plain) was left out of VA4.
