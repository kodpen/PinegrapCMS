---
name: decisions-made-workspace-18
description: Decisions list, workspace part 18 (2026-11-12): the channel sound silent in a hidden tab (#8798); one canvas with drawing, live page and data, the guarded bridge to data, the order (#8850)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Does the channel sound play in a hidden tab? (owner, 2026-11-12, #8798)
- No. A hidden tab stays silent, as built in #8792: the channel screen does not ask the site while its tab is hidden, the person counts as away, and the background's signal is web push ("Notifications on this device").
- The choice of option is the architect's, made under the owner's word while he was away. Owner (2026-11-12): "ben uyuyorum gerisi sende. genel olarak senin kararların benim için güvenilirdi o yüzden soru gelirse değerlendir ve seçim yap". He was given the choice on his return and did not overturn it.
- Not chosen: a slow look every 30 seconds in a hidden tab while the sound is on (a request only for the sound, and it holds web push back); the slow look only when web push is off.
- Open: a person who has not turned on "Notifications on this device" hears nothing in a hidden tab; a hint beside the sound switch is the owner's to decide.

## Is the canvas one thing? (owner, 2026-11-12, #8850)
- Yes: a drawing, a live page and data are parts of one canvas.
- Owner (2026-11-12), the direction: "tuval claude uygulamasındaki yapıt özellikleri (uzaktan api ilede kullanma ile), workspace tablolar da data bölümüne dönüşüyor. arayüzleri sadeleşiyor, mobil kullanırlık için iyileşiyorlar."; on the architect's three proposals: "3 önerin de ok."
- The live page is the agent's HTML page of #8762, not the tool that designs the site's pages. Owner (2026-11-12): "canlı sayfa dedin burada neyi kastettin? görsel sayfa tasarımcısı değil doğru mu"; confirmed to him.

## May a live page reach data? (owner, 2026-11-12, #8850)
- Yes, through a guarded bridge: only the tables bound to that canvas, with the rights of the person looking; the first write is asked of that person.
- This widens #8762 (`decisions-made-workspace-16.md`), where a page reached nothing; the rest of that guard stays.

## In which order is it built? (owner, 2026-11-12, #8850)
- DL1 and DL2 of the panel's design language and the bridge first; workflow tables as Data and the phone on top of them. Plan: `plans/canvas-one.md`.
