---
name: general-appearance-settings-tidy-screens
description: Plan general-appearance-settings-tidy, its first screen: the General pane as folded rows, Organization in three groups, one Site address row, the words and what must be true
---

## By which rules?
- `ui/screen-composition-rules.md`; parts copied from the Design examples screen, its section in brackets (`workflow/screen-described-in-plan.md`). "Value rows" comes with DX2b (`plans/design-examples-patterns-mail.md`) before GA4; the rest is there today. No part is new: nothing to ask the architect for a drawing.

## How is the pane laid out?
- Head: "General" and one purpose line [Type and text]: who runs the site, where it is, its licence and its clock.
- Folded rows [Settings pane], one open, each line saying its state ("Kodpen, İstanbul", "example.com", "Licence set", "Europe/Istanbul, 14/2/2026"), in this order: "Organization", "Site address", "Software", "Date and time".
- Organization: three groups under small labels [Type and text]: "Name and address" (name, two address lines, city, state, zip code, country), "Support E-mail Address" with its line ("System mail is sent from this address; DKIM signs with its domain when none is set"), "Seller" (the logo, tax number, tax office in Türkiye).
- Site address: Hostname; under it a meta line "This server's IP: 1.2.3.4"; from GA4 one inner card [Value rows]: "Other host names" and "HTTPS", today's state and Change; then "Outgoing proxy" with its line ("Requests this site sends to payment gateways, updates and some connected services go through it. Proxies in front of the site are set under Trusted proxies", the last two words a link).
- Software: Language, Subscription key with its licence line, "Verbose database errors" [Fields, switch] and its warning line.
- Date and time: the current site time as a meta line, Timezone, Date format and Time format [Segmented control and status]; under Date format the line "A page served in another language uses that language's date format (Languages)".
- Under 576 px: rows full width, the segmented controls wrap under their label. Foot: Save the fill, one only.

## Which words?
- "Site address", "Outgoing proxy", "Name and address", "Seller", "Verbose database errors", "Date and time"; sentence case; no name starts with "Enable"; "Support E-mail Address" kept as it is (decided).

## What must be true?
- Every field posts under today's name; `general.save.php` stores what it stored.
- `#settings/general/pgset-organization`, `…/pgset-server`, `…/pgset-software`, `…/pgset-datetime` open their rows; `proxy_address` and `hostname` open "Site address".
- The settings search finds "proxy", "hostname", "VKN" and "timezone".
- Nothing in "Site address" writes the SEO or Firewall settings.

Related: docs/memory/plans/general-appearance-settings-tidy.md, docs/memory/plans/general-appearance-settings-tidy-steps.md, docs/memory/plans/general-appearance-settings-tidy-screens-appearance.md
