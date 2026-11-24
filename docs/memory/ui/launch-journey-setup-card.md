---
name: launch-journey-setup-card
description: Dashboard card "Set up your site" (cards/launch.php): the launch_journey config_text row, the facts reader, "Do it", "Not for this site", "Hide set-up", the purpose dialog, General's purpose field
module: launch_journey_site
---

## Where is it?
- Card id `launch` in `pg_dashboard_v2_cards()` (General, width L, gate `launch`: role 0 and the card not closed, `pg_dashboard_v2_launch_gate()`); data from `includes/dashboard/v2/cards/launch.php`, renderer `pgDash.renderers.launch` in `assets/js/pg_dash_cards_launch.js`, styles "General cards: Set up your site" in `pg_dash.src.css`. Tests: `tests/launch_journey_test.php`, `tests/dashboard_v2_cards_test.php`.
- New dashboard only. `pg_dashboard_v2_launch_first()` puts the card first in any stored or preset layout that lacks it, so an administrator gets it without editing the dashboard; the gallery offers it too.

## What is stored?
- One `config_text` row `launch_journey` (`PG_LAUNCH_JOURNEY_ROW`), JSON: `purpose` ('' until chosen), `suggested` (a template's purpose), `live_at`, `hidden` (card steps), `closed`. No schema (`data/config-table-no-varchar.md`). Read `pg_launch_journey_stored()`, written `pg_launch_journey_store($changes)`; `pg_launch_journey_stored_change()` keeps keys a later step adds.
- `hidden` is cleared only when a closed card is brought back.

## Where do the facts come from? (`includes/fn/launch_journey_site.php`, `pg_launch_journey_facts()`)
- https `check_ssl_status()`; certificate from the operations alert state file (`pg_ops_alert_state()['cert']`), never a call from the request; mail `pg_mail_settings_ready()` and `pg_mail_domain_health()` (DNS lookups, only when a way to send is set); legal `pg_legal_kvkk_settings()`, `pg_consent_enabled()`, `pg_legal_documents_enabled()`; robots `config.additional_robots_content`; home page `page.noindex`.
- Search Console and Analytics `pg_connections_screen_state()`; payments (shop purpose with the shop on) `pg_launch_journey_payment_facts()`; booking hours (clinic) `booking_staff.hours`; backup `pg_backup_age_state(pg_backup_newest())`; cron `pg_ops_cron_stalled()` over `pg_cron_health_last_runs()`; team: another person's `dashboard_layout.signed_in_at`; maintenance `pg_maintenance_holds()`.
- Never a System Status check by its English title. A source that cannot be read leaves its key out: the row says "Could not be checked".

## What does the card do?
- Head "N of M done", a pill Blocked / In progress / Ready, the card menu "Change purpose" and "Hide set-up". Rows blockers first (`pg_launch_journey_card()`), each with an icon tile, its line ("SPF found, DKIM missing, DMARC found"), "Do it" (a `#settings/<category>/pgset-*` anchor or a screen, `pg_launch_journey_step_face()`) and a menu "Not for this site"; a blocker also shows the pill "Must be fixed" and has no menu; a step done shows a check.
- Saves go through the panel action `launch_journey_save` {purpose | hide | close}: general gate, token, role 0 in the handler, values kept by `pg_launch_journey_request_changes()`; then the card is drawn anew.
- The card reads the states again when the tab is shown again and when the Site Settings dialog closes.
- Every step done or hidden: the card stores `closed` and answers denied, which takes it off the dashboard. "Ready to go live?" and the go-live check: `ui/launch-journey-go-live.md`.

## How is the purpose chosen?
- While no purpose is stored the dialog "What is this site for?" opens once per page: eight radio cards (`.pg-start` starting-point cards, one a row under 576 px), "Choose" the fill, "Later" quiet; the card meanwhile follows the purpose Other.
- A design template install (`includes/install_design_template.php`) notes `pg_launch_journey_template_purpose()` as `suggested`, picked in the dialog; an unknown template leaves nothing picked.
- Site Settings › General › Organization: "What is this site for?" and the switch "Show the set-up card on the dashboard", drawn and saved for role 0 only (`general.save.php`, guarded by the hidden `launch_form`).
