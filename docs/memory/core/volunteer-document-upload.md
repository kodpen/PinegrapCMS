---
name: volunteer-document-upload
description: "Upload a new one" under My documents (VS4c): who uploads, the kind, number and date checks, PDF or picture by first bytes, 10 MB cap, 20 documents, the stored row and the Open links
module: volunteer_documents
---

## Where is it?
- Under "My documents" on the signed page `volunteer.php` and in My Account's volunteering section: a folded "Upload a new one" form (Document, Document number (optional), Valid until, File), one multipart POST to `volunteer.php` with `action=document`. Built in step VS4c (`volunteer-shifts.md`).
- Code: `includes/fn/volunteer_documents.php` (loaded by `volunteer_page_view.php`), tested in `tests/volunteer_documents_test.php`.
- Shown only when the programme's roles need a document kind (`pg_volunteer_programme_kinds()`); the kind is chosen from those, never typed.

## What is checked?
- `pg_volunteer_doc_input_check()`: a listed kind, a real `YYYY-MM-DD` from today on and at most 50 years ahead, the number cut to 100 characters.
- `pg_volunteer_doc_file_check()`: PDF, JPG, JPEG, PNG or WebP by extension, the upload blocklist, at most `PG_VOLUNTEER_DOC_MAX_BYTES` (10 MB) and never above the server's file limit; `pg_volunteer_doc_sniff()` then matches the first bytes to the extension, so a page renamed `.pdf` is refused.
- At most `PG_VOLUNTEER_DOC_MAX_COUNT` (20) documents per volunteer; a body over `post_max_size` gets the 413 "request was too large" page.
- Each refusal has its own sentence in `pg_volunteer_answer_text()` (`doc_kind`, `doc_date`, `doc_missing`, `doc_file`, `doc_size`, `doc_full`, `doc_failed`).

## What is stored?
- A `files` row named `volunteer-doc-<volunteer id>-<random hex>.<ext>`, `folder = 0`, `attachment = 1`, `user` = the signed-in user or 0; then a `volunteer_documents` row (kind, number, valid_until, file_id, added_by). A failed second write removes the file and its row.
- An upload counts for sign-ups at once: the fit rule reads only `valid_until` (`core/volunteer-shift-rules.md`); `seen_by`/`seen_at` stay 0.

## Who opens the file from where?
- The volunteer: "Open" beside the document on their page; the coordinators and roles 0-2: "Open file" in the profile drawer (`edit_volunteer_programme.php`). Both go through `get_file.php`'s volunteer door (`core/volunteer-document-file.md`). The number is still never listed.

Related: docs/memory/core/volunteer-document-file.md, docs/memory/core/volunteer-signup.md, docs/memory/core/volunteer-programmes.md
