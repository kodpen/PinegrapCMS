---
name: toolbar-phone-fit
description: Toolbar filter forms capped to the bar (.pg-toolbar-filter), facts of Edit Page / Edit Comment / Edit Theme File in the grow slot, the Subscriptions date range wrapping at 375 px (#4264)
---

## Why does a toolbar filter no longer push the page sideways?
- A flex item's automatic minimum width is its content, and a select is as wide as its longest option. In the Accessibility report ("1.3.1 …" rule) and Browse Items (`editor_select_page_or_file.php`, a long folder name) the filter ran to 494 / 425 px at 375.
- `backend.src.css` (block *Admin screen toolbar*) gives `.pg-toolbar-filter`, its `> .input-group` and its `select` `min-width: 0; max-width: 100%`, the select `text-overflow: ellipsis`. It applies at every width: wrapping uses the content width, so a desktop bar is unchanged; the cap only bites when the filter is alone on a line wider than the bar.
- The markup keeps `w-auto flex-nowrap` (icon beside the select). Every filter form in a bar carries `pg-toolbar-filter` (`pg_filter_select()` adds it).

## Where do a screen's facts go?
- Into `pg_screen_toolbar()`'s `grow` slot, never a block above the bar (owner rule, `admin-screen-toolbar.md`).
- Edit Page: the Access / Page Type / Short Link / Searchable / Next and Skip Page line (and the home badge) is the grow; the bar is built after the page-type block for every user, so it shows even with no edit buttons or no right to set the type. The buttons collect in `$output_button_bar_buttons`.
- Edit Comment: Added / Page / Form Reference Code / verified-purchase lines are the grow; "Go To Comment" is a slot 2 button (`btn btn-sm btn-text theme-secondary`).
- Edit Theme File: the h2 repeating the file name is gone (the breadcrumb names it; Download File / Export open it); "Page Style Head Tag" is the grow.
- A bar with only a filled grow is drawn and shown (#4142 CSS).

## Why does the Subscriptions date range wrap?
- Two `pg-date-box` boxes (~177 px each) plus the dash sat in a `d-inline-flex` span without wrap: 386 px at 375. The span now carries `flex-wrap row-gap-3 max-w-100`.

Pinned by `tests/panel_narrow_layout_test.php`. A real 375 px layout was not measured in a browser.
