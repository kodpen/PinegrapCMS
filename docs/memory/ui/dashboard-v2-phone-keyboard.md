---
name: dashboard-v2-phone-keyboard
description: New dashboard on phones and from the keyboard (#6145): the edit bar keeps its row, the grid is no live region, Save buttons are solid, the shortcut remove cross is 24 px
---

## What did the phone and keyboard pass fix?
- The edit bar is drawn as `card pg-dash-bar`. Bootstrap 6's `.card` is a flex column, and with the text block's flex basis the bar stood some 18rem tall on a phone; `.pg-dash-bar` sets `flex-direction: row` itself.
- `#pg_dash_grid` is no `aria-live` region: every card drawn or refreshed was read out whole. Moves, additions and removals are announced one sentence at a time through `pgAnnounce()` ("Moved to place 2 of 14").
- The edit bar's and the goal form's Save had `theme-primary` without `btn-solid` and drew as plain text. Every `btn … theme-primary` in `pg_dash*.js` names a variant (`tests/dashboard_v2_access_test.php`).
- A shortcut's remove cross in edit mode is at least 24 by 24 px (WCAG 2.5.8), 32 px on touch screens.

## What was tried and holds?
- 390x844 and 844x390 with touch: no horizontal scroll; holding a card's grip and dragging moves it.
- Keyboard: every control on the tab path has a name and a focus ring; a grip's arrow keys move its card and keep focus on the grip; a card's "…" opens its settings with focus inside and Escape gives focus back.
- Reduced motion: nothing runs. Forced colours: chips, pills and buttons keep their borders.
