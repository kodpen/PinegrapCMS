---
name: decisions-made-product-80
description: Decisions list, product part 80 (2026-11-20 on): Design Canvas layer groups; the phone's Workspace writing box; a File Upload field in product forms and the sweep of unordered carts' files
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-79.md`.

## Does the Design Canvas group layers, and may a layer follow another layer? (owner, 2026-11-20, in the architect's window, channel message 9832)
- "tasarım tuvalinde gruplama gelecekti o özellik atlanmış."
- "yerleşmiş bir vektör, nesne veya resim takip edilir olsun. yani bir vektör diğerini takip edebilsin (bir nesne yol olarak kaydırıldığında. videodakinden farklı olarak gruplandırılmış mantığı manuel olabilir nesne takibinde)".
- Of Follow on a video: "takip yapılamıyor diye defalarca uyarı aldım küçük bir gemiyi takip edemedi."
- The architect's reading, not his words: Group and Ungroup, "Attach to" a layer (no pixel search), the tracker reading a small thing larger; `plans/design-canvas-groups.md`.

## How is the Workspace writing box laid out on a phone? (owner, 2026-11-20, in the architect's window, channel message 9832)
- With a picture of the narrow field: "workspace yazma alanı mobilde daralıyor ve gerçekten zorlayıcı oluyor. bunun yerine yazı alanı üstte alt taraf kanal araçları olabilir. (masaüstünde zaten o şekilde.)"
- It replaces the phone's one row with the tools switch (`workspace/workspace-channel-composer.md`): the field takes the full width, the tools row with Send sits under it. His words of 2026-10-31 stay: the box rides on the keyboard and the messages keep the screen.

## Does a product form take a shopper's file? (owner, 2026-11-20, agent channel poll 160, channel message 9994)
- Asked of a personalised product (photo mug, printed shirt, invitation, logo print), where the file is asked for by e-mail after the order today.
- His answer: "Evet, plana girsin". Not chosen: "Evet, önce yalnız resim", "Hayır, gerek yok".
- The field's settings choose the allowed types; cart, order, order e-mail and API show the file. `plans/product-form-uploads.md`.

## When are an unordered cart's uploaded files deleted? (owner, 2026-11-20, poll 162, channel message 10010)
- His answer: "30 gün sonra": the general job deletes the uploaded files of a cart that never became an order 30 days after the cart's last touch; an order's files are never swept.
- Not chosen: "7 gün sonra", "Silinmesin".

Related: docs/memory/workflow/decisions-made.md, docs/memory/ui/image-editor-overview.md, docs/memory/workspace/workspace-channel-composer.md, docs/memory/commerce/cart-product-form-file-field.md
