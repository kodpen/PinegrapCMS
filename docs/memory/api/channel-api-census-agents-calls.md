---
name: channel-api-census-agents-calls
description: CA1b census, an agent's own calls (requests, brief, claim, answer, fail, report, wake) and its questions, approvals and proposals beside their routes; its own routes are off MCP
---

## Which routes does an agent call with its own key?
- Requests: `GET /workspace/agent-requests`; brief `GET …/{id}/brief`; claim, answer (text, no_answer, emoji, conclude, offer_conclude), fail `POST …/{id}/claim|answer|fail` (`api_agent_requests.php`, dry run on the writes).
- Its schedule: `GET|POST /workspace/agent-wake` (every, at, off). Its report: `POST /workspace/agent-report`. Its instructions: `GET /workspace/agent-instructions`.
- All `'mcp' => false` and only for an active `app` agent's own key (`ws_api_agent_caller()`), 403 for any other caller. A model assistant makes none: the site answers for it.
- Write in a channel with no request (a wake with channel 0): `POST /workspace/channels/{id}/messages`. Read more: `GET /workspace/channels/{id}/context`. Edit its own message: the message update route.

## How does an agent ask the people?
- A question: `POST /workspace/channels/{id}/polls` (tool); the key makes it an agent question (`poll-agent-question.md`). Change, take back, ask again, close its own: `/workspace/polls/{id}`, `…/withdraw`, `…/again`, `…/close` (off MCP; close refused for an agent question).
- An approval: `POST /workspace/channels/{id}/approvals` (tool); naming agents in `agents` is refused to a key (by-hand bound).
- A checklist line ticked: `POST /workspace/messages/{id}/checks` (app_id kept).

## How does an agent read the answers?
- The brief of the wake request the answer makes (`ws_agent_question_brief_lines()`), or `GET /workspace/polls/{id}` and the poll on the message (`answered_by`, `answer`). The memory index in every brief lists answered questions.
- An approval's answers: `WorkspaceApproval` (`people`, `guests`, `agents`).

## How does an agent answer what it is asked?
- An approval it is asked to approve: `POST /workspace/approvals/{id}/decide` with its own key (off MCP, `approval-agents-api.md`).
- An agent question asked of people: never; votes stay the person's.

## How does an agent propose?
- Record changes: `POST /workspace/channels/{id}/changes` (`workspace.changes.propose`), applied at once within its tier (`agent-risk-tiers.md`); task twin `POST /workspace/tasks/{id}/changes` refuses an agent key. Read: `GET /workspace/changes`.
- A flow or scheduled draft: the same route with `type` flow or scheduled.
- Concluding a discussion: on the answer route (conclude, offer_conclude), nowhere else.
