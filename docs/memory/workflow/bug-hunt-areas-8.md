---
name: bug-hunt-areas-8
description: The three bug-hunt areas of 2026-11-28 (k1, request 3610): help centre, Word documents edited in place (server side), link previews and Translate on a message; what each leaves alone
---

## Why were areas named?
- k1 (2026-11-28, agent channel, request 3610): five of its eight places stood free, `php tools/free_work.php` listed only blocked steps and steps being worked on, no `bug` issue was free, and the hunts of `bug-hunt-areas-7.md` had run.
- The limits of `bug-hunt.md` hold again: at most three hunts a coordinator, a plan step or an open `bug` issue goes out before a hunt, a hunt that ends is not replaced.

## Which areas?
- `help-centre` (HC1 to HC9): the public doors `support_request.php`, `kb_vote.php`, `article_suggest.php`; `includes/fn/support_requests.php`, `support_forms.php`, `kb_votes.php`, `widgets_support_requests.php`; `assets/js/pg_kb_vote.js`; on the mail side `includes/workspace/mail_priority.php` and the resolve-by time.
- `office-docx-edit` (DA1 to DA7), the server side of a Word document edited in place: `includes/fn/office_docx_*.php`, `office_edit.php`, `document_paragraphs.php`, and their callers `includes/explorer/handle_file_window.php` and `includes/workspace/file_edits.php`. The sheet side of `office_edit.php` was an area of `bug-hunt-areas-7.md`.
- `message-link-preview-translate`: `includes/workspace/link_preview.php` with its job in `job.php` (which addresses the server refuses to read, redirects, size and time limits, charset), the card and "Remove preview", and `assets/js/workspace_translate.js`.

## What is left alone?
- `style_designer.src.js`, as in every hunt.
- `assets/js/pg_office_view.js` while the slides preview's PR (#12272) is open.
- In `includes/workspace/messages.php` and `assets/js/workspace.js` only the preview and translate parts are read: the file group steps (`plans/channel-file-groups.md`) work in the same files.
- A fault in a file an open PR holds is a `bug` issue.

## Why these three?
- Each was built in the weeks before, is met by visitors or by every Workspace member, and no file under `workflow/bug-hunt*.md` named it on 2026-11-28.
- The help centre and the link preview take input from outside the panel: a visitor's form, vote and search text; a web address the server reads.

## Where are the findings?
- Each hunt writes its own `bug-hunt-areas-<area>.md` under the area names above.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-7.md
