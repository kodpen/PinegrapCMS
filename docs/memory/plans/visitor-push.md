---
name: visitor-push
description: Plan (owner 2026-11-16, agent channel message 5667): notifications a site visitor subscribes to in the browser without an account, sent from the panel; steps VP1 to VP8
priority: 6
---

## What is asked?
- The owner's words and the layout of the set: `plans/website-features.md`. It was item 8 of agent channel message 5657, narrowed in message 5709.
- A visitor presses "Notify me" on the site and gets the site's news on their phone or computer, with no account, no e-mail address and no app store.

## What is there today?
- Push reaches accounts only: `push_subscriptions` rows need a user (`pg_push_subscription_save()`), the queue is keyed by user, and a push carries no text: the woken worker asks `api.php` with the person's session (`includes/push.php`). A member is woken when staff answer their chat (`ui/chat-push-recipient.md`).
- The site has no service worker ("No service worker on sites", `ui/panel-offline-and-install.md`); the panel's `sw.js` has the software directory as its scope. The site's manifest comes with Site Icon and says `display: browser` (#5065).
- Back-in-stock notices go by e-mail or SMS (`commerce/back-in-stock-notices.md`); an order's moments by SMS and WhatsApp from fixed calls. No event says a record was published.
- Communication, Channels shows a card per channel (`pg_messages_channel_cards()`).

## What is built?
- The numbered features: `visitor-push-features.md` (N1 to N18). Steps: `visitor-push-steps.md`. Claim as `[plan:visitor-push] <step> — <topic>`; the rules: `website-features-rules.md`.
- In short: tables of its own, a worker for the site that only shows notifications, a "Notify me" block, and a screen to send a message. As today the push carries no text: the worker fetches its message with the subscription's secret, so no payload encryption is needed and PHP 7.1 keeps working.

## What binds it?
- `core/push-disabled-account.md`: the panel's queue deletes the rows of an account it cannot wake, so a visitor's row never goes there (N9).
- `core/consent-log-cookie-notice.md`: a stored page carries no form token; the door checks the origin and the rate.
- An anonymous visitor names the address the server will call: only a known push service's host is taken (N5).

## What did the owner decide? (2026-11-16)
- App mode (N17): an iPhone delivers web push only to a site installed with `standalone`, which #5065 had left at `browser`. Owner (agent channel poll 73, message 5759): "Evet, ayrı bir anahtarla ve kapalı başlayarak".
- Whether "news" needs more than the browser's own question. Owner (agent channel poll 74, message 5760): "Evet, tarayıcının sorusu yeterli"; the yes and the stop go to the consent log (N8).

## What did the architect decide?
- Architect (2026-11-16, agent channel message 5763): who may send is named (N14); no topics in the first version (N10); a fixed short notification when the message cannot be read (N12).
- The three tables' columns: `workflow/decisions-made-schema-24.md`. A stock request and an order name a device in one table, `site_push_watch` (owner, poll 94; `workflow/decisions-made-schema-25.md`); VP5's notice goes beside the mail or SMS, never instead.

## What is left out?
- Topics a visitor chooses; a flow box "Send a notification"; a push by itself when a record is published; clicks counted; subscribers picked by contact group. Each is a later yes.

## Whose look?
- The "Notify me" block (VP3) and the panel's card and screen (VP4): the owner's. The Features switch: the architect's.
