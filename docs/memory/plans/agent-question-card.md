---
name: agent-question-card
description: Plan (owner 2026-11-14): a question card only a channel's agents can ask, with described, pictured and linked options, answered by the named person, who wakes the asking agent; steps AQ1 to AQ6
---

## What is asked?
- Owner (2026-11-14, in the architect's window): the poll confuses as the way an agent asks him; agents get a question area of their own with more options, pictures and links, the poll left as it is. Only a channel's agents ask (nothing in the "+" menu), an agent can take a question back, change it and ask again, and the answer wakes the asking agent. His words: `workflow/decisions-made-workspace-29.md`.
- Today (`workspace/agent-questions.md`): a one-choice poll; a vote wakes nobody, the agent keeps its own waiter, the outcome is in the agent's name.

## What is a question?
- A card in the channel from an application agent the channel's "Agents in this channel" allows, addressed to one named person; with nobody named, the channel's owner.
- One to four questions on a card, each with two to ten options: a label, a line of description, an optional picture (a channel file; a drawing is a file too, never inline markup), optional links. One answer or several, a mark on the recommended option, "Other" in the person's own words unless the agent turns it off.
- Three shapes, chosen by what the options carry: a large picture beside the options that follows the option pointed at; a picture on every row; no picture, the option told in text.
- Only the addressed person answers, all questions with one Send; the others read the card and its state. The answer is written under the card as a decision in the person's own name, so the owner account's answer is the owner's word.
- States: waiting, changed (while waiting; the card says so), taken back (no answer taken), answered. Asking again opens a new card that names the old one.

## Which steps?
- AQ1 (S, no product code) Census: what the poll, the approval and the checklist keep and draw, how a request row and its brief are made (`workspace/agent-doors.md`), how an agent's change of its own message is held, how a channel file is drawn inside a card. It names the tables and columns; the architect asks the owner before AQ2.
- AQ2 (M, schema) The server: asking, changing, taking back, asking again, answering, with the gates above and their tests. An answer makes one request row for the asking agent and knocks its door as a mention does; the brief carries the question and the answers. The person may change the answer until the agent has claimed that request.
- AQ3 (M) The card in the channel in its three shapes and four states, at phone width too. Look A; the whole picture set goes to the owner, the design is new and his to approve.
- AQ4 (M) Routes for an application agent's key and connection (ask, change, take back, ask again, read), never a person's session; the answer route person-only and off MCP. `tools/ws_ask.php` for an agent's own side; `php tools/check_api_schema.php` passes.
- AQ5 (S) The addressed person is rung whatever their notify level (`ws_question_ring()`); the question stands under what waits for them in Today and on the dashboard's Assistant card, each line opening the card. A channel's export writes the card as text.
- AQ6 (S) Close-out: `workspace/agent-questions.md` says how an agent asks a person from then on, the working rules follow, this plan deleted.

## What does every step keep?
- The poll, the approval and the checklist do not change. A question one agent asks another stays a checklist.
- No person makes a question card, from the panel, the API or a team device.
- A guest never sees one; a customer channel takes none.
- Later, not here: several addressed people, a reminder or a last day, answering from the bell, a model assistant asking.
- No table or column without asking the architect. Texts through `lang()`, Turkish by `tools/tr_add.php`.
- AQ1 first, AQ2 after the owner's answer on the tables, AQ3 and AQ4 after AQ2, AQ5 after AQ3. Claim as `[plan:agent-question-card] <step> — <topic>`.
