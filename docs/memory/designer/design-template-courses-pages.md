---
name: design-template-courses-pages
description: Online Courses template 2.0.0: home, courses page and course pages on the Online Courses blocks: the builders, each page's composition, prices in the base currency, what left, the test
---

## Which builders draw the blocks?
- Version 2.0.0: the home, `courses` and every `course_<key>` place the Online Courses blocks ([[designer-blocks-courses]]); their rules ship through `pg_design_template_user_styles(array('courses-hero', 'courses-shelf', 'courses-curriculum'), $courses_css)`.
- In `pages_public.php`: `$fx`, `$fade_in`, `$rise($node, $stagger = 100)`, `$line_detail` (not `$detail`: `assignments.php` has its own), `$second_button` (`… pg-courses-second`), `$block_eyebrow`, `$minutes($n)` (a `time` with `PT{n}M`), `$locked`, `$preview_pill`, `$level_meter($key)`, `$subject_link($key)` (to `courses#subject-<key>`).
- `$course_card($key)`, `$lesson_row($lesson, $open)`, `$course_path($key)` (modules on the dashed path, the course's assignment as the last stop), `$classroom_hero()` (the page's only `h1`; the Web course's free lesson in the player, three locked lessons up next).

## What does each page hold?
- Home: the classroom hero, the shelf (subject pills, the three course cards rising), Inside a course on `bg-body-tertiary` (the Web course's facts as a `dl`, Watch the free lesson, See the course, its path rising), the instructors, testimonials with `google_reviews`, `home_posts`, the enrol band.
- Courses: per subject a `pg-courses-shelf` section (`subject-<key>`, searchable) with its cards rising.
- `course_<key>`: header, What you will learn (`learn-<key>`), the Curriculum (`#curriculum`) as the path, the instructor, the sticky enrol box. The separate Your project box left: the project ends the path.

## Where do the prices come from?
- `parts.php` writes each course's `price` and the membership's from `prices` with `$money()`: the base currency's figure (USD's for a currency not listed), `pg_money_text()` with the base symbol, so a card shows what the shop asks. `$amount_in_base()` picks the figure; the pricing page's One course says From the cheapest course.

## What left?
- The `pco-hero` band and its rules, the `$step` builder, the old list-group syllabus with `text-bg-success` badges, the fixed `$79`-style price strings.

## How is it tested?
- `test_courses_pages_on_online_courses_blocks` (`tests/design_template_courses_assembly_test.php`): version at least 2.0.0, every placed `pg-courses-*` class's rule ships, wanted classes per page, interactions kept by `pg_fx_normalize()`, one `h1`, each course path's lessons in order with only the first linked and the rest locked, the project last, prices from `pg_money_text()`, From and the membership on the pricing page.

Related: docs/memory/designer/design-template-courses.md, docs/memory/designer/design-template-own-look.md
