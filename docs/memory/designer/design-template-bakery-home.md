---
name: design-template-bakery-home
description: Bakery template's home and celebration cakes on the Bakery blocks (1.1.0, #8715): sections, the oven data, motion, what the editor cannot do yet, tests
---

## How are the home and the cakes page built?
- On the Bakery blocks ([[designer-blocks-bakery]], `bakery-2` of `plans/design-template-own-looks-order.md`, PR #8715): `pages_public.php` draws Bakery: Fresh from the Oven Hero, Menu: Tabs, Hours: Card and Bakery: Cake Sizes with the blocks' structure and classes. The Bakery rules ship with `pg_design_template_user_styles(array('bakery-hero', 'bakery-cakes'), $bakery_css)`; template version 1.1.0.
- Motion is node interactions only (`props._fx`; the `$fx`, `$rise` and `$fade_in` builders at the top of the home section of `pages_public.php`); the template carries no script. Look `organic`, palette `coral` stay.

## What does each page hold?
- Home: the hero (Ovens lit at badge, `h1`, two buttons, today's three batches as `<time>`, a 4:5 picture with the last-order tag hung over its corner), the counter as Menu: Tabs (a pill per kind of bake, every bake once in two columns with badges and price, a link to `{{page:bakes}}#bky-cat-<kind>`), celebration cake cards, baking classes, Order and Collect beside Hours: Card (`bky-home-order`), a slim wholesale line (`bky-home-wholesale`), customer stories with Google reviews, the Journal.
- The figures row and the two picture story bands left the home; the about page keeps both.
- Cakes: header, the cake cards (`bky-cakes`), Cake Sizes (`bky-cake-options`: four sizes drawn to scale, servings, the price each starts at, sponge and filling pills, the lead-time note), How It Works (`bky-cakes-how`).
- Daily bake: unchanged but for the kinds' cards rising one by one.
- A section holding a column that slides in from the side (`bky-home-order`, `bky-cakes-how`) carries `overflow-hidden`: the armed column sits 24 px beside its place, and a 375 px phone scrolled sideways until it arrived (seen in the sandbox, #8715).

## Where do the figures come from?
- `parts.php`: `$oven` (lit, the batches with their times and names, the last order; times of the day only, never a date), `$cake_sizes[...]['price']` (the plainest cake's price in that size, USD/EUR/GBP/TRY), `$opening_hours[...]['closed']` greys Monday on the hours card.
- `$figures` takes its first value from `$oven['lit']`, so the about page and the hero agree.

## What could the editor not do? (Needs from the designer)
- Knowing the time of day to mark the batch that is out now, or to hide the last-order tag after the cut-off.
- Not seen on the canvas: the tag's slant and hover, the tabs switching, the size drawings in dark mode.

## How is it tested?
- `test_bakery_assembly_home_and_cakes_on_blocks` (`tests/design_template_bakery_assembly_test.php`): each `pg-` class's rule ships in User Styles, every interaction kept by `pg_fx_normalize()`, the motion per page (home, cakes, bakes), five machine-readable times, a pane per tab with one open, every bake once on the counter, the closed day greyed, four sizes drawn (two layers for the tiers); `test_bakery_assembly_side_entrances_clipped`: every slide-left or slide-right on the two pages sits in a clipped section.

Related: docs/memory/designer/design-template-bakery.md, docs/memory/designer/designer-blocks-bakery.md
