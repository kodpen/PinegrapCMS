---
name: decisions-made-workspace-49
description: Decisions made, workspace part 49 (2026-11-23 on): a note written in a channel is the channel's; an agent channel's item shared by a link; memory search for agents; git stays the record
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-workspace-48.md`.

## Whose is a note written in a channel? (owner, 2026-11-23, in the architect's window)
- The channel's. His words: "notlarda karışmadan kanal notu olarak kalsa? bugün için notlar benim notlarıma karışıyor."
- It is on nobody's My Notes screen and counts in nobody's note limit; it opens from the channel. Versions, editing in place and export stay.
- This replaces the architect's call of 2026-11-20 that an agent's note belongs to the person its key acts for: the agents' plans filled the owner's own notes. A person's own note shared into a channel stays that person's.
- Built by PR #11198, #11226 and #11272: `workspace/channel-note-owned.md`; the column: `decisions-made-schema-48.md`.

## Is an item of an agent channel shared by a link? (owner, 2026-11-23, in the architect's window)
- Yes. His words: "ajan kanalında paylaşılan içerikler karışık bir kısa bağlantı ile paylaşılabilir olsa ve koordinatör işçileri ile paylaşsa bu sayede görseller işçiler tarafından görüntülenebilse?"
- One item a link, read only, an address nobody can guess, taken back at any time, always with an end: 1, 7 or 30 days, 7 unless chosen (his answer "Varsayılan 7 gün").
- Kinds: a picture and any file first, then a note, a table, a list: "türlerini çoğaltabiliriz, görsele ek; tablo, liste vb. olabilir".
- The item carries a mark while a link to it is open: "dosya kenarında da bir belirteç mantıklı olur".
- The channel's head shows a share mark while the channel has an open link or a guest; its menu holds the channel's own sharing and the shared items together, the same list in Channel info (his answer "Başlıkta paylaşım işareti"; "o alan hem kanal hem de kanaldaki paylaşılan içerikler için ortak alan olur").
- Why: a worker has no key of the channel, so a picture the owner posted there reached a worker only when the architect copied it into the repository.
- The "No" to a secret-address feed of a person's notes stays (`decisions-made-product-13.md`, #3880): that was every note, for good, with nothing to cut it; this is one item with an end and a take-back.
- Built by the share links steps (SL1 to SL6): `workspace/share-links.md`; the table: `decisions-made-schema-48.md`.

## Do agents get an index of the memory and a search in it? (owner, 2026-11-23, in the architect's window)
- Yes, for the later move of this project's work into the agent channel, not for today: "bellek kısmı bizim ileriki tam ajan kanalı geçişimiz için ayarlamalıyız, bugün için değil".
- Plan: `plans/agent-memory-search.md`; no step of it is given out until he opens it.

## Do this project's memory and plans leave git for the agent channel? (owner, 2026-11-23, in the architect's window)
- Not now. He asked whether keeping them in git slows the work. The architect's answer, which he accepted ("makul", "önerilerinin büyük bölümü yerindeydi. bu şekilde ilerleriz"): the texts stay in git, where most PRs change memory together with code; what slows is a docs-only PR's full check and the plan file shared by workers and the architect.
- Those two are taken now: "darboğaz için yukarıdaki önerilerini bugün yapmak makul" (`plans/plan-state-channel.md`).
- The agent channel on dev may be set up again and emptied at any time, so nothing lives only there; its last home is meant to be pinegrap.com.
