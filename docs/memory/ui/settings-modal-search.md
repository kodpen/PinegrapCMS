---
name: settings-modal-search
description: The Site Settings dialog's search box (index, system tools, ranking, Turkish keywords keeping English terms) and how a text names a card's place
---

## How does the search in the dialog work? (#4118)
- A box over the category list (`#pg_settings_modal_search`, outside the form: never posted, never makes a pane dirty). On first use it asks `settings_pane.php?search=1` once per page: `pg_settings_search_build()` draws every category with `pg_settings_fragment()` (output buffered, one failing category keeps its registry row) and reads each card's heading, labels and legends (`pg_settings_card_labels()`); `pg_settings_search_index()` keeps only the sections the registry lists for this role. No hand list: a field added to a card is found.
- Matching (`backend.src.js`, `score()` and `rankHits()`): 0 card name, 1 a keyword that starts with the query, 2 a field label, 3 a keyword the query starts with, 4 every word of a multi-word query somewhere; Turkish letters folded. In one rank the exact text, then the longer rank-3 keyword, then list order. Hits replace the list; a hit calls `open(pane, card)`, the same as a `#settings/<cat>/<card>` link. Escape empties the box before it closes anything; Down/Return go to the first hit.
- Why a keyword the query starts with ranks under a field label (#4303, issue #4218): short keywords ("zaman", "dil", "logo", "google") pulled a setting's full name onto another card; 206 of 619 field labels did not find their own card first. Test: `tests/settings_search_test.php` runs the matcher under Node.
- System tools (#4303, issue #4219): `pg_settings_search_tool_rows()` adds the System tools screen and every tool of `pg_settings_tool_groups($user)` (same gates), each row with a `url` (a tool with a `confirm` opens `system_tools.php`); its hit is an `<a class="pg-sm-away">` link, so only `button.pg-sm-hit` opens a pane. Keywords are `pg_settings_tool_keys()` (in the registry, shared with the palette) minus the palette's shorthand that only starts another word ("tem", "sis").
- Every Turkish keyword list keeps the English terms of its source (`pg_settings_keywords()`); a test checks all of them. Before #4303, Private Label and 14 other cards dropped them.
- Palette keywords fixed with it: "logo" no longer lands on E-Invoice (Logo İşbaşı stays findable by "isbasi"), "prefetch" finds Feature Options, "workspace" finds Related screens in Turkish too.

## How does a text name a card's place?
- As `Site Settings › <category> › <card>`, with today's category names: Update Channel, Alerts, Maintenance Mode under Updates and maintenance; Visitor Tracking, Legal Documents under Visitors and privacy; Google Reviews, Application API under Integrations; Audit Trail under Accounts and sign-in.
- After the regrouping (#4135) panel, e-mail, installer and open-version note texts still named old places; #4303 (issue #4217) moved them. A moved card means its texts move too: `grep` the old name. Test: `tests/settings_place_texts_test.php`.

Related: docs/memory/ui/settings-modal-navigation.md, docs/memory/ui/settings-system-tools.md
