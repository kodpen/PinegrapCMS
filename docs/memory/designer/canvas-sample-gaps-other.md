---
name: canvas-sample-gaps-other
description: CS0 census, the rest (CS4): comments and comment e-mail card, calendar, Google reviews, booking, accessibility statement, ads, menus, language, loop values
---

## What do the comments widget and the comment e-mail card draw wrong?
- The card's values (`SW_COMMENTS_TOKEN_GROUPS`, the E-mail canvas state included, `comments-widget-email-design.md`): 25 of 43 samples. ‹label› `__comment_id`, `__comment_anchor`, `__comment_datetime`, `__comment_rating`, `__comment_stars_text`, `__comment_parent_id`, `__comment_depth`; "1" `__watcher_count_text` (a sentence on the page), `__comment_reply_count`; "Example" `__comment_rating_label`.
- The sample comments after the card (`_sdCommentSample`) draw their own values.
- Recent comments: 10 of 18; the same ‹label› for id, date and time, rating, stars text.

## What do the calendar widgets draw wrong?
- Calendar view: `__month_name` "Example"; ‹label› `__year`, `__view`, `__empty_message`, `__event_date`, `__event_is_all_day`; `__event_excerpt` the stock blurb. `calendar.events` the same; `calendar.days`, `.weeks`, `.weekdays` samples.
- Event view: `__event_excerpt`, `__event_description` the stock blurb; ‹label› `__event_notes`, `__reservation_message`.
- Event entity values (`SW_EVENT_ENTITY_TOKENS`): no sample; `__event_reservation_price` money, the rest ‹label› (spots left, updated date, recurrence, reservations open, spots limited, recurring).

## What do the other system widgets draw wrong?
- Google reviews: no sample. `__place_name`, `__review_author_name` "Example"; `__place_review_count` "1"; `__review_total` money; ‹label› `__place_rating`, `__google_attribution`, `__review_rating`, `__review_text`, `__review_relative_time`; grey box `__review_author_photo`.
- Booking: `__service_total`, `__service_price` the money sample; `__service_name` "Example"; `__service_description` the stock blurb; ‹label› `__service_id`, `__service_length`.
- Accessibility statement: no sample. 12 ‹label› (organisation, scope, standard, conformance, contact, enforcement, date, last check, pages, limit criterion, level, pages); `__a11y_feedback` and `__limit_total` money; `__limit_label` "Example".
- Ad region: no sample. Region name, ad name and label "Example"; `__ad_count` "1"; `__ad_content_html` the stock blurb; ‹label› `__ad_ride`, `__ad_interval`, `__ad_wrap`, `__ad_caption_html`.
- Menu sequence: `__menu_name`, `__previous_name`, `__next_name` "Example", `__sequence_count` "1", `__sequence_position` ‹label›. Menu region and `menu.children`: samples but `__menu_name` "Example".
- Language switcher: `__language_label`, `__current_language_label` "Example"; `__language_count` "1"; ‹label› `__language_code`, `__current_language_code`, `__language_active_class`, `__language_aria_current` (the last two are attribute values, never text).
- Loop position values (`SW_LOOP_POSITION_TOKEN_GROUPS`, every loop): ‹label› `__loop_index`, `__loop_is_first`, `__loop_is_last`, `__loop_is_odd`, `__loop_is_even`; `__loop_count` "1". A "first row only" condition shows on every row.
