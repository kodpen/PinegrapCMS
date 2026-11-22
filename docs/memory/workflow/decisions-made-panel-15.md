---
name: decisions-made-panel-15
description: Decisions list, panel part 15 (2026-11-22 on): View Order, All Orders and Edit Product redrawn as their sheets show; Edit Product gets a Performance card with figures about the product
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-panel-14.md`.

## Is View Order redrawn? (owner, 2026-11-22, in the architect's window)
- Owner: "sipariş görüntüleme sayfasında da modernleştirme yapalım. fatura vs. ayarları için sipariş ürün ve detayları bayağı aşağıda kalıyor, bu ekran çok eskiden kalma o yüzden modernleştirmekte fayda var. sağ paneli de olabilir hangi amaç uygunsa."
- The architect drew the screen (`commerce/commerce-screens-drawing/order.html`) and showed it to him with what is new named: the head's three state pills, the copy buttons, the count of the customer's orders, "Same as the shipping address".
- Owner, on that: "bunu ve ürün sayfasını ve tüm siparişler ekranıyla birlikte güncelleyelim." So View Order is built as drawn: a head with the states, a card per shipment holding its lines, Payment with the totals, the customer and billing in the side panel. Plan `plans/order-screen-redraw.md`.
- Nothing of the screen is taken away and Save writes what it wrote; a history of the order is not part of it.

## Are All Orders and Edit Product updated with it? (the same words)
- Yes, both: "ürün sayfasını ve tüm siparişler ekranıyla birlikte". Plans `plans/orders-list-redraw.md` and Edit Product's (built: `commerce/product-insights.md`), each with its sheet in `commerce/commerce-screens-drawing/`.
- Shown the two drawings with what is new on each named (All Orders: quick filters with counts, the figures as one strip with the charts behind Summary, the payment and shipping pills and Items on a row, the change against the period before; Edit Product: a head and the Performance card), owner (2026-11-22): "onay". Both are built as drawn.

## Does Edit Product show figures about the product? (owner, 2026-11-22, the same message)
- Owner: "ürün düzenleme sayfasına ürün hakkında bilgiler de yer verelim. mesela ürün kaç tane satılmış kaç kere sepette bırakılmış gibi analitikler."
- So Edit Product gets a read-only part with the product's figures; units sold and the times it was left in a cart are his two examples. The figures of the drawing he accepted are listed in `commerce/product-insights.md`.
- This opens the Product Builder, one of his kept screens (`decisions-made-panel-3.md`), for this part; the rest of the screen stays as he likes it.

Related: docs/memory/workflow/decisions-made.md, docs/memory/workflow/decisions-made-panel-14.md, docs/memory/workflow/decisions-made-panel-3.md, docs/memory/plans/order-screen-redraw.md, docs/memory/plans/orders-list-redraw.md, docs/memory/commerce/product-insights.md
