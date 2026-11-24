---
name: launch-summary-files
description: Plan: the agency summary's customer page shows the steps still open, and a "What we need from you" line can ask for one file or several instead of text; LS1, LS2
priority: 6
---

## Why is this a plan?
- Owner (2026-11-24, in the architect's window): the customer's page listed only the done steps, and a line asking for a logo got a text box (`workflow/decisions-made-product-101.md`, the column: `workflow/decisions-made-schema-50.md`).
- Steps `[plan:launch-summary-files] LS<n> — <topic>`; both write `launch_summary.php` and `includes/fn/launch_summary.php`, so LS2 starts after LS1 landed. The table: `ui/launch-summary-lines.md`.

## What does each step build?
- LS1 Open steps on the customer's page. Above the list "{done} of {all} steps done"; the done steps as today, then the steps not done with a "Waiting" pill, in the set-up card's order. Never the reason, the blocker's text or a setting's value; a step hidden on the card is not listed. `pg_launch_summary_done_steps()` gains a pure sibling for all steps with their state; tests.
- LS2 A line that asks for files. The migration step adds `answer_kind` to `launch_summary_lines` with `install_add_column()` (0 text, 1 one file, 2 several files; existing rows 0). In the drawer every line has an "Answer" select: Text, One file, Several files. On the customer's page a file line draws a file field (several: `multiple`) and the list of what was sent, each with its name, size and a remove; no textarea.

## What are the rules of an upload?
- The page has no signed-in user: every upload checks the link (open, not expired, not withdrawn) and the line's kind on the server, and the page's CSRF token.
- Kept types: pictures (jpg, png, gif, webp, svg refused), PDF, office documents, zip; the type is read from the content, not the name. The site's upload limit per file; 20 files a line at most; the route is in `waf_sensitive_scripts()` and rate limited as the answers are.
- Stored through `pg_file_store_upload()` into a folder "From the customer" that is not public (served only through `get_file.php` to a signed-in panel user); never placed in a page, the logo or a gallery by the upload itself.
- The line's `answer` holds the file ids as a JSON list, so no second table; a file deleted in the file manager drops from the list when the line is read. A remove on the customer's page deletes the file. The bell line to the agency names the line and the count.
- A new file must not be executable or reachable by a guessed address; the tests cover a refused type, an expired link, a line of the wrong kind and the 21st file.

## How is each drawn?
- Look A from the Design examples screen: the pill as the card's own; the select and the file field from Fields; the sent files as gathered rows with a quiet remove. Phone: one column, the file names cut with an ellipsis.

## What is left out?
- Placing a file as the logo or into a gallery by itself; a customer account; files on a text line.
