---
name: bug-hunt-areas-designer-search
description: Bug hunt of the designer's Select similar, Find and Replace, Options search and tree search (2026-11-28, PR #12242): six faults fixed, what was read clean, one small fault left
---

## What was the area?
- Second designer area of `bug-hunt-areas-7.md`: `assets/js/style_designer_similar.js`, `_findreplace.js`, `_optsearch.js`, `_treesearch.js`, with the pure matchers they load (`pg_select_similar.js`, `pg_find_replace.js`, `pg_options_search.js`).

## What did it fix? (PR #12242)
- Find and Replace rewrote code: a custom HTML's `<script>` and `<style>` blocks were read as words ("Contact" also changed a script string and a `.Contact` selector). They are now kept whole with their tags.
- With Regex and Whole word both ticked the regular expression ran as typed ("cat" replaced the start of "category"). It is now held to whole words; a pattern the Unicode mode refuses (`\-`) falls back to Latin letters.
- Any case did not match the Turkish i: "istanbul" missed "İstanbul", "ılık" missed "ILIK". A plain search now reads i, I, İ, ı alike, as the tree and Options searches fold them; Case sensitive keeps them apart.
- A typed space did not find a no-break space ("our&nbsp;team", or the sign in a button's words). A plain search now does; a regular expression reads the space as typed.
- Tree search: `_tsFold()` cut every decomposed letter to its first part, so a Korean syllable became its first consonant and "한국" found "하고". Only combining marks come off now.
- Select similar left out the children of the `guest` slot and of the booking widget's steps and form as "a system widget's own parts": the matcher's `KEEPS_CHILDREN` had drifted from the editor's `_SD_SECTION_KEEPS_CHILDREN` and `_SD_BOOKING_KEEPS_CHILDREN`. A test now holds the lists equal.

## What was left?
- The "One of its classes" second list is closed and drawn again on every `mouseover` inside its row (`openMenu()` in `style_designer_similar.js`): it flickers, and a keyboard focus in it is lost when the pointer moves over the row. Small; no Node DOM to test it, no issue opened.

## What was read clean?
- Find and Replace: one undo step a page, the pages left out (held, no rights), content-level filter, code files and their read-only tabs, `$` groups in replacements, entities and tags in markup.
- Select similar: the four ways, three places, the wider place, left-out counts, the lists' keys.
- Options search: index, ranks, the class offered when nothing is found, Enter opening the row (`_sdOptSearchGo()` finds a row by its label when the count differs).
- Tree search: entities, the late answer dropped, order, snippet and path.

## How was it checked?
- Unit tests under Node, each red before its fix: new cases in `tests/designer_find_replace_test.php`, `designer_tree_search_test.php`, `designer_select_similar_test.php`. No browser run.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/designer/designer-find-replace-scopes.md, docs/memory/designer/designer-select-similar-matcher.md
