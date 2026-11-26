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
- TR1 Done #11829: the crossings census, `settings-crossings-census.md` and its six files.
- TR2 Done (2026-11-26, agent channel poll 232): the rule, `settings-crossings-rule.md`. TR3 is cut in four, one at a time: they share `connections.php`.
- TR3a Done #11862 (`ui/connections-return-to-card.md`). The return: the helper every lead uses, the key check as a pure function, "Back to {card}", the return after Save and Connect and through the sign-in, the two old parameters folded in. Every lead to the service's page in the same tab: E-mail sending's and Backups' leads that land on the list, MCP servers' new tab.
- TR3b Done #11880 (`ui/connections-lead-keeps-pane.md`): nothing is lost: the pane's unsaved fields kept and put back, the assistant's drawer opened again (paths 7 and 8 of `settings-crossings-census-worst.md`).
- TR3c Done #11894 (`ui/connections-page-finishes.md`): the page finishes it: the application's fields on the page of a Google or Microsoft account service, the drives' two answers made one, Sign in with Microsoft given its anchor and button (paths 1 to 6).
- TR3d Done #11911 (`ui/connections-list-opens-card.md`): no empty page: the list opens the card for the status-only services (path 9); the census files corrected where a path changed.
- TR4 Done by the architect (2026-11-26): Ecommerce's plan is `settings-ecommerce-tidy.md`, steps EC1 to EC6, free beside TR3 (another file).
- TR5 Done by the architect (2026-11-26): Accounting's plan is `settings-accounting-tidy.md` (SA1 to SA4), Updates and maintenance's `settings-maintenance-tidy.md` (SM1 to SM4; SM3 after TR3a). The two S3 sets stay apart.
- TR6 (S) Close-out: the rule into `ui/`, the survey files corrected or deleted, this file deleted.

## What about the other panes?
- SEO, Languages and Translation, Fediverse, Integrations, Channels and Team have their folded-row lines in `settings-scan-findings.md`; they follow TR5 unless TR1 shows one of them among the worst paths.

Related: docs/memory/plans/settings-crossings-rule.md, docs/memory/plans/settings-ecommerce-tidy.md, docs/memory/plans/settings-tidy-census.md, docs/memory/plans/settings-tidy-census-order.md, docs/memory/plans/settings-scan-findings.md, docs/memory/ui/connections-one-place.md
