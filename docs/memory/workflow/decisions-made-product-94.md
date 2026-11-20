---
name: decisions-made-product-94
description: Decisions list, product part 94 (2026-11-21 on): queue notices and the unfilled-shift call are service messages; a volunteer's document file is kept, no health report; Go live turns both off itself
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-93.md`.

## Under which consent do the queue's notices and the unfilled-shift call go? (owner, 2026-11-21, agent channel poll 207, channel message 11149)
- "İkisi de hizmet iletisi": the queue's notices go to the number the person gave for the queue, at most three; the call for an unfilled shift goes to a volunteer who applied and chose the channel, once a shift. Neither needs marketing consent or İYS, neither holds promotion; a volunteer turns the calls off on their profile.
- Not chosen: "Sıra hizmet, vardiya çağrısı ticari", "İkisi de ticari".

## Is a volunteer's document kept as a file? (owner, 2026-11-21, agent channel poll 208, channel message 11150)
- "Dosya saklansın, sağlık raporu hariç": the file is kept, seen by the programme's coordinators only and deleted with the volunteer. No health report: it is not among the document kinds, and the roles' set-up says in one line not to ask for one.
- Not chosen: "Dosya saklanmasın", "Dosya saklansın, sağlık raporu da".
- Architect: the file is an attachment served only through get_file.php after the document's own check (the programme's coordinators, roles 0 to 2, the volunteer themself), never by a direct address and never listed in the File Manager. The step that builds it brings that door to the architect before ready.

## What does "Go live" do? (owner, 2026-11-21, agent channel poll 209, channel message 11152)
- "İkisini kendisi kapatsın": the button turns maintenance mode off and removes the robots catch-all itself, each named in the confirmation and written to the Site Log. It never changes a payment mode.
- Not chosen: "Yalnız o ekranlara götürsün".
