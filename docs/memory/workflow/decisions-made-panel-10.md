---
name: decisions-made-panel-10
description: Decisions list, panel part 10 (2026-11-17 on): new screens in the design language; an area's tab strip centred in the window; the Design Canvas renamed Artifact Canvas, its new icon
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-panel-9.md`.

## Is every newly drawn screen built in the design language? (owner, 2026-11-17, agent channel message 8553)
- Yes. His words to the architect: "ekipteki arkadaşları uyar. artık yeni tasarlanan her ekran yeni tasarım dilimiz ile yapılsın. özellikle tab lar ve düğmeler atlanıyor gibi geldi bana."
- He went on: "modern yeni tasarımımızla yapılan her ekran bize ek düzeltme zorluğu ve üzerinden 2. kez geçme sorunu doğuruyor". Read by the architect as: a screen not built in it costs an extra fix and a second pass over the same place.
- The architect's reading, not his words (message 8554): it covers what a step newly draws in the panel and the Workspace, a new part of an old screen included; buttons are `pg-button`, tabs the segmented control `pg-seg`.
- Not covered in that reading: an old screen's untouched markup (the plan panel-old-screens moves it), site output, the visual designer (the drawings of the plan designer-panels). He was told so in the answer (message 8555) and asked to say if he meant another place too.
- The rule and its check: `ui/look-new-parts.md`.

## Where does an area's tab strip sit? (owner, 2026-11-19, agent channel poll 153, message 9494)
- "Pencerenin ortasında": the strip is centred in the window on every tab screen of its area, whatever the screen's own width, and the lists' width rule stays.
- Asked on his words that day (agent channel message 9493), with two pictures of Communication: "tablardan birisi sola dayanıyor. diğeri tabı ortaya çekiyor (tablo genişliği yüzünden) bu başka sayfalarda da fark ettim kullanıcı deneyimini etkiliyor."
- Not chosen: at the left with every tab of the area as wide as the window; at the window's left with the content left centred.
- Built in #10390 and #10413: `ui/area-tab-strip-centred.md`.

## Is the Design Canvas renamed Artifact Canvas? (owner, 2026-11-20)
- Yes: Artifact Canvas, in Turkish "Yapıt Tuvali", wherever a screen names it. It replaces Design Canvas ("Tasarım Tuvali") of 2026-11-12 (`decisions-made-panel-6.md`); Page Design Studio and Designs keep their names.
- Owner (2026-11-20): "Adını ayrıca "Artifact Canvas" yani Yapıt Tuvali olarak güncellemek istiyorum." Told that in picture editing "artifact" also names a compression fault, he went on with the name.
- The plan writer's reading: only texts change, as in #8894, and the short word "canvas" stays. Built by `plans/artifact-canvas-rename.md`; until its step AC2 lands the screens say Design Canvas.

## Which drawing is the Artifact Canvas icon? (owner, 2026-11-20)
- Drawing A of the sheet of 2026-11-20 (`plans/artifact-canvas-rename/icon-candidates.png`): two round-cornered sheets, the back one hidden behind the front one and tinted, a wave in the accent hue on the front sheet. It replaces drawing B (`decisions-made-panel-7.md`); the one-colour rule of #9250 stays.
- Owner (2026-11-20), who brought two pictures he liked (two see-through squares with a wave, two overlapping cards) and asked what joining them gives, on the sheet: "bunu yapalım".
- Where, in his words: "özellikle dosya yönetici, kanallar ve sayfa tasarımcısında olmak üzere ikonun ve adının geçtiği alanlarda adı ve ikonu güncellensin".
- Not chosen: the first picture as it is, the wave with drawing B's pen anchors, portrait cards, one line that turns into the wave. Until the plan's step AC1 lands the code draws B.

Related: docs/memory/workflow/decisions-made.md, docs/memory/ui/look-new-parts.md, docs/memory/ui/look-page-widths.md
