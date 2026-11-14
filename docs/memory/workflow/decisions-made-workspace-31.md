---
name: decisions-made-workspace-31
description: Decisions made, workspace part 31 (2026-11-14 on): the conversion of e-mail campaign profiles into flows (MA9e2) was shown to the owner on a local copy and he let it land
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## How was the profiles' conversion shown to the owner before it landed? (owner, 2026-11-14, agent channel poll 3466)
- The plan's gate for MA9e2 (#9502): the update step is shown to him on a copy of a running site before it lands.
- He chose "Yerel kopyada, dev'deki örnek profillerle göster". Not chosen: "Gerçek bir sitemin kopyasında göster", "Görmeme gerek yok, insin".

## Do e-mail campaign profiles turn into flows at the update? (owner, 2026-11-15, agent channel poll 3525)
- Yes: he voted "Evet, bu haliyle birleşsin" (outcome: channel message 3528). Not chosen: "Hayır, önce değişiklik istiyorum (yazacağım)", "Kendi gözümle bakmak istiyorum, kopyayı açık tut".
- What he was shown (channel message 3520 and the pictures under it): a throwaway install on his machine with dev's ten profiles (the examples 35 to 42 and the starter's two, all off), dev only read.
- The trial's outcome: the steps run twice marked the conversion once; one pass of the general job made five paused flows (one of them with its follower as a wait and a second mail) and left four profiles with their reasons in the report; a second pass made nothing.
- Not seen in the trial: an enabled profile becoming a live flow and being turned off, because dev's examples are all off; the worker's sandbox run and the tests cover it.
- The architect's, not the owner's words: the report keeps a left profile's reason as a sentence in the job's language; a reason code drawn when the report is read is a follow-up, not a condition.
- How such a trial is made: `workflow/sandbox-on-windows.md`.
