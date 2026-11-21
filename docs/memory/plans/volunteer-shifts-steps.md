---
name: volunteer-shifts-steps
description: Plan volunteer-shifts, steps VS1a to VS9: pure rules, store, programmes, shifts and board, sign-up, calls, check-in and hours, certificate and report, API, close-out
---

## Which steps are there?
- VS1a (S) first, no schema (done, #10922): new `includes/fn/volunteer_shifts.php`, pure: who fits a role on a day (skills, trainings, a document valid then, free hours, no other shift), the waitlist pass, the cut-off, the call's pick, the show-up rate, hours sums, document notice days, the signed token. `tests/volunteer_shifts_test.php` red first: overlapping shifts, a document ending the day before, a cancel 23 and 25 hours before, two waiting. Features: G4, G7, G8, G10, G13 (the rules).

- VS1b (M) after VS1a: the five `volunteer_*` tables (poll 206, `workflow/decisions-made-schema-46.md`) as a migration step in the open version, their columns brought to the architect before ready; the five privacy registrations and `pg_contact_holders()`; a document's file (poll 208) behind `get_file.php` as `volunteer-shifts.md` says, that door brought to the architect before ready; no health report kind; their tests. Features: G2, G4 (the store).
  - Built (#10949), `core/volunteer-store.md`; columns approved with changes (decision schema-47); lands after WQ1b #10956 with the next free label.

- VS2 (M) after VS1b: Contacts › Volunteers: the programmes list and a programme's Set-up (form and its fields, roles, rules, messages, coordinators); applications with Approve; the volunteer's profile. Features: G1, G2, G3.
  - If it opens document uploading: the same PR leaves `volunteer-doc-` `files` rows out of the panel search and the file pickers (decision schema-47, `core/volunteer-document-file.md`); uploading is never opened without it.

- VS3 (M) after VS2: shifts with roles and counts, the weekly series, "Copy last week", the board. Features: G5, G6.

- VS4 (M) after VS3: "My volunteering" in My Account (`_pg_my_account_extras()`, its list and designer twin) and the signed-link page (new root `volunteer.php`, in `waf_sensitive_scripts()`): sign up, cancel, the waitlist pass, "Offer my place". Features: G7, G8, G9.
  - If it opens document uploading first: the same PR leaves `volunteer-doc-` `files` rows out of the panel search and the file pickers (decision schema-47, `core/volunteer-document-file.md`); uploading is never opened without it.

- VS5 (M) after VS4: the general job's passes: the unfilled call, the day-before reminder, the document notices; SMS and WhatsApp kind `volunteer`, a service message (poll 207); the profile's "Shift calls" switch turns the calls off. Features: G4, G10.

- VS6 (M) after VS4: the QR poster per place and the scan page (`event_check_in.php`'s way), the coordinator's list of the day, "Did not come", hours and their approval, hours outside a shift. Features: G11, G12, G13.

- VS7 (M) after VS6: the hours certificate (`certificate.php`'s way, a signed check code, nothing stored), the thank-you mails, the report and Excel. Features: G14, G15.

- VS8 (S) after VS7: the six events through `pg_announce()`, their catalogues and payload fixtures (`workspace/workspace-flows-adding.md`); the API routes with their types, `php tools/check_api_schema.php`; the MCP tools. Features: G16.

- VS9 (S) close-out: every G number checked against the code with its PR; `core/volunteer-shifts.md` written; this plan's files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- VS1a can start before the architect's answer. After VS4: VS5 and VS6. VS4 enters `waf.php` and `includes/fn/widgets_account.php`, VS5 `includes/fn/sms.php`, VS8 the webhook catalogue: check the open PRs before claiming.

Related: docs/memory/plans/volunteer-shifts.md, docs/memory/plans/volunteer-shifts-features.md, docs/memory/plans/volunteer-shifts-screens.md
