---
name: decisions-made-product-48
description: Decisions list, product part 48 (2026-11-12 on): Legacy to Bootstrap helper (#8967), the picker's list of everything (#9070), designer canvas samples (#9076), WhatsApp marketing consent (#9087)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## What does the Legacy to Bootstrap helper do on a design that is not Bootstrap 3? (owner, 2026-11-12, #8967)
- Nothing: the card says that the design does not use Bootstrap 3 and offers no report and no trial design; such a site stays on its Legacy design. Rules `plans/legacy-bootstrap-helper-rules.md`.
- Asked with the pictures of the Materialize trial (`designer/legacy-materialize-trial.md`) and three options (nothing; a report only; a second helper with a Materialize map), the owner (2026-11-12): "Ta, Ma" (Ma: nothing).
- The architect's, not the owner's words: Bootstrap 3 is found by the classic framework sheet's asset row, never by class names.

## What does the picker open on? (owner, 2026-11-12, #9070)
- A list of everything of its kind: the files, pictures or pages of every folder in one list, newest first, search and sort above; the folder tree stays at the side as a filter. Plan `plans/picker-all-list.md`.
- Owner (2026-11-12): "her seferinde klasör klasör açtığı için […] aranan dosya bulmak zorluyor. dosya klasör sistemi ek olarak olmalıydı."; asked what it opens on, he chose "Tümü listesi".
- It replaces the opening on a folder of the screens approved on 2026-11-10 (`decisions-made-product-42.md`, #8514); the rest of those screens stays.

## What does the designer canvas draw for a bound value, and what of closed blocks? (owner, 2026-11-13, #9076)
- A realistic sample for every bound value, never its label in ‹…› or an empty box, so the canvas looks like the published page; and the components still drawn as one closed block are listed and opened into editable elements. Plans `plans/designer-canvas-real-samples.md`, `plans/designer-closed-blocks.md`.
- Owner (2026-11-13), with a picture of an order e-mail card: "şu anda birçok bileşen yer tutucudan ibaret. bunu bir an önce değiştirmeliyiz. kullanıcılar gerçek sayfa bileşenleri tasarlamayı bekliyor olacak."; asked which of the two, he chose "İkisi de".

## Do loop bands and condition chips stay on the canvas? (owner, 2026-11-13, #9076)
- Yes, always on as today; only their overlap and overflow are fixed. Asked between "only on the selected element", "always on, tidy" and "a toolbar switch", he chose "Hep açık, düzgün".

## Which consent lets a marketing WhatsApp template go? (owner, 2026-11-13, #9087)
- The number's stored SMS marketing consent (granted, no refusal there or in İYS), read again at the send. From WA10 the SMS consent box reads "SMS and WhatsApp" while WhatsApp is set up. Consents taken as "SMS" cover WhatsApp too: the option he chose said so.
- Owner (2026-11-13): "SMS pazarlama izni sayılsın (Önerilen)". Not chosen: only consents given after WhatsApp is set up; a WhatsApp consent of its own.
- Architect: a key for one recipient is a single-use `key_codes` row made at enqueue and kept in the queue row's payload; no schema.
