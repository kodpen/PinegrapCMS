---
name: bug-hunt-modules-endpoints-2
description: Module list of plans/bug-hunt.md, part 4: root endpoint groups optimize to zone by area word, one line each with its state; single endpoints in endpoints-2b
---

## Which root endpoint groups (optimize to zone) are hunted?
- Done: optimize, order #5115 #5125 #5170 #5178 #5202 #5210 #5215 #5270 #6153, page #5135 #5151, parasut, privacy, preview #6033, product #5257 #5355 #5371 #5393, recurring #5525, referral, region, registration #6041, remove #6041, return, search #6064, seo #6091, setting, shipping #6086, shopping #6113, short, software #6121, style #6180, submitted #6098, subscription, system #6111, theme #4725 #4739 #4764 #4775, translation, user #4489 #4505, verified, visitor, view #6116, waf #4601 #4620, workspace #6120, zone
- Lead hunted: barcode generation checked only product_barcodes, not other products' codes, in the panel generate and bulk assign, the shared generator and the API barcode (edit_products already checked both) #6664
- Settings screen values and the File / Catalog Manager, second round (`includes/settings/prep.php`, `view_folders.php`): clean (config reads and option lists escaped, gateway, e-document, backup and translation secrets masked or drawn empty, chat operators staff only, dashboard card gated by role, backup download manager + token + path inside the backup folder, view modes for role 3, script values whitelisted or JSON-encoded, toasts as text)
- Second round: workspace flows (includes/workspace/flows.php, flow_draft.php and their panel actions and API routes) taken by bughunt-flows
- Workspace calendar (includes/workspace/calendar.php, calendar_sync.php, calendar_feed.php): done #7040 (a plan item ending at midnight drawn on the next day too); feed token, ICS escaping, all-day and clock-change arithmetic and sync read-back clean
