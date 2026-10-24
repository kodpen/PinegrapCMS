---
name: comments-widget-form
description: The comments widget's add-comment form: the comment_form binding, value bindings and slots, server sections, the PHP/JS binding-off twins, Messages placement, the watch box, frontend init
---

## How is the form wired?
- A semantic `form` bound to `comment_form` (`pg_comments_apply_form_bindings()`): the server writes `action` (add_comment.php), `method`, `enctype` (multipart only with attachments on), `id="software_add_comment"` (the handler's anchor), `data-pg-comment-form` and `data-pg-comment-label`, and puts the token and hidden `send_to`, `page_id`, `item_id`, `item_type`, `parent_id` (0) first. They come from the server, never from an earlier post, and are not bindable.
- One form per widget; a second one, and the form for a visitor who cannot comment (closed, must sign in, search index), is dropped.

## Which controls bind, and how do they get their values?
- Value bindings (Field binding): `comment_message` (name message, required, textarea or input), `comment_name` (name, input), `comment_name_type` (name_type, select), `comment_file` (file), `comment_watcher` (watcher, checkbox, value 1). Default ids `comment_*` only when the control has none.
- Values go in through internal slots `^^pg_comment_form_message|name|name_options|label^^`, filled with the thread values in one `strtr()` pass, escaped by `pg_sw_filter_value_html()`. No `__`, so `check_bindings` sees only designer tokens.
- A control outside the form, of the wrong tag, or switched off is dropped with its `<label for>` and an emptied wrapper (`_pg_member_drop_controls()`); `comment_submit` outside the form too. Inside: type=submit, `data-pg-comment-submit`.

## Which sections are server-built?
- `comment_captcha`: `get_captcha_fields()` (site setting; staff get none). `comment_rating_input`: btn-check radios 1-5, 5 checked as in the classic form, no inline style. `comment_publish`: staff and the form editor; now / schedule / later, the date, cancel; its own ids. Empty: container dropped.

## What switches a binding off?
| Binding | Off when | Canvas (`_sdCommentsBindingOff()`) |
|---|---|---|
| comment_file | attachments off | not yet |
| comment_watcher | no watch box | watcher e-mail page 0 |
| comment_name / _name_type | name mode not typed / chosen | not yet |
| section comment_rating_input | stars off | `pg_comments_rating` |
| section comment_captcha | no CAPTCHA | site flag |
| section comment_publish | not staff | - |
- PHP: `_pg_comments_binding_disabled()`. The designer has no attachment or name-select page field yet; the JS twin learns them with those fields.
- Name mode (`pg_comments_widget_name_mode()`, add_comment.php's rule): typed for signed out, role < 3, folder access, form editor; else chosen with the select on, else fixed (`__comment_author_name`).

## Where does the Messages node sit?
- First in the form (starter and PHP tree): a refused post lands on `#software_add_comment` with its errors right there. Named `add_comment`; the form state is read before the draw and `remove_form()` runs after. Errors of a form not drawn (closed) are dropped, as in the classic block.

## When is the watch box offered?
- `pg_comments_watcher_box()`: watching on, signed in, not a watcher (thread's watchers), not told another way (moderator address, form editor, conditional admin, record's submitter). Ticked unless a post said otherwise.

## What does the browser add?
- `software.init_comment_forms()` (frontend.src.js and the hand-made frontend.min.js twin; not a bundle), on document ready, by data attributes: submit lock, leave warning with the label, publish picker toggling the `hidden` attribute (Bootstrap's `[hidden]` beats inline display) and the date picker once. The classic form keeps its own init.
