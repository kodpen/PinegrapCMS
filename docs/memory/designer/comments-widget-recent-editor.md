---
name: comments-widget-recent-editor
description: The recent comments list in the editor: the kind twin, palette, Change type and copies, template starters, default layout, bind lists, fades, canvas states and samples
---

## How does the editor tell a list from a thread?
- `_swKindOf(cfg)` (twin of `pg_sw_widget_kind()`, compared in tests): `comments_recent` for `regionType 'comments'` + `mode 'recent'`, else the regionType. `SW_TYPES` and `pg_sw_type_labels()` list "Recent Comments" (slug `recent-comments`) right after Comments (designer_components_test compares their order); the System palette's widget list takes icon and tooltip from the kind.
- Thread-only places ask the kind: `_sdCommentsWidgetOf()` (settings shortcut), `_sdCommentsPickWidget()` and `_sdCommentsNamedRows()` (Make designable), Back to the fixed block, `_sdPreviewWidgetPayload()` (no comment settings for a page with only lists), `pg_designer_preview_widgets()` (`?r=` only for a thread), `pg_comments_component_census()` (no save warning for a list).

## How is one made or changed?
- Palette or Change type to `comments_recent`: `_swTypeDefaults()` writes `regionType 'comments'`, `mode 'recent'` and the recent defaults when unset; back to Comments writes `mode 'thread'` only if a mode was set. The select, `_offerStarterTree()` and auto names use the kind.
- `_swForkForThisPage()` compares the asked kind with `_swKindOf()`, so a list copied as a thread (or back) gets that kind's defaults and starter; `_swNameKind()` names a list `<page>-recent-comments-widget`.
- Template `'tree' => 'starter'`: `pg_design_template_starter_kinds()`, `pg_design_template_install()`, `_pgTemplateApply()` (`_swTemplateStarterTree()`) and the placeholder repair in `prefetchShared()` all build the kind's starter.

## What is the default layout?
- `_buildStarterTree('comments_recent')` = `pg_comments_recent_default_tree()` (compared node for node; its loop is also the server's fallback): heading, list-group of status, title link (`__comment_url`), name line, stars, excerpt; "No comments yet." No form, no Messages node (`_SW_NO_MESSAGES`; `prefetchShared()` drops one by kind).

## What does the panel show?
- Its own section, not Display and Comment settings: [[comments-widget-recent-panel]].

## Which bindings does it offer and fade?
- `SW_COMMENTS_RECENT_TOKEN_GROUPS`, `SW_VISIBILITY_FLAGS.comments_recent` (6 list + 9 row flags), `_swBindTokenGroups('comments_recent')`; actions only `comment_share`; no value or section bindings.
- `_sdCommentsModeOffBindings()` (twin of `pg_comments_mode_off_bindings()`, compared under Node) holds the lists; `_sdCommentsModeOff()` fades by them: in a list the form, thread controls, sections, thread-only tokens and flags; in a thread the list's tokens and flags. A list without a page fades its root.

## What does the canvas draw?
- States (`_sdCommentsCanvasStates(cfg)`): comments, no comments, signed out (mine only), staff; no e-mail state (a list draws nothing in an e-mail). `_sdCommentsRecentFacts()` -> `_sdCommentsRecentFlags()`, line for line `pg_comments_recent_flags()` (Node).
- Samples `_sdCommentsRecentSamples()` in the list's scope: a record's and a page's comment; pending: both awaiting approval ("Not Published" for staff); mine: plus one of the member's own waiting (the author's status, staff too); published: none waiting.

## How was it checked?
- `tests/comments_widget_designer_test.php`, `designer_widget_fork_test`, `comments_recent_test` (twins, starter, tab round trip), `node --check`. No browser run of the panel.
