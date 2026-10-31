---
name: dashboard-hover-figures
description: Dashboard figures and states printed as text instead of a native title: shipped state, firewall request count and score limit, address and request counts, Search Console basis, Away
---

## Why not a title?
- A native `title` shows on mouse hover only, never on keyboard focus or touch, and on a span, div or bar a screen reader at best gets a description it skips in browse mode. `pg_widget_apply()` (`welcome.php`) appends card markup after load and binds no popovers, so a card keeps no figure or state only in a title.

## Where is each one written now?
- Recent Orders (`widget_8.php`): Shipped / Not shipped yet in the meta line when shipping is on, as Canceled already was; the truck or box glyph is `aria-hidden`.
- Firewall rows (`widget_21.php`): the `×N` pill is `aria-hidden` beside a visually hidden `{var:1} request{suffix:1}`; the score prints `s / threshold` with a visually hidden `Score:`, and the bar is `aria-hidden`.
- Threat sources (`widget_22.php`, drawn inside widget 21) and slowest pages (`widget_23.php`): the address count and the `{var:1} request{suffix:1} · peak {var:2} ms` text are a small `fg-secondary` line under an `aria-hidden` bar.
- Search Console (`widget_31.php`) and Google Analytics (`widget_32.php`, also `+N points`): `+N%` is followed by a visually hidden "Against the 28 days before"; the title stays for hover.
- Activity tiles (`widget_7.php`) and the Search Console week bars: the count says `{var:1} today` and the bars their week total as visually hidden text (`chart-text-equivalents.md`); the titles stay for hover.
- Online Engagement (`widget_4.php`): an Away row's seen line reads `Away · <relative time>`, since the avatar (with its dot) is `aria-hidden` and Offline prints the same relative time.

## What pins it?
- `tests/dashboard_hover_figures_test.php`. Nothing was rendered in a browser.

Related: docs/memory/ui/dashboard-keyboard.md
