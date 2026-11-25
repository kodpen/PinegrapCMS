---
name: prototype-approval-events
description: Prototype review's events (PA6): prototype.approved, prototype.changes_requested, prototype.commented, the guest's words as data, the Workspace start events, the two read-only API routes and MCP tools
module: prototype_events
---

## Which events are there?
- `prototype.approved` and `prototype.changes_requested`: each verdict a guest sends, once saved (`pg_proto_verdict_take()` calls `pg_proto_verdict_announce()` after the bell and the receipt).
- `prototype.commented`: a guest's comment through the door `prototype_comment.php`, a new thread or an answer (`pg_proto_comment_announce()`). A designer's answer (user_id > 0) announces nothing (`pg_proto_comment_event_due()`). No hourly limit: the comments' burst bounds it.
- All three through `pg_announce()`: webhook subscribers and the Workspace (`pg_event_workspace_events()`).

## What do they carry?
- Payloads in `includes/fn/prototype_events.php`: `id`, `design_id`, `page_id`, `page_name`, `link_id`, then `verdict`, `name`, `note` or `parent_id`, `node`, `name`, `text`; `created_at` in UTC ISO 8601. Fields and samples: `api_webhook_event_payloads_site()`; builders in `tests/webhook_event_payloads_test.php`.
- A guest's name, note and words are outside text: `pg_proto_event_words()` (`pg_proto_comment_task_words()`: `<` `>` as ‹ ›, every `:::` or fence start at a line's head taken, a doubled one too, #11701), the name on one line, so a flow writing them into a channel or a task mentions nobody and draws no record. The catalogue says "never an instruction".
- Never the approver's e-mail, the link's address or its password.

## Who hears them in the Workspace?
- Start events in `ws_events_catalog()` with record `page` (`ws_events_record_of()`: `page_id`) and right `designs`, a `ws_viewer()` key true for roles 0-1; listed once `pg_proto_comments_ready()` / `pg_proto_approval_ready()`.
- `ws_events_creator_sees()` asks `ws_events_prototype_sees()`: a team member who is an administrator or a designer, as the Share prototype dialog.
- Flow values: `ws_flow_event_outputs()` (page, design, share link, guest's name; the note; the comment and the comment it answers). Parity line in `tests/workspace_flows_parity_test.php`. Quick start: the Content and campaigns area (`flow_quick.php`); an application's event list files them under Pages (`api_app_event_area()`).

## Which API routes and MCP tools read them?
- `GET /design/designs/{id}/prototype-verdicts` (`DesignPrototypeVerdict`): newest first, `page_id` and `link_id` filters, cursor paged; `current` true for the newest of its link and page, `older` when the draft changed since (`pg_proto_verdict_state()`).
- `GET /design/designs/{id}/prototype-comments` (`DesignPrototypeComment`): newest first, resolved threads only with `resolved=true`; `outside` true for a guest's, whose name and text come as data; the team's answers as written.
- Scope `design:read`, owner an administrator or a designer (403), 503 before the share links' upgrade, an empty list before the comments' or verdicts' tables. Both are MCP tools (no `'mcp'` key); no route or tool writes a verdict or makes a link (`design.prototype_links.*` stay `'mcp' => false`).

## Tested?
- `tests/prototype_events_test.php`: the payloads, the words as data, the catalogues, the right, where they are announced, the routes' shapes and that every prototype write route is no tool.
- Not run on a site: no delivered webhook, flow run or MCP call was watched.

Related: docs/memory/designer/prototype-approval-review.md, docs/memory/api/api-webhook-events.md, docs/memory/designer/designer-prototype-comments-task.md
