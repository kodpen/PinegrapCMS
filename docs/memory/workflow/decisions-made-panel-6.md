---
name: decisions-made-panel-6
description: Decisions list, panel part 6 (2026-11-12 on): the panel's design language from look A (#8842); the names Design Canvas and Page Design Studio, the design menu with Other (#8849)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Does the panel get a design language, and from which look? (owner, 2026-11-12, #8842)
- Yes, written from look A, the quiet list: an icon tile in the module's tone (a person's avatar for a person), a title and at most two lines of text, the time at the right, an unread dot, a button only where the row asks for something.
- Owner (2026-11-12), choosing among three drawn looks: "A olsun, B'deki toplama fikri ve simgelerin biraz daha küçük olma fikri eklensin". Rows of one kind are gathered in one row that opens; the icon tiles are a little smaller than first drawn.
- Why: the bell's drawer still looked outdated to the owner after #8783 ("tasarım hala demode. bu konuda çıkmazdayız bir tasarım sistemi mi geliştirsek?"); every step had read "like the new dashboard" its own way.
- Not chosen: B, one card per notification; C, one-line inbox rows with the actions on hover.
- The architect's reading, not the owner's words: 28 px tiles; the look as drawn (Inter, titles at medium weight, no title in a status colour, at most one filled button in a view).
- Who draws: the architect with the owner; a step copies the reference and does not design. Owner (2026-11-12): "Panel tasarım dilini senle geliştirsek daha iyi olurdu", "opus 5.5 ne görürse onu yapıyor".
- Reference: `plans/panel-design-language/bell-drawer.jpg`; the numbers in `plans/panel-design-language-look.md`; the steps in `plans/panel-design-language-steps.md`.

## What are the image editor and the visual page editor called? (owner, 2026-11-12, #8849)
- The Image Editor ("Görsel Düzenleyici") is Design Canvas ("Tasarım Tuvali") and gets an icon of its own. The Visual Page Editor, in texts the visual designer, is Page Design Studio ("Sayfa Tasarım Stüdyosu"). Designs are Designs ("Tasarımlar"), not Visual Designs.
- Owner (2026-11-12): "Görsel tasarımcının adını "Tasarım Tuvali" olarak güncelleyelim ve kendine özgün bir ikonu olsun. Görsel Sayfa Tasarımcısının adını "Sayfa Tasarım Stüdyosu" olarak güncelleyebiliriz. yine tasarımlar "Tasarım" olarak anılır, ama menüdeki adı ve kavramı netleşmiş olur."
- The architect showed which screen gets which name; owner (2026-11-12): "eşleşme doğru, kaydet ve plana çevir".
- The architect's reading: the English names; only texts change, no address, file or API name. Plan: `plans/design-names-menu-steps.md`.

## Where do the legacy designer's entries and Ads sit in the main menu? (owner, 2026-11-12, #8849)
- Under the Page Design Studio row, behind an Other entry that opens to the present legacy designer menu (Page Styles, Themes and the rest); Ads is there too.
- Owner (2026-11-12): "Sayfa stilleri vs. ana menüde Sayfa tasarım stüdyosu altına taşınır, şu şekilde: diğer seçeneği eklenir genişleyince mevcut legacy tasarımcı menüsü gelir. reklamlar da artık kullanılmayan bir özellik o da diğer altında legacy tasarım altında kalır."
- The architect's reading: Ads is not removed, it stays a working feature; only its place changes.
