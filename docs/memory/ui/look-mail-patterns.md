---
name: look-mail-patterns
description: The e-mail settings drawing's seven parts and the Features board's switch rows as Design examples patterns (DX2b #10930), with the classes that draw each
module: look_examples
---

## Where are they?
- `pg_look_examples_mail_patterns($p)` in `includes/fn/look_examples.php`, merged at the end of `pg_look_examples_patterns()`; the recipes in the block "Look A: tabs with a state, value rows, choice cards, step line, provider sign-in, result line, confirm with its effects" of `backend.src.css`, after the starting-point cards.
- Source: the owner-approved drawing `ui/email-settings-drawing/email-settings.html` (boards A to E), copied value for value. The E-mail category's parts copy these and write no recipe of their own (`settings-mail.md`).
- A pattern with `'phone' => true` is the phone pane's (DX4): the choice cards and the step line.

## Which classes draw which part?
- Tabs with a state: `.pg-seg.pg-seg-tall` (34 px items, label 550) with `role="tablist"`, `.pg-seg-item role="tab"`, a `.pg-pill` whose word sits in an inner `<span>` and in `title`; under 576 px the pill is its dot.
- Value rows: `.pg-card-inner` holding `.pg-value-row` (tile, `.pg-value-row-name`, `.pg-value-row-line`, Change quiet small); one `.pg-field-help` under the card.
- Choice cards: `.pg-start.pg-start-choice` on DX2's `form-field-card` radios: two a row from 576 px (the plain `.pg-start` keeps 768 px), the chosen card's accent ring, `.pg-start-mark` at the head's end (the radio is visually hidden, focus rings the card), `.pg-start-note` for a duration; then `.pg-group-label` and only the chosen card's fields.
- Step line: `ol.stepper.pg-steps`, the name in `.pg-steps-name`, `aria-current="step"` filled with the accent, `.is-done` shows the teal check (`\F26E`); under 576 px only the current name shows.
- Provider sign-in: `.pg-card-inner.pg-signin` with `.pg-signin-google` (white, 1 px #747775, 4 px, 40 px, "Google Sans" then Roboto, 500 14/20, no web font loaded, #1f1f1f, G at 18 px) or `.pg-signin-microsoft` (white, 1 px #8c8c8c, square, 41 px, Segoe UI 600 15 px, #5e5e5e, logo at 21 px); same in both themes, no look A button class or tone.
- Result line: `.pg-result role="status"` with a teal tile, `.pg-result-title`, `.pg-result-line`.
- Confirm with its effects: a `.pg-card.pg-card-sheet role="dialog"`, rose tile, `ul.pg-card-inner.pg-effects` of `.pg-effect-keep` (green check), `.pg-effect-go` (rose cross), `.pg-effect-info` (blue info), each with an optional `.pg-effect-line`; Cancel quiet, then `.pg-button-danger`. Its second example (`pattern-confirm-go`, #11642) is a change History can take back ("Build again" of a sketch page): blue tile, the History note in the foot, Cancel quiet, then `.pg-button-fill`.
- Switch rows (the Features board design of docs PR #10932, architect channel message 11411): `.pg-set-fold` holding `div.pg-set-switch-row`s: tile, `.pg-set-fold-name` as the switch's `label`, `.pg-set-fold-line` as its `aria-describedby`, quiet "Settings", `input.pg-switch` last; a category row has a pill and "Open it" (Turkish "Aç"; "Open" is "Açık"). `.is-off`, `.is-locked`; a switch turned off greys its row through `:has()`.

## What was found while building them?
- The key "Sending" is the mail queue's status ("Gönderiliyor" in Turkish), so the example's tab says "E-mail sending"; the EM step names its own tab key.
- Google's branding page (read 2026-11-21) names Google Sans Medium as the button font; the architect chose Google Sans first, then Roboto, with no web font loaded (PR #10930). Microsoft's redlines match the box note.
- The existing Turkish of "Sign in with Google" / "Sign in with Microsoft" is "… ile Giriş", not the providers' own "… ile oturum açın"; left as it is (the designer uses the keys too).
