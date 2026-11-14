---
name: addon-hook-system-site
description: Add-on hook system, the owner's additions of 2026-11-14: the start in two parts, UI slots on core screens, a site's own add-on that updates never touch, an add-on prepared from the panel
---

## What did the owner add? (2026-11-14, in the architect's window)
- His case: on customers' copies he adds buttons to the order screen and the orders list and parts to the printed order by editing core files. An update then means carrying each change by hand; the last time one was missed. His way around it, a page built in dynamic regions with the order screen redirected to it, is still a core edit and misses what the core screen gains later.
- He asked whether preparing an add-on from inside the software, as Dolibarr does, would help. Told what solves his case, he answered: "Evet, ekle". Record: `workflow/decisions-made-product-52.md`.

## What is added to the plan?
- UI slots. Named places on core screens where an add-on adds without editing the screen: first the order screen (actions, an extra card), the orders list (a row action, a toolbar button), the printed order (above and below the items, the foot). The add-on names the slot, the label and what runs; the core draws it with look A's components, so it survives a redesign. The slot list is fixed and documented like the hook list; a screen gets slots when a real case asks.
- The site's own add-on. A customer's custom code lives in `includes/addons/<slug>/`, never in core files; an update does not touch the folder and the integrity check does not count it. The same folder is carried to another copy.
- Prepared from the panel. "New add-on" makes the folder and the manifest, slots and hooks are picked from the list, the code is written in the panel's code editor; enable, disable, export. No generator of objects, tables or list pages: forms, dynamic regions and Workspace tables are that.

## What is still to settle in the design note?
- Who may write an add-on's code in the panel: no wider than who may write a PHP region today.
- Carrying an add-on between sites against "no zip upload without its signature check" (#3344): a key of the agency's own, or the folder put on the server by hand.
- Replacing a whole screen is not offered: a slot adds, the core screen stays.

## In which part does each go?
- Before 2026.11.0 ships: the design note (hook list, slot list, manifest; the owner's look), the registry, the hooks and the first slots, the site's own add-on as a folder on disk, core improvements and dead-code clean-up.
- After it ships: preparing from the panel, zip upload with its signature check, the shipped drivers moved into add-on folders, the contract opened to others.