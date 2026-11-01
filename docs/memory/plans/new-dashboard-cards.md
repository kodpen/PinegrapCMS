---
name: new-dashboard-cards
description: New dashboard plan, cards 1-16 (General, Content, Visitors): id, default width and period, what each shows, where its data comes from, its gate; what every card shares
---

## What does every card share?
- Header: icon in its category colour (the Assistant's in the gradient), title, optional status chip, badge "n new" (items after the previous sign-in, hideable per card), period pill where it has a period, "…" (edit mode with its settings open). No data: one line (`ui/dashboard-percent-and-empty-text.md`). Rows and figures open their record or list as counted (`ui/dashboard-list-links.md`).
- A card the person may not see is not drawn, offered or saved; the widgets' `show` rules carry over. A button that changes something calls its screen's endpoint with its checks; with none, it opens the record.

## Which General cards?
- `ai` Assistant (Claude), M. Connected: chip "Claude · connected"; "Claude's daily summary · 17:30", three lines with a link each (question 3); "Proposals waiting for your approval · n", chips opening each change card (`workspace/workspace-change-proposals.md`); "Give Claude a job: …" posting with @Claude where Claude answers (`workspace/workspace-claude-routine.md`), with the prototype's note. Not connected (screen 15): "Connect Claude", Connect Claude and Connections, the MCP note. Workspace members.
- `goals` My goals, S: up to three rings (name, value, %, toned note); "+ Add goal": name, a tile figure or revenue, target, week or month; kept in the layout.
- `pipeline` (title by preset), L, 30 days: four step boxes (label, figure, note, eight-point sparkline, optional flag chip); a box opens its list for the period (`new-dashboard-presets.md`).
- `queue` Next up, M: "From open modules; a row goes when it is done"; rows with a module tag (FR form, İÇ content, EK team, MĞ shop, MH accounting), text, toned note, an action opening the record (Reply, Publish, Moderate, Approve, Show, Shipping label, Add stock, Remind). Sources: unanswered forms, drafts ready, comments waiting, the person's Workspace approvals, SEO and accessibility issue counts, unshipped orders, low stock, refund requests, overdue tasks and invoices.
- `activity` Activity, S: chips All, Content, Form, Team, Security; five timeline rows; "Open all activity". Widget 7's feed.
- `flows` Flows, S: tiles Live (paused), Today (runs), Approval (waiting on you); "Now · <flow>" as five nodes (done, waiting, to do); "Last runs" (done; waiting with Approve; failed with Retry); "Go to flows". `ws_flow_runs`, `ws_flow_run_steps`. Workspace members.
- `pins` Pinned, S: tiles of pinned screens and records, "+ Pin" from the panel's search; in the layout.
- `notes` My notes, S: one plain-text box saved as typed, at most 2,000 characters, in the layout.

## Which Content cards?
- `content` Content and design, M: thumbnails of the four last edited pages (name, Draft, Live or the scheduled time, when); "Open the Visual Editor".
- `comments` Comment moderation, S: waiting comments as bubbles with Approve and Spam; "All comments". Widget 25.
- `files` File management, S: storage ring (images, documents, other, free), last uploads; "Go to files". Widget 18.

## Which Visitors cards?
- `visitors` Visitor summaries, M, Today: tiles visits (change), unique visitors, average time; a 24-point line (days for 7 and 30 days) with the previous period or last year dashed; "Most read" and "Where from" bars. Widget 5's rollups.
- `visitmap` Visitor map, S, 30 days: Turkey with a bubble per city sized by visits; "Last 30 days · 1,284 visits · abroad 6%"; "Cities" bars. Question 2.
- `engagement` Online engagement, S: "n people on the site now", "11 visits in the last 30 minutes", 30 one-minute bars, pages being viewed. Widget 4; refreshes.
- `gsearch` Google Search, S, 7 days: clicks, impressions, click rate, average position with changes; top queries. Widget 31, when linked.
- `ga` Google Analytics, M, 7 days: users, sessions, engagement rate, average time; sources as one stacked bar. Widget 32, when linked.
