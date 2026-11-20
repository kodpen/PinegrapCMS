---
name: designer-options-style-advanced
description: The Options panel's Style and Advanced tabs (DP5b): the sections' names and order, which open by themselves, the card ids a renamed section keeps
---

## Which sections do Style and Advanced hold, in which order?
- Style: Text, Spacing, Color and background, Border and corner, Size and display, Shadow and effect. Advanced: Layout, Show by the visit, Accessibility, Tooltip, Search and SERP, Interactions. The order is `_SD_OPT_RANK` in `style_designer.src.js`; `_sdOptPlan()` sorts each tab by it, pieces of one rank in the order built.
- The former names: Text Options, Colors, Border, Display & Sizing, Styling, Flexbox. The keys stay (`text`, `colors`, `border`, `display`, `styling`, `flexbox`; `designer-options-tabs.md`).
- The Shadow row moved from Color and background to the top of Shadow and effect, above the placeholder and focus ring rows of the former Styling; it writes the same classes.
- The several-element panel (`renderMultiProperties()`) shows the new names in the order `rowCss()` builds them.

## Does a renamed section keep its open state?
- Yes. `sect(icon, title, body, idName)` and `as(title, content, idName)` make the card id from `idName`, the former name through `_sdT()` (`_sdT('Colors')`), so `sd_cs_<sid>` a browser kept still applies (K10). A section renamed later is given its old name the same way.

## Which sections open by themselves?
- While a browser kept nothing for it, `_sdOptOpenByDefault(key, ctx)` decides: Text opens on a text element, Spacing always, Layout on a flex box or the child of one, Show by the visit when a condition is set; the other Style and Advanced sections stay closed. A state the browser kept wins (`_sdSectOpenRead()`).
- A text element (`_sdOptIsText()`): h1 to h6, p or span, or a heading or paragraph content element, the ones whose Text section offers display sizes. A link or a button is not.
- A flex box (`_sdOptIsFlex()`): `d-flex` or `d-inline-flex` at any screen size, else a grid row or a Bootstrap class that is flex by itself (`row`, `nav`, `card`…) unless a base display class says otherwise; the list and Layout's rows by context are in `designer-options-layout.md` (#10504).
- `renderProperties()` reads the context once per draw into `_sdSectBuild.ctx` (`_sdOptCtx()`: the node, its parent through `_sdAnyParent()`, `_sdCondRowsOf()`); `_sdSectKey()` applies the rule to a keyed Style or Advanced card and holds back or draws its body to match. Content keeps the rules of `designer-options-section-cards.md`.

## What do their closed sections say?
- The summary of `designer-options-section-cards.md` (the first changed row's colour or value, "default"). The sheets differ on the words for an unchanged section (options-2 "none", options-5 "default") and the measures text names none, so they stay.

## Where is it tested?
- `tests/designer_options_tabs_test.php`: each kind's Style and Advanced order, the names with their former card ids, the shadow's new place, the open rules and the two kinds, a content-level user's hidden sections in no tab and an empty tab not drawn.
