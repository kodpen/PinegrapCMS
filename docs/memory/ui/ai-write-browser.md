---
name: ai-write-browser
description: "Write with AI" on the browser's built-in AI (Prompt API) for a site without Pinegrap AI: when, the two server steps, languages and the translation bridge, gesture, long data
---

## When does the browser write?
- When the feature is on and Pinegrap AI is not set up (`pg_ai_write_engine()` → `browser`). The buttons are drawn with `d-none`; `assets/js/pg_ai_write.js` shows them only when `LanguageModel.availability()` says the model can write the site's language, or English that the Translator API then brings into it.
- In practice desktop Chrome 148 and newer (Prompt API on the web; 16 GB RAM or a GPU with more than 4 GB, about 22 GB free disk). The first use downloads the model; the panel shows its progress. Other browsers keep the buttons hidden.

## How does a draft go?
- The click creates the model (`LanguageModel.create()`, and `Translator.create()` for the bridge) at once: a model still to download needs the person's gesture, which is gone after the first `await`.
- `ai_write` with `engine: browser, step: prompt` (and `language`): the server runs the same checks and context as for Pinegrap AI (`pg_ai_write_field()`, `pg_ai_write_prepare()`, `pg_ai_write_browser_prompt()`) and answers `rules` (the system message) and `data` (the record JSON). The per-person limit applies; the daily allowance does not (no licence quota is used), so the panel shows no "drafts left".
- The model gets `rules + "\n\n" + data` in one prompt with `responseConstraint` `{text}` and `omitResponseConstraintInput`: the session exists before the rules arrive, and a system prompt can only be given at creation. The web exposes no temperature.
- `step: finish` with the answer's text: `pg_ai_write_browser_finish()` reads, tidies and builds the HTML on the server (never in the browser) and logs "asked the browser's built-in AI for a draft of …".
- Closing the panel or asking again destroys the running model session.

## Which languages?
- The model writes en, ja, es, de, fr (Oct 2026), not Turkish. For such a site the server writes the rules for English with one more line ("translated into Turkish by a machine…", `translated_to` of `pg_ai_write_messages()`), and the script translates the draft line by line, keeping "- " marks (`pg_ai_write_browser_language()` accepts only the site's code or `en`).
- Input languages tried: [en, site], [site], else [en]: the model reads Turkish data although not listed (as the translations screen's `chrome_ai` engine does).

## What if the data is too long for the model?
- On `QuotaExceededError` the script halves every value over 200 characters in `data` and `current_text`, up to three times.

## Why not the Writer API?
- An origin trial only (Chrome 137–148): every site would need its own token.

## How is it checked?
- `tests/ai_write_test.php`: engine choice, language code, the bridge's language, the translation line, the hidden button, the script's hooks. The sandbox has no model: the flow was run with a fake `LanguageModel`/`Translator` in Playwright.
