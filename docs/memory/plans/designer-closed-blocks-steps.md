---
name: designer-closed-blocks-steps
description: Steps CB2 to CB10 of designer-closed-blocks, written from the owner's answer of 2026-11-15 (the proposed order): one block family per step, what each opens, what runs side by side
---

## What did the owner answer?
- Owner (2026-11-15, channel message 4146, his vote): "Önerilen sıra (Önerilen)": the order of `designer/canvas-closed-blocks.md`, most met first. Custom HTML, SVG and Custom PHP stay closed. Record: `workflow/decisions-made-product-54.md`.

## Which steps?
- CB2 (M) Comments widget slots: pagination as a tree, one family with Search results' `pagination` (order 7 goes with it); CAPTCHA, star rating input and publish options settings only, with a true sample; the watcher list keeps its children. The star rating input is redrawn as five stars (owner, channel message 4700, `workflow/decisions-made-product-55.md`). Done (#9716): `designer/comments-widget-open-slots.md`.
- CB2b (S) The Page links starter on the canvas (`_sdPagingListStarter()`): after "Convert to design elements" Previous and Next stand outside the widget's frame at 1440 and are not seen at 390, in Comments and Search results alike. The canvas drawing only; the site's output stays. Done (#9765).
- CB3 (S) Comment e-mail pages: a page that keeps its System region gets a note on the canvas saying what it prints. The design templates keep the region (owner 2026-11-01, `workflow/decisions-made-product-16.md` (a)): it prints the comment when no Comments card on the page draws it. The fixed markup stays for a page with no card: nothing changes on a running site. Done (#9685).
- CB4 (M) Custom form slots: CAPTCHA settings only; on a form with steps the step break, the step progress and the step buttons drawn true, the buttons a tree. Done (#9806): `designer/custom-form-open-slots.md`.
- CB5 (S) Messages node: settings only (alert look, dismiss button) and a sample error on the canvas. Done (#9723).
- CB6a (M) Shop, catalog detail: variant attribute, breadcrumb, product carousel, price block; payment methods settings only. Done (#9849, #9871).
- CB6b (M) Shop, catalog: category tree, filter offcanvas and its buttons. Done (#9890).
- CB6c (M) Shop, cart: offers, quick add, gifts. Done (#9901).
- CB6d (M) Shop, checkout steps: the order preview parts as trees; installments settings only. The custom shipping and billing forms stay as their form draws them.
- CB6e (M) Shop, after the order: order view's return actions; My Account's order history, subscriptions, bookings, store credit.
- CB7 (M) Login and sign-up: password rules and the sign-in and sign-up links as trees; social sign-in buttons settings only.
- CB8 (M) Booking widget: the twelve `booking_*` steps and parts; two PRs when over the size (CLAUDE.md, Working mode 5).
- CB9 (M) Calendar: picker, month grid as a tree, reserve form.
- CB10 (M) Classic sites: the converter button for a classic page type's System region and the legacy regions (`designer/canvas-closed-blocks-regions.md`); page types with no widget are listed for the architect, not built.
- Close-out (S): `designer/canvas-closed-blocks*.md` rewritten as what is open now; both plan files deleted.

## What does every step keep?
- The rules of `designer-closed-blocks.md`: a tree on bindings compared with the classic output, a published page keeps its output until its user converts it, output that depends on the request stays a placeholder in the saved body, no schema change.
- Each step reads its family in `designer/canvas-closed-blocks-*.md` first and writes one memory file of what it opened.
- A slot the step cannot open by those rules stays closed and is named in the PR; a product question goes to the architect.

## In which order, and how many at once?
- In the order above. Two steps may be open at once, one per coordinator, on widgets that share no server file; both edit `style_designer.src.js`, so each keeps its lines there together.
- CB2 to CB5 are done; the shop steps go one after another beside CB7 to CB10.
- Claim as `[plan:designer-closed-blocks] <step> — <topic>`.
