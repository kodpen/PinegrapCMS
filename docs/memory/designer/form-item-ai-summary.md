---
name: form-item-ai-summary
description: AI on a designed form item view (2026.4.8): four assistant links and when they fill, the prompt setting, the in-browser summary wired by bindings, why the box is an aside, the translator bridge.
---

## Which AI tokens does a form item view fill?
- `pg_sw_ai_tokens($page_url, $cfg)` (`includes/fn/widgets.php`): `__ai_summary` (always an empty box) and four assistant addresses `__ai_chatgpt_url`, `__ai_claude_url`, `__ai_perplexity_url`, `__ai_google_url` (Google's AI Mode: Gemini takes no question in its address).
- The addresses are filled only for a found record anybody may open (access `public`), on the page itself, never in an e-mail: an assistant cannot open a page behind a sign-in, and an e-mail has no page. Flag `has_ai_links`.
- The question is the widget setting `ai_prompt_text` (read through `pg_tr_text()`) or the software's `lang()` sentence; `{url}` stands for the page's address, and a wording without it gets the address at its end.

## How is the in-browser summary wired?
- By bindings, not hand-written attributes: the button `_bindings.action = 'ai_summarize'`, the box `_bindings.text = '__ai_summary'`. `_pg_sw_apply_ai_summary_bindings()` writes `data-pg-ai-summarize` / `data-pg-ai-summary` plus `hidden` and `d-none` (a display utility would show a `[hidden]` element); a link bound to the action becomes `role="button"` with `href="#"`.
- `pg_ai_summary_inject()` adds `assets/js/pg_ai_summary.js` (with its labels in the page's language) only when the page holds the button's marker, after every component and widget is drawn. The script shows the button only in a browser that can summarize (the Summarizer API of Chrome and Edge) and writes into the nearest bound box, or a box it adds below the button.
- The text summarized is the button's `<article>` (or `<main>`) without forms, buttons, navigation, asides, hidden parts and the assistant links: make the box an `aside`, so its own text does not enter the summary.

## What about a page in Turkish?
- The Summarizer does not write Turkish: the script summarizes in English and the browser's Translator API brings the points into the page's language; without either the button stays hidden. The `create()` calls stay synchronous inside the click, because a model still to download needs the visitor's gesture.
- Tests: `tests/ai_summary_test.php`. The panel's own "Write with AI" on the browser model: `ui/ai-write-browser.md`.
