---
name: trendyol-return-rejection
description: Rejecting a Trendyol return (claim issue): the reasons read with the returns, which need a document, the query-plus-multipart call, the screen's form and checks, what stays unverified (PR #1837)
---

## Where is a return rejected?
- Returns card of `marketplace_settings.php` (`trendyol-returns.md`): a folded "Reject" form under a return with waiting units, where the connector can `reject_claim`. Action `claim_reject`. Ticked units are intersected with the session read's waiting units, the reason must be one from the session's reason read; nothing the marketplace did not name is sent.
- Success sets `rejected` on that return in the session ("Rejection sent" pill, no approve or reject button until the next read) and writes an activity-log line. Statuses are not changed locally.

## Where do the reasons come from? (`mp_trendyol_claim_issue_reasons()`)
- `GET /integration/order/claim-issue-reasons` (not seller-scoped) → `[{id, name}]`, name in Turkish. Read together with the returns on "Read returns", kept as `marketplace_read[<account>]['claim_reasons']`; a failed read is a warning and keeps the last good list. Without a list the form says to read again.
- They answer the Turkish and global reasons together without marking which; all are offered, a wrong one is refused by them.
- `file` = reason needs a document: every reason except 451 (analysis), 1651 (package never arrived), 2101 (exchange asked in a question) (`mp_trendyol_claim_reason_needs_file()`). 1651 cannot be chosen in the first 24 h after WaitingInAction; their refusal is shown.

## How is the call sent? (`mp_trendyol_reject_claim()`)
- `POST /integration/order/sellers/{sellerId}/claims/{claimId}/issue?claimIssueReasonId=&claimItemIdList=<ids, comma>&description=<rawurlencoded>`, body `multipart/form-data` with one part `files` per document (an empty multipart when none). Success: any 2xx, body ignored.
- Their guide (modified 2026-10-14) puts the three fields in the query; the API reference (2026-01-28) lists them in the multipart body. The guide's form is sent, each field once: a Spring endpoint reads a field in both places as two values.
- `mp_trendyol_call()` sends a text body as is, with its `$content_type` argument (default JSON).

## Which checks run before the call? (`mp_trendyol_claim_reject_problem()`)
- Reason id 1–18 digits, description trimmed, 1–500 characters, valid UTF-8; at most 5 files (ours), each non-empty and at most 5 MB (theirs); extensions jpg, jpeg, png, pdf, tif, tiff, doc, docx, mp4, mov (`mp_trendyol_claim_file_types()`); a document when the reason needs one.
- The screen reads `$_FILES['claim_files']` with `mp_settings_claim_files()` (name through `pg_basename()`, `is_uploaded_file()`); a file the server did not take whole is an error. An over-`post_max_size` post answers 413 via `pg_post_body_discarded()`.

## What is unverified?
- No live call. Whether the query form is what they read (vs the reference's body fields); whether a multipart body with no part is accepted for reasons that need no file; the answer body; the reason list as their production answers it; whether units of one return can be rejected in part.

Related: docs/memory/commerce/trendyol-returns.md, docs/memory/commerce/trendyol-api-facts.md
