---
name: comments-widget-form
description: The comments widget's add-comment form: bindings, slots, server sections and their fallbacks, binding-off twins, Messages, frontend init
module: widgets_comments
---

## How is the form wired?
- A semantic `form` bound to `comment_form` (`pg_comments_apply_form_bindings()`): the server writes `action` (add_comment.php), `method`, `enctype` (multipart with attachments on), `id="software_add_comment"` (the handler's anchor), `data-pg-comment-form|label`, and first the token and hidden `send_to`, `page_id`, `item_id`, `item_type`, `parent_id` (the comment answered, else 0: [[comments-widget-replies]]): the server's values, not bindable.
- One form per widget; a second one, and one for a visitor who cannot comment (closed, must sign in, search index), is dropped.

## Which controls bind, and how do they get their values?
- Value bindings: `comment_message` (name message, required, textarea or input), `comment_name` (name, input), `comment_name_type` (name_type, select), `comment_file` (file), `comment_watcher` (watcher, checkbox, value 1). Default ids `comment_*` only when the control has none.
- Values go in through internal slots `^^pg_comment_form_message|name|name_options|label^^`, filled in one `strtr()` pass, escaped by `pg_sw_filter_value_html()`; no `__`, so `check_bindings` skips them.
- Dropped with its `<label for>` and an emptied wrapper (`_pg_member_drop_controls()`): a control outside the form, of the wrong tag or switched off; `comment_submit` outside the form. Inside: type=submit, `data-pg-comment-submit`. Only a semantic button or btn component sends; anything else bound to it is dropped; the panel offers it to buttons only.

## Which sections are server-built?
- `comment_captcha`: `get_captcha_fields()` (site setting; staff get none). `comment_rating_input`: btn-check radios 1-5 (5 checked), no inline style. `comment_publish`: staff and the form editor; now / schedule / later, the date, cancel; its own ids. Empty, or outside the wired form: dropped.
- Fallbacks for add_comment.php: no `comment_publish` the visitor gets (left out, below a false flag) -> hidden `publish` now / manual by auto-publish (staff posts were held); CAPTCHA on, no `comment_captcha` -> one before the send button's box (anonymous posts failed).

## What switches a binding off?
| Binding | Off when | Canvas (`_sdCommentsBindingOff()`) |
|---|---|---|
| the whole widget | page comments off | `pg_comments` (root fades) |
| comment_file, files_on | attachments off | `pg_comments_attachments` |
| comment_watcher | no watch box | watcher e-mail page 0 |
| comment_name / _name_type | name mode not typed / chosen | select: `pg_comments_select_name` |
| comment_rating_input | stars off | `pg_comments_rating` |
| `__comment_date(time)` text | dates off (empty) | `pg_comments_show_date` |
| comment_captcha | no CAPTCHA | site flag |
| comment_publish | not staff | - |
| comment_submit, no button | always | always |
- PHP: `_pg_comments_binding_disabled()`. The watcher checkbox and the watch box: [[comments-widget-watch-staff]]. Page fields: [[comments-widget-panel]].
- Name mode (`pg_comments_widget_name_mode()`, add_comment.php's rule): typed when signed out, role < 3, folder access, form editor; else chosen (select on) or fixed (`__comment_author_name`).

## Where does the Messages node sit?
- First in the form (starter, PHP tree): a refused post lands on `#software_add_comment` by its errors. Named `add_comment`; state read before the draw, `remove_form()` after; an undrawn (closed) form's errors are dropped, as classic. No node left for it (a design kept one only in a small form): one goes first in the container ([[comments-widget-watch-staff]]).

## What does the browser add?
- `software.init_comment_forms()` (frontend.src.js, hand-made .min twin), on ready, by data attributes: submit lock, leave warning with the label (`[name="message"]`, any tag), publish picker toggling `hidden` (beats inline display), the time box on the date-and-time control (`ui/site-datetime-loader.md`); the classic form keeps its own.
