---
name: decisions-made-product-43
description: Decisions list, product part 43 (2026-11-11 on): classic styles sharing a CSS/JS set move as one "Legacy" design, its badge "Legacy" / "Eski", what follows the move (#8671)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.
- The owner's request and his two answers below are on #8671 (the `Karar:` comment).

## How do classic styles that share CSS and JS move to the visual designer? (owner, 2026-11-11, #8671)
- As one group: the styles that use the same CSS and JS files become one design of a kind of its own, shown as "Legacy" in the Visual Designs list.
- Owner (2026-11-11): "legacy sayfaları yeni görsel tasarımcıda açmayı sağlayacak plan ne durumda kontrol et, bu planı hızlandıralım. aynı css ve js leri paylaşan sayfalar bir grup halinde (gorsel tasarımlar listesinde legacy diye belirtilir.) özel tasarım olarak eklenebilir. olursa planda bir sıçrama yapacaktır ve sonrası içinde plan yapalım bugün bir tanesini düzenleyince css ve js uyumsuz olarak açıyor".
- Reason: a style moved alone opened with CSS and JS that did not fit, and a site's classic styles mostly share one set (49 styles, 7 sets on the starting site).
- What stays (#6006, #6251; `decisions-made-product-21.md`, `decisions-made-product-23.md`): nothing classic is removed; the classic `style` rows and their code stay; a move starts only from a designer, after the dry run.
- Not a duplicate to clean up: several frame components (`classic_style:<style_id>`) in one Legacy design, and a classic style still standing beside the design its pages moved to.
- Built by steps LG1-LG7: `designer/legacy-overview.md`.

## What does the badge of a Legacy design read? (owner, 2026-11-11, #8671)
- "Legacy", Turkish "Eski". Owner (2026-11-11): "Legacy / Eski (Önerilen)". Not chosen: "Classic / Klasik" and an untranslated "Legacy".
- Architect (2026-11-11, #8671): only this kind of design is named Legacy; the panel's other texts keep "classic" where they say it today.

## What follows the group move? (owner, 2026-11-11, #8671)
- Owner (2026-11-11): "İçerik sayfada düzenlensin (Önerilen),Klasiğe geri al düğmesi,Bootstrap 5.3.8'e geçiş yardımcısı".
- Page content edited in the page: the one frame split into a top and a bottom shared component, the page's own content in the page's tree.
- A "back to classic" button for a moved group or a single page; safe, as the classic style stays in place.
- A helper that turns a Legacy design towards Bootstrap 5.3.8 and a theme: a plan of its own.
- Not chosen: joining the designs converted one by one earlier to their set's Legacy design. Do not propose that merge as a cleanup.
