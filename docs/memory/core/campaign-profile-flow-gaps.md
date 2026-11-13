---
name: campaign-profile-flow-gaps
description: What no flow box covers of an e-mail campaign profile (MA9a, #9077), and the proposal: the e-mail box, the Wait box and the events gain it first; the profiles that would keep running, listed
---

## Which gaps would be closed first? (proposed in #9077, for the architect)
- One step before the converter, the e-mail box sends as a campaign does: optional From name, From address, Reply-to and BCC; a page body with a base tag and absolute links, its `^^fields^^` (and `[[…||]]` fallbacks) filled from the run's start values and the recipient's contact (`^^name^^`, `^^first_name^^`, `^^last_name^^`), the page's PHP given the run's record as `data`.
- The same step, for marketing: `?t=` codes on same-site links and the campaigns' commercial footer (organization address, preferences and unsubscribe links); a run started by `contact_group.joined` refuses a contact opted out of that group.
- A second step, the Wait box and the events: an optional time of day (HH:MM, the site's zone: the first such moment at or after the wait's end); in until mode "If the moment has passed: go on / end the run" (go on by default, so flows drawn before keep their way).
- The same step: `calendar_event.reserved` gains `start_at` (the reserved repeat's start) and its contact as the e-mail box's person; the events carry the single values their profile mails fill (shipment, booking, subscription, credit fields) as start-box outputs.
- Why these: each is general (every flow gains it), none needs a column, and without them hardly a real profile converts: the starting sites' profiles all set their own sender, and most bodies greet with `^^name^^`.

## Which profiles would keep running as profiles? (proposed)
- An action with no start ([[campaign-profile-flow-map]]): order abandoned, review request, product ordered, booking reminder, credit expiring, subscription price changed. Each needs a start of its own (a cart-left scan like `no_order`, an event announced where the moment is worked out): later steps, not MA9.
- A wait longer than `WS_FLOW_DELAY_MAX`; a `^^field^^` that no start value fills.
- A profile whose last 30 days would pass 80% of a flow's limits: starts an hour (`WS_FLOW_STARTS_HOURLY`, and `WS_SCHEDULED_CHAIN_HOURLY` for its start box) and runs open (`WS_FLOW_MAX_OPEN_RUNS`; a 7-day wait holds a week of starts open). A start over a limit is refused, so its mail would be lost; a profile has no such limit.
- A chain with any of these stays whole ([[campaign-profile-flow-switch]]).
- What stays keeps its screens: the profile screens and the import list only the profiles left, and leave on a site with none (in place of MA9d's "leave").

## Which differences would be accepted? (proposed)
- Marketing to an address no contact holds is not sent: consent belongs to a contact.
- "View this email at our site" goes: there is no campaign row to view. Flow mails leave the campaign history and its counts; the Sends list (MA2) shows them from the mail queue.
- A follow-up goes after a first mail that was sent (`sent` ≥ 1), not after one given up; a transactional follow-up reaches a person with no contact, where the profile needed the contact.
