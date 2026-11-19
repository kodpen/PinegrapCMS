---
name: decisions-made-product-68
description: Decisions list, product part 68 (2026-11-16 late): the Bundles screen and the digest mail accepted; the highlight bar as one sentence; a picture count on a poll's box; squeezes; RF1's part refund
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-67.md`.

## Is the Bundles screen accepted? (owner, 2026-11-16, agent channel poll 107, message 7134)
- "Evet, böyle çıksın" to the Bundles screen (RB2 #9998), shown on one sheet (message 7133).

## Is the digest mail accepted? (owner, 2026-11-16, agent channel poll 109, message 7241)
- "Evet, böyle çıksın" to the digest mail (DG2 #10026): each post's picture is linked and carries the title as its alternative text, in its own shape; a post without a picture has none.

## What does the highlight bar read? (owner, 2026-11-16, poll 108 and typed to the architect)
- He chose "Şunu değiştir" on the bar that spelled both verbs out, and wrote: "uzatmak yerine "(herkes|kendim) için vurgula" gibi olsa?".
- So the bar is one sentence with the shared words once and the two choices as buttons: "[Herkes] [Kendim] için vurgula", English "Highlight for [everyone] [me]" (issue #10018, PR #10021).
- The architect's reading, not his words: one click as before, the two choices one joined control, the highlighter icon once at the bar's start, each choice's accessible name the whole sentence; where only "me" is allowed, one button "Kendim için vurgula". The look of the new shape is his.

## Does a poll's box say how many pictures it has? (owner, 2026-11-16, typed to the architect)
- "ajan sorusunda birden fazla görsel varsa önizlemede 2 görsel olduğu yazılsın yada bootstrap 6 carousel indicator gibi bir şey olsun".
- Issue #10023: a count mark on the large picture when a poll has two or more different pictures; one file on several options counts once. Words were chosen over dots: dots say there are more, not how many, and he asked for the number first. The look is his.

## What did he say of squeezed parts? (owner, 2026-11-16, typed to the architect)
- "başta daha dikkatli tasarımlar yapılsın sonradan tekrar tekrar aynı yere geri dönmeyelim. bu ve bunun gibi sıkışıklıklar düzelsin ve modernleşsin"; the whole sentence, the two issues and the working rule that followed: `look-narrowest-column.md`.

## Does a part refund take a referral's reward? (architect, 2026-11-16, agent channel message 7257)
- The order's value is computed again less refunds. Under the smallest order of the programme's setting the whole reward is taken back once and the row is no longer eligible; otherwise nothing is taken. No share, no give-back, never taken twice.
- The census of `commissions` readers was accepted with three differences: the Commissions totals count affiliates' rows only; `GET /commissions` gets a read-only `kind` (affiliate or referral); Edit Commission is read-only for a referral's row.
