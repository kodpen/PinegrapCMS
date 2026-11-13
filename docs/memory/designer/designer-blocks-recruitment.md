---
name: designer-blocks-recruitment
description: The Recruitment block family (recruitment- prefix): job search hero, job board with contract filters, salary guide with ten-step bars; general blocks placed instead; rules, test
---

## Where does it live?
- `assets/js/style_designer_blocks_recruitment.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `recruitment-`, palette group "Recruitment" (`bi-person-workspace`). Built in step `recruitment-1` for the Recruitment Agency template ([[design-template-recruitment]]); `recruitment-2` places it.

## Which blocks does it hold?
- `recruitment-search` (Recruitment: Job Search Hero): eyebrow, `h1`, a line; a search form (`role="search"`, GET, no `action` until wired): a labelled keyword field (`q`), a labelled city `select` (`city`), a `<button type="submit">`. Popular searches as four pill links. Beside it `h2` "Just added" and three job cards (`h3` title link, city, way of working, salary). Words rise, the bar fades in, the jobs rise one by one, on load.
- `recruitment-board` (Recruitment: Job Board): eyebrow, `h2`, All Jobs; a `nav` named "Contract type" of pill filters, the current one `active` with `aria-current`; four job cards (`pg-recruitment-job`: sector icon, `h3` title as a `stretched-link`, the employer described, city, contract, way of working, salary). The featured job has a badge that is an icon and a word. An open-application line closes it. Header fades, jobs rise (stagger 80) on view.
- `recruitment-salaries` (Recruitment: Salary Guide): on `bg-body-tertiary`, words, three notes and Ask a Consultant beside a table in a positioned `table-responsive` region. A caption, a `th scope="col"` per column (Job title, Sector, Monthly salary), a `th scope="row"` per job. The range is written as two figures and drawn under them as ten steps (`pg-recruitment-scale`, `aria-hidden`). Rows fade in one by one on view.

## Why are these not reused blocks?
- No block searches by keyword and place (`hero-split`: buttons, `cityhall-hero`: tiles, `error-404-search`: one field). `events-list`, `ferry-departures` are led by a date, not a job's contract and salary. `services-price-list`, `compare-table` list prices, not ranges on one scale.

## Which general blocks does a recruitment site place instead?
- Sectors: `services-icon-cards`. Candidate and employer steps: `steps-numbered`. Consultants: `team-profiles`. Employer services: `features-alt` or `services-icon-cards`. Clients: `logos-grid`. Offices: `locations-cards`. Figures: `stats-icons`. Call band: `cta-inline`. Questions: `faq-categorized`.

## What do its rules and texts follow?
- Rules: `pg-recruitment-search` (the city select keeps 12rem from 768 px), `pg-recruitment-job` (border follows hover and `:focus-within`), `pg-recruitment-icon` (a 3rem square), `pg-recruitment-figure` (tabular figures, no break), `pg-recruitment-scale` (ten equal steps). No colour, transition or child selector of their own.
- Salaries are `_sdT('$3,500')` keys, one node per figure and `/mo` apart ([[designer-block-sample-content]]); Turkish takes the template's lira amounts (`₺120.000`), so the steps still match. Job titles, sectors, contracts and employers reuse the template's keys.
- The submit is a semantic `button`: the family check reads every `componentType` in the file as a block type, so a Button component inside a family fails it.
- Hero and board are for `bg-body`: on a tertiary band their primary text gave 4.31:1 in dark (Emerald).

## How is it tested?
- `tests/designer_blocks_recruitment_test.php` (Node run): headings, no fixed colour or `prc-`/`rc-` class, no year or month, interactions kept; the search's fields and cities; jobs with links and salaries; one current filter; an icon-and-word badge; six rows of ten steps.
- Read from the file: the GET search form, the labels, the submit, the named nav, the hidden bars, the caption and region. The rules are checked through `_pg_designer_block_css_parse()`.

Related: [[design-template-own-look]], [[designer-blocks-overview]]
