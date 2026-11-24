---
name: volunteer-shifts-steps
description: Plan volunteer-shifts, steps VS1a to VS9: pure rules, store, programmes, shifts and board, sign-up, calls, check-in and hours, certificate and report, API, close-out
---

## Which steps are there?
- VS1a (S) first, no schema (done, #10922): new `includes/fn/volunteer_shifts.php`, pure: who fits a role on a day (skills, trainings, a document valid then, free hours, no other shift), the waitlist pass, the cut-off, the call's pick, the show-up rate, hours sums, document notice days, the signed token. Features: G4, G7, G8, G10, G13 (the rules).

- VS1b (M) after VS1a (done, #10949): the five `volunteer_*` tables (poll 206, `workflow/decisions-made-schema-46.md`); the privacy registrations; a document's file behind `get_file.php`. Features: G2, G4 (the store).
  - Built (#10949), `core/volunteer-store.md`.

- VS2 (M) after VS1b (done, #11136): Contacts › Volunteers: the programmes list and a programme's Set-up (form and its fields, roles, rules, messages, coordinators); applications with Approve; the volunteer's profile. Features: G1, G2, G3.
  - Built (#11136), `core/volunteer-programmes.md`; uploading not opened, so the exclusion goes with VS4c.

- VS3 (M) after VS2 (done, #11163): shifts with roles and counts, the weekly series, "Copy last week", the board. Features: G5, G6.
  - Built (#11163), `core/volunteer-board.md`; its Call volunteers goes by e-mail.

- VS4 (M) after VS3: "My volunteering" in My Account (`_pg_my_account_extras()`, its list and designer twin) and the signed-link page (new root `volunteer.php`, in `waf_sensitive_scripts()`): sign up, cancel, the waitlist pass, "Offer my place". Features: G7, G8, G9.
  - Built (#11217), `core/volunteer-signup.md`: the sign-up store, the signed page, the link from the profile drawer, the swap approval, the My Account section and flag.

- VS4b (S) after VS4 (done, #11251): the `account.volunteering` list and its designer twins, as `account.tickets` (`commerce/event-tickets-page.md`). Features: G7.
  - Built (#11251), `core/volunteer-account-list.md`.

- VS4c (S) after VS4 (done, #11279): "Upload a new one" on the volunteer's page; `core/volunteer-document-upload.md`. Features: G2.

- VS5 (M) after VS4 (done, #11250): the general job's passes: the unfilled call, the day-before reminder, the document notices; SMS and WhatsApp kind `volunteer`, a service message (poll 207); the profile's "Shift calls" switch turns the calls off. Features: G4, G10.
  - Built (#11250), `core/volunteer-messages.md`.

- VS6 (M) after VS4 (done, #11256): the QR poster per place and the scan page (`event_check_in.php`'s way), the coordinator's list of the day, "Did not come", hours and their approval, hours outside a shift. Features: G11, G12, G13.
  - Built (#11256), `core/volunteer-hours.md`.

- VS7 (M) after VS6 (done, #11285): the hours certificate, the thank-you mails, the report and Excel; `core/volunteer-certificate.md`, `core/volunteer-report.md`. Features: G14, G15.

- VS8 (S) after VS7 (done, #11307): the six events through `pg_announce()`, their catalogues and payload fixtures (`workspace/workspace-flows-adding.md`); the API routes with their types, `php tools/check_api_schema.php`; the MCP tools. Features: G16.
  - Built (#11307), `core/volunteer-events.md`, `api/api-volunteers.md`.

- VS9 (S) close-out: every G number checked against the code with its PR; `core/volunteer-shifts.md` written; this plan's files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- VS1a can start before the architect's answer. After VS4: VS5 and VS6. VS4 enters `waf.php` and `includes/fn/widgets_account.php`, VS5 `includes/fn/sms.php`, VS8 the webhook catalogue: check the open PRs before claiming.

Related: docs/memory/plans/volunteer-shifts.md, docs/memory/plans/volunteer-shifts-features.md, docs/memory/plans/volunteer-shifts-screens.md
