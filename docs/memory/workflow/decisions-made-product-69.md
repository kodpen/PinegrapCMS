---
name: decisions-made-product-69
description: Decisions list, product part 69 (2026-11-17 on): the e-mail campaign's Create stores it paused; integration packages at priority 4, outside steps after 2026.11.0; the Options panel in a 240 px column
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-68.md`.

## What does Create do on New campaign › E-mail? (owner, 2026-11-17, agent channel poll 116, message 7554)
- He chose "Duraklatılmış kaydet, Kampanyayı Düzenle'yi aç": Create stores the campaign as paused and opens Edit Campaign, where the send time, "Send a test" and the preview are. It goes out once Status is set to "Ready to Send" and saved.
- Before, Create stored it `ready` with an empty start time, which the campaign job takes as due: it went out on the job's next run with no look, no test and no send time (issue #10085).
- Not chosen: the SMS form's "Keep as a draft / Send now / Send at this date and time" choice on the create form.
- The architect's bound, not his words: only `add_email_campaign.php`'s own Create changes. The API route (`includes/api/resources/campaigns.php`) and the flows' e-mail box (`includes/workspace/flow_mail_campaign.php`) keep the status they pass to `pg_email_campaign_insert()`.

## Are the integration packages of the census wanted, and when? (owner, 2026-11-17, typed to the architect, agent channel message 7562)
- "entegrasyonlar ile ilgili olumlu düşünüyorum ancak 2026.11.0 yayınlanmadan önce bir test demosu açamayabilirim, bağdaştırıcı hazırlıklarımızı yaparız ancak 2026.11.0 yayınlanması sonrasına bırakırız. bugün engel olmayanları 4/10 öncelikle onaylarız."
- So the packages of `plans/integrations-census-proposal.md` are wanted and their plans carry `priority: 4`. What needs a demo site, a test account or his repositories and developer accounts is prepared now and done after 2026.11.0 is released.
- The architect's reading, not his words: which item goes now and which waits (`plans/integrations-census-order.md`), and that the poll "which packages, in which order" is no longer asked.
- Still his, each when its step is near: the look of the hub's drawing, the subscription form in Application Access, each setting that moves, the wording of the public figure, Google's verification with the broker.

## How does the Page Design Studio's Options panel come out in a 240 px column? (owner, 2026-11-17, agent channel poll 117, message 7593)
- He chose "Evet, böyle çıksın" for the R&D agent's drawing (discussion "Görsel Tasarımcı geliştirme", message 7583): on a 1024 px screen the right column is 240 px, and the panel keeps the parts and the order of the approved drawings; only what does not fit moves, as the drawing's ten notes say.
- The one difference from the measure sheets, his by this answer: a button group that does not fit beside its label gets the label above it and stays on one line (note 4).
- Not chosen: "Hayır, böyle çıkmasın".
- The drawing joins `plans/designer-panels/` by the R&D agent's PR; a step that draws the panel accounts for it as for the other drawings (`plans/designer-panels-rules.md`).
