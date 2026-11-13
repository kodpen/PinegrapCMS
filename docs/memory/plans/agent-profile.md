---
name: agent-profile
description: Plan: an agent's role chosen from a fixed list on its form, and the model and plan usage an application agent reports with its own key; steps AP1-AP3 (owner 2026-11-13, #9260)
---

## What is asked?
- Owner (2026-11-13, #9260): "peki ajan kanalının içinde bir oluşturulan ajanlara rol ve model bilgisi versek. hatta planta kota takibi de vardı saınırım o unutuldu mu bilmiyorum"
- Today an agent's role is only in its name. `ws_agents.provider` and `model` are a model assistant's; the site cannot know which model a session runs on. The Agents card counts tokens only for what spends the site's key (`workspace/agent-activity.md`, `agent-token-limit.md`): a session's plan usage is seen nowhere.

## What is decided?
- Record: `workflow/decisions-made-workspace-22.md`; the columns: `decisions-made-schema-17.md`.
- **The role** is chosen on any agent's form from a fixed list, not typed: Architect, General coordinator, Coordinator, Merger, Worker, Tester, Bug hunter, Marketing, Design, Data analysis, Other; empty is allowed. Owner: "liste biraz geniş olsun".
- **The model** of an application agent is what the agent reports with its own key. A model assistant's stays the one on its form; an agent with no key of its own reports nothing.
- **The usage** is the agent's report too: its plan's windows as it reads them (five-hour, weekly), percent used and reset time. The site cannot check it; an agent that cannot read it reports nothing.
- **Four columns** on `ws_agents`: the role, the reported model, the reported usage, the time of the report. The last report only, no history.

## What does the architect add?
- The list is one function in code (key and English label, the label through `lang()`); the column holds the key. A new role is a code change, never a schema change; an unknown key shows as no role.
- A role is a label: it gives no right, changes no wake rule and makes no mention word.
- The role is not put into the brief. Where the API already returns an agent's row it carries `role`, so an agent can find who holds one.
- A report is shown as a report, with its age ("reported 12 minutes ago"); older than a day it is drawn muted. A reported value is text from outside: bounded and escaped.

## Which steps are there?
- Each S, in this order; a step starts when the one before it has landed. Column, route and file names are the worker's call.

- AP1 (S, schema) The role. Done: #9302. A 2026.11.0 step adds the four columns with the idempotent helpers, empty by default: nothing changes on a running site. The role select on the agent's form (every kind), saved through `ws_agent_save_check()`; the role beside the name on the Agents card and in a channel's member list; `role` in the API's agent rows. Tests: the list, an unknown key, the save check.
  - Turkish with `tools/tr_add.php`: Mimar, Genel koordinatör, Koordinatör, Birleştirici, İşçi, Test, Hata avcısı, Pazarlama, Tasarım, Veri analizi, Diğer.
- AP2 (S) The report. Done: #9322 (`workspace/agent-report.md`). One route an application agent calls with its own key: a model name of at most 80 characters and at most four usage windows, each a name from a fixed set, a percent 0-100 and a reset time; anything else is refused whole. It writes the report columns of the caller's own row only. The Agents card shows model and usage with the report's age.
- AP3 (S, tools only) The session's side. `tools/ws_profile.php`, beside `tools/ws_post.php`, sends the model and the windows given as arguments; how a session reads its plan's limits is its own. `workflow/agent-channel-wake.md` says when: at a session's start and on its round, at most once an hour. Close-out: this plan is deleted.

## What is not in the plan?
- A history or chart of reports, a limit or warning on plan usage, rights by role, a mention of everyone holding a role.
- Not tried: whether a cloud session can read its plan's limits. Agents on one account report the same account's numbers.
