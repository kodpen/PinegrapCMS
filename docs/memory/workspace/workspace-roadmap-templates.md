---
name: workspace-roadmap-templates
description: Team roadmap templates (PC12, #9562): Save as team template, the two tables, who changes or deletes one, Team templates under New roadmap, Move the following steps too
---

## Where does it live?
- `includes/workspace/roadmap_templates.php` (loaded by `bootstrap.php`), tables `ws_roadmap_templates` and `ws_roadmap_template_steps` of step 11.0.208 (the decisions list, schema part 20; the train may renumber the label).
- It names no plan table: it reads a plan only through `ws_personal_plan()` and `ws_personal_plan_step_rows()` (`tests/workspace_roadmap_templates_test.php`). A template is never a plan: no plan reader, the gate or the API sees it. Why own tables: a template has no days, customer, reminder or people, and its rule (the team uses, the maker changes) is not the gate's.

## What does a template keep?
- The plan's title, `all_day`, `start_minute` (time of day of a timed plan), `length_minutes` (an all-day plan in whole days × 1440), and each step with a title: `position`, `title`, `day_offset` (calendar days after the earliest dated step), `has_day` (a step with no day keeps none). Pure: `ws_roadmap_template_from_plan()`.
- No note, customer, people, done mark or task: a template names nobody but its maker (`user_id`).
- At most 200 per person (`WS_ROADMAP_TEMPLATE_MAX`); a roadmap with no titled step is refused ("Add a step to the roadmap first.").

## Who sees, changes and deletes one?
- Every team member sees and uses it (`$viewer['member']`); a guest never.
- Only the maker changes it (`ws_roadmap_template_can_change()`), and only while on the team.
- Deleted by the maker, or by a manager (roles 0 to 2; Designer 1 is above Manager 2) once the maker's account is gone (`ws_roadmap_template_can_delete()`, `ws_people()` gives `gone`). Owner (2026-11-14): the template stays for the team after the maker's account is deleted and nobody changes it; it never blocks an account's deletion (`pg_user_delete()` does not touch it). Its row shows "Former member".

## How is one made?
- "Save as team template" under the roadmap of the person's own saved plan (`workspace_my_calendar_roadmap.js`): it saves the drawer first, then `ws_roadmap_template_save`. A maker who already has templates picks "As a new template" or "In place of: <title>" (one of their own) and "Save the template". The toast names the template.
- Architect (look, channel 3574): one template control at a time: while the chooser is open, "Save as team template" is hidden and the chooser has "Save the template" and "Cancel" (which brings the first button back).

## How is one used?
- "New roadmap" on a customer's card lists "Team templates" after "Copy one of my plans": up to 100, the latest first (`WS_ROADMAP_TEMPLATE_LIST_ROWS`); the first five show, then "Show all (n)" (architect, 3574). Each row: "<n> steps · <maker>" (the name `ws_people()` gives) and, for whoever may delete it, a trash button ("Delete the template"). It asks first with the panel's confirm, naming the template and saying it is removed for the whole team; the roadmaps made from it stay.
- A template's link opens My calendar with `&template=<id>`; `ws_roadmap_template_draft` fills the New plan drawer (`ws_roadmap_template_shift()`, pure): it starts today, the earliest step today and each other step its offset later, no note, the card's customer, Only me. Nothing is written until Save.
- Not in the API or MCP (the test checks `api.php` names none).

## What does "Move the following steps too" do?
- A switch under the steps (shown with two dated steps or more, off by default), with the muted line "Changing a step's day moves the later steps by the same number of days." While on, changing a step's day moves every later step (by position) with a day by the same calendar days; a step with no day keeps none. Pure `PGWsRoadmap.shiftFollowing()`, tested in Node. Nothing is stored: the steps are saved as before.

Tests: `tests/workspace_roadmap_templates_test.php`.
