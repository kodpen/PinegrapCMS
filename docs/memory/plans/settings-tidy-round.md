---
name: settings-tidy-round
description: Plan (owner 2026-11-26): the settings tidy round: first the crossings between Site Settings and Connections, then the categories the 2026-11-21 survey left; steps TR1 to TR6
priority: 6
---

## What is asked?
- Owner (2026-11-26, agent channel message 14113): "yapılacak iş kalmadıysa ayar toparlama turu yapabiliriz. bazı ayarların bağlantılardan ayarlara ayarlardan bağlantılara kullanıcıyı yöndendirip durması kafamı karıştırdı."
- It continues his request of 2026-11-21 (`settings-tidy-census.md`): contradictions fixed, scattered settings gathered, old screens made modern.

## What is there today?
- Done since the survey: E-mail, Mailboxes, Assistants and agents, Features, Visitors and privacy, Bookings, Communication, Firewall and HTTPS, Accounts and sign-in, General, Appearance (`settings-tidy-census-order.md`).
- Left by it: Ecommerce (127 fields), Accounting's ERP card, Updates and maintenance. They waited for the CX11 steps, which all landed by 2026-11-24 (`ui/connections-one-place.md`).
- Keys moved to Connections, the behaviour stayed on the cards (owner polls 146, 149, 222). A card leads to its service's page and the page leads back; how each end hands over was written service by service, in seventeen steps, and nobody has read them as one path.

## What must it never do?
- Move a key back to a card, or a switch to Connections, without the owner's answer: where each sits is decided (`workflow/decisions-made-product-76.md`, `-77.md`, `-100.md`).
- Lose an anchor, a settings key or a right: `workflow/decisions-made-product-14.md`.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- TR1 (S, no product code) The crossings census, into `plans/settings-crossings-census.md` (more files past 4 KB). For every service page of the registry and every card that leads to Connections: where the lead goes (page, anchor), whether and where the page leads back, where the person stands after Connect, Save and Disconnect, the names at both ends.
  - Then, for each feature: the hops of a first set-up from its card to working, the round trips with nothing to do at one end, and the ten worst paths.
- TR2 (architect) after TR1: one rule for the hand-over, asked of the owner with the worst paths as its examples; this file gains the rule and TR3's cut.
- TR3 (M, cut by the rule) after TR2: the crossings follow the rule.
- TR4 (architect, then steps) Ecommerce: its plan, by the survey's order.
- TR5 (architect, then steps) Accounting's ERP card and Updates and maintenance (cron steps, one S3, K2): their plans.
- TR6 (S) Close-out: the rule into `ui/`, the survey files corrected or deleted, this file deleted.

## What about the other panes?
- SEO, Languages and Translation, Fediverse, Integrations, Channels and Team have their folded-row lines in `settings-scan-findings.md`; they follow TR5 unless TR1 shows one of them among the worst paths.

Related: docs/memory/plans/settings-tidy-census.md, docs/memory/plans/settings-tidy-census-order.md, docs/memory/plans/settings-scan-findings.md, docs/memory/ui/connections-one-place.md
