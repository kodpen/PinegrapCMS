---
name: design-template-events-words
description: Events template wording (1.0.1, #4825: bilet, oturum dizisi, own keys for registration pages, cancelled status, speakers address) and heading order (1.0.2, #4824).
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
