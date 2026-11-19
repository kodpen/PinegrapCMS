---
name: designer-blocks-listen
description: The listen- block family (Listen group): the "Listen" block that reads the article aloud in the visitor's browser; its buttons, canvas and Preview, the d-flex trap, what is left out
---

## What does the family hold?
- `pinegrap/assets/js/style_designer_blocks_listen.js` ([[designer-block-family-files]]), prefix `listen-`, palette group "Listen" (`bi-volume-up`), one block "Listen" (`listen-buttons`), #9924.
- Its root `div.pg-listen` carries the mark `data-pg-listen`, `hidden`, `role="group"` and the aria-label "Read this article aloud". Inside, three real buttons: Listen (`data-pg-listen-play`, `bi-volume-up`), Pause (`data-pg-listen-pause`, `aria-pressed="false"`, `hidden`), Stop (`data-pg-listen-stop`, `hidden`); each icon `aria-hidden`, each label a span `data-pg-listen-text` (node name "Label").
- No script, handler or inline style: the page brings `pg_listen.js` for the mark, which shows the root, writes the labels in the page's language and speaks ([[listen-script]]).

## Why does it reuse nothing?
- No block read a page aloud. The nearest relative is a script, not a block: the form item view's in-browser summary (`pg_ai_summary.js`, [[form-item-ai-summary]]), which reads the same `article`, else `main`.

## What does its rule do?
- `pg-listen`: the row's flex layout only on `.pg-listen:not([hidden])`; buttons at least 44 px high with icon and label on one line. No `!important`.
- Never a display utility (`d-flex`) on the root: it is `!important` and comes after Bootstrap's `[hidden] { display:none !important }`, so it showed the buttons where the browser has no voice: a button that does nothing ([[catalog-binding-disabled-no-output]]).

## How is it drawn in the editor?
- Canvas: Bootstrap's `[hidden]` rule would hide the root there too, so one `IFRAME_CSS` line in `style_designer.src.js` (`.sd-wrap > [data-pg-listen][hidden]`) draws it visible, idle and silent (Pause and Stop stay hidden), with its `_sdT()` texts. The palette's hover sketch (`_sdBlockSketch()`'s `hidden()`) draws the root too.
- Preview: loads `pg_listen.js` from its `_SD_PAGE_SCRIPTS` row and behaves as the page; with no `pgListenLabels` there, the buttons read the English keys ([[listen-script]]).

## What was left out?
- Left out by the plan (2026-11-16), each a later yes of the owner: a recorded voice made by an AI service and kept as a file (the Workspace speaks through `ws_voice_speech_ready()`, [[workspace-voice-speech]]); the sentence being read marked on the page; speed and voice choices; pictures read by their alt text.
- Turkish: "Listen" is a shared key ("Dinleyin"); Pause "Duraklat", Resume "Devam ettir", Stop "Durdur".

## How is it tested?
- `tests/designer_blocks_listen_test.php`: the palette item, the tree (no display utility on the hidden root), the printed page (the hidden marked root `pg_listen_inject()` finds, three buttons, no script, `onclick` or style), the rule, the canvas line and the Preview's row. `tests/designer_blocks_test.php` and `tests/designer_block_sketch_test.php` check it with every family; the script's own tests are `tests/listen_script_test.php`.
