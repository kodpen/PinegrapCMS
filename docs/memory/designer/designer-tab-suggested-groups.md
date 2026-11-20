---
name: designer-tab-suggested-groups
description: The page strip's suggested groups (DP21, D53): which pages a design without groups is offered as groups, the "Suggested groups" button, accepting through designer/tab_layout, refusing per browser
---

## When is a grouping suggested?
- While the design has no group, the layout can be edited (`_pgTabLayoutEditable()`: the column exists, not content level), it is not a component-only editor, and the offer was neither accepted nor refused in this browser.
- Only for six pages or more, and only when at least one group comes out.

## Which groups does it suggest?
- `_pgTabSuggestGroups(pages, opts)` (pure, `tests/designer_tab_suggest_test.php`), from `{ key, address, kind }` in strip order.
- A page of a kind joins its kind's group: Account, Shop, Blog, E-mails. Any other page whose address starts with the same word as another's joins that word's group ("/services-web", "/services/seo" → "Services"); "/blog…" joins Blog.
- The word: the address lowercased up to the first `/`, `-`, `_` or `.`; one letter or only digits is no word. The home page and a page alone stay without a group ("Other").
- A group needs two pages; one holding every page (an e-mail design) is dropped. At most eight groups (one colour each, grey last): the smallest is left out first, of equals the later one; strip order kept.

## How is a page's kind read?
- `_pgTabSuggestKind(p)`: e-mail when its tree root has `props.pgEmail`; else the classic `page_type` (account or shop types); else, on a standard page, the system widget it places (`_swTabHasWidget()`: login, registration, account widgets → Account; cart, checkout, catalog → Shop; `blog_list` → Blog).
- Worked out again only when a page comes, goes or changes its address (`_pgTabSuggest.sig`).

## What does the operator see?
- A "Suggested groups" button (magic icon) right after the Pages button; under 768 px the icon only. It opens a menu: the groups with colour and page count, "Group the pages" and "No, thanks".
- "Group the pages" makes the groups `g1`… with the names and colours shown and saves through `_pgTabsLayoutChanged()` → `designer/tab_layout`, like any group change. No new storage, no new endpoint.
- Accepted or refused, `localStorage` `pg_sd_tabsuggest_<styleId>` = `1`: the offer does not come back for that design in that browser, even when the groups are ungrouped later.
