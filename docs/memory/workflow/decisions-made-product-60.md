---
name: decisions-made-product-60
description: Decisions list, product part 60 (2026-11-16): the click and scroll map's handling of visitors' data and its start on a new install (agent channel polls 67 and 75)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The other answers to the website feature plans' questions: `decisions-made-product-59.md`.

## What does the click and scroll map count, and how does it start? (owner, 2026-11-16, polls 67, 75)
- Poll 67: asked how the switch starts on a new install, he wrote instead of choosing: "bu konuyu mimara danışırmısın. o tasarımcımız ve kullanıcı verisi işleme ile ilgili fikrini sunsun olgunlaşsın." The architect's view is agent channel message 5774.
- Poll 75: "Evet, Mimar'ın görüşüyle". No consent is asked: a total that knows nobody, as page views and the speed figures. A visitor who refused statistics in the cookie notice, or closed it unanswered, sends nothing; a signed-in member's view sends nothing.
- An e-mail, phone or messaging link keeps its kind only; a code-like part of an address's path becomes `*`; a label shaped like an e-mail or holding a long run of digits is kept empty.
- The switch starts off on a new install too. The empty state in Visitors and in the designer says in two lines what is kept and turns it on with one button; the Features card holds a ready sentence for the site's privacy text.
- A heat map by coordinates and recorded sessions are outside this answer: each needs a decision of its own.
- The architect's, not asked of him: with the speed beacon on, both go in one request a page view.
- The architect's too (2026-11-16, agent channel messages 5840, 6043): a pop-up's counts (#9877, `visitors/auto-dialog-counts.md`) follow the same rule, by one check both counters call. With the cookie notice off a count is sent. With it on, it is not sent when `pg_consent` refuses statistics (`a0`, which closing the notice writes); it is sent on `a1` and while the visitor has not answered.
- That check has its own name on the server and in the browser, tested with the same cases. `pg_consent_allows()` is not it: it stays the gate of cookies and held scripts, where an unanswered visitor is not allowed. A signed-in member's pop-up is counted, staff's is not.
