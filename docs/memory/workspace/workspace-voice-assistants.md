---
name: workspace-voice-assistants
description: Voice transcripts the assistants, recaps, bells, banners and search read (V4a #2798, V4b): ws_voice_transcripts(), ws_voice_with_transcript(), ws_voice_excerpt(), ws_voice_search_or()
---

## How does a reader get a recording's transcript? (V4a)
- `ws_voice_transcripts($rows)` (voice.php): one query, file id => transcript, `done` rows only, deleted messages skipped; no file in the rows asks nothing. `ws_voice_transcript_of($row)` for one message.
- The reader was already allowed the message: a transcript is read as its message is, no further rule.
- `ws_voice_transcript_clean()`: one line, tag-shaped text (`<@user:4>`, `<#order:12>`) taken out (a voice chose no tag, the service may write anything), cut by characters.
- `ws_voice_with_transcript($text, $transcript, $limit)`: the person's words, then `[voice message, machine transcript: …]`, so machine text never passes as theirs (owner, #2417); no transcript leaves the words as they were.

## Which readers carry it?
- Pinegrap AI: `ws_ai_channel_context()` (the briefing's channel block and `read_channel`), and `ws_ai_heard_text()` for the request, the message it answers and a discussion's message in `ws_ai_start()`; the MCP ask (mcp_ask.php) the same. The request's language is guessed from words and transcript together.
- Claude's API: `message.transcript` and `message.reply_to_transcript` on `WorkspaceClaudeRequest` ('' for none; a note, task or editor request has none); `transcript` (string or null) on each message of `ws_api_channel_context()` and on `WorkspaceContextMessage` (a discussion's message). Kept apart from `text`, which stays the person's words.
- Recaps: `ws_recap_transcript()` writes the transcript in place of `[file: name]` (400 characters).

## How do bells and device banners show a voice message? (V4b)
- `ws_voice_excerpt()` in `ws_inbox_describe()` (mention, read-receipt reminder): the words, then "Voice message: <transcript>" once written out; "Voice message" before that, so a recording without words is never an empty banner (`ws_voice_excerpt_text()`, cut at 140).
- The banner is described when it is sent, after its wait (`ws_push_pending()`, `ws_bell_describe()`), so a transcript written out meanwhile is in it.

## How does search find it? (V4b)
- `ws_voice_search_or($like)` joins `OR file_id IN (SELECT … ws_voice … status 'done' AND transcript LIKE …)` to `ws_message_search()` (the channel search, the palette's messages group, GET /workspace/search) and the palette's files group; '' while the table is missing. Reach rules unchanged: rows are still kept by `ws_can_read_channel()`.
- A hit matched only by its transcript (`ws_voice_found_in_transcript()`) is titled "Voice message: <snippet>" with `bi-mic`; a file hit shows the transcript snippet in its detail.

## What is still open?
- A recording that asks an assistant once written out (V4c): plans/workspace-voice-messages.md. Tests: tests/workspace_voice_assistants_test.php.
