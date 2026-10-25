---
name: form-list-viewer-filter
description: A form list's viewer filter (submitter, editor, watcher, ORed): who counts as which; watchers of the detail page, classic or designed with a record view of the list's form
---

## What does the viewer filter show?
- `form_list_view` config `viewer_filter` on: only the signed-in viewer's records, by any of three audiences, ORed: `viewer_filter_submitter`, `viewer_filter_editor`, `viewer_filter_watcher` (each defaults on). Built in `_render_system_widget_form_list()`, ported from `get_form_list_view.php`.
- Signed out, or all three off: no record (`TRUE = FALSE`).
- Editor: a staff role (`USER_ROLE < 3`) or edit access to the custom form's folder sees every record; anyone else the records whose `form_editor_user_id` is theirs.
- Submitter: `forms.user_id`, or the form's connect-to-contact e-mail field equal to the viewer's address.

## Which watchers count?
- Watchers of the list's `detail_page_id` only, item type `submitted_form`, by the viewer's user id or e-mail address: one `EXISTS` per record with the page id as a constant (the indexed `page_id` and `item_id` columns; fine on MySQL 5.7 and MariaDB 10.2). The page comes from `pg_sw_list_watcher_page_id()` (one page query, the widget lookup kept per request); its pure part is `pg_sw_list_watcher_page_decision()`.
- The page needs comments and watching on (`comments = 1`, `comments_watcher_email_page_id != 0`): `add_or_remove_watcher.php` stores no watcher on a page without them.
- Page type `form item view` (classic): counts by its page type, as before. A record view widget on it is not asked.
- Any other page: counts when its first form_item_view widget shows the list's own form. That is the widget `pg_sw_record_comment_context()` asks, so the thread `add_comment.php` and `add_or_remove_watcher.php` store under ([[comment-thread-verify]]); its form is `pg_sw_record_view_form_page_id()` (custom_form_page_id, else form_page_id; a source_page name is not looked up), which the comment context reads too.
- A page without such a widget, a record view of another form, or no detail page: the watcher audience matches nothing. The list's own `forms.page_id` keeps every other form's records out.

## What was the symptom?
- The watcher clause asked for page type `form item view` only. A designed record page is `standard`, so a "Topics you follow" list (viewer filter, watcher audience) on a designed site always showed nothing, although following a topic stored the watcher row on that page. The forum template's My topics list and its inbox (conversations a member follows) rely on the fix ([[design-template-forum]]).

## What is left alone?
- Watchers stored on another page (a second record view of the same form) do not count; only the detail page's.
- The submitter and editor audiences are unchanged.
- A viewer filtered list is not public: no feed, no sitemap records, no search index ([[record-view-filters]], [[designer-records-site-search]]).

## Where is it tested?
- `tests/form_list_watcher_test.php`: the decision table (classic, designed, another form, comments or watching off, no row), the form rule, and the renderer and comment context wiring. The SQL itself needs a running site.

Related: [[system-widget-form-records]]
