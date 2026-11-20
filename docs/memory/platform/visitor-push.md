---
name: visitor-push
description: Visitor notifications start file: the "Notify me" block, the Features switch, Send a notification; its decisions, which file holds each part, the known gap
module: site_push
---

## What is it?
- A visitor presses "Notify me" on the site and gets the site's news on their phone or computer, with no account, no e-mail address and no app store. Staff send from Communication, Channels, "Send a notification". Built 2026-11 (#9947, #9957, #10288, #9977, #9975, #9976, #10480, #10481).
- The push carries no text: the site's worker fetches its message from `site_push.php` with the subscription's secret, so no payload encryption is needed and PHP 7.1 keeps working.

## Which file holds what?
- Tables, the switch, the door `site_push.php`, the secret, consent log, export and erasure: `visitor-push-rules.md`.
- The sender (queue, batches, held services, a day's limit, tidy-up, counts): `visitor-push-sender.md`.
- The "Notify me" block, `pg_site_push.js`, the worker `site_push_sw.php`: `visitor-push-block.md`.
- The panel's card and screen: `ui/visitor-push-panel.md`.
- One device waiting for one record (`site_push_watch`): `visitor-push-watch.md`; back in stock: `visitor-push-stock.md`; an order's shipped and delivered: `visitor-push-order.md`.
- App mode (standalone manifest, offline page): `visitor-push-app-mode.md`.

## What did the owner decide?
- Owner (2026-11-16, agent channel poll 73, message 5759): App mode under a switch of its own that starts off, because an iPhone delivers web push only to a site installed with `display: standalone`.
- Owner (2026-11-16, agent channel poll 74, message 5760): the browser's own question is enough consent for news; the yes and the stop go to the consent log.
- Owner (2026-11-16, agent channel poll 94, message 6781): one extra table, `site_push_watch`, for the stock request and the order to name a device.

## What did the architect decide?
- Architect (2026-11-16, agent channel message 5763): roles 0 to 2 send, as the Channels tab; no topics in the first version; a fixed short notification named after the site when the message cannot be read, because a browser punishes a silent push.
- Architect (2026-11-16, agent channel message 6528): the columns, message kinds 0 to 3 and the queue's tidy-up (`visitor-push-rules.md`, `visitor-push-sender.md`). A stock notice goes beside the mail or SMS, never instead.
- A visitor's subscription never goes into the panel's `push_subscriptions` / `push_queue`: that queue deletes the rows of an account it cannot wake (`core/push-disabled-account.md`).

## Is there a known gap?
- Back in stock (N15) on the page: the server takes `push_secret` (`stock_notify.php`) and sends the push, but the product page's back-in-stock form (`widgets_catalog.php`, the hidden `stock_notify.php` form) has no `push_secret` field and nothing fills it from `window.pgSitePush.secret()`; `pg_site_push.js` is loaded only on a page holding a "Notify me" block or an order button. So no request names a device today. Found at the close-out (VP8); to be planned as its own step.
- App mode's manifest exists only while a Site Icon is set (`site-icon.md`); without one, App mode changes only the worker.

## What was left out?
- Topics a visitor chooses; a flow box "Send a notification"; a push by itself when a record is published; clicks counted; subscribers picked by contact group. Each needs a new yes from the owner.

## Whose look?
- The "Notify me" block and the panel's card and screen: the owner's (screen: poll 101, message 6884, "Evet, böyle çıksın"). The Features switch: the architect's.
