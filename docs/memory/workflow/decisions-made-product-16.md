---
name: decisions-made-product-16
description: Decisions list, product part 16 (2026-11-01 on): design templates finished (comments, events, testimonials); Turkish address fields (İl and İlçe)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## Design templates finished: comment threads, events, testimonials (2026-11-01)
- Owner, 2026-11-01, so the templates ship with no temporary stand-ins. (a) Every template moves its comment threads, public and private (conversation, desk or submission, Café reservation and quote, Clinic appointment, the forum's three private pages), from the fixed comments block to the Comments widget; a comment e-mail page gets the widget's card and keeps its System region. (b) The bookshop's events become form records the business adds in the panel, as the Café's. (c) A template's hard-coded testimonials stay as sample text, with a Google Reviews widget beside them that shows once configured. (d) The forum template gets the standard blog pages. (e) The hello template's product page gets reviews.
- Installed sites keep their pages: rows are never reused, no migration. Shop templates: `designer/design-template-shop-comments.md`; hello: `designer/design-template-hello.md`.

## Turkish address fields: İl and İlçe (2026-11-01)
- Owner, 2026-11-01: a Turkish address is asked as İl (the state box) and İlçe (the city box) in every form: site, designer widgets and their starters, classic pages and layouts, panel. The separate district box (#3569, #3641) is not drawn for it; it stays for an address abroad whose carrier asks. A Turkish recipient's `ship_tos.district` is written from the city box; readers take `ship_tos.district` when filled, else the city (the e-waybill too). City and district stay free text where no list exists.
- Reason: in Turkey the city is the province, so "Şehir" beside "Eyalet / İl" was typed twice, while the account card, e-invoice, e-waybill, imports, carriers and the Turkish default layouts read the city box as the district. The separate box is unreleased (2026.11.0). Plan: `plans/address-fields-by-country.md`.
