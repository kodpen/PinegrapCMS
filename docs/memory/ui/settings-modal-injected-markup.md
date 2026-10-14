---
name: settings-modal-injected-markup
description: Rules for settings pane markup injected by AJAX: jQuery .html(), pg_settings_scripts, scope-taking initializers, delegated listeners, CodeMirror save.
---

## Why must the pane be filled with jQuery `.html()`?
- Inline `<script>`s inside cards set up CodeMirror and tag fields (`tagin`). jQuery `.html()` runs an injected script, `innerHTML` does not. Not a preference.
- `$pg_settings_scripts` is appended INSIDE the pane: jQuery runs a script only while its host node is attached to the document; appending to a detached `<div>` silently never runs it.

## How must initializers be written so AJAX markup works?
- Scope-taking initializers live in one place: `pgBindTitlePopovers(scope)` and `pgSyncCollapseSwitchers(scope)` were taken out of the ready block; ready calls them on `document`, `pgInitInjected(scope)` calls them on the pane.
- A new initializer written in the ready block that takes no scope leaves AJAX-delivered markup silently unset (42 `collapse-switcher` opened in the wrong state, nothing in the console).
- `collapse-switcher` and `collapse-if-selected` listeners are delegated (`$(document).on(...)`); a directly bound listener does not see a control that arrives later.

## What about CodeMirror in the pane?
- CodeMirror dirtiness comes from its own `change` event: it keeps its own document and fires no event on the textarea. Listening only to `:input` leaves the Save button disabled.
- Before saving call `save()` on every `.CodeMirror` instance, otherwise `serialize()` sends the value the pane arrived with.

## Where does a card's script go?
- Into that card's module (`$pg_settings_scripts`). A function called by `onclick` that stays in the page script drops out in the split and the button silently does nothing (happened once with `pgRobotsNoIndex`).

Related: docs/memory/ui/settings-modal-dirty-tracking.md
