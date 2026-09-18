<!-- Fill every section; write "none" rather than deleting one. Turkish or English. -->

## What

<!-- What changed, in one or two paragraphs. -->

## Why

<!-- The problem this solves and why this approach. Link the decision issue if one applies. -->

## Side changes in the same flow

<!-- One line each: things fixed or moved because they were in the same code path. "none" if there are none. -->

- 

## Affects released version?

Affects released version: yes/no (2026.x) <!-- name the release line when yes; a migration or changelog line is then expected -->

## Verified by running

<!-- Exact requests, commands or screens and their results. One per line. -->

- 

## Could not verify

<!-- What was not executed (for example: no runnable instance, no IIS at hand) and why. -->

- 

## Linked issue

Closes #

## Checklist

- [ ] `php tools/lint.php` exits 0
- [ ] `php tools/check_lang.php` exits 0 (new `lang()` / `_sdT()` keys added to `tr.json`)
- [ ] New PHP/JS files carry the Pinegrap header block; new `includes/` files start with the `PG_API_ENTRY` gate
- [ ] Comments are English and describe the code, not the process that produced it
- [ ] Schema changes go through the migration runner and are re-runnable
- [ ] `.src.js` and `.min.js` updated together
- [ ] Changelog line added where the change is significant to site owners
- [ ] Open issues/PRs checked for the same `file:line` ranges; overlaps noted above
