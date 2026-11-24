---
name: volunteer-document-file
description: A volunteer document's file: a files row (attachment 1, folder 0, volunteer-doc- name), get_file.php's door, and the search and picker exclusion due with uploading
---

## Who opens a document's file?
- `get_file.php`, before the folder's check: an attachment a `volunteer_documents` row holds opens for roles 0-2, the programme's coordinators, the volunteer signed in (by contact) and the volunteer of the session's signed link (`$_SESSION['volunteer']['ids']`); `pg_volunteer_document_access_verdict()`.
- Anyone else: sign in, or 403; answered `private, no-store`; the form attachment check is skipped. A document file is written with `attachment = 1`, or the door is not asked.

## Where is a document's file stored?
- Architect (2026-11-21, decision schema-47): a `files` row with `attachment = 1`, `folder = 0` and a `volunteer-doc-` name, so it is in no File Manager folder.
- Condition: the step that opens uploading (VS4c, "Upload a new one" on the volunteer's page) leaves these rows out of the panel search and the file pickers in the same PR; uploading is never opened without it.

Related: docs/memory/core/volunteer-store.md, docs/memory/core/volunteer-shift-rules.md
