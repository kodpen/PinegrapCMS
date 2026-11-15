---
name: channel-api-gaps-steps
description: Steps CA2 to CA16 of plans/channel-api-gaps.md: the agents' side (CA2 to CA6) and a channel's own side (CA7 to CA16), each a group of the censuses' gaps with who may call it
---

## How is a line read?
- All S. G numbers are the census's: CA2 to CA6 from `api/channel-api-census-agents-gaps.md`, CA7 to CA16 from `api/channel-api-census-channel-gaps.md` and `api/channel-api-census-channel-gaps-content.md`, which name the function behind each gap.
- "Person only" is `ws_api_person_only()` and no MCP tool; "own words" is `ws_api_own_words_only()` (`workspace/workspace-api-callers.md`).
- Given 2026-11-15 (agent channel messages 5101 and 5119). A done step gets its PR number here.

## The agents' side?
- CA2 (`@elcin`) G1 the "Add an agent" list (`addable` on the channel's agents) and G2 the agent card (`GET /workspace/channels/{id}/agents/{agent_id}`), with MCP tools.
- CA3 (`@adnan`) G3 the person card (`GET /workspace/channels/{id}/members/{user_id}`): team readers only, `seen` for a manager.
- CA4 (`@elcin`, after CA2) G4 a person sets an agent's wake (`POST /workspace/channels/{id}/agents/{agent_id}/wake`: every, at, off; dry run). Person only: an application key sets no other agent's wake.
- CA5 (`@adnan`) G5 `agent:<id>` and `model:<id>` as `agent` values on `GET /workspace/assistant-requests`. Done (#9770).
- CA6 (`@adnan`) G6 and G7, the words an agent reads: the brief says its own routes are HTTP only; the answer route's refusals name the next step; a paused agent's 403 says why. Done: #9768.

## A channel's own side, `@elcin`?
- CA7 Messages: G1 `note_id` on forward, G3 who has seen and acknowledged, G4 bring the mentioned people in. Done #9767.
- CA8 Files in a message: G2 post a file of the file manager, G29 an edited file in place of the old, G30 a file's edit history.
- CA9 Scheduled messages: G6 change one, G7 schedule a reply, G8 send now. Own words, as create is. Done #9786.
- CA10 Discussions and approvals: G9 conclude, G10 rename, G11 take back or close an approval (who may is the function's own rule). Done: #9794.
- CA11 Status and check-ins: G12 status update, G13 start a check-in and answer one (the answer person only).

## A channel's own side, `@adnan`?
- CA12 Channel reads: G14 one channel's detail, G23 the Files and Board tabs, G33 `channel_id` on the tables list, G19's list of versions, G24 export (REST only, no tool). Done (#9790).
- CA13 Channel fields: G17 records watch, G18 summary read and write, G16 the Claude, Pinegrap AI and voice switches (the three switches person only). Done: #9791.
- CA14 Channel access and versions, person only: G15 make private or public (the answer names who leaves and who comes in), G20 join a public channel, G19 clear the conversation and rename a version.
- CA15 Canvases: G26 put an older version back, G27 undo an agent's edit (person only), G28 the tables a page may bind.
- CA16 Tables and the rest: G31 delete a binned row for good (person only), G32 a flow from a table, G5 saved blocks search and pull, G22 customer follow-up.
