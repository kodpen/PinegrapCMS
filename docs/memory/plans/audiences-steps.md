---
name: audiences-steps
description: Steps AU1-AU6 of plans/audiences.md: rule model and SQL builder, recompute job and announce, the rule editor, readers and doors, the audience token, close-out; claim as [plan:audiences] AU<n>
---

## Which run side by side?
- Wave 1: AU1 alone (questions 1-2 first). Wave 2: AU2, AU3 and AU4 at once (job, screen, readers: different files). Wave 3: AU5 after AU2 and `page-experiments.md` PX6. Wave 3b: AU5b and AU5c at once after AU5 (different files). Wave 4: AU6.
- S under 400 product lines, M about 1,000. Tests in `tests/audiences_<topic>_test.php` (pure functions, red first), memory under `core/`, texts through `tr_add.php`.

## Which steps are there?
- AU1 (M) Rule model and SQL builder. Done in #6158 (`core/audiences-rules.md`).

- AU2 (M) Recompute job. After AU1. Done in #6181 (`core/audiences-recompute.md`).

- AU3 (M) The rule editor. After AU1. Done in #6223 (`ui/contact-group-rules.md`, contact card door included).

- AU4 (S) Readers and doors. After AU1. Done in #6249 (`core/audiences-doors.md`).

- AU4b (S) Rows the recompute cannot reach. Done in #6307 (`core/audiences-recompute.md`, `core/audiences-doors.md`).

- AU4c (S) Switch review screen. Done in #6418 (`ui/contact-group-rules.md`, `core/audiences-doors.md`).

- AU5 (S) Audience token. Done in #6598 (`designer/audience-conditions.md`): `__audience_group_<id>` reads a dynamic group's rule rows as any xref row; the designer's group list carries the kind and labels a dynamic group.

- AU5b (S) Flow event "joined a group". After AU5; side by side with AU5c (different files). In progress (`workspace/contact-group-joined-event.md`). Decided in #6599 (owner: both triggers, once per contact who joined; `workflow/decisions-made-product-27.md`).
  - `contact_group.joined`, one `ws_events_in` row per added contact, recorded where `pg_announce_contact_group_members()` announces (every door and the recompute pass through it; a big pass queues thousands, drained by the existing event run); `contact_group.members_changed` stays the webhook event. Entry in `pg_event_workspace_events()` and `ws_events_catalog()` (record `contact`, right `contacts`, filter: the group, dynamic groups named with their kind); outputs `contact_id`, `group_id`, `group_name` (`ws_flow_event_outputs()`). Memory under `workspace/`.

- AU5c (S) Campaign-profile action "joined a group". After AU5; side by side with AU5b.
  - `contact_group_joined` appended to `email_campaign_profiles.action` and `email_campaigns.action` in one sub-step (`workflow/decisions-made-schema-10.md`); `action_item_id` the group: picker in the profile form, the import and the API (`pg_email_campaign_profile_action_rows()`); `create_auto_email_campaigns('contact_group_joined', $group_id, …)` per added contact from the same announce seam, under the purpose rules of every profile mail. Memory: `ui/email-campaign-profile-shared-form.md` grows.

- AU6 (S) Close-out. After AU1-AU5c.
  - PR body names `[NEW] Added contact groups filled by rules: orders, visits, consent, membership and fields decide who is in`; decisions to `core/`; the two plan files and the line in `feature-plans-2026-11.md` deleted; `php tools/memory_index.php`.
