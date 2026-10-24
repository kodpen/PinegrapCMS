---
name: comments-widget-panel
description: The comments widget's panel: page comment settings shared with the fixed block, display options, inner-node shortcut, the 15 page settings (pg_comment_editor_keys), save, templates
---

## What does the widget's panel show?
- Display (`_sdCommentsDisplaySect()`), the widget's own `system_region_config`, shared by every page placing it: Comment order, Featured comments, Replies, Reply depth (threaded only), Comments per page. Selects on the generic `[data-sw-cfg]` handler, kind `choice`: stored as text (`'10'`, `'2'`), read as numbers by `pg_comments_widget_options()` (ctype_digit) and `parseInt` in JS; the selected value follows that function's defaults and clamps. The row says "Comment order": `Order` is "Sipariş" in tr.json.
- Comment settings (`_sdCommentSettingsSect({ widget: true })`): the same section the fixed `comments_block` region shows (`{}`), headed "Comment settings for <page>" with "Saved with this page; other pages that show this widget keep their own", and a warning while comments are off (the widget draws nothing). Body wrapped in `#sd-cmt-settings`.
- Canvas Preview and Back to the fixed block: [[comments-widget-canvas]], [[comments-widget-conversion]].

## How does an inner node reach the settings?
- `_sdCommentsWidgetOf(node)` (a node inside a widget of regionType comments, never its shared_ref) gets the "Comment settings" button on top of its panel (`_sdCommentsSettingsShortcut()`, with the comments-off warning) and a `bi-chat-square-text` toolbar button. Both call `_sdOpenWidgetSection(sid, 'sd-cmt-settings')`, the opener `_cfOpenFormSettings()` now shares ([[custom-form-settings-shortcut]]).
- A switch or the e-mail page redraws everything (`_sdCommentSettingsChanged(true)`); a text box redraws the canvas on change only, so the click that left it is not lost. `_setCmtVal()` schedules the live preview.

## Which page settings exist, and where do they go?
- `pg_comment_editor_keys()` (includes/fn/comments.php): 15 editor keys -> kind (flag / page / text), columns, max length. New in this slice: `pg_comments_attachments` (comments_allow_file_attachments), `_select_name` (comments_allow_user_to_select_name), `_message` (comments_message, 255), `_closed_message` (comments_disallow_new_comment_message, 255), `_watchers_managed` (comments_watchers_managed_by_submitter).
- `pg_comment_editor_columns($fields)`: only keys sent; flag 1 for 1/'1'/true; page a whole number > 0 else 0 (`tab:` too); text trimmed, cut by characters.
- Editor: `_PG_CMT_FIELDS` (same keys, same order; `_pgReadFieldsIntoPage` / `_pgLoadPageIntoFields`), `_PG_PAGE_FIELDS`, `_PG_BOOL_FIELDS`, the 8 page literals, the hidden inputs and both `$js_pages` lists of designer_screen.php, `pg_designer_load_pages()`. Tests check every list against the map.

## How does the save write them?
- `pg_designer_save_page()` puts the five new columns in `$c_extra` (escaped; the carried check covers them). `pg_designer_page_insert_defaults($c_extra)` names `comments_disallow_new_comment_message = ''` in a new page's INSERT unless the save writes it: named twice, MySQL refused the INSERT.

## What do templates carry?
- Per page `'comments'` (`pg_design_template_page_comments()`, used by prepare; install copies every key): label, allow_new, rating, auto_publish, show_date, login, email_page, email_subject, notify_email, and attachments, select_name, message, closed_message. Watchers-managed is not a template key (stays off). The export (`pg_dtc_page_comments()`) writes the same four new keys.

## How was it checked?
- `tests/comments_widget_designer_test.php` (keys, columns, insert default, template keys, wiring, the display section under Node), `design_templates_test`, `designer_page_tabs_test`. No running instance.
