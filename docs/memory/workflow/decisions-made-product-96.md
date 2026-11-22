---
name: decisions-made-product-96
description: Decisions list, product part 96 (2026-11-21 on): the KVKK notice gets a switch of its own; the unused Chat Retention Period field leaves Chat for an opt-in "Site chats" rule in the Privacy centre
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-95.md`.

## Does the KVKK notice depend on the distance sales switch? (owner, 2026-11-21, poll 214)
- "Evet, kendi anahtarı olsun": the KVKK notice page and the consent sentence get a switch of their own.
- Binds: the switch is a `config_text` row (no schema change); the update sets it to the state the distance sales switch has at that moment, so no running site changes; after that the two are turned on and off apart, and a site that sells nothing can show the notice.
- Why: the notice is about personal data, not about selling; until now it worked only while the distance sales switch was on (`commerce/checkout-kvkk-notice.md`).
- Not chosen: the notice staying tied to the distance sales switch.

## Where is the retention of site chats set, and is it applied at the update? (owner, 2026-11-21, poll 215)
- "Evet, kural Gizlilik merkezine girsin": the "Chat Retention Period" field leaves Communication › Chat; a "Site chats" rule enters the Privacy centre's retention rules.
- Binds: the rule is off by default; turned on, it first shows how many chats it would delete (a dry run) and deletes only after a yes; 60 days is the suggested period. The update deletes no chat on any site, whatever the saved value of the old field.
- Why: the field (default 60 days) was saved but never applied (`CHAT_RETENTION_DAYS` is read nowhere), so chats stay until deleted by hand; applying it now would delete every chat older than 60 days at the update.
- Not chosen: keeping the field in Chat and applying it; removing the field with no rule at all.
