---
name: designer-turkish-terms
description: Turkish terms for designer layer names, palette labels and panel rows; how a shared tr.json key with a misfit Turkish is fixed (in place or a narrower key); tests/designer_turkish_terms_test.php
---

## Why does one wrong value show up in many places?
One English key serves every caller: `_sdT()` in `style_designer.js`, `lang()` in the panel and in the design templates (`includes/design_templates/*.php`). `Pause` was `Duraklatma` (a noun, or "do not pause") on both the carousel row and the scheduled-action button; `Previous` was `Öncesi` in every pager, carousel and lightbox.

## How is a misfit shared key fixed?
- List every use first: `lang('Key')`, `_sdT('Key')`, and arrays later passed to `lang($x)` (for example `$short_labels` in `includes/fn/system_status.php`).
- One Turkish fits every use: change the value in place. Only the value string changes; the key, its line and the file format stay.
- The uses need different Turkish: leave the shared key and give the designer use a narrower English key, appended at the end of `tr.json`. Example: the flex-wrap select uses `Wrap Lines` (`Satır Kaydırma`); the carousel switch keeps `Wrap` (`Döngü`).

## Which terms does the designer use?
- Carousel: `Karusel` (`Karusel İçi`, `Görsel Karuseli`), never `Carousel`.
- Accordion: `Akordeon`. Footer: `Alt Bilgi`. Tile: `Karo`.
- Tab pane: `Panel` (`Sekme Paneli`, `Ödeme Paneli`), never `Pane`.
- Form control: `Girdi` (`Kod Girdisi`, `Adet Girdisi`), never `Input`/`Inputu`.
- Carousel and rotator prev/next buttons: `Önceki Düğmesi` / `Sonraki Düğmesi`.
- `<figure>`: `Görsel Bloğu`; `<figcaption>` and `Caption`: `Açıklama Yazısı` (`Altyazı` means subtitle); `Figure Caption`: `Görsel Açıklaması`.
- Radio input: `Radyo düğmesi` (`Aylık Radyo Düğmesi`, `Radyo listesi`).
- Table header cell of a plan: `<Plan> Başlığı` (`Pro Başlığı`).
- Menu legend block (`Diet Key`): `Beslenme Simgeleri`; "key" there is a legend, not `Anahtar`.
- Parent element: `Üst Öğe` (`Üst Öğeyi Seç`). External file: `Harici`. Stylesheet: `Stil Dosyası`.
- Snapshot stays `Snapshot` and product attribute stays `Attribute`: both are used the same way across the designer.
- News blocks: Story = `Haber` (`Haber Listesi`, `Haber Kartı`), Lead = `Manşet` (`Manşet Haberi`), Headline = `Haber Başlığı` for every part (`Haber Başlığı Bağlantısı` / `Satırı` / `Listesi` / `Öğesi` / `Karuseli`). `Başlık Satırı` alone is a table `Header Row`; a headline part must not read like it.
- Shipping state: `Shipped` = `Sevk edildi`, `Unshipped` = `Sevk edilmemiş` (same select), `Not shipped yet` = `Henüz sevk edilmedi` (dashboard order list). Documents: `Belgeler`, `My Documents` = `Belgelerim`; `Döküman` (with ö) is a misspelling, `Doküman` is a correct word.

## Which shared values were kept on purpose?
- `Done` = `Bitti`: a button that closes a panel and a short state next to `Sürüyor` / `Sırada`; it fits both.
- `Return` = `İade Faturası`: every use, the designer badge included, is an invoice kind.

## What guards this?
`tests/designer_turkish_terms_test.php` pins the corrected shared values and fails when a short designer key with Carousel / Pane / Input / Accordion gets an English half or `Akordiyon` back.

Related: docs/memory/designer/designer-layer-names.md, docs/memory/designer/designer-sdt-translation.md
