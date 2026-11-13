---
name: designer-blocks-courses
description: The Online Courses block family (courses- prefix): classroom hero with a lesson player, course shelf with level meters, curriculum on a dashed path; blocks placed instead; tests
---

## Where does it live?
- `assets/js/style_designer_blocks_courses.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `courses-`, palette group "Online Courses" (`bi-mortarboard`). Built in `courses-1` for [[design-template-courses]]; `courses-2` places it. The template's own classes are `pco-`; the blocks write `pg-courses-*` only.

## Which blocks does it hold?
- `courses-hero` (Online Courses: Classroom Hero): eyebrow, the `h1`, a lead, Browse courses and Watch a free lesson, three promises. Beside it a lesson player card (`pg-courses-player`) on a second sheet (`pg-courses-desk`): the free lesson's picture with a round play link (`aria-label`), a played bar (`aria-hidden`), course line with the Free preview pill, the lesson as `h2`, instructor, `h3` Up next and three locked lessons. Words rise, the player grows, on load.
- `courses-shelf` (Online Courses: Course Shelf): `h2` and a `nav` of subject pills (Bootstrap tints, icon and name), then three course cards (`pg-courses-card`): picture with the length chip, subject, `h3` with a stretched link, summary, level meter, instructor's face and the price. Cards rise one by one and lift under the pointer.
- `courses-curriculum` (Online Courses: Curriculum): the course (`h2`, summary, a `dl.pg-courses-facts` of level, instructor, length and access, two buttons) beside an `ol` of modules (`pg-courses-module`, `h3` "Module N: …", lessons and minutes); each lesson a `<time datetime="PT…M">`, the free one a link with the pill, the others a lock and a hidden "Enrolled students only". The project ends the path. Modules rise one by one.

## Why are these not reused blocks?
- No hero holds a lesson player; `filmstudio-reel` plays one film with a `video` and its chapters, `podcast-episode` an episode's chapters. The shelf is not `services-image-cards`: a course is chosen by subject, level and length. The curriculum is not `timeline-vertical`: a stop holds a module's lessons with their length and access.

## Which general blocks does a courses site place instead?
- Plans and membership `pricing-tiers`; instructors `team-profiles`; figures `stats-counter`; students' words `testimonial-grid`; questions `faq-accordion`; the enrol call `cta-inline`.

## How are the drawings made?
- The second sheet is the desk's `::before` in `--bs-primary-bg-subtle` behind the card (`isolation: isolate`), a little lower and to the end, less on a phone so it stays inside the page.
- The level meter is three spans of fixed heights (`pg-courses-mid`, `-tall`), filled with `pg-courses-on`; fixed heights and no `:nth-child`, as the canvas wraps every node. The level's name stands beside it.
- The path is each module's `::before`, a dashed `border-inline-start` from under its marker; the project has none. Markers are hidden, the headings say the module.

## Which colours does small text take?
- Eyebrows and details `text-body-secondary`; icons `text-primary-emphasis` or the subject's emphasis tint; the Free preview pill `bg-success-subtle text-success-emphasis` at the text's size, not a `.badge` (9.8 px beside small text in the technical look). Second buttons: `btn border link-body-emphasis pg-courses-second`.

## How is it tested and was it seen?
- `tests/designer_blocks_courses_test.php` (under Node): shared checks, words, headings, interactions, three cards and meters, lessons in order, one preview and three locks, rules.
- Drawn in headless Chromium with six looks and palettes, Turkish and English, light and dark, 320 to 1,440 px: no sideways scroll, no text under 4.5:1 (3:1 large), none under 11 px.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-overview.md
