---
name: image-editor-touch
description: The image editor on touch: the two-finger twist that turns a layer, 44 px handle targets and the finger's reach for points and edges (M.touchReach())
module: image_editor
---

## How does the two-finger twist work?
- On touch, a finger moving one unlocked layer (Select's `move` drag, also from Draw, Text, Redact and Vector) plus a second finger turns that layer with the line between the fingers, instead of zooming the view: `twistAngle()` adds the turn, keeps -180..180 and holds on multiples of 45° within 4°. Lifting either finger ends it; the move and the turn are one undo step. Two fingers elsewhere still pan and pinch.

## How big is a touch target on a phone?
- A handle drawn as `.pgie-sh` (crop, box handles, warp mesh points, deformation pins) or `.pgie-h` gets a 44 px `::after` target under `(pointer: coarse)`, its drawn size kept.
- A point or edge found by distance takes `M.touchReach(e.pointerType, px)`: 22 px (half of 44) for a finger, the tool's own reach for a mouse or a pen. Used by the polygon's first corner (Select area, 10 px with a mouse), a pin added on the shape's edge (Free deformation, 6 px) and a scissors click on an outline (8 px); a finger's scissors tap may wander 10 px before it counts as a cutting line (4 px with a mouse) (E29).
