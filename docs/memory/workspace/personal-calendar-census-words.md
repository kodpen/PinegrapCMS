---
name: personal-calendar-census-words
description: PC1 census (2026-11-14): words taken beside "plan" (Plan item, Planning Board, /workspace/plan, "#plan") and the names proposed for a private plan
---

## Which words are taken today?
- "Plan item" / "Plan items" (Plan kaydı / Plan kayıtları): the team's `ws_events` rows (screen.php, refs.php, calendar_feed.php, api_day.php).
- "Planning Board" (Planlama Panosu) in the menu, tour and screen; "Planning board" (Planlama panosu) on the dashboard's team card: one Turkish name since PC3c (`plan-board-grid.md`).
- "Plan" / "Plans" (Plan / Planlar) on design templates (membership and subscription plans).
- "Work Calendar" (İş Takvimi), "Calendar subscription" (Takvim aboneliği), "Only me" (Yalnızca ben), "Reminder" (Hatırlatma), "Steps" (Adımlar), "Add to calendar" (Takvime ekle). "My calendar", "Roadmap", "New plan" have no translation yet.
- In code: `ws_plan_grid()` and `ws_plan_day_state()` are the planning board; GET /workspace/plan is the board, /workspace/plan-items the plan items; the reference type `plan` ("#plan", aliases izin, toplantı, ziyaret) is a plan item.

## What is proposed on screen?
- "Plan" stays the owner's word for the new record, always "My plans" / "New plan" / "Plan" in the drawer, never alone in a list beside plan items.
- A plan with steps is shown as "Roadmap" (Yol haritası); its rows "Steps" (Adımlar).
- Architect (2026-11-14): the team's record keeps "Plan item"; the board's Turkish name is "Planlama panosu" everywhere (done in PC3c).
- "My calendar" (Takvimim), "Who can see" (Kimler görebilir), "Only me", "Chosen team members" (Seçtiğim ekip üyeleri), "The whole team" (Tüm ekip).

## What is decided in code and API?
- Architect (2026-11-14): prefix `ws_personal_plan_` (tables and functions), gate `ws_personal_plan_can_see()` (not `ws_plan_*`, the board's), reference type `personal_plan`.
- API path /workspace/personal-plans, scope `workspace.personal_plans` (PC8), webhook none.
- The reference is never `plan`; it is not offered in "#" search to others (`personal-calendar-census-readers.md`).
