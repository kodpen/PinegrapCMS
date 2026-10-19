---
name: settings-modal-injected-markup
description: Rules for settings pane markup injected by AJAX: jQuery .html(), pg_settings_scripts, scope-taking initializers, delegated listeners, code fields.
---

## Why must the pane be filled with jQuery `.html()`?
- Inline `<script>`s inside cards set up the code fields (`pg_code_editor_attach()`) and tag fields (`tagin`). jQuery `.html()` runs an injected script, `innerHTML` does not. Not a preference.
- `$pg_settings_scripts` is appended INSIDE the pane: jQuery runs a script only while its host node is attached to the document; appending to a detached `<div>` silently never runs it.

## How must initializers be written so AJAX markup works?
- Scope-taking initializers live in one place: `pgBindTitlePopovers(scope)` and `pgSyncCollapseSwitchers(scope)` were taken out of the ready block; ready calls them on `document`, `pgInitInjected(scope)` calls them on the pane.
- A new initializer written in the ready block that takes no scope leaves AJAX-delivered markup silently unset (42 `collapse-switcher` opened in the wrong state, nothing in the console).
- `collapse-switcher` and `collapse-if-selected` listeners are delegated (`$(document).on(...)`); a directly bound listener does not see a control that arrives later.

## What about code fields in the pane?
- The facade's editor writes every edit into its textarea and raises `input` on it, so `serialize()` and the pane's `change input` listener see it with nothing to push down. The pane is painted only once the editor is loaded (`pgCodeEditorReady()`); `pgReleaseEditors()` destroys the editors before the pane is replaced (docs/memory/ui/panel-code-fields.md).

## Where does a card's script go?
- Into that card's module (`$pg_settings_scripts`). A function called by `onclick` that stays in the page script drops out in the split and the button silently does nothing (happened once with `pgRobotsNoIndex`).

Related: docs/memory/ui/settings-modal-dirty-tracking.md
