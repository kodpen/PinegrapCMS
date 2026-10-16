---
name: text-inner-elements
description: Elements written inside a heading, paragraph, link or span text (span, strong, a, badge, icon) are picked by themselves on the canvas and in the overview, and edited through the text.
---

## Why could a span inside a paragraph not be edited?
- A content `heading`, `paragraph`, `link`, `span` or `text` keeps its words as one piece of markup in `props.text` (`_SD_RICH_TEXT_CONTENT`; the HTML import writes `<p>Start <span class="hl">…</span></p>` this way, `pg_di_inner_html()`). The span is not a node, so a click on it selected the paragraph and nothing but its words could be changed. Seen on a Custom-framework design (PR #496).
- Semantic elements (`pre` > `code`, `li`, `td`, `button`) hold real child nodes and were always selectable.

## How is an element inside a text picked and edited?
- A click on any element inside such a text picks that element (`_sdTextInnerClick()`); the text's own words pick the text. Hovering shows a dashed outline, the picked one a solid one (`.sd-inner-sel`, canvas only).
- The overview lists the elements under the text, nested ones indented (`_sdTextInnerTreeRows()`); a row click picks the element. The picked element's row is the one the overview brings into view (`_sdTreeRevealSelection()`), also for a pick inside the text already selected. A link of a paragraph or heading keeps its take-out button (`_sdIlTakeOut()`).
- The Options panel is the element's own (`_sdTextInnerPanel()`): a breadcrumb back to the text, tag, text (HTML when it holds elements), link / target / rel for `a`, src / alt for `img`, id, classes, style, every other attribute, and "remove the element, keep the words", take out (link), delete. The Attributes panel lists its attributes read-only.
- Delete removes the picked element only; Escape goes back to the text. Double-click still edits the whole text in place: the pick is dropped first, so its mark never reaches the stored markup (`_sdInlineEditValue()` also strips it).

## Which elements are picked?
- Every element inside the text except `_SD_TEXT_INNER_SKIP` (br, wbr, script, style, template). A drawing (`svg`, `math`, `_SD_TEXT_INNER_OPAQUE`) is picked whole. The tag list offered in the panel is `_SD_TEXT_INNER_TAGS`.
- Not picked: a text bound to data (`_sdTextIsBound()`), a child node of a link (it has its own wrapper), and, for a content operator, a text outside an editable area. Inside a locked element, or in a view-only session, it is picked with a read-only panel.

## How is a pick stored and kept valid?
- `_sdInner = { node, path }`: `path` is the index of each picked element on the way down (`_sdTextInnerKids()`, designer chrome left out). `_sdInnerSel()` drops it when another node is selected or the path no longer resolves (after undo, an edit).
- A canvas click is picked only when the tags along the path match the stored text (`_sdTextInnerTags()`): a preview that differs (token, translation) falls back to the text.
- Every change parses `props.text`, changes the element, writes it back as one undo step (`_sdTextInnerApply()`). The text stays one piece: page output and in-place editing are those of any text. Markup is re-serialised by the browser on such an edit (`&middot;` becomes `·`), as for the links section.

Related: docs/memory/designer/designer-overview-follows-selection.md, docs/memory/designer/link-button-inline-children.md, docs/memory/designer/node-text-and-whitespace.md, docs/memory/designer/html-import-and-paste.md
