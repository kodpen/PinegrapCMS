---
name: api-form-rich-text-in
description: Rich text answers sent through POST /forms/{id}/submissions and its update (sanitised, {path} folded, html limit), author_user_id, single-choice updates (Status field), the record's url
---

## What happens to a rich text answer?
- A text area with `wysiwyg` on takes HTML (`rich_text` in the field list, `api/api-form-facts.md`). `api_form_html_answer_in()` runs `pg_sanitize_rich_text()` with the visitor profile, as `custom_form.php` does for a visitor (staff on the panel screen are not filtered, an API caller is), then writes the site's own addresses (`api_form_site()`: the configured scheme, https and http on `HOSTNAME_SETTING` + `PATH`, and the relative `PATH`) as `{path}`. Reading expands `{path}` again (`api_form_value_out()`). Before this the create stored the HTML unfiltered with a 10000 limit, and the update refused nothing but wrote it as `standard` text.

## Who is the author, and what may an update write?
- `author_user_id` (create only, optional): an enabled user with role < 3 or the forms right (`api_form_author_is_staff()`, the forms scope's own rule), checked before the rehearsal stop; it becomes `forms.user_id` (the byline's submitter), and `last_modified_user_id` stays the key's owner. Default: the owner.
- The update (`api_form_field_updatable()`) writes text box, text area, e-mail address, and a single-answer choice field (pick list without multiple, radio button) with one of its option values: a collection's Status field is written as sent, so a post is published in one call (owner, `workflow/decisions-made-product-36.md`). Check box groups, multiple pick lists, dates, times, uploads and signatures stay refused. A rich answer is stored with `form_data.type = 'html'`.
- Every FormSubmission carries `url`, the record's address on the site (`api_form_submission_url()`: complete records only, the page from `api_form_record_page()`), null when no public page shows the form's records.

Tests: `tests/api_forms_test.php`.
