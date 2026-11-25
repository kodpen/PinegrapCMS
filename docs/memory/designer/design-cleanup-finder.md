---
name: design-cleanup-finder
description: The clean-up finder (includes/fn/design_cleanup.php): how the classes a design uses are gathered, how a stylesheet's rules are matched, every case it cannot tell and so never lists
module: design_cleanup
---

## What is it?
- The pure part of "Clean up" (plan `designer-cleanup`, CU1): a state of the classes in use is filled from every place that can name one, then `pg_cleanup_unused_rules($css, $state)` lists the rules of one of the design's own stylesheets that nothing uses. No database, no output; the window (CU2) gathers the inputs and draws the rows.
- It always errs towards "used": a row it is unsure of is never listed.

## What fills the state?
- `pg_cleanup_state(array('known' => [...]))`: `known` are classes never counted as unused (the caller passes the framework's own, as a widget or a node setting draws them without naming them).
- `pg_cleanup_add_tree($state, $tree, $source)`: a page's, shared component's, e-mail design's or system widget default layout's tree. `cssClass` and a `class` entry of `_attrs` are classes; other `_attrs` values, interactions (`_fx`), conditions (`_bindParams`) and component settings count by their words; markup in a prop by its class attributes; prose props (`text`, `alt`, `title`, `_label`, …, `pg_cleanup_prose_keys()`) name no class.
- `pg_cleanup_add_markup()`: a classic page, an e-mail body, another design's page: class attributes, other attribute values as words, inline scripts as scripts; text between tags does not count.
- `pg_cleanup_add_script()`: every word of a script; a name built at run time (`'tone-' + t`, `` `size-${n}` ``) covers every class starting with that text.
- `pg_cleanup_add_classes()`: a setting such as the style's additional body classes.

## Which cases can it not tell?
- A class bound to a value (`_bindings.cssClass` or `class`): `blocked = bound_class`, nothing listed. A class attribute that starts with a token or code (`{…}`, `<?php`): `dynamic_class`. A token after plain text (`badge-{__kind}`) only covers names with that start.
- A sheet it cannot read (unclosed block, stray `}`, unclosed comment or string): `unreadable`, nothing listed.
- Never listed: a selector alternative with no class (`h1`, `#main`, `[class*=x]`), classes only inside `:not()`, `:is()`, `:has()` or brackets, state classes (`active`, `show`, `collapse`, `is-invalid`, …), `pg-*`, `bi`/`bi-*`, a known class, a rule or at-block right after a comment containing the word "keep".
- Read: rules inside `@media`, `@supports`, `@layer`, `@container` (row's `media` is the chain); `@keyframes`, `@font-face`, `@import` hold no class rule and are skipped.

## What is a row?
- `selector`, `classes` (the ones nothing uses), `media`, `start`/`end` (byte range of the whole rule, for the removal in CU3), `bytes`; the result's `bytes` is what all rows would free. A rule is listed when every alternative of its selector needs a class nothing uses.
- What the caller must pass for a page with interactions: the `pg_fx` script among the scripts, so the classes it sets count.

## Tests?
- `tests/design_cleanup_test.php`.
