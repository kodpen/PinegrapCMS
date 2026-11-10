---
name: user-docs-coverage-modules
description: pinegrap.com documentation pages for visitors, SEO, design, languages, commerce, ERP, Workspace, API and security: what each page covers and the memory folders and clusters it is written from
---

## Which pages cover visitors, design and languages?
A line gives the page's subjects, then the memory to write it from. The mark of each page is in `user-docs-pages.md`; the other 15 pages are in `user-docs-coverage-content.md`.
- `/visitors`: visitor reports, visit details, how long visitor records are kept, Google Search Console. `visitors/`.
- `/seo`: SEO score and checks, `sitemap.xml`, `robots.txt`, IndexNow, structured data, social sharing tags. `visitors/`, `ui/`, `platform/`.
- `/design`: classic page styles, menus, regions, themes, design files, Import My Site, Find And Replace, Mass Edit, Migration. `designer/`.
- `/visual-page-editor`: the editor's home, starting a design, templates, import, the editor, pages and tabs, building blocks, shared components, system widgets, data binding, theme, e-mail pages, publishing, who can edit, working together, the assistant. `designer/`: `designer-blocks-*`, `designer-canvas-*`, `designer-code-*`, `system-widget-*`, `widget-lists-*`, `comments-widget-*`, `design-template-*`.
- `/multilingual`: site languages, translation engines, the Translations screen, glossary, the language switcher, visitors and search engines in other languages. `core/`: `content-translation-*`.

## Which pages cover commerce and the ERP?
- `/commerce`: orders, Add Local Order, order reports, commissions, products, variant sets, Catalog Manager, product attributes, gift cards. `commerce/`: `order-completion-*`, `order-returns-*`, `payment-driver-*`, `product-price-*`, `product-review-*`, `gift-card-*`, `store-credit-*`; totals from `commerce/order-totals-overview.md`.
- `/commerce-cont`: offers, key codes, shipping zones and methods, arrival dates, containers, tax zones, countries, states, currencies, referral sources. `commerce/`: `carrier-label-*`, `shipment-label-*`, `stock-locations-*`.
- `/erp`: switching the ERP on, accounts, invoices, repeating invoices, returns, quotes, purchase orders, incoming e-invoices, account sync, the aging, profit and VAT reports. `erp/`: `erp-account-*`, `erp-invoice-*`, `erp-order-*`, `erp-edoc-*`, `erp-parasut-*` (`erp/erp-parasut-overview.md`).
- `/erp-cont`: stock and cost, stock counts, expenses, delivery notes, export, the accountant pack, cash and bank, cheques, bank statements, the audit trail, ERP Settings. `erp/`.

## Which pages cover the Workspace, the API and security?
- `/workspace`: channels, messages, blocks, approvals, mentions, slash commands, tasks, the planning board, the work calendar, the decision timeline, guests, Workspace Settings. `workspace/`: `workspace-channel-*`, `workspace-command-*`, `workspace-editor-*`.
- `/workspace-cont`: discussions, channel templates, My Notes, scheduled actions, AI assistants, MCP servers, notifications, the Workspace API. `workspace/`: `workspace-notes-*`, `workspace-voice-*`, `workspace-api-*`. Flows (`workspace/workspace-flows-overview.md`) and workflow tables (`workspace/workflow-tables.md`) have no section yet.
- `/api`: the external API, Application Access, authentication, resources, webhooks, marketplaces, team devices, connected apps and MCP. `api/`: `api-store-*`; marketplaces in `commerce/`.
- `/security`: firewall, bot filtering, IP lists, country restriction, Firewall Log, security headers, sign-in protection, Two-Step Verification, Device Limit, Sign in with Google, Microsoft and Apple, Developer Lock. `waf/`; sign-in and the audit trail in `core/` (`audit-trail-*`).

## Where does a Workspace subject go?
- Channel features (the welcome, status updates, check-ins) on `/workspace`; scheduled actions, assistants and MCP on `/workspace-cont`.
