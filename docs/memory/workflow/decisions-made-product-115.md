---
name: decisions-made-product-115
description: Decisions made, product part 115 (2026-11-26 on): the spreadsheet and document editors get all eight abilities offered; simple formulas change the no-formula-engine scope of 2026-11-19; "Preview on a phone" shows the live page's QR code in a design with no draft
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-114.md`.

## Which abilities do the spreadsheet and document editors get? (owner, 2026-11-26, agent channel poll 235, message 14401)
- Owner (2026-11-26, in the architect's window): "yeni tablo ve word belgesi ekleme ekranları çok kararsız. word ekranı bir word yazma alanı gibi değil input gibi. […] her ikisi için yeteneklerini genişletelim, stabiliteyi artıralım."
- What was found: a workbook takes one kind of change a save, so a format button does nothing while a typed cell waits and no cell is typed while a format, row, column or sheet change waits; a document's paragraph opens in a box. Both are repaired whatever the answer (`plans/office-sheet-steady.md`, `plans/office-document-page.md`).
- Asked with eight choices, several answers allowed. Workbook: more formats (underline, strikethrough, font size, borders, wrap text); layout (column width, row height, a frozen top row); copy and paste (a range copied, a paste from Excel, fill down, clear); simple formulas (SUM, AVERAGE, MIN, MAX, COUNT and the four operations). Document: format (alignment, text colour, highlight, font size); links; tables; pictures from Files.
- Owner: all eight.
- So the workbook's first three are `plans/office-sheet-abilities.md`, the formulas `plans/office-sheet-formulas.md`, the document's four `plans/office-document-abilities.md`; each starts when the plan that makes its editor steady has landed.

## Is a formula engine built after all? (owner, 2026-11-26, the same poll)
- The scope of 2026-11-19 built no formula engine (`decisions-made-product-79.md`, `platform/office-files-scope.md`). The choice "Tablo: basit formüller" said it changes that decision; the owner chose it.
- So: simple formulas are built with the product's own code: numbers, references and ranges of one sheet, the four operations, SUM, AVERAGE, MIN, MAX, COUNT. Every other formula stays as it is: locked, its stored result taken out when a cell it may depend on changes.
- Charts, pivot tables, tracked changes, comments and page layout stay never built; no library is added.

Related: docs/memory/plans/office-sheet-abilities.md, docs/memory/plans/office-sheet-formulas.md, docs/memory/plans/office-document-abilities.md

## What does "Preview on a phone" show in a design with no draft? (owner, 2026-11-26, agent channel poll 243, message 14627)
- Owner (2026-11-26, in the architect's window, with a picture of the window saying "Bağlantı oluşturulamadı"): "telefonda görüntüle qr göstermiyor tekrar dene işe yaramıyor."
- What was found: the phone link is made over draft pages only (`decisions-made-product-106.md`); a design with no draft is answered "This design has no draft pages." and the window hid that reason (bug issue #11966).
- Asked what the row does in a published design with no draft, among the live page's QR code, the row left out and the reason alone, he chose "Yayındaki sayfanın QR'ı çıksın".
- Architect's reading, not his words: the code holds the live address, so no share link is made, and the window says it opens the live page. Issue #11968; a design with a draft behaves as before.
