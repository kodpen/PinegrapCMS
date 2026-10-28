<!-- Fill every section; write "none" rather than deleting one. Turkish or English. -->

## What

<!-- What changed, in one or two paragraphs. -->

## Why

<!-- The problem this solves and why this approach. Name the owner decision in docs/memory/workflow/decisions-made.md if one applies. -->

## Side changes in the same flow

<!-- One line each: things fixed or moved because they were in the same code path. "none" if there are none. -->

- 

## Affects released version?

Affects released version: yes/no (2026.x) <!-- name the release line when yes; a migration or changelog line is then expected -->

## Changelog

<!-- The merging session writes changelog.txt when it lands this PR; the PR itself does not edit the file. Name the one-line "[NEW] Added …" of a plan-driven major feature, the item for the version's one "[SCHEME] …" line, or "none". -->

none

## Verified by running

<!-- Exact requests, commands or screens and their results. One per line. -->

- 

## Could not verify

<!-- What was not executed (for example: no runnable instance, no IIS at hand) and why. -->

- 

## Decision question

<!-- A schema or product-behaviour question, or one this PR cannot settle: open an architect issue (docs/memory/workflow/architect-asking.md), write "Decision: #<issue>" here, turn the PR back to draft and end the session; the architect answers on the issue and comments on this PR. Never ask the owner here or in your reply. Leave "none" otherwise. -->

none

## Checklist

- [ ] `php tools/lint.php` exits 0
- [ ] `php tools/check_lang.php` exits 0 (new `lang()` / `_sdT()` keys added with `php tools/tr_add.php`, never by hand)
- [ ] New PHP/JS files carry the Pinegrap header block; new `includes/` files start with the `PG_API_ENTRY` gate
- [ ] Comments are English and describe the code, not the process that produced it
- [ ] Schema changes go through the migration runner and are re-runnable
- [ ] `.src.js` and `.min.js` updated together
- [ ] `changelog.txt` not edited; the Changelog section above names its line or says "none"
- [ ] Open PRs (drafts included) checked for the same files; overlaps noted above
- [ ] When CI is green and nothing is left: the `ready` label, stay subscribed, no merge of `development` into it after that; once it is merged, the plan's next step (queue limit first)
