---
name: design-template-podcast-design
description: Design of plans/design-template-podcast.md: what the Podcast template reuses for podcasts, episodes, sponsorship and supporters, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Podcasts are a collection (the Music Releases pattern, [[collection-fields]]): a category pick list (`?category=`), cover image, host names as words, the schedule as words ("New episodes every Tuesday"), a summary, the podcast's own listening links as hidden URL fields, office field `featured`.
- Episodes are a collection: a reference to the podcast (`_cf.ref tab:podcasts`, [[collection-template-refs]]; the podcast page lists the episodes referencing it, [[collection-lists]]), season and episode number, `aired` date written back from today on opening (`$aired_on()`), length as words, guests, notes, chapters as long text (one line per chapter, "00:00 — Title"), a transcript, the audio file (`audio`, a file field into the public `episodes` folder) and a hidden `listen_url`. The episode page links the file or the address ("Listen"); listeners' words are the record page's comments.
- A sponsorship enquiry and a guest pitch are request records (the Hotel's booking way): office-only status, messages on the record page's Comments widget, `submitter_only` view, My Enquiries, the staff list.
- Supporters' membership is the Courses way ([[design-template-courses]]): tier products with `membership_days` renew the buyer's membership; the supporters' area and its bonus episodes are pages in the `supporters` folder (access `membership`). A bonus episode page carries a static `<audio controls preload="none">` (the lesson video's way): the staff put its file in place. Without the shop the support page still stands and links the contact page.
- The newsletter is the standard `newsletter` page (contact group `newsletter`).

## What is not built? (new behaviour)
- An `<audio>` source bound to an episode record's file or address: asked of the architect (plan step 8). Until then a Listen link.
- A podcast RSS feed with enclosures and iTunes tags for the podcast apps (the apps read the network's host's feed; the template links it), download counts and listener statistics, a player that keeps playing across pages, chapters that seek the player, per-episode paywalls, dynamic ad insertion.

## Look and names
- Prefix `ppd-` for classes (`ppd-brand`, `ppd-display`, `ppd-eyebrow`, `ppd-hero`, `ppd-lift`, `ppd-wave`, `ppd-chapters`, `ppd-quote`), `pd-` for field ids. Two fonts: Space Grotesk headings, DM Sans body. Look `bold`, palette `sunset`, order 99 (91 music, 93 school, 95 museum, 97 architecture), icon `bi-mic`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample: episodes are dated back from the day the template opens.
- Turkish: "podcast" as is ("Podcast'ler"), "bölüm" for an episode, "sunucu" for a host, "konuk" for a guest, "sezon" for a season, "bölüm notları" for show notes, "destekçi" for a supporter, "sponsorluk" for sponsorship. Check every shared key in tr.json before reuse: `Show` is "Göster", `Shows` "Gösterim", `All Shows` "Tüm Konserler", `Season` "Mevsim", `Show Notes` "Notları Göster", `Host` is a server word elsewhere; the template never uses those keys and writes `Podcasts`, `All Podcasts`, `Episode Notes`, `Season %d`, `Hosted by`.

Related: docs/memory/plans/design-template-podcast.md
