---
name: decisions-made-workspace-30
description: Decisions made, workspace part 30 (2026-11-14 on): an approval request can ask a channel's agents and assistants, within a bound; the tag @herkes mentions everyone in a room but the writer
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Can an approval request ask a channel's agents and assistants? (owner, 2026-11-14, in the architect's window)
- Yes. Owner: "Onay iste aracını da geliştirelim eş zamanlı. Onaylayanlar kısmına kanalda olan ajan ve asistanları da dahil edelim olur mu? api uçları yoksa onlarıda ekleyelim."
- His case: "@ajan1 @ajan2 @ajan3 mesajımı aldınız mı?" asked as an approval request; "onay kutusunu işaretlerler".
- The bound the architect proposed: only a request a person makes by hand may ask an agent or an assistant; a flow's request and an agent's request ask people only; no agent answers a request it made. A flow's approval is a gate put there for a person to look. He answered: "planın mantıklı uygun".
- Plan: `plans/agent-approvers-everyone.md`. The column an agent approver needs is asked of him after the census.

## Is there a mention of everyone in a room? (owner, 2026-11-14, in the architect's window)
- Yes, `@herkes`. Owner: "birde bir odada birden fazla ajan veya kişi varsa @herkes etiketi de ekleyelim. bu etiket kişinin kendisi dışındaki herkese anmaya yarasın. örneğin ajan kanalında tüm ajanlar. (asistanlar hariç)"
- Told the shape and two bounds (an agent's `@herkes` rings people and wakes no agent; a guest of a customer room is not counted and gets no mail), he answered: "@herkes uygun."
- The architect's, not the owner's words: typed `@everyone` does the same; `herkes`, `everyone` and `hepsi` are no agent's handle.
- The roll call of 2026-11-11 (`decisions-made-workspace-16.md`) is not withdrawn: a checklist still wakes nobody. What wakes is a person's approval request that asks agents, or a person's `@herkes`.
