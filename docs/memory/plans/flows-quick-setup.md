---
name: flows-quick-setup
description: Plan (owner 2026-11-12, #8950): a quick setup that builds a flow by choices (start, steps one after the other, summary) over every area and action of the site; rules and steps QS0-QS5
---

## What is asked?
- Owner (2026-11-12): "birde akışlarda hızlı kurulum adımları olan bir yapımız olsun, kullanıcıya seçmeli sipariş geldiğinde x sonra sonraki adımlar sonra sonraki sonraki şeklinde ileri ile whatsappdan kullanıcıya yazılabilsin. (whatsapp şablonlarından seçilerek)".
- On the first drawing: "hızlı kurulum fena değil biraz daha geliştir ve kapsamı genişletelim. site diğer eylemlerinide kapsasın."; the widened drawing was approved ("Ha"). Record: `workflow/decisions-made-workspace-20.md`.

## What is drawn?
- `plans/flows-quick-setup/quick-setup.html` with its pictures in both themes; a dialog of 820 x 700 in look A (`panel-design-language-dialogs.md`), three stages in its head: Start, Steps, Summary.
- Start: the site's areas in a left rail (orders, cart and quotes, forms, customers, reservations, products and stock, reviews, subscriptions, invoices, content and campaigns, Workspace, by time, by hand), a search over all of them, the area's starts as cards, an optional "Only when" row.
- Steps: ready recipes that fit the chosen start on top, then every action in groups (message, people, records, logic, system); a chosen action opens its own setting; "One more step" returns here.
- Summary: the steps in order with move and edit buttons, a condition's steps indented under it, the flow's name, "Start working when saved", "Open on the canvas", "Start the flow".

## Which rules does every step keep?
- It only creates. The result is a normal draft made through the store's functions, with the same graph check and problems (`workspace/workspace-flows-store.md`); nothing at run time knows the flow came from the quick setup, and it is edited on the canvas afterwards.
- Every start and action shown is a box kind or a start event that exists (`workspace/workspace-flows-overview.md`). An action with no box yet is not shown; a new box is general and added by `workspace/workspace-flows-adding.md`, never for the quick setup alone. The WhatsApp step comes from `whatsapp-channel-steps.md` (WA7).
- An area is a grouping of the event start's catalogue, kept in one list on the server; "Only when" becomes a Condition box after the start; a recipe is data of the template kind (`workspace/workspace-flows-template-adding.md`), not code.
- A step's setting is drawn by the canvas's own field code for that box, so a field is described once.
- Rights as for making a flow on the canvas. No schema.
- The names in the drawing are examples; the labels are the boxes' own.

## Which steps?
- QS0 (S, no product code) The map: each drawn start and action against today's kinds and events; what has no box (an SMS step, "Add to a group", "Give an offer code") as proposals to the architect; the shape of the areas list, of a recipe and of the choices the dialog sends.
- QS1 (M) Server: the catalogue (areas, starts, action groups, recipes filtered by start) and the function that turns the choices into a draft through the store; tests on the graph it makes.
- QS2 (M) The dialog's first stage: rail, search, start cards, "Only when".
- QS3 (M) The second stage: recipes, actions, a step's setting, "One more step".
- QS4 (M) The summary: order, edit, insert, a condition's indented steps, the name, start or open on the canvas.
- QS5 (S) Ways in: "Quick setup" beside "New flow" on the Flows list and in its empty state; close-out: the lasting rules into `workspace/`, this plan deleted.

## When does it start?
- QS0 may start now. QS2 to QS4 follow QS1 one after the other (one dialog script). Claim as `[plan:flows-quick-setup] <step> — <topic>`.
