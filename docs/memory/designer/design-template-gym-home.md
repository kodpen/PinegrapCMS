---
name: design-template-gym-home
description: Gym template's home, classes and timetable pages on the Gym blocks (1.1.0, #8655): sections, motion, the parallax picture layer, pgy-anchor and pgy-depth, what the editor cannot do yet, tests
---

## How are home, classes and timetable built?
- On the Gym blocks ([[designer-blocks-gym]], `gym-2` of `plans/design-template-own-looks-order.md`, PR #8655): `pages_public.php` draws the Free Pass Hero, the Class Finder, Booking: Weekly Timetable and Hero: Image Background with the same structure and classes. Their rules ship with `pg_design_template_user_styles(array('gym-hero', 'gym-classes', 'booking-week', 'hero-image-bg'), $gym_css)`; template version 1.1.0.
- Motion is node interactions only (`props._fx`, the `$fx` builder in `parts.php`); the template carries no script. The theme system stays: look `bold`, palette `crimson`.

## What does each page hold?
- Home: a dark hero (badge with the opening hours, `h1`, Get a Free Pass, a 4:5 picture with the trial pass card over it), the class finder (focus pills to `{{page:classes}}#gy-classes-<focus>`, three class cards), trainers, a personal training band with a parallax picture, the floor (a sticky column with four figures beside six facility tiles), plans, member words (one large quote beside two cards, then Google reviews), Training Notes.
- Classes: a sticky bar of focus pills, a section per focus (`#gy-classes-<focus>`, search anchors) with every class card, How It Works.
- Timetable: the week as a `pg-week` region (`role="region"`, named, `tabindex="0"` so a keyboard can scroll it), seven days, a Book link per class with `?class=&day=&time=`.
- The class card (`$class_card`) has an intensity meter (`pg-gym-meter`, five steps) on every page; `$class_list` is the card row.

## Which template classes did it add?
- `pgy-anchor`: `scroll-margin-top` so a section reached from the sticky pills is not hidden under the bar.
- `pgy-depth`: the parallax picture layer, 20% taller than its band above and below, so speed 12 never shows an edge. The band's words sit on a `pg-shade-bottom` scrim with `data-bs-theme="dark"`; the second action is `btn-secondary`, never `text-primary` on the scrim ([[designer-block-theme-surfaces]]).

## What could the editor not do? (Needs from the designer)
- A parallax picture layer on a section (needed `pgy-depth`); a scroll-margin setting for anchors under a sticky bar (needed `pgy-anchor`); count-up figures; scroll-linked scenes and pinned stepped sections; knowing today for the hours badge (it shows Monday to Friday).
- Not seen on the canvas: block rules with child selectors (`.pg-week > ol`, `.pg-gym-meter > span`, `.pg-gym-photo > .ratio`) may miss there behind `.sd-wrap` ([[bootstrap-direct-child-canvas]]).

## How is it tested?
- `test_gym_assembly_home_classes_timetable_on_blocks` (`tests/design_template_gym_assembly_test.php`): each `pg-` class's own rule ships in User Styles, every interaction kept by `pg_fx_normalize()`, the motion in place per page, a meter per class card, every class on the classes page, the week's region and seven days.

Related: docs/memory/designer/design-template-gym.md, docs/memory/designer/designer-blocks-gym.md
