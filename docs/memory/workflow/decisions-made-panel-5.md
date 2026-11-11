---
name: decisions-made-panel-5
description: Decisions list, panel part 5 (2026-11-11 on): the dashboard's notes card as a My Notes shortcut, last online times, the world map without city names (#8672); the report screens' renewal (#8673)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.
- The owner's words below are on #8672 and #8673 (each issue's `Karar:` comment).

## Why does the dashboard's notes card keep no note of its own? (owner, 2026-11-11, #8672)
- It is a shortcut to My Notes: a short "Add note" box that grows when clicked and saves a new note of My Notes; under it the latest notes by title and date, a row opens the note on the My Notes screen.
- Owner (2026-11-11): "bu widget notlarıma bir kısayol olabilir miş. en üstünde eklenen not ta yine notlarımdan erişilmesi lazımmış / not ekle daha kısa olsun input gibi tıklayınca genişlesin ve yazacak kadar alan olsun yeni not olarak eklensin. / altında diğer notlar sadece başlık ve tarihleri ile dursun tıklanınca notlarımı sayfasında açsın. bu şekilde güncellensin buraya özel bir not uygun olmamış".
- Not a lost feature: the text the card kept before is moved once into My Notes as a note.

## What does the Online Engagement card show when nobody is on the site? (owner, 2026-11-11, #8672)
- When visitors were last online, not only the empty line. Owner (2026-11-11): "bu widget ta en azından en son çevrim içi  olma zamanlarını gösterebilirdi."

## What does the Visitor map card draw without city names? (owner, 2026-11-11, #8672)
- The world map with the visitors' countries; the "Countries" bars stay. Owner (2026-11-11): "bu widget ta eğer şehir isimlerini göremiyorsa dünya haritası üzerinde görünmeli".
- The three cards are built by `plans/dashboard-owner-look.md`.

## Which report screens are renewed, and how do they look? (owner, 2026-11-11, #8673)
- Owner (2026-11-11), on the Sales map card: "bu widget tümünü gör diyince https://dev.pinegrap.com/pinegrap/view_order_report.php sayfasını açıyor ancak burası uygun şekilde rapor sunmuyor. bu rapor sayfalarını da moderize edelim".
- All four screens: order, visitor, shipping, ERP VAT. Owner (2026-11-11): "Sipariş raporu (Önerilen),Ziyaretçi raporu (Önerilen),Kargo raporu,ERP KDV raporu".
- The look is the dashboard's: figures, a chart, the table; the report's settings (summarize by, filters, save) in a drawer from the right. Owner (2026-11-11): "Pano gibi: rakam + grafik + tablo (Önerilen)". A new look without a chart was not chosen.
- Kept in every case: saved reports, filters and summarize-by levels; nothing is removed.

## Are there ready reports on a report screen? (owner, 2026-11-11, #8673)
- Yes: a strip of ready reports on top of the screen, and each dashboard card's "See all" opens its own ready report (the Sales map: sales by region, last 30 days). Owner (2026-11-11): "Evet, hazır raporlar şeridi (Önerilen)".
- Building and saving an own report stays as it is. Built by `plans/report-screens.md`.
