---
name: decisions-made-product-65
description: Decisions list, product part 65 (2026-11-16 on): the place map default tiles (poll 99), his look at the Content review screen (poll 100), the amount a minimum order compares
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The second study's features and picture text translation: `decisions-made-product-64.md`.

## Which server draws the place map's tiles? (owner, 2026-11-16, agent channel poll 99, message 6821)
- Owner: "OpenStreetMap ile başlasın, site isterse kendi sağlayıcısını yazar". The tile address and its credit line are a setting: OpenStreetMap's by default, a provider's address and key as the site's option; the credit is always printed.
- Not chosen: "Sağlayıcı anahtarı girilene kadar harita çizilmesin" (no map until a key is entered).
- What it opened: MP3 and MP4 no longer wait for him. Built in #10581: `designer/designer-blocks-map.md`.

## Does the Content review screen come out as shown? (owner, 2026-11-16, agent channel poll 100, message 6855)
- Owner: "Evet, böyle çıksın", on the sheet of agent channel message 6854: the list of pages and collection records with who reads each again and by when, the row menu's four actions and the selection bar, the three windows (the person, the date, the clearing), the dark theme and the phone.
- The screen stands as CH2 built it (PR #9967); a later change he would see on it comes to him again. Feature: `platform/content-review.md`.

## Which amount does a contact group's minimum order compare? (architect, 2026-11-16, agent channel message 6895)
- The cart's goods subtotal, before discounts, tax and shipping. Why: the offers' order ladder measures an order by its subtotal too, and the customer can check the missing amount against the cart's "Subtotal" line.
- Given with the look at WS4 (PR #9987): the "Minimum Order" card on the contact group's edit screen, the cart's warning and the refusal at submit use the boxes that exist. Plan: `plans/wholesale.md`, feature J9.
