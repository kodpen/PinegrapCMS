---
name: bootstrap-6-panel-steps
description: Steps B0-B12 of plans/bootstrap-6-panel.md with dependencies and status (each done step names its PR); claim as [plan:bootstrap-6-panel] B<n>
---

## Which steps run before the switch (on 5.3.8, nothing visible changes)?
- Done: B0a, B0b, B1a, B1b, B2a, B2b, B3, B4, B5, B6, B7, B8 (`bootstrap-6-panel-done.md`).

## What happens after the switch?
- B8, the switch, is done (#1866, `bootstrap-6-panel-done.md`); B9 can start.
- B9 Component swaps on v6, one claim each (`bootstrap-6-components.md`): B9a Chips (done, #2208), B9b Combobox (done, #2243), B9c Datepicker and time (done, #2262), B9e Range (done, #2207), B9f form-adorn, B9g Stepper (done, #2206), B9h hover-lift (done, #2217, #2237), B9i Strength, B9j Avatar, B9k research proposals.
- B9j Avatar in slices (`ui/panel-avatar.md`): j1 server initials and tone, `pg_avatar_markup()`, header account button and menu (#2216); j2 Workspace screens, `tasks.php`, `workspace_settings.php` (#2242); j3 chat, dashboard widgets 4/28/30, designer collaborators' letters, contacts list, order screen, guest page, delegate tone (#2256); left: `edit_contact.php` (held by #2246 at the time).
- B9i: the panel meter is done (#2211, `ui/password-strength-js.md`). Left as B9i2, after B9g (#2206, which holds `install/index.php`) merges: the installer's `.pg-strength` becomes `pg_password_strength_meter('admin_password', false)` plus `pg_password_strength_script()`, and its own scoring script and CSS go.
- B10 Token tuning, B11 redesign, B12 jQuery UI out of the panel: `bootstrap-6-redesign.md`.

## Where do I start?
- After the claim check, take the lowest open step whose dependencies are done and that no open PR holds; independent steps run in parallel sessions, one draft PR each; a step over about 1,000 product lines goes in several PRs, one after the other (`workflow/working-mode.md`). B1-B5 are independent; B6 needs B2a; B7 needs B1a and B2a; B8 needs B1a-B7; B9 needs B8 (B9c also B5); B10 needs B9; B11 needs B10; B12 needs B9c.
- Pre-switch PRs show their screens unchanged on the B1b screenshots (`ui/panel-screenshots.md`); later PRs show before and after. Mark a step done here with its PR numbers; once all its parts are done, its line moves to `bootstrap-6-panel-done.md`.
