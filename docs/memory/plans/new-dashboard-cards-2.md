---
name: new-dashboard-cards-2
description: New dashboard plan, cards 17-30 (Security, Team, Ecommerce, System): id, default width and period, what each shows, where its data comes from, its gate
---

## Which Security card?
- `firewall` Firewall, S, Today: tiles Blocked, Bots, Failed sign-ins (red text); 24 hourly bars, red above the threshold; "Most blocked" bars (countries, AI crawlers); "Security log". Widget 21 (and its widget 22 half). Role < 3.

## Which Team cards? (Workspace members, the gates of widgets 27-30)
- `team` Team pulse, S: avatars with a presence ring (online, away, offline), "3 online · 7 open tasks · 4 late", "Who works on what" rows. Widget 30.
- `mytasks` My tasks, S: rows with a box ticking the task done (`ws_task_status`, as the Workspace's Today page), "Today 1/4 done", "All tasks". Widget 27.
- `waiting` Waiting on me, S: "Ayşe → you · 1 day" rows with Open. Widget 28.
- `week` My week, S: seven day boxes with event dots, today marked; then the coming items as dated rows. Widget 29.
- `calendars` Calendars, S: "26 October – 29 November · 3 calendars", a five-week grid with event days marked, the next three events, "Go to calendars". Widget 20; CALENDARS on.

## Which Ecommerce cards? (ECOMMERCE on, each with its widget's gate)
- `shop` Ecommerce summary, M (S in the Ecommerce preset), 30 days: the revenue figure with "last 30 days · 41 orders", seven daily bars (today highlighted), best sellers. Widget 3.
- `salesmap` Sales map, M, 30 days: the revenue figure, Turkey shaded by province with a Few-Many legend, "Provinces" bars. Widget 1 (`ui/dashboard-sales-map-data.md`); commerce reports right.
- `ops` Operations desk, M: three lanes, To ship, Refund, Cannot be sold, each a count and up to three rows. Widget 9.
- `orders` Orders, S: the last three orders (number, customer, state in a tone, amount); "All orders". Widget 8.
- `offers` Offers, S: running campaigns and coupons (amount, code, note). Widget 15.
- `fx` Exchange rates, S: "Updated every day at 09:00 · last 7 days"; per currency the code, name, rate, change and a seven-day sparkline. Widget 16.

## Which System cards?
- `perf` Performance, S: a ring "182 ms" "Fast, average response, target under 300 ms"; tiles page load, errors, uptime; "Slow pages". Widget 23; monitoring on; role < 3.
- `subs` Subscriptions, S: "Pinegrap Pro · Active", the renewal date, the period as a bar ("56% of the period passed · 43 days left"), the support package. From the licence the vendor API client returns (`plans/kodpen-vendor-api-client.md`); not offered until that answers. Widget 14 stays retired and its number unused. Role < 2.

## Which cards are new data, which are existing widgets?
- From existing widget functions (their queries shared, their output unchanged): activity, comments, files, visitors, engagement, gsearch, ga, firewall, team, mytasks, waiting, week, calendars, shop, salesmap, ops, orders, offers, fx, perf.
- New data: ai, goals, pipeline, queue, flows, pins, notes, content, visitmap, subs.

## Which sizes and periods may a card take?
- Every card takes all three widths; the body reflows (bars beside or under the chart, tiles in one or two rows). Only cards with a period above show the pill and the compare setting; compare is drawn by charts (visitors) and by the change figures.
