---
name: campaign-profile-flow-gaps
description: What no flow box covers of an e-mail campaign profile (MA9a, #9077), and what is decided: the e-mail box, the Wait box and the events gain it first (MA9b, MA9c); the profiles that keep running, listed
---

## Which gaps are closed first? (decided: `plans/messages-area-decided-2.md`)
- MA9b, before the converter, the e-mail box sends as a campaign does: optional From name, From address, Reply-to and BCC; a page body with a base tag and absolute links, its `^^fields^^` (and `[[…||]]` fallbacks) filled from the run's start values and the recipient's contact (`^^name^^`, `^^first_name^^`, `^^last_name^^`), the page's PHP given the run's record as `data`.
- The same step, for marketing: `?t=` codes on same-site links and the campaigns' commercial footer (organization address, preferences and unsubscribe links); a run started by `contact_group.joined` refuses a contact opted out of that group.
- MA9c, done #9437, the Wait box and the events: an optional time of day (HH:MM, the site's zone: the first such moment at or after the wait's end); in until mode "If the moment has passed: go on / end the run" (go on by default, so flows drawn before keep their way) (`workspace/workspace-flows-delay-box.md`).
- The same step: `calendar_event.reserved` gained `start_at` and its contact (else its order) as the e-mail box's person; the events carry their mail values as start-box outputs: shipments `tracking_numbers_text`, `carrier_name`, `tracking_url`; bookings `service_name`, `staff_name`; subscriptions `product_name`, `order_number`; credit `source`, `name`.
- Not carried: a booking's manage link, a subscription's card and confirm links (they would reach every webhook subscriber); a profile filling them keeps running as a profile (a field no value fills).
- Why these: each is general (every flow gains it), none needs a column, and without them hardly a real profile converts: the starting sites' profiles all set their own sender, and most bodies greet with `^^name^^`.

## Which profiles keep running as profiles? (decided)
- An action with no start ([[campaign-profile-flow-map]]): order abandoned, review request, product ordered, booking reminder, credit expiring, subscription price changed. Each needs a start of its own (a cart-left scan like `no_order`, an event announced where the moment is worked out): a later plan, not MA9.
- A wait longer than `WS_FLOW_DELAY_MAX`; a `^^field^^` that no start value fills.
- A profile whose last 30 days would pass 80% of a flow's limits: starts an hour (`WS_FLOW_STARTS_HOURLY`, and `WS_SCHEDULED_CHAIN_HOURLY` for its start box) and runs open (`WS_FLOW_MAX_OPEN_RUNS`; a 7-day wait holds a week of starts open). A start over a limit is refused, so its mail would be lost; a profile has no such limit.
- A chain with any of these stays whole ([[campaign-profile-flow-switch]]).
- What stays keeps its screens: the profile screens and the import list only the profiles left, and leave on a site with none (step MA9f).

## Which differences are accepted? (decided)
- Marketing to an address no contact holds is not sent: consent belongs to a contact. The dry-run report counts those recipients.
- "View this email at our site" goes: there is no campaign row to view. Flow mails leave the campaign history and its counts; the Sends list (MA2) shows them from the mail queue.
- A follow-up goes after a first mail that was sent (`sent` ≥ 1), not after one given up; a transactional follow-up reaches a person with no contact, where the profile needed the contact.
