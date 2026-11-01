---
name: new-dashboard-presets
description: New dashboard plan: the five preset layouts (Corporate, Blog, Ecommerce, Agency, Simple): their cards and widths, shortcuts, tiles, pipeline, sign-in chips, recommended cards, the first default
---

## What is a preset?
- A starting layout: cards with widths, the shortcuts, the four tiles, the pipeline's title and steps, the sign-in chips and the gallery's Recommended list. Five: Corporate (Kurumsal), Blog, Ecommerce (E-ticaret), Agency (Ajans), Simple (Sade: its own cards, the rest from the first default).
- The first default: Ecommerce when the ECOMMERCE switch is on, else Corporate; Pinegrap has no site-type setting. A role default (N13) comes before it. Kept in one PHP list (`presets.php`, N5) the page and the save both read.
- A card the person may not see is skipped; the others keep their order.

## Which cards? (S narrow, M medium, L full)
- Corporate: ai M, goals S, pipeline L, queue M, flows S, visitors M, visitmap S, content M, activity S, firewall S, team S, week S, comments S, gsearch S, pins S.
- Blog: ai M, goals S, pipeline L, content M, comments S, visitors M, visitmap S, queue M, gsearch S, calendars S, notes S, pins S, perf S, flows S.
- Ecommerce: ai M, goals S, pipeline L, ops M, orders S, salesmap M, shop S, visitors M, engagement S, queue M, flows S, offers S, firewall S, comments S.
- Agency: ai M, mytasks S, pipeline L, queue M, waiting S, flows M, team S, content M, week S, files S, calendars S, perf S, visitors M.
- Simple: ai M, goals S, queue M, activity S, visitors L.

## What else does each set?
- Shortcuts, the first the main one. Corporate: Edit page, Forms · n, Task for the team, Publish announcement. Blog: Write a post, Comments · n, Visual Editor, Send newsletter. Ecommerce: Orders · n, Products, Campaign, Visual Editor. Agency: Designs, Share prototype, Client tasks, New page.
- Tiles. Corporate: visits, forms, SEO, tasks. Blog: visits, subscribers, SEO, inbox. Ecommerce: orders, visits, online, SEO. Agency: tasks, visits, accessibility, flows.
- Pipeline title and steps. Corporate "Request line": visits, form entries, new contacts, meetings (tasks opened from forms). Blog "Reader line": visits, page views with average time, comments and shares, new subscribers. Ecommerce "Sales line": visits, carts (abandoned in the note), orders (average in the note), delivered (from tracking). Agency "Project line": designs in work, prototypes shared, waiting for the client's approval, going live this week.
- Sign-in chips. Corporate: visits, forms, comments, firewall blocks, tasks done. Blog: readers, comments, subscribers, drafts saved. Ecommerce: orders, sales, refund requests, visits, blocks. Agency: client comments, tasks done, approvals, files uploaded.
- Recommended. Corporate: engagement, mytasks, notes, calendars. Blog: notes, engagement, ga, files. Ecommerce: fx, perf, mytasks, visitmap. Agency: notes, pins, gsearch, comments.
- The prototype's goal examples ("October: 1,500 visits", "Answer forms within 24 hours", "4 announcements a month") are samples, not defaults: a new layout has no goals.
