---
name: decisions-made-product-64
description: Decisions list, product part 64 (2026-11-16 on): the second study's eight features and his answers, picture text translation's first version, the architect's rules for DG1, MP1 and VP5
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. His looks of polls 89 and 90: `decisions-made-product-63.md`.

## Which features of the second study are planned, and what did he answer on them? (owner, 2026-11-16, agent channel polls 82 and 85 to 88)
- Poll 82 (message 6434), eight of nine: the post digest, the personal meeting link, the place map, customer value reports, the referral programme, wholesale, event tickets, courses. Left out: the first-steps list. Start file: `plans/more-features.md`.
- Poll 85 (message 6514), a member's private plans as busy times of the meeting link: no. "diğer ekip üyesinin kişisel planları ekibin planlarından önce gelemez"; who wants a plan counted turns it into a team plan everybody sees (`plans/meeting-link.md`).
- Poll 86 (message 6515), the referral programme: "Siparişi olan müşteri getirsin; ödül mağaza kredisi" (`plans/referral-programme.md`).
- Poll 87 (message 6516), an administrator's yes for a wholesale buyer's order: "bunu mimara sor ve yapılmışken sağlam temelli yapılsın veya plandan çıksın". The architect took a company's buyers out of the plan (`plans/wholesale.md`).
- Poll 88 (message 6517), a cancelled order's places: "Evet, yerler geri dönsün" (`plans/event-tickets.md`).

## How are the texts in pictures translated in the first version? (owner, 2026-11-16, agent channel poll 98, message 6808)
- His request: "görsellerdeki metinlerde çeviri olayını da 6/10 olarak öne alalım". Asked after the census with four points, he chose "Evet, böyle olsun".
- The pictures: made in the Design Canvas, their text standing as layers, shown by a page opened in another language. Writing inside a photo or a scan is not in the first version.
- A language's picture is drawn by a signed-in person in the panel, in their browser; nothing is drawn with nobody there.
- While a language's picture is not ready, or once the source changed, a visitor sees the source picture. Not chosen: "Evet, ama eskiyince eski çeviri görünsün" (an old translated picture may show a wrong price or campaign).
- The languages' pictures in a small table of their own, columns the architect's: `decisions-made-schema-25.md`. Plan: `plans/picture-text-translation.md`.

## Which records does a post digest's run take? (architect, 2026-11-16, agent channel message 6791)
- Those dated between the earlier run that counts and this one. An earlier run counts when its flow run is done or still open; a failed or cancelled one does not, so its records go with the next run. The first run starts at the version's publish time; "Try" reads and sends nothing.
- A record's date is `submitted_timestamp`; where the list page's own filter holds exactly one "date field <= now" condition, the later of the two. No column on `forms`.

## What does the place map use to find an address, and where is it listed in the notice? (architect, 2026-11-16, agent channel message 6794)
- "Find from the address" and a visitor's typed address use Photon only: a Google result may not be kept past 30 days or shown on another map, a Mapbox one only on Mapbox tiles. Which Photon, and the button: `decisions-made-product-66.md`.
- The map is a `marketing` service of the cookie notice, listed only on a page that carries the map with the Maps switch on; no third class. The security headers do not change. Plan: `plans/place-map.md`.

## Does a back-in-stock notice on the device replace the mail or SMS? (architect, 2026-11-16, agent channel message 6786)
- No, it goes beside it. The device came with the request by a hidden field, unasked, so it cannot be the visitor's one chosen way; the mail or SMS rule is unchanged. The one-message rule of #1864 was about the paid SMS.
