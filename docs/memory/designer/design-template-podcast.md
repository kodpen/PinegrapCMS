---
name: design-template-podcast
description: Podcast Network template (pinegrap-podcast): podcasts and episodes as collections, bound player, sponsorship and pitch requests, supporters' membership folder, ppd- look, tests.
---

## What does it ship?
- Public pages (`home` with `home_episodes`, `home_podcasts`, `home_posts`; `about`, `hosts`, `listen`, `faq`); `podcasts`, `podcast`, `podcast_new`; `episodes`, `episode`, `episode_new`; sponsorship (`sponsor`, `sponsor_enquiry`, `enquiry_view`, `my_enquiries`, `staff_enquiries`) and the guest pitch (`guest_pitch`, `pitch_view`, `staff_pitches`) with their e-mails; `support`, the supporters' area and shop pages; standard pages with News; account and staff pages. All keys: `_test_ppd_page_keys()`.
- Shared: account_bar, notice, header, footer, staff_bar, subscribe_band, email_header/footer. Contact group `newsletter` (Podcast Newsletter). Built in #7653, #7812, #7819, #7922, #8133, #8154, #8176, #8211 and the assembly.

## Why a main file plus a folder?
- `pinegrap-podcast.php` includes `parts.php`, `mail.php`, then `pages_public.php`, `podcasts.php`, `episodes.php`, `sponsors.php`, `supporters.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`.
- Trap: `parts.php` keeps `$podcasts`, `$episodes`, `$hosts`, `$packages`, `$tiers`, `supporters.php` `$catalog`; no part's return is kept under such names.

## Which shipped features does it reuse? (no new schema, module or behaviour)
- Podcasts and episodes are collections ([[collection-fields]]): an episode references its podcast (`_cf.ref tab:podcasts`), its lists print the podcast's title and cover through `__ref`, and the podcast page lists its episodes with a `shown record` filter ([[collection-template-refs]]). No `?podcast=` filter; podcasts filter by `?category=`.
- Sample episodes are dated back from the day the template opens (`$aired_on()`); no fixed date anywhere.
- The episode's recording is a file field into the public `episodes` folder, played by the bound player ([[bound-media-src]]); Listen buttons stay as fallback.
- Sponsorship enquiries and guest pitches are requests: office-only status, messages as comments, `submitter_only` views, the sponsorship start `min_date` today, attachments in the `private` folder.
- Supporters (the Courses way, [[design-template-courses]]): tier products with `membership_days` open the `supporters` folder (access `membership`), whose bonus episode pages carry a static `<audio preload="none">`. Gift memberships are plain products. Only shop pages and `address_book` need the shop; the shared parts never link it.
- Not built: an RSS feed, download counts, a player across pages, seeking chapters, per-episode paywalls, ad insertion.

## What is the ppd- look?
- `ppd-brand`, `ppd-chapters` (one chapter per line), `ppd-date`, `ppd-display`, `ppd-eyebrow`, `ppd-hero`, `ppd-lift`, `ppd-price`, `ppd-quote`, `ppd-thumb`, `ppd-wave` (the player's box); field ids `pd-`. Space Grotesk headings, DM Sans body. Look `bold`, palette `sunset`, order 141 (139 is Bakery's), icon `bi-mic`. No `opacity-*` on text ([[design-template-text-contrast]]).
- Turkish: "podcast" as is, "bölüm" (episode), "sunucu" (host), "destekçi", "sponsorluk". Never the keys `Show`, `Shows`, `All Shows`, `Season` ("Mevsim"), `Show Notes`, `Host`: it writes `Podcasts`, `Episode Notes`, `Season %d`, `Hosted by`.

## Which tests cover it?
- `design_template_podcast_test.php` and one test per part file load the parts; `…_assembly_test.php` the main file: pages in order, shop pages and links, widgets placed once, folders, search sections, `ppd-` rules, the five comment threads and the reply card, samples kept.
- In the shared lists of the collection-form (`blog_new`, `episode_new`, `podcast_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`pd-se-`, `pd-gp-consent`), custom-form-rules (`pd-se-start`) and faded-text tests.
- `tests/helpers/template_threads.php` nests a threaded list's third comment deeper only when `max_depth` >= 2.
