---
name: bug-hunt-areas
description: The areas given to a bug hunt so far (2026-11-15 on), the issues each ended with and what it read and found clean; the next area is not one of these
---

## Which areas were hunted, and what did each find?
- The first two (channel messages 4114, 4115): the mail inbox with the mail chat tools, the personal calendar with its API.
- Both ended on 2026-11-15 with their issues (mail: channel message 4152; calendar: #9662 to #9667).
- Given after them on 2026-11-15: team device sessions over the API (#9678, #9679); cart and checkout totals; the agent channel's requests, approvers and wake doors (channel message 4877; the agent card #9730 and the stale door error, AS2 of `plans/agents-card-settings`, are known and not reported again).
- That last hunt ended the same day with #9740 to #9743 (approvals asked twice, two agents on one application, an approver never asked, requests left open after an approval is settled).
- Given after those on 2026-11-15 (channel message 5402): polls and agent questions over the API. It ended the same day with #9807 to #9812 (channel message 5416).
- Given after those on 2026-11-15: scheduled messages and reminders (the `/zamanla` command, change and send-now of a scheduled message over the API, "remind me later", task reminders; panel and API). It ended the same day with #9832 and #9833.
- Given after those on 2026-11-16 (channel message 5646), CA12 to CA16 having landed: a channel's settings and members over the API. It ended the same day with #9855 to #9857 (channel message 5659). Read and found clean: person-only routes refuse an application's key; the list of who loses a channel made private equals who really leaves; panel and API share their checks.
- Given on 2026-11-17 (channel message 8165), every open bug issue having a PR or a worker: the wholesale prices (the offers' group condition, shared prices, the minimum order, quantity prices on a product page; WS1, WS2, WS4, WS5). It ended the same day with #10195 to #10197 (channel message 8182).
- Read and found clean there: the group condition on every offer path; the one-hop pointer and its refusals; the lower of an account's and a campaign's price, never stacked; retail in feeds, structured data, price history and the API; the page copy marked personal; the minimum's highest group and its refusal at submit.
- Given on 2026-11-17 (channel message 8337): publish approval's server side (PA2 #9892, PA4a #9930, PA4b #9939, PA5 #9968): every save door, who may apply, a page changed since, what a visitor sees meanwhile. Known, not reported: PA3 (#10188) and the close-out. It ended the same day with #10215 to #10220 (channel message 8399).
- Read and found clean there: a held save after the gate writes nothing before it; the API, assistant and import doors untouched as decided; apply, dismiss and take back refuse role 2 in the panel and in the Workspace answer; a stale change, two editors on one element, the switch turned off.
- Four of its fixes (#10216 to #10219) share `includes/fn/content_proposals.php`: `held-file-hunt-fixes.md`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/held-file-hunt-fixes.md
