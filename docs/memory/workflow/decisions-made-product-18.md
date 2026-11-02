---
name: decisions-made-product-18
description: Decisions list, product part 18 (2026-11-01 afternoon on): search and audits; editor presentation and silent video; redacted Workspace replace; a thank-you page behind the registration gate
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## Search and audits: one screen, defaults, the editor's accessibility findings and measurements (2026-11-01)
- Owner (2026-11-01, PR #4693, `plans/search-and-audits.md`): one panel screen "Search and audits" for the index, structure, accessibility and backup engines (Pages tools menu, main menu, Site Settings › SEO), the popup window gone; the three jobs on for a new install and unchanged on a running site until switched on; the editor's audit panel lists the draft's accessibility findings beside SEO; contrast and target size measured in the editor are kept with the report, marked as measured there, replaced on each save.

## Image editor animation: presentation only in the editor, silent video (2026-11-01)
- Owner (2026-11-01, #4714, #4716): a document's scenes are presented in the editor's full-screen Present mode and exported as PDF, video and GIF; nothing is added to the live site. Saved videos carry no sound; a music track may follow as its own step if asked.

## Workspace: who sees the original after a redacted picture replace (2026-11-01)
- Owner (2026-11-01, #4783): after a redacted replace of a picture posted in a channel, the earlier (unredacted) version is listed in the message's history only for the person who made the edit; everyone else reading the channel sees the redacted file alone. The file stays in the file manager under its folder's access, so the reversibility chosen for Files (#4203) holds. Reason: a redaction that every reader could undo from the history list protected nobody. Shape: `decisions-made-schema-7.md`.

## A form's thank-you page in the `registration` folder opens for the person who just sent the form (2026-11-01)
- Owner (2026-11-01, #4938; bug #4804): the `registration` folder gate (`get_page.php`) lets a visitor without a session through only when all three hold: the page is a custom form's confirmation page (`confirmation_page_id`), the request carries `r=<code>`, and that code is in the session's `submitted_form_reference_codes`. The record view keeps its own `submitter_only` rule, so only that record shows. Why: the templates' booking and contact forms open an account on the first submission, so a returning visitor who has not set a password was sent to the sign-in page instead of "your request has been received". Running sites: the only change is that the person who just submitted sees their own thank-you page; no other folder rule changes. Not chosen: a redirect to the sign-in page with a notice (the visitor still saw no record), moving the templates' pages out of the folder (new installs only).
