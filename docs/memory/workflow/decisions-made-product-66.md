---
name: decisions-made-product-66
description: Decisions list, product part 66 (2026-11-16 on): his looks of polls 101 to 103 (VP4, the Content review row "Make a task", QR3), the architect's rules for CH3, CH4, MP2 and RB2
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-65.md`.

## Do the Notifications card and the "Send a notification" screen go out as built? (owner, 2026-11-16, agent channel poll 101, message 6884)
- Yes: "Evet, böyle çıksın", on the sheet of message 6883 (VP4, #9977): the "Notifications" card of the Channels screen, "Send a notification" with its confirmation, the sent list, a phone and dark.

## Does "Make a task" stand in the Content review row menu? (owner, 2026-11-16, agent channel poll 102, message 7034)
- Yes: "Evet, böyle çıksın", on the sheet of message 7033 (CH3, #10008): the row "Make a task" in the manager's row menu after "Clear", and the notice with a link to the task when the item already has an open one. Nothing else on the approved screen changed.

## Do the bar over the selected object and the Motion popover go out as built? (owner, 2026-11-16, agent channel poll 103, message 7038)
- Yes: "Evet, böyle çıksın", on the sheet of message 7037 (QR3, #10003): the bar over a text, over a video in Animate with the Motion popover open, several layers, a layer at the stage's top edge (the bar below it), a locked layer, dark.

## Which scope do the content review API routes ask? (architect, 2026-11-16, agent channel message 7025)
- A pair of their own, `content_review:read` and `content_review:write`, in `api_scope_groups()`, open to roles 0 to 3 in `api_owner_scopes()`.
- A role 3 caller reads only the items it is the person of and may only mark those reviewed; another's item answers 404. Interval, person, clear and the bulk actions stay with roles 0 to 2.
- Why: `pages:*` ends at role 2 and V17 lets an item's person, who may be role 3, work through the API; opening `pages:*` to role 3 would open every page route.

## Who gets the task of the gallery flow "A page is due for review"? (architect, 2026-11-16, agent channel message 7026)
- The item's person, through a setting on the flow's task boxes, "the person the event names" (the payload's `user_id`), with a required fallback person for an event that names nobody or a person not on the team. It is step CH3b; CH3 lands without the template.
- Why: a template with a fixed person breaks V16, and a setting only for this event would split the task box.

## Where do a record's coordinates come from while Google Places or Mapbox is on? (architect, 2026-11-16, agent channel message 6927)
- Always from Photon: the Photon account's host when Photon is the provider that is on, else Photon's public instance, also while Google Places or Mapbox serves the address suggestions. The button is always drawn and a help line names the service. It replaces message 6794's "with such an account the button is not drawn".
- Why: a site with Google suggestions at checkout must not change them to place its records; nothing is asked of Google or Mapbox, so their terms on keeping a result do not bind.

## When does the Bundles screen get its main menu row? (architect, 2026-11-16, agent channel message 6918)
- After RB3 and RB4 have landed, never before: until then the screen cannot publish, take back or time a bundle.
