---
name: decisions-made-product-82
description: Decisions list, product part 82 (2026-11-20 on): the referral friend check (account and folded e-mail, no address match, a busy lock tried again); the sales watch
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-81.md`.

## Who is "this friend" in the referral programme's friend check? (architect, 2026-11-20, agent channel message 10330, answer to K1's request 1742)
- The account and the folded e-mail together (#10629): an earlier complete or exported order counts when it has the buyer's account, or when its billing e-mail, folded, equals one of the buyer's e-mails.
- The buyer's e-mails are the order's billing e-mail and, with an account, the account's e-mail. One folding rule, `pg_referral_fold_email()`, on both sides: SQL narrows the candidates, PHP compares.
- Why: feature E7 means the friend's first order. "Account, else e-mail" missed a guest order placed before the account was opened, and took `name+2@…` or a Gmail address with other dots for a new friend, so one friend earned a reward per spelling up to the month's cap.
- No table, no column, nothing new stored; rewards already given are not taken back.
- The reading of E7 is the architect's, not the owner's words.

## Does the self check compare addresses? (architect, 2026-11-20, agent channel message 10330)
- No (#10630): a customer who orders by their own link as a guest, with a new e-mail and their own address, earns the reward. It is a known limit, written with the checks in the commerce folder (`commerce/referral-rules.md` names them).
- Why: people of one home refer each other rightly, so an address match refuses real friends; the month's cap and the smallest order bound the loss; the Referrals report shows the top referrers.

## What happens to a reward whose referrer's lock was busy? (architect, 2026-11-20, agent channel message 10330)
- It is tried again (#10631). Only an order whose first try answered `busy` is tried: its id goes into the `config_text` row `referral_pending` (written with `pg_config_text_claim()`), and a scheduled job that exists calls `pg_referral_order_completed()` for each; any answer but `busy` takes the order off the list.
- Not a pass over every referred order without a reward row: an order a check refused at completion would pass later (the month's cap in a new month, a programme switched on afterwards, a referrer's later first order).
- The list holds at most 200 orders and drops an entry after 7 days. No new cron entry; `order_completion.php` is not changed.

## Does the store learn its own order rhythm and say when it breaks? (owner, 2026-11-20, agent channel poll 165, channel message 10147)
- "Evet, plana girsin": all five signals (orders stopped, a channel silent, payment broken, the price error shield, the return spike), told through the operations alert channels once a day; a small shop stays quiet.
- On a price error the product is taken off sale only when a setting allows it; one click puts it back; stock is never changed.
- Not chosen: "Evet, ama yalnız uyarsın" (never take a product off sale), "Evet, önce sipariş durdu ve ödeme kırıldı" (the two flow signals first), "Hayır, gerek yok".
- The plan: `plans/sales-watch.md`.
