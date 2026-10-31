---
name: marketplace-settings-screen-a11y
description: Marketplaces account screen: phone-width rows, emphasis inks, named dialogs, current account marked, formatted counts, a refused Connect or Save drawn again
---

## Why do question rows not use .mp-row?
- `.mp-row` is a six-column grid with `min-width: 760px`, meant for the orders and returns blocks inside `.mp-scroll`. The Trendyol questions sit outside it, and `d-block` only changed the display (the utilities layer comes after `@layer custom`), so the 760px width stayed and a 390px phone scrolled the whole page sideways.
- Question rows are `.mp-question`: padding and the top border, no grid, no minimum width.

## How does a failure row fit a phone?
- `.mp-fail` is `d-flex flex-wrap`. The name is `.mp-fail-name` (`flex: 1 1 150px; min-width: 0`); the message takes the full width below md and shares the row from md up. A fixed 150px name and a nowrap time left the message about 80px.

## Which colours carry status and secondary text?
- Green and red text (`.mp-pill.on`, `.mp-pill.bad`, `.mp-err`) use `--pg-success-fg-emphasis` / `--pg-danger-fg-emphasis`; the fills were 1.85:1 and 2.74:1 on the light card. Borders keep their fill mixes.
- Column heads, Paused/Test pills, the account card's small line, count captions and the failure time use `--pg-fg-2`, not opacity (`panel-content-contrast.md`).

## Names and state
- `#mp_new` and `#mp_drawer` carry `aria-labelledby` pointing at `mp_new_title` / `mp_drawer_title` (`panel-accessible-names.md`).
- The account card on show (`.mp-tab.sel`) carries `aria-current="page"`.
- The stale-match and push-all notices format their count with `pg_format_number($n, 0)` like the rest of the screen.

## What does a refused Connect or Save show?
- `#new_name`, the Connect dialog's text credentials and `#edit_name` are `required`; the hidden marketplace blocks are disabled, so they are not checked. The drawer's credential boxes stay optional: blank keeps what is stored.
- A refusal the server still makes (blank name, unknown marketplace) does not redirect, so `$mp_refused` (`create`/`update`) draws the form from `$_POST` in the same request: the posted marketplace selected, name, non-secret text credentials and Send prices as posted (`mp_settings_credential_field()` draws a create value, never into a secret box, which keeps `autocomplete="new-password"`). The script reopens `#mp_new` or `#mp_drawer` with `pgUI.ready()` + `pgUI[kind].show()`.
- A refused Save takes `$selected_id` from the posted `account_id`: the post goes to the bare address, and the screen used to jump to the first account with the stored switches.

Tests: `tests/marketplace_settings_names_test.php`, `tests/marketplace_settings_refused_connect_test.php`.
