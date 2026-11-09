---
name: channel-export
description: Plan (owner 2026-11-09): a channel's conversation downloads as Markdown or opens as a print page, with its decisions and file list, for a date range; step CE1
---

## What is it for?
- A note exports as Markdown, a print page or a vault (`workspace/workspace-notes-export.md`); a channel has nothing. The decisions and the story of a customer channel or an agent channel are needed outside too: a hand-over, an archive, a file for the customer.
- Decision: `workflow/decisions-made-workspace-10.md`. No schema.

## How does it behave?
- The channel's menu gets Export for whoever may read the channel (`ws_can_read_channel()`); a guest never sees it. The drawer asks for the dates (all by default) and the form: a Markdown file, or a print page that the browser saves as PDF.
- The export holds what the reader sees on the screen, oldest first: who wrote, when, the text with names in place of tags (`ws_note_export_tokens()` for Markdown, as a note's export writes them; `ws_render_body()` for the print page), thread replies under their message, a poll or an approval as one line with its outcome, a file as its name and link. Deleted messages and messages hidden from the reader stay out (`ws_message_payloads()`).
- It opens with the channel's name, the range, who exported and when, then the channel's decisions (`ws_channel_decisions()`), and ends with the file list (`ws_channel_files()`).
- At most 5,000 messages an export. A longer range is refused with a sentence that asks for a shorter one.
- An export writes an audit line (`pg_audit('export', …)`) that names the channel and the range: a channel may hold customers' data.

## Which steps are there?
- CE1 (M, no schema) The export. A root file beside `workspace_notes_export.php` in its shape (`ws_screen_gate()`, a send function, the print page with `window.print()`), with the header and the lists a new root file needs (`core/`). The messages are read in pages (`ws_messages_page()`). A pure builder turns the payloads into Markdown, with tests: tags, a thread, a poll's line, a deleted message left out, the range. The drawer in `workspace.js`; texts through `lang()` and `php tools/tr_add.php`; `php tools/build_js.php` after the edit. Memory: `workspace/workspace-channel-export.md`. Claim as `[plan:channel-export] CE1 — channel export`.

## What is left for later?
- A zip with the files themselves, as the notes vault has; an export route in the API (agents read messages by the API already).

## Done when?
- CE1 merged; the lasting points go to `workspace/` and this file is deleted.
