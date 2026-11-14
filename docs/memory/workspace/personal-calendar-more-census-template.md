---
name: personal-calendar-more-census-template
description: PC10 census (2026-11-14) for PC12 team roadmap templates: how a roadmap is copied today, what Save as team template and Move the following steps too need, the tables proposed
---

## How is a roadmap copied today?
- "New roadmap" on a customer's card (`ws_customer_card_roadmap_offer()`, personal_plan_contact.php) offers "Empty roadmap" and "Copy one of my plans" (the reader's 5 latest own plans, `WS_PERSONAL_PLAN_CARD_ROWS`).
- A copy writes nothing: `&from=<plan>` fills the New plan drawer by `ws_personal_plan_copy_draft()` and the pure `ws_personal_plan_copy_shift()`: title, note, customer, steps; it starts today, keeps its length, time of day and each step's distance to the start in calendar days; nothing done, no task, no reminder, Only me (`workspace-personal-plan-customer.md`).
- Steps live in `ws_personal_plan_steps` (`position`, `title`, `day`, `done_at`, `task_id`), at most `WS_PERSONAL_PLAN_STEP_MAX` (`workspace-personal-plan-roadmap.md`).

## Why not keep a template as a plan row?
- Every reader of `ws_personal_plans` (between, of contact, reminders, the API, the guard test) would have to leave templates out, and the gate is about one owner and a share, not "the whole team may use, only the maker may change". A template has no days, no customer, no reminder and no people, so its own tables keep it out of every plan reader.

## Which tables are proposed?
- `ws_roadmap_templates`: `id` INT UNSIGNED AUTO_INCREMENT; `user_id` INT UNSIGNED NOT NULL DEFAULT 0 (the maker, the only one who changes or deletes it); `title` VARCHAR(255) NOT NULL DEFAULT ''; `all_day` TINYINT(1) NOT NULL DEFAULT 1; `start_minute` SMALLINT UNSIGNED NOT NULL DEFAULT 0 (time of day of a timed plan); `length_minutes` INT UNSIGNED NOT NULL DEFAULT 0 (the plan's length); `created_at`, `updated_at` INT UNSIGNED NOT NULL DEFAULT 0; key `user_id`. InnoDB, utf8mb4.
- `ws_roadmap_template_steps`: `id`, `template_id` INT UNSIGNED, `position` SMALLINT UNSIGNED, `title` VARCHAR(255), `day_offset` SMALLINT UNSIGNED NOT NULL DEFAULT 0 (days after the first step's day), `has_day` TINYINT(1) NOT NULL DEFAULT 1 (a step with no day stays without); key `template_id`.
- Not kept: the note (it may name the customer), the customer, the people, a step's done mark and task. So a template holds no name but its maker's (the plan's rule).

## What reads and changes a template?
- Seen and used by a team member (`ws_team_ids()`); never a guest or a site member; changed or deleted by `user_id` alone. "New roadmap" adds the team's templates under the person's own plans; using one fills the New plan drawer the way a copy does (`&template=<id>`), the first step on today. Nothing is written until Save.
- Account deletion: owner question, the template stays for the team (maker shown as a former member, nobody changes it) or goes with the account.
- Not in the API or MCP until the owner says so (as PC6 left the card out).

## What does "Move the following steps too" need?
- Nothing stored: when a step's day changes in the drawer, the later steps (by `position`) move by the same number of calendar days before the steps are saved by `ws_personal_plan_steps_save()`. No column.

## What must PC12 not change?
- The plan gate and the plan tables' shape, the Work Calendar, the planning board and capacity, the channel templates and flow templates (`ws_flow_templates`): a roadmap template is none of them.

Related: `personal-calendar-more-census-repeat.md`, `personal-calendar-more-census-write.md`.
