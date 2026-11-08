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
- F2 (S) Form and field facts. Done (#7973), `api/api-form-facts.md`.
- F3 (M) Rich text in. Done (#8043): `api/api-form-rich-text-in.md`.
- F4 (S) MCP guidance. Done (#8042): `api/mcp-form-guidance.md`.
- F5 (S) Pictures in a post. Done (#8066): `POST /files` `form_id`, `api/api-form-rich-text-in.md`.

## What stays as it is?
- The visitor path (`custom_form.php`) with its CAPTCHA and spam rules; file upload and signature fields stay unsendable; an API caller is staff (forms scope).

## What did the audit (F1) find?
- 245 GET routes and 353 `dry_run` writes ran on a sandbox: no 500; the 8 writes without `dry_run` were not called. None of the gaps needs a product decision; screen and route of each in `api/api-panel-coverage.md`:
  - G1 `GET /inventory/moves` (stock history). Done (#8097).
  - G2 no route writes the staff answer to a product question. Done (#8116): `POST /comments/{id}/answer`.
  - G3 done (PR #8098): `by_rule` and `computed_at` on ContactGroup, `POST /contact-groups/{id}/refresh`.
  - G4 no read or restore of a collection item's revisions. Done (#8111): `api/api-form-submission-revisions.md`.
  - G5 Customer has no bounce state; no Allow again.
  - G6 experiments start/pause/end take no `dry_run`. Done (#8099).
  - G7 done (#8106): a dry-run page or file delete no longer creates the Recycle Bin; the real delete makes it as the key's owner.

## Where does it start?
- F1-F5 done. Left: G1-G7 (free work, one small PR each); then delete this file.
