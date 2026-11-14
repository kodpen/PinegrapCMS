---
name: campaign-profile-flow-dry-run
description: The dry-run report of turning e-mail campaign profiles into flows (MA9a, #9077): what it lists per chain and per profile left, where it is shown, the starting sites' profiles by hand
---

## What does it list? (decided: `plans/messages-area-decided-2.md`; step MA9d)
- Per chain: the head's name and state, the flow it becomes as one line per box (start and its filter, Condition, Wait, Send an e-mail with To, Why, page or text, sender), the owner and why it changed.
- Per profile left: its name, action, state and the reason in words: no start for its action, a field no start value fills, a wait too long, the flow's limits, a chain partner that stays ([[campaign-profile-flow-gaps]]).
- Counts per profile: the campaigns it made in the last 30 days and its busiest hour, the runs it would hold open (starts × wait), its ready and paused campaigns that stay with the campaign job ([[campaign-profile-flow-switch]]); for a marketing chain the recipients of those 30 days that no contact holds, who would no longer be mailed.
- Nothing is written to work it out: the converter's pure functions (MA9d, done #9458, [[campaign-profile-flow-converter]]) give it from the rows; the update step (MA9e) writes the same lines into its result in the panel. The owner sees it on a copy of a running site before MA9e lands (`plans/messages-area-steps.md`).

## The starting sites by hand (2026-11-13)?
- `english_default`: "Order Assistance" (off; order abandoned; page `order-assistance-email`; From, Reply-to and BCC info@kodpen.com; 10:00; commercial) stays: no start covers an abandoned order, and no Wait box keeps a send time yet.
- `turkish_default`: "Sipariş Yardımı", the same profile in Turkish, stays for the same reasons.
- `turkish_default`: "Kargo Yola Çıktı" (off; order shipped; page `order-shipped`; the same sender; any time; transactional) becomes a paused flow: When something happens order.shipped → Send an e-mail (the record's person, info, the page, the sender carried), once the e-mail box has a sender and fills a page's fields ([[campaign-profile-flow-gaps]]): the page greets with `[[^^name^^, ||]]`.
- The abandoned-order page also uses `^^order_reference_code^^`, which the campaign job fills only for that action.
