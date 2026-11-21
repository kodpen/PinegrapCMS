---
name: privacy-rules-card
description: Privacy centre › Inventory's Retention rules card (KV3b): switch rows with periods, each form's own period, the interval, Dry run with its effect lines and sample, the yes; anchor privacy-rules
module: privacy_centre
---

## Where is it?
- Contacts › Privacy centre, Inventory view, between Personal data and Transfers: the card "Retention rules", `id="privacy-rules"`; a link to the rules targets `privacy_centre.php#privacy-rules`. The dry run's block is `#privacy_rules_dry_run`.
- Drawn by `pg_privacy_centre_rules_card()` and `pg_privacy_centre_dry_run_card()` (`includes/fn/privacy_centre.php`); the work is `includes/fn/privacy_retention.php` (`privacy-retention-rules.md`), only called.

## How is it put together?
- One form: a switch row (Design examples › Switch rows) a rule of `pg_privacy_rules_catalogue()`, its name from `pg_privacy_rules_labels()['rules']`, its line the state, a pill, Dry run (quiet, only when on), the period field with its unit, the switch. A rule the catalogue gains (VS1b's `volunteers`) gets a row with the hourglass tile.
- State words, the same as Site Settings › How long we keep data: "Off: kept until deleted"; "On, waiting for a dry run and your yes: kept until deleted" with an amber "Waiting for your yes"; "Runs every night" (or every N days) · last run, teal "Running" (`pg_privacy_centre_rule_state()`).
- Under the rows one line of what no rule removes (P8), then "Each form's own period" folded: forms with submissions or a stored period (`pg_privacy_centre_rule_forms()`), empty follows the rule, 0 keeps the form. Then "Run the rules every" (days, 1 to 183) and Save (quiet: the toolbar's Download Excel is the view's fill).
- Posts `action=rules_save` with `rules[<key>][on|period]`, `rules[forms][pages][<page id>]`, `interval` to `pg_privacy_rules_save()`; an invalid field is one error naming the rule. A save that changes a rule drops its yes (the save function's rule).

## How do the dry run and the yes work?
- Dry run is a GET (`?dry_run=<key>`), the stored rule, nothing written: `pg_privacy_rule_dry_run()`. Shown as effect lines (Design examples › Confirm with its effects, `pg_privacy_centre_dry_run_effects()`): how many records reach the period and how many of the first 200 were checked, what goes by erase entry (`pg_privacy_erase_labels()`), who is left alone by skip, and up to five sample ids linked to their screen (`pg_privacy_centre_rule_sample_url()`).
- While the rule waits, "Yes, run this rule" (danger) posts `action=rules_approve` to `pg_privacy_rule_approve()`; it is offered with zero records too, so a rule may be approved before anything is due.
- Who: `pg_privacy_rules_may_change()` (role <= 2). Another role sees the fields disabled, no Save, no yes, and one line saying who changes them. The token is checked before either write.

## Tests and what was not run?
- `tests/privacy_centre_test.php`: the state words, the effect lines, the sample screens, the card's field names and anchor, the yes only for the role that may change, the screen calling the rule functions after the token check.
- Not run on a database or in a browser when built: the dry run's queries are KV3's, first seen on dev.

Related: [[privacy-retention-rules]], [[privacy-centre-screen]]
