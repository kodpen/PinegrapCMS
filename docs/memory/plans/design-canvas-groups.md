---
name: design-canvas-groups
description: Plan (owner 2026-11-20): Design Canvas layer groups, a layer attached to another layer, Follow holding a small thing, and picking and moving an animated text on a phone; GC1-GC4
---

## What did the owner ask?
- Owner (2026-11-20, in the architect's window; agent channel message 9832): "tasarım tuvalinde gruplama gelecekti o özellik atlanmış."
- "mobilde bir yazı animasyon yaparken seçilemiyor seçme aracı da seçemiyor konumunu değiştirmek için Metin sekmesi tıklanıp seçmek gerekiyor ayrıca mobilde taşımak zor."
- "bugün videoda takibi denedim takip yapılamıyor diye defalarca uyarı aldım küçük bir gemiyi takip edemedi."
- "yerleşmiş bir vektör, nesne veya resim takip edilir olsun. yani bir vektör diğerini takip edebilsin (bir nesne yol olarak kaydırıldığında. videodakinden farklı olarak gruplandırılmış mantığı manuel olabilir nesne takibinde)".
- Record: `workflow/decisions-made-product-80.md`.

## What is built?
- GC1, groups. Two or more picked layers become a group (Group, Ctrl/Cmd+G; Ungroup, Ctrl/Cmd+Shift+G; both in the layer menu and the bar over the object). A group is one row in the layer list that opens to its members; a click picks the group, a second click a member. Move, size, turn, opacity, order, copy, delete and Animate's keys work on the group as on one layer; a member keeps its own keys inside it. Groups nest.
- GC1: export and the server's draw give the same pixels as the ungrouped layers at the same places.
- GC2, attach. A layer's "Attach to" (layer menu and Animate's Path tab) names another layer or group; from then on it takes that layer's move, turn and size at every moment, its path and keys included, and keeps its own offset and its own keys on top. "Detach" leaves it where it stands. No circle of attachments; a deleted target detaches its followers in place. This is the manual "follow" of his words: nothing is searched in pixels.
- GC3, Follow holds a small thing. The tracker (`ui/image-editor-follow.md`) searches frames shrunk to 320 px, so a small box is a few pixels. It reads a region round the last place at a scale where the box's shorter side is at least 24 px (never above the clip's own pixels). When it still loses the thing the message says why and what helps (a box inside the thing, a larger thing), once, not on every try.
- GC3: the step first rebuilds his case as a fixture: a thing about 2 % of the frame's width moving over water.
- GC4, an animated text on a phone. In Animate and with Select a tap on a text layer picks it (today the Text tab must be opened first); the fault's cause is found and a test holds it. A picked layer moves by a drag anywhere on it, with the finger's reach of `ui/image-editor-touch.md`, and the pill over the stage gets nudge arrows (1 px, held: 10 px) for fine moves. Started 2026-11-20.
- Order: GC4 and GC3 first (faults he met), then GC1, then GC2 (it attaches to groups too). One PR each, a and b where a step passes the size; they share the canvas's model, so one step at a time.

## Which rules does every step keep?
- No schema: groups and attachments live in the document the canvas already stores; an older document opens unchanged, and a document with groups opened by the server's draw gives the same picture.
- No library and no model (`ui/image-editor-overview.md`). Texts through `lang()`, shortcuts on the help sheet (`ui/image-editor-access.md`), each control reachable on a phone.
- Unit tests in Node on the pure model for every command; an undo step per command.

## How does the plan close?
- `ui/image-editor-select.md`, `ui/image-editor-follow.md`, `ui/image-editor-touch.md` and a new file for groups and attachments say the new state; this plan is deleted.
