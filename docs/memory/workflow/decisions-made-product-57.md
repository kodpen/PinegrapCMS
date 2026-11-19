---
name: decisions-made-product-57
description: Decisions made, product part 57 (2026-11-15 on): the Recovered carts section (poll 55); the record count on All Offers (poll 46); the Prototype view rebuilt on the graph canvas core
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Does the Recovered carts section come out as drawn, and where does it sit? (owner, 2026-11-15, agent channel poll 55)
- Yes. Asked with one sheet of CR2's pictures right above it (message 5385, the sheet is message 5384), his own vote: "Evet, böyle çıksın".
- This is his look at the Recovered carts figures (#9796, `commerce/cart-recovery-figures.md`) and his yes to the section's place: on the Visitors overview, between Conversions and Sources. Passed on in channel message 5403.
- The architect's points before it, not his words: the left carts tile shows a rise as worse (`pg_visitors_change()` with lower-is-better); the card header's menu button at 390 is the shared frame's and stays; the section's tables scroll sideways on a phone as every table of the overview does.

## Does All Offers show its record count beside the title? (owner, 2026-11-15, agent channel poll 46)
- Yes. Asked with the picture right above it (message 4850, the picture is message 4849), his own vote: "Evet, görünsün". Built by #9804.

## Is the designer's Prototype view rebuilt, and on which engine? (owner, 2026-11-15, in the architect's window)
- Yes. His complaint: "sayfalardaki prototip ekranı çok karışık ... hepsi yan yana kimden çıkan kime gidiyor belli değil"; he had imagined it "figmadaki gibi ama daha modern ve kullanışlı hali" and finds Figma's own view tangled too.
- The first drawing (agent channel poll 57, picture 5488) got "biraz daha değerlendirelim."; to the second (agent channel message 5499): "evet buna yakın, planını yaz".
- The engine is the graph canvas core, strengthened, not a second one: "ayrı motor yerine akış motorunu güçlendirme yoluyla ilerleyelim. birkaç pr daha fazla geçeriz ama prototip ve ileride akışları belli mantıksal işler için ortak kullanabiliriz" (as his decision of 2026-10-24, one core with adapters).
- He liked that the view finds a prototype's errors ("prototip hatalarını tespiti sevdim"). Links are to find their way by themselves once a prototype is designed ("otomatik yönlerini bulsunlar ... bunun için bağlantılar için ek sorular olabilir").
- What that covers (agent channel poll 58, 2026-11-16, picture 5635, his own vote, all three options): arrows route themselves; a link without a target is offered the page whose name fits, the unsure ones asked, nothing saved before he confirms; a page no link leads to and a link that leads nowhere are marked as problems too. Built in #10204 and #10243 (`designer/prototype-problems.md`, `designer/prototype-suggest.md`).
- He asked for a plan detailed enough that no feature is skipped: the prototype-canvas plan, numbered features, keeps and steps PV1 to PV12, built in #9864 to #10243 and closed; what is built: `designer/prototype-view.md`, what it keeps: `designer/prototype-view-rules.md`.
- The architect's readings, not his words: the frame is the page's own picture, links marked where they sit, arrows only for the selected frame, sections per tab group, the list of the selected page's links.
