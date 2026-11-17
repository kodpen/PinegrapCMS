---
name: prototype-problems
description: The Prototype view's problems (PV11a): links without a target, pages no link leads to, "N to check", the three groups, the amber count and badge
---

## What is a link without a target?
- (F26) A node's own link (href, attrs, btn, a text's `<a>`) whose address is empty or `#` alone (`emptyHref()`, trimmed): `mark()` gives it the mark `empty`. `#name` stays an anchor; a Bootstrap toggle is no hotspot (`collect()`); a link kept by page id reads its page (`markBound()`); menu and region items keep their own marks.
- `CHIP_MARKS` has no `empty`: such a link gets no chip and no arrow. Its mark on the picture is amber (`.sd-proto-hot.sd-proto-mark-empty`); its panel row says "No target" in amber (`sd-proto-li-empty`). It can still be pointed at a page by drag or by the target menu (designer/prototype-drawing.md).

## Which pages does no link lead to?
- (F27) `problemsOf(layout).unreached`: the graph canvas core's `check()` over the page frames from the Start page (`layout().start`), which never counts. Shared parts and chips are no boxes of it; a shared part's links stand in as one source, so a page reached only from a header is reached. A link to its own page reaches nothing.
- A system widget leads to the pages its settings name (`widgetPages()`, the keys in `WIDGET_LINKS`: login, register when its link is shown, account, my account, staff, forgot password, a list's detail page, where a form goes after it is sent); `design()` gives its shared frame `reaches` and those pages count as reached. A form's data page and an e-mail's page are no links.
- A link the server fills per request (an href bound to `__profile_url`, `__login_url`, `__logout_url` and the rest of `BOUND_WIDGET_URLS`) is no hotspot, but `collect()` lists its field in `urls`; it leads to the pages placing a system widget of that kind (`widget`, its `regionType`), as `pg_sw_page_type_widget_url()` resolves it.
- Architect (2026-11-17, agent channel message 8336): on the Clinic template giris and kayit-ol were listed though the login widget's "Giriş Yap" and "Kayıt Ol" lead there; the widget draws them from its settings, so no tree link gave an edge. hakkimizda stays: no link of the design leads there, only its own breadcrumb names it.
- Its head carries the amber "No link leads here" (`.sd-proto-unreached`), also when the frame is dimmed.

## What does the bar say, and what does the list show?
- (F28) `problemsOf(layout)` answers `broken` (`brokenOf()`), `empty` (`list`, `count` per frame), `unreached` and `check` (empty links plus unreached pages). After the red "N broken" the bar shows the amber "N to check" (`ui.check`, `.sd-proto-check`); either opens `.sd-proto-problems` under it. A link count that does not fit beside them is left out whole, never cut (`_sdProtoStatusFit()`, measured again on resize); its words stay in the bar's title.
- The list has three groups, an empty one left out: "Broken links" (its heading red), "Pages no link leads to", "Links without a target" (amber). A link's row picks it (`_sdProtoPick()`); a page's row selects its frame and brings it into view. Escape closes it and refocuses the button that opened it.
- (F29) The head's red count stays broken links only; an amber count beside it (`.sd-proto-badge-empty`) is that frame's links without a target.

## How is it checked?
- `tests/proto_hotspots_test.php`: `test_proto_hotspots_empty_links_marked` (`''`, `#`, `#top`, a toggle, a kept link, text links; no chip, no arrow), `_problems_of_a_design` (a shared part's link reaching a page, the Start page unlinked, the counts), `_widget_settings_reach_pages`, `_bound_fields_reach_widget_pages`, `_view_what_to_check`.
