---
name: listen-script
description: The "Listen" block's script pg_listen.js and its two doors: what it reads, in which language, when the button shows, the markup it expects
module: listen
---

## How does the script reach a page?
- `get_page_content.php` loads `includes/fn/listen.php` only when the drawn page holds `data-pg-listen` (not in an e-mail) and calls `pg_listen_inject()`: `window.pgListenLabels` (Listen, Pause, Resume, Stop through `lang()`) and `assets/js/pg_listen.js` deferred, once, before the last `</body>`.
- The mark is found with `pg_listen_pattern()` (`\sdata-pg-listen(?=[\s=>\/])`, so `data-pg-listen-play` alone is no mark). The Preview's `_SD_PAGE_SCRIPTS` row uses the same expression; `tests/designer_page_scripts_test.php` compares them. The Preview has no labels: the script falls back to the English keys.

## What markup does it expect?
- A root `[data-pg-listen]` with `hidden`; buttons `[data-pg-listen-play]`, `[data-pg-listen-pause]`, `[data-pg-listen-stop]` (the last two `hidden`), each with a `[data-pg-listen-text]` element the script fills with its label; a button without one gets `aria-label`. The root gets `data-pg-listen-state` idle / speaking / paused; Pause carries `aria-pressed` and reads "Resume" while paused.

## What is read, in which language?
- The nearest `article` around the block, else the page's `main`, else nothing (`scopeFor()`). Left out: nav, header, footer, aside, form and controls, label, button, script, style, svg, iframe, media, dialog, `hidden`, `aria-hidden="true"`, the block, and what the page's styles hide.
- `pieces()` (pure) gives `{text, lang}` in order: a new piece at every block element and every change of language, then sentences (a mark followed by a space or the end; "3.5" stays whole), each at most 200 characters cut at a space.
- The language is `<html lang>` with everything from `-x-` cut (`langOf()`: `en-x-mtfrom-tr` is `en`); a part's own `lang` wins inside it.

## When does the button show?
- Only with `speechSynthesis`, a page language, something to read, and a voice for the page's language (`hasVoice()`: same tag, else same language in another region); asked again on `voiceschanged`. Otherwise the root stays hidden.
- One block speaks at a time; Stop, the end, `pagehide` and a hidden page put the button back and move the focus to it. No request, storage or cookie.

## What is the block?
- `assets/js/style_designer_blocks_listen.js` (prefix `listen-`, group Listen, type `listen-buttons`): the root above with `role="group"` and an aria-label, three `btn-outline-*` buttons with a Bootstrap icon (`aria-hidden`) and the label span; rule `pg-listen` (44 px buttons).
- Bootstrap's `[hidden] { display:none !important }` would hide it on the canvas too: one `IFRAME_CSS` line (`.sd-wrap > [data-pg-listen][hidden]`) draws the root there, idle and silent; the Preview loads the script and behaves as the page.

Tests: `tests/listen_script_test.php`, `tests/designer_blocks_listen_test.php` (under Node, `tests/fixtures/listen/dom.js`).
