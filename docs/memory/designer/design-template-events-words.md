---
name: design-template-events-words
description: Events template wording: "Events" is Etkinlikler (1.0.3, #4810), bilet, oturum dizisi, own keys for registration pages (1.0.1, #4825), heading order (1.0.2, #4824).
---

## Which Turkish words does it use?
- One word per thing: a pass is "bilet" (never "geçiş"), a track "oturum dizisi" (never "kol"); the help center's FAQ sends a visitor to "Etkinliğe Kayıt", not to Sign Up ("Kayıt Ol", the account page).
- Own keys where a shared key read wrong: the form page is `Register for an Event` ("Etkinliğe Kayıt"), a registration's page `My Registration` ("Kaydım"), the status `Registration cancelled` ("İptal edildi"; `Cancelled` is the panel's "İptal Edildi" in 68 places), the speakers address `our-speakers` ("konusmacilar"; `speakers` is also the "40+ konuşmacı" figure). `News` itself is "Haberler" everywhere.

## What is still open?
- The ticket product's breadcrumb still starts with "Main Catalog" (`pg_sw_list_crumb_rows_item()` has no root label): a platform change, not part of 1.0.1.

## Which test checks it?
- `test_events_turkish_words_name_one_thing_one_way` checks every Turkish value of the template's keys.


## How are its headings ordered? (1.0.2, #4824)
- Never more than one level down: the programme's day theme is an `h2` between the page `h1` and the sessions' `h3`, a ticket card's title an `h2`. `test_events_headings_never_skip_a_level` walks every page tree (widget trees not included).

## Why does "Events" read "Etkinlikler"? (Events 1.0.3, Bookshop 1.3.1, #4810)
- The templates' events page, menu, footer, form name and help topic use `lang('Events')`; it read "Olaylar" ("incidents") while the same sites say "etkinlik". The key is now "Etkinlikler"; the API screens (`api_settings.php`, `api/console_view.php`) use `Webhook events` ("Webhook olayları"), the firewall log card `Logged events` ("Kayıtlı olaylar"). Installed sites keep their stored names. Test: `test_events_key_reads_as_site_events_in_turkish`.
