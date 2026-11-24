---
name: designer-compare-changes
description: Compare's change reader (pg_compare_changes.js, PgCompareChanges): two designer trees in, plain-words changes with their place out; matching, kinds, order, rows, texts
---

## Where does it live, and what does it read?
- `pinegrap/assets/js/pg_compare_changes.js`, `window.PgCompareChanges` (CL1 of plan `designer-compare-live`, #PRNUM). Pure: no DOM, no request; not loaded by any screen yet. The Look tab's part loads it on first use and puts it in that part's bundle (`pg_js_bundles()`, `pg_designer_parts()`).
- `read(before, after, texts)`: the live tree and the draft, each a node, a list of nodes or their JSON text. Answers `{ state, changes }`; `state` is `changed`, `same` ("Nothing has changed"), `new` (no live tree: the page was never on the site, draft shown alone) or `unreadable` (a side that is no tree, or no draft).

## How are elements matched?
- By `_id`. Without one, by place: `#` and the child indexes from the top (`#0.2`). A second element with the same id goes by order: `id#2`, `id#3`. The change's `node` is the id (the key when there is none), `key` the matching key.

## What is a change?
- `{ kind, code, node, key, text, what, name, side, parent, section, count, before, after }`. `kind`: `added`, `removed`, `moved`, `words`, `picture`, `link`, `look`, `other`. `side`: where the place is found, `after` (draft), `before` (live page) or `both`. `section`: id of the nearest section-like element (section, article, aside, header, footer, nav), else its block under the top. `count`: elements of an added or removed subtree.
- An added or removed subtree is one change at its top. Moved: to another parent (`moved_into`, "Heading moved into Section: Prices"), or out of the longest run of siblings that kept their order (`moved_up` / `moved_down`); a sibling only pushed along by a move is not moved.
- Prop changes, at most one change per kind per element. Words: `text`, `html`, `title`, `tooltipTitle`, with old and new plain words in `before` / `after`; the same words in new markup are a look change. `alt` is words with "Picture description changed". Picture: `src`, `srcset`, `poster`. Link: `href`, `target`, `rel`. Look: `tag`, `cssClass`, inline style, `_styles`, `_fx`, grid and size props (`LOOK`). A changed `type` or any other prop is `other`: never dropped.
- Not compared, as they never reach the site: `_label`, `_labelDetail`, `_labelExpand`, `customName`, `_notes*`, `_locked`, `_expanded`, `_editable`, `starterKeep`, `partOf`. An empty value (`''`, null, false, `[]`, `{}`) equals a missing one.

## In which order, and what are the rows?
- Most visible first: added, removed, picture, words, moved, link, look, other; within a kind, document order (removed in the live page's order).
- `rows(changes, texts)`: what the list draws; look and other changes of more than one element are one row each ("Look changed (3 elements)", "Other changes (2)") with `nodes` and `changes`; every other change is its own row.

## How are the sentences translated?
- English fallbacks in `TEXT` (`texts()` lists them): codes `added`, `added_named` ("Section added: Prices", from `_label`), `removed`, `removed_named`, `moved_*`, `words`, `alt`, `picture`, `link`, `look`, `other`, `look_rows`, `other_rows`, `place_named` and the element words `kind_*` (Heading, Text, Picture, Section…). Placeholders `{what}`, `{name}`, `{where}`, `{count}`. The screen hands `_sdT()` translations in as `texts`; the step that draws the list adds the keys.

## How fast, and where is it tested?
- Node 22, median of 25 on the 1,539-node fixture (`designer-performance.md`): about 3 ms for equal trees and for the main's sections reversed. It runs only when Compare's Look tab opens; the editor's open and edit do not load it.
- `tests/designer_compare_changes_test.php` (Node, skipped without it): the four states, subtrees, words / picture / link with old and new, moved up / down / into, look and other, editor props, rows, handed-in texts, missing and doubled ids, the big fixture.
