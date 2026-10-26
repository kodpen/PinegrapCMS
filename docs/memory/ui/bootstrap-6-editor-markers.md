---
name: bootstrap-6-editor-markers
description: The visual editor's /* @pg-site-output */ markers (step B7a, #1822): what they cover, how the codemod and check_bs6.php read them, the test that keeps them, what is still unmarked
---

## Why are parts of style_designer.js marked?
- The editor's chrome moves to Bootstrap 6 with the panel; the markup it writes into a page (starter, palette and component trees, `generateHTML()`) and what it draws in the canvas (its own 5.3.8) must stay Bootstrap 5. `/* @pg-site-output */` tells `tools/bs6_migrate.php` and `tools/check_bs6.php` to leave that code as it is.

## What does a marker cover?
- On a line of its own right above a top-level member: the whole member, up to the next line at its indentation that is not a closing bracket (`bs6_site_output_ranges()` in `tools/bs6_rules.php`).
- At the end of a code line: that line only (the carousel's `carousel-dark` toggle on the canvas in `bindPropEvents()`).
- `bs6_marked_editor_files()` names the editor files the codemod converts outside their markers: only `style_designer.js`. The other editor files stay by hand; the designer's vocabulary (`designer_vocab.php`, `pg_style_predict.js`, `pg_style_complete.js`) is site API data and never converted.

## What keeps the markers?
- `tests/bs6_site_output_test.php`: the 53 marked members by name, and a guard: a member with `createNode(`, a `toHTML:` property or a `cssClass: '…'` literal is marked or listed as chrome in the test (`bindCtxOptsEvents`, `initLeftPanel`, `_wireSystemWidgetProps`, `propsButtonLinkOptions`, `bindPropEvents`, the `createNode` factory).
- After the markers, `php tools/bs6_migrate.php pinegrap/assets/js/style_designer.js` rewrites about 640 chrome classes and keeps 168 class lists.

## What is still unmarked?
- `_SD_HTML_PREVIEW_TOKENS` (canvas previews of widget output, about 20 class lists the codemod would rewrite) and `SD_OV_STATUS_CLASS_DEFAULTS`: an open PR edited the lines around them when B7a landed; marking them is in B7's remaining work (`plans/bootstrap-6-panel-steps.md`).
- Canvas code inside chrome members (`.dropdown-menu.show` on `canvasDoc`, `win.bootstrap`) holds no class the codemod rewrites.
