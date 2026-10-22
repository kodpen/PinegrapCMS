---
name: system-widget-render-rules
description: System widget renderer rules: Messages node named after the liveform, state messages fitted to the loop parent, pg-hide-if-empty, stretched links, the PHP tree renderer mirroring the exporter.
---

## How must a renderer name its Messages node?
- Every renderer calls `_pg_inject_messages_node($tree, '<liveform name>')`, never with `''` (one deliberate exception: the custom form widget before a form is bound, `_render_system_widget_custom_form()`). An unnamed node prints every form's messages on the page and uses them up, so the widget drawn first takes the other's error (on My Account the `my_account` widget printed the security card's error at the top).
- The name is the classic page's liveform name; a widget whose classic page prints no messages uses its own name (`calendar_view`, `logout`, `error_page`). Test: `tests/designer_messages_test.php`.
- The account-band widgets (`cart_link`, `login_region`, `language_switcher`) and `account_invoices` (submits nothing) are named with a name nothing submits and show no messages. In JS they are `_SW_NO_MESSAGES`: their starter tree gets no "PHP Messages" node, and one an older tree carries is dropped on load (`prefetchShared()`). A new such type goes into both sides. Member widgets: member-widgets-rendering.md.

## Where does a state message go?
- "No records" / "not found" is written by `pg_sw_state_message_html($message, $parent_tag, $class)` as an element that may stand where the records go: an `li` in a list, a `tr` with `colspan` in a table, a `col-12` in a grid row, a `span` in text, an `option` in a select. `$parent_tag` is the `loop_parent` that `_split_widget_tree()` returns.
- Never write a div, a search box or a pagination inside a list or table: `_split_widget_tree($tree, true)` puts `<!--pg-loop-before-->` / `<!--pg-loop-after-->` beside an element that may hold only its rows (`pg_sw_loop_parent_is_closed()`).
- A widget always draws its own tree: a state prunes it, never a plain `<div class="alert">` in its place (member-widgets-rendering.md).
- Its default words fit every view a visitor can switch to: the calendar's one `empty_message` serves month, week and upcoming, so it names no period ("There are no events to show."); a stored "There are no events this month." (either language) reads as that default (`pg_sw_calendar_empty_message()`, JS `_swCalendarEmptyMessage()`).

## How does `pg-hide-if-empty` decide?
- `pg_sw_hide_empty_fields()` drops a marked element that came out empty. Its value is the inside of its `pg-field-value` element, or the whole element when it has none. Words, a picture with an address, a video, a frame, a link or a form control count; inner marked elements go first, so a box whose rows all left goes too.
- An empty semantic element closes on the same line (`_render_tree_node()`), so `:empty` matches it; never add pretty-print whitespace inside.

## Which box does a stretched link cover?
- `_render_tree_node()` gives `position-relative` to the box the link reaches only when nothing above it is positioned (`_pg_stretch_claims()`, `_pg_node_is_positioned()`). A card, a list-group-item, a position class or an inline `position:` already is, and then nobody gets the class.

## Why must the PHP renderer follow a new toHTML()?
- `_render_tree_node()` mirrors the editor's exporter: a content or component node with children (link, `btn`, `badge`) writes them through `_render_content_html()` / `_render_component_html($props, $pad, $kids)`. A new `toHTML(p, pad, children)` needs the PHP side too, or the children vanish on the live page inside a shared component or widget while the canvas and the preview (drawn in the browser) still show them.

Related: docs/memory/designer/system-widget-change-checklist.md, docs/memory/designer/system-widget-form-records.md
