---
name: user-docs-2026-11-pages
description: Plan user-docs-2026-11: which 2026.11.0 feature goes on which existing pinegrap.com page, with the memory to write it from
---

## How is a row read?
- Page, then its new subjects from the 2026.11.0 `[NEW]` lines, then the memory to start from. The step of each page is in `user-docs-2026-11.md`; the module pages (designer, commerce, ERP, Workspace, API, security) are in `user-docs-2026-11-modules.md`, the new pages in `user-docs-2026-11-new-pages.md`.

## Which subjects go on the panel and settings pages?
- `/introduction`: new dashboard (cards, edit mode, Google Analytics card), redrawn main menu and Workspace list, the bell as one inbox and the notification drawer, one picker for files, pictures and pages, one date-and-time picker, Export to Excel, Content review. `ui/dashboard-v2-overview.md`, `ui/main-menu-overview.md`, `ui/picker-overview.md`, `ui/list-table-overview.md`.
- `/site-settings`: regrouped Site Settings (search, headings, Accounting, System tools), Speculation Rules switch, S3-compatible storage, bounce handling and mail transports (link to `/communication`). `ui/settings-modal-overview.md`.
- `/system`: operations alerts and their Slack, Teams and Telegram targets, backup before update and the roll-back in Repair Software, maintenance mode and the health address, page cache, signed updates and the license check, Docker setup, importers for WordPress, WooCommerce and Shopify.
- `/installation`: Docker, live template previews in the installer.

## Which subjects go on the content pages?
- `/files`, `/folders`: Word and Excel previews and edits, Google Drive, OneDrive and Dropbox on Upload File, Pinegrap's own image editor with animated layers, layer groups, Follow in Animate. `ui/image-editor-overview.md`.
- `/pages`: Write with AI, Content review, the classic custom form converter.
- `/pages-cont`: short link redirects, `*` names, CSV import and export, the 404 log.
- `/calendars`: appointment booking (services, staff hours, slots, reminders), course progress and certificates.
- `/forms`: double opt-in (link to `/communication`), conditional fields, multi-step forms, payment step.
- `/contacts`: contact groups filled by rules. `core/audiences-overview.md`.
- `/users`: user writes in the API, passkeys on the account page (link to `/security`).
