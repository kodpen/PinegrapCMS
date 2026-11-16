---
name: visitor-push-features
description: Plan visitor-push, features N1 to N18: the switch, the site's worker, the "Notify me" block, a subscription, a message and how it arrives, the panel's card and screen, stock, an order, app mode
---

## What switches it on, and what runs on the site?
- (N1) A switch "Visitor notifications" in Site Settings, Features, a settings text, off on every site. With it off no worker is registered, the block draws nothing and the door refuses.
- (N2) The site's worker is one file under the software directory, answered with `Service-Worker-Allowed: /`. It handles a push and a click on a notification and nothing else: no fetch handler, so no request of the site or the panel changes.
- (N3) A block "Notify me" (mark `data-pg-push`, its script added as the scroller's is): hidden where the browser cannot subscribe or the switch is off. Its states: "Notify me", "Notifications are on" with "Stop", and a hint when the browser has them blocked. The worker is registered only when the visitor presses the button.

## What is a subscription?
- (N4) The press asks the browser's own question; on yes the browser's endpoint and keys go to `site_push.php` with the page's language. The server keeps them with a secret it hands back once; the worker keeps the secret.
- (N5) An endpoint is taken only as https on a known push service's host, with keys of the right length, from the same origin and under a rate limit per address.
- (N6) A subscription is the browser's. Signed in, it is tied to the account so export and erasure find it; signed out, it names nobody.
- (N7) "Stop" removes it on the site and in the browser. One the push service reports gone is removed when a send finds out.
- (N8) A yes and a stop are written to the consent log under a purpose of their own.
- (N9) Never a row in the panel's `push_subscriptions` or `push_queue`.

## What is a message, and how does it arrive?
- (N10) A title (at most 60), a text (at most 200) and a link that is an address of the site; to everyone or to a topic.
- (N11) A queue row per subscription, written in batches; the general job and `push_job.php` send an empty push with the panel's VAPID key. A service's "slow down" holds that service; a message not delivered within a day is dropped.
- (N12) The woken worker asks `site_push.php` with its secret for its waiting message and shows it; a click opens the link in the site's open tab or a new one. The push itself carries no text.

## What does the panel show?
- (N13) Communication, Channels: a card "Notifications" with the number of subscribers and the last message.
- (N14) A screen "Send a notification": the form of N10, "Pick a page or record" to fill it, "Send a test to this device", and the sent messages with sent, failed and removed counts.

## What comes after the first version?
- (N15) Stock: on a product that is out, a subscribed browser's back-in-stock request can name this device; `pg_stock_notify_send()` then sends the push for it, once.
- (N16) An order: the order's own pages offer "Notify me on this device"; shipped and delivered reach that device. Transactional, as the SMS is.
- (N17) App mode (owner, agent channel poll 73), under a switch of its own that starts off: the site's manifest says `standalone`, and the worker answers a failed page load outside the software directory with an offline page.
- (N18) A subscription tied to an account or contact is in the privacy export and goes with an erasure.
