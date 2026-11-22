---
name: privacy-request-answer
description: "Prepare the answer", the Answer tab of a privacy request's drawer (KV4b, #10894): four numbered steps, the gather (export counts or erase dry run), the kind's text, Send / Erase and send
module: privacy_requests
---

## How is the Answer tab built?
- `pg_privacy_request_answer_pane()` in `includes/fn/privacy_requests.php`: Bootstrap 6's `ol.stepper.pg-stepper-cards` (`ui/step-rail.md`) drawn by `pg_privacy_request_answer_stepper()`. KV4b adds no Design examples section; DX2 (#10885) draws numbered steps there (architect, channel message 11228).
- Four steps: "Check who asks", "Gather", "Write the answer", "Send". Each folds in a `<details name="privacy_answer_step">`, so one is open at a time; a done step is `.active.theme-success`, the current one `.active` with `aria-current="step"`, a later one plain; `.theme-reset` on the details keeps the theme out of its buttons.
- States from `pg_privacy_request_answer_steps()`: before the check only step 1 is open; after it Gather is open and Write is current. A gather this operator may not run (`refused`, `self`) stops at Gather and Send stays disabled.

## What does each step do?
- Check who asks: the "I checked who asks" box (`verified_at`, moved here from the Request tab) and a quiet Save; the screen comes back on the Answer tab (`tab` field).
- Gather runs only once checked, when the drawer is drawn (`pg_privacy_request_gather()`): an erasure runs the erase dry run (`pg_privacy_erase(..., false)`), listing what is erased and what the law keeps with personal fields replaced; every other kind the export's counts by section, and a link to Personal data export. Nothing is written and no audit line.
- Write the answer: a textarea filled from `pg_privacy_request_answer_text()` (greeting, the kind's paragraphs with the gathered lists, "Kind regards," and the site's name); staff changes it. The e-mail carries no file: the information answer lists the records and, under the text, a signed download link valid 7 days (`privacy-request-export-link.md`).
- Send: who it goes to and what follows. The button is the drawer's foot.

## How does the foot change?
- On Answer: Cancel and "Send the answer" (fill), or for an erasure "Erase and send" (danger, a confirm that it cannot be undone), disabled until the steps allow it. On Request and History: Refuse, Cancel, Save. The script swaps them by `data-privacy-foot`.
- `action=send` calls `pg_privacy_request_send()`; its codes become the screen's errors (mail not sent: nothing was erased).

## What was fixed on the way?
- The Requests view called `pg_list_table_when_cell()` without loading `includes/fn/list_table.php` (fatal with one row).
- History read `audit_log.actor_name`, which does not exist (the tab was always empty): `pg_privacy_request_history_query()` selects `user_id, username`, pinned by a test.
- The drawer named by the address opened before Bootstrap's module had run, now through `pgUI.ready()`. On a phone the sheet is 88vh (`--pg-drawer-height`).

## Tests?
- `tests/privacy_request_answer_test.php`: the step states, the gather mode and lists, each kind's text, the stepper markup. A sent answer was not seen: the sandbox has no mail server.

Related: [[privacy-requests]], [[personal-data-erasure]], [[personal-data-export]]
