---
name: api-forms-for-agents
description: Plan (owner 2026-11-06): the external API and MCP let Claude Desktop and other clients send designed forms and collection records (a blog post with rich text) completely; steps F1-F5
---

## Why?
- Forms are sent through the API (`api/api-form-submission-writes.md`), but a client cannot write a blog post well: a rich text (`html`) answer is cut at 10,000 characters and the update route writes only text box, text area and e-mail fields; the field list says nothing about the HTML a rich text field keeps (`pg_sanitize_rich_text()`), its length or the page's framework (`framework` sits on designs and pages only: `bootstrap5` 5.3.8 or `custom`); a collection form is not told apart from a plain form; a picture inside a post has no path in.
- Nothing since the gap list (#5239, X1-X13, `api/api-panel-coverage.md`) compared the panel with the API again: collections, product questions, store credit, flow events and the new templates may have no routes.
- Decided (`workflow/decisions-made-product-36.md`): an optional `author_user_id` among the staff, the Status field written as sent.

## Who holds it?
- One coordinator; the steps are small and independent except as marked. Claim as `[plan:api-forms-for-agents] F<n> — <topic>`; a route as `api/api-endpoint-rules.md` says, the OpenAPI declarations checked by `php tools/check_api_schema.php`, memory in `api/`.

## Which steps?
- F1 (S, docs and tests) Audit. Done (#7967): the gaps below; `tests/api_route_examples_test.php`.
- F2 (S) Form and field facts. Done (#7973): a rich text area is `rich_text`, `max_length` 200000 (`api/api-form-facts.md`). Form: `framework` (of the page's design), `is_collection`, the record's site address pattern; a FormField of type `html`: `html_allowed` (the tags and attributes `pg_sanitize_rich_text()` keeps) and `max_length`; the choice fields keep their `options`; `GET /forms/{id}`, `/fields`, `api_form_field_schema()`.
- F3 (M) Rich text in. After F2. `html` answers on create and update through the visitor path's sanitiser (`pg_sanitize_rich_text()`), the site's own address folded back to `{path}`, the length limit the column takes instead of 10,000; `author_user_id` (staff, the key's owner by default); the Status field as sent with its choices checked; `form.submitted` as today; the FormSubmission answer carries the record's address.
- F4 (S) MCP guidance. After F2. The tools' descriptions say to read `forms_fields` first and what an `html` field takes; a resource `pinegrap://forms/{id}/fields`; a prompt `blog_post` (the form, the framework, the allowed HTML, the picture flow) (`api/mcp-resources-prompts.md`).
- F5 (S) Pictures in a post. After F3. A picture sent through `POST /files` (`api/api-file-endpoints.md`) and referenced by its address in an `html` answer is kept as the page keeps it; the folder the form's pictures go to; size and type as the panel's upload.

## What stays as it is?
- The visitor path (`custom_form.php`) with its CAPTCHA and spam rules; file upload and signature fields stay unsendable; an API caller is staff (forms scope).

## What did the audit (F1) find?
- 245 GET routes and 353 `dry_run` writes ran on a sandbox: no 500; the 8 writes without `dry_run` were not called. None of the gaps needs a product decision; screen and route of each in `api/api-panel-coverage.md`:
  - G1 no `GET /inventory/moves` (stock history, `includes/fn/stock_moves.php`).
  - G2 no route writes the staff answer to a product question.
  - G3 ContactGroup does not say it is filled by rules; no Refresh now.
  - G4 no read or restore of a collection item's revisions.
  - G5 Customer has no bounce state; no Allow again.
  - G6 experiments start/pause/end take no `dry_run`.
  - G7 a dry-run page or file delete creates the Recycle Bin before its stop (`pg_recycle_folder_id(true)`), warning `sessionusername` on the API path.

## Where does it start?
- F3 and F4 now; G1-G7 are free work outside the F steps.
