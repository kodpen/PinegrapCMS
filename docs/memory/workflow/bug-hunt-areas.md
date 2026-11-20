---
name: bug-hunt-areas
description: The areas given to a bug hunt on 2026-11-15 and 2026-11-16, the issues each ended with and what it read and found clean; the hunts from 2026-11-17 on are in bug-hunt-areas-2.md
---

## Which areas were hunted, and what did each find?
- The first two (channel messages 4114, 4115): the mail inbox with the mail chat tools, the personal calendar with its API.
- Both ended on 2026-11-15 with their issues (mail: channel message 4152; calendar: #9662 to #9667).
- Given after them on 2026-11-15: team device sessions over the API (#9678, #9679); cart and checkout totals; the agent channel's requests, approvers and wake doors (channel message 4877; the agent card #9730 and the stale door error, fixed by #9759 (`workspace/agent-door-words.md`), are known and not reported again).
- That last hunt ended the same day with #9740 to #9743 (approvals asked twice, two agents on one application, an approver never asked, requests left open after an approval is settled).
- Given after those on 2026-11-15 (channel message 5402): polls and agent questions over the API. It ended the same day with #9807 to #9812 (channel message 5416).
- Given after those on 2026-11-15: scheduled messages and reminders (the `/zamanla` command, change and send-now of a scheduled message over the API, "remind me later", task reminders; panel and API). It ended the same day with #9832 and #9833.
- Given after those on 2026-11-16 (channel message 5646), CA12 to CA16 having landed: a channel's settings and members over the API. It ended the same day with #9855 to #9857 (channel message 5659). Read and found clean: person-only routes refuse an application's key; the list of who loses a channel made private equals who really leaves; panel and API share their checks.
- The hunts given from 2026-11-17 on: `bug-hunt-areas-2.md`, from 2026-11-19 on `bug-hunt-areas-3.md`. The next area is none of the three files.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-2.md
