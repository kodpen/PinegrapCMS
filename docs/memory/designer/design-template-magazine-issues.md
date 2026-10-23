---
name: design-template-magazine-issues
description: Magazine issues: office pick list with the stored default Online only, ?issue= contents, index of live Editor's letters, token hrefs, plain names, launching one.
---

## How do issues work?
- An office pick list `issue` on the Stories form: first option `Online only`, selected, then the issue names (the samples newest first). Every option is a stored value.
- One archive: `issues?issue=<name>` (a URL-parameter filter, [[form-list-url-filters]]) lists that issue's live stories as numbered contents (`issue_list`, 30/30, `ol.pm-rank`). No filter title: the page title is the name alone, which already says "Issue".
- The issue index (`issue_index` on `issues`, `home_issues` on home, 3/3) lists the live Editor's letters whose issue is not `Online only`, one per issue: the letter's picture, date and standfirst stand for the issue.
- The cover card, the story header and the issue cards link `{{page:issues}}?issue=^^issue^^`: a static href whose token `pg_sw_apply_tokens()` fills from the row. Only these four live public views print it (test 23).

## Why must an office pick list's default be a stored option?
- `_pg_cf_controls()` stores no option whose value is `''`, and takes the default from the `selected` option. The panel's edit screen (`edit_submitted_form.php`, opened by the template's Change Status button) prints only stored options, with no blank row.
- With an empty "Not in an issue" default, a new story stored nothing, the screen showed the first issue as selected, and the first status-only save put the story in that issue. `Online only` is a real state, so it may print, and it is its own archive.
- Test 5 asserts one selected option and no empty value on every office pick list ([[custom-form-model]]).

## Why plain issue names?
- The token value is HTML-escaped, not URL-encoded. A name with `& # ? + % = " ' < >` would cut or break the address; `pg_sw_url_param_value()` keeps 100 characters. The browser percent-encodes spaces and letters such as ü. Test 23 checks every name and its Turkish value; the staff note says so.

## How does an operator launch an issue?
1. An administrator or designer (editors and managers open the visual editor for content only, and a system widget stays closed to them): in the visual editor, add the name to "Part of the issue" on Submit a Story with Add an option (it goes last), check that `Online only` keeps the default star, and publish the design (the panel cannot change a designer form's fields, `edit_field.php`). Not with the bulk edit box: it exports the default as `value|on` and reads that back as label `on`, not selected, so the default is lost (`style_designer.js`, choice options).
2. Give each story the issue, one the Cover story placement and one the Editor's letter. Give the stories one publication time, the cover one minute later and the letter one minute after that: `issue_list` orders by publication time, latest first (equal times put the last story to reach the editors first), so the letter and then the cover open the contents. The staff note says so.
3. When the letter goes live the issue card appears on home and on Every issue, its archive lists its stories, and the home cover and letter switch to the newest.
- A renamed issue keeps the old value on stored stories: set the new name on them too. Two letters in one issue give two cards.
- Withdrawing the letter takes the issue off the index; its archive stays indexable while any story of the issue is live (the value check uses `issue_list`'s live filters), and goes `noindex` after the last one.

## Why not a page per issue, and why no static issue cards?
- A page per issue means a new page and filter for every issue; the parameter archive gets its title, canonical, noindex for an unknown value and its feed for free.
- Static cards go stale; a list of live letters changes the moment a letter goes live and never shows an issue before its launch.
