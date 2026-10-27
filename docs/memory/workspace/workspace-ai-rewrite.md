---
name: workspace-ai-rewrite
description: "Rewrite with AI" in the Workspace composer (ai_write target ws_message): what the model reads, the button, Use as one undo step, tags kept, no AI mark
---

## What is it?
- A stars button in the composer's tool row (channel and discussion reply share `composer()`, `assets/js/workspace.js`), PR #2141. It is shown only when the box holds text and a model writes in the browser. It opens the "Write with AI" draft panel (`assets/js/pg_ai_write.js`) above the box, with the tone choices, an optional request, "Try again" and "Use this text". The box does not change until the person uses the draft.
- Same endpoint and rules as the form buttons (docs/memory/ui/ai-write.md): Pinegrap AI, or the browser's model without it; the daily cap and the per-minute limit apply; the style note is reused.

## What does the model read?
- Target `ws_message`, record `message`: `text`, the box's markup kept as written (marks, `<@user:12>` tags, line breaks, `pg_ai_write_message_text()`), sent as `current_text`, plus the channel's name. Nothing else: no other message, no record's data. Without text the server refuses ("Write your message first…").
- The rule: rewrite, do not answer; keep tags, links, numbers, names and marks; add no facts, greetings or signature; written in the text's own language (the browser bridge writes English, then translates). Format `message`: the answer is only tidied (line ends, blank runs) and cut to `WS_MESSAGE_MAX`, so the meter never shows it over.

## Who may ask?
- A Workspace team member (`pg_ai_write_ws_member()`, `ws_screen_gate()`'s rule). The screen offers it on the channel screen only (`ws_screen_ai_rewrite()` → `CFG.ai_rewrite`, and the panel's script printed by `ws_screen_assets()`); the plain fallback box has no button.

## How does "Use" work?
- `pg_ai_write.js` hands the draft to the button's adapter (`button.pgAiWrite`: `context()`, `place(panel)`, `use(text)`). `aiRewriteUse()` drops tags the person did not pick (`aiRewriteTokens()`), keeps the chips' names and calls `rich.setMarkup(…, labels, true)`: one step, so one Ctrl+Z brings back the original text.
- The browser's model is found after the box is drawn: `PinegrapAiWrite.ready` and the `pg:ai-write-ready` event show the button then.

## Is the message marked?
- No mark (marks belong to record fields). Asked the owner in #2141, with the recommendation no: the person reads, edits and sends it as their own.

## How is it checked?
- tests/ai_write_test.php (target, context, rules, tidy), tests/workspace_ai_rewrite_test.php (`aiRewriteTokens()` under Node, wiring). A real Pinegrap AI or browser-model draft was not run.
