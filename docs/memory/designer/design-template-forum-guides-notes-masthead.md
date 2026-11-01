---
name: design-template-forum-guides-notes-masthead
description: Forum template 1.3.2: guides' structured-data author is the byline (#4873), the masthead's main links from xl (#4874), the report notes' own button words (#4875).
---

## Who is a guide's author in its structured data?
- The Community Docs form carries `schema_author => field:byline`, as the News and Magazine forms do: the BlogPosting author is the name readers see ("The docs team" on the samples), not the site as an Organization or the sender's contact name. Installed sites keep their form setting.

## Why do the main links show from xl?
- Five links (Forum, Docs, Blog, Guidelines, Search) beside the theme, language, sign-in and Start a topic wrapped at lg (992–1199 px) on a site with a language switcher and a longer name: Search fell to a second row and the name was cut. The nav is `d-none d-xl-block`, the menu button `d-xl-none`, the brand `me-auto me-xl-3`; the drawer (`offcanvas-start`) works at any width.

## Which words do the report notes use?
- `$note_words`: submit `Add a note` (the heading's words, "Not ekle"), close `Stop new notes`, reopen `Allow new notes`, like the other threads' own words.

## Which tests check it?
- `test_forum_guide_author_is_the_byline`, `test_forum_masthead_links_from_xl`, `test_forum_report_note_words`, and `_test_forum_threads()` for the notes.
