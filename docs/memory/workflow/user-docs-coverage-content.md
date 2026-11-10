---
name: user-docs-coverage-content
description: pinegrap.com documentation pages for the panel, settings, files, pages and people (/introduction to /chat): what each page covers and the memory folders and clusters it is written from
---

## Which pages cover the panel, its settings, content and people?
A line gives the page's subjects, then the memory to write it from. The mark of each page is in `user-docs-pages.md`; the other 13 pages are in `user-docs-coverage-modules.md`.
- `/introduction`: roles and rights, Dashboard, main menu, top bar, keyboard shortcuts, sorting and Quick edit on lists, Site Log. `ui/`: `dashboard-v2-*`, `main-menu-*`, `list-table-*`; roles in `core/`.
- `/installation`: requirements, the installation wizard, `data/config.php`, web server rules, upgrading, moving a site. `release/`, `platform/`.
- `/system`: Software Updater and its channels, System Status, File Integrity, Write Permissions, cron, Performance Log, caches, Database Engine, Notifications, the panel as an app, private label. `platform/`, `core/`, `release/`.
- `/site-settings`: every settings category, SMTP Settings, Connected Accounts, Mail queue, Repair Software, Backup Manager, Cloudflare Tools. `ui/`: `settings-modal-*`, `settings-card-*`; `platform/` (`object-storage-*`); mail in `core/`.
- `/folders`: File Manager, Recycle Bin, Shared Folders, folder rights and settings. `ui/`, `platform/`.
- `/files`: Upload Files, Edit File, Image Editor, optimizing images. `platform/`; `ui/`: `image-editor-*`.
- `/pages`: the page list, page properties, editing content, the rich-text editor, comments, page types, custom forms, form views. `designer/`: `custom-form-*`, `form-list-*`; `ui/`.
- `/pages-cont`: commerce page types, the views of the page list, Short Links, Auto Dialogs. `commerce/`: `express-order-*`; `platform/`.
- `/calendars`: calendars, events, event locations. `ui/`: `calendar-event-*`. The Bookings screens link here too; their memory is in `commerce/`.
- `/forms`: Submitted Forms. `ui/`.
- `/contacts`: contacts and their views, contact groups, import. `core/`.
- `/users`: users and their views, roles, sessions and connected accounts. `core/`.
- `/campaigns`: campaigns, campaign history, campaign profiles. `ui/`: `email-campaign-*`; `core/`.
- `/ads`: ads. No cluster.
- `/chat`: Live Chat: the chat window, the site's chat bubble, chat settings. `ui/` (`chat-*`), `platform/`.

## Where does a subject that spans pages go?
- The Recycle Bin is described where each deletion is: `/commerce` (products, product groups, Catalog Manager), `/pages`, `/pages-cont` (short links), `/design`, `/folders`, `/visual-page-editor`.
- The advanced search tags of lists are on `/pages`; sorting by several columns and Quick edit on `/introduction`.
