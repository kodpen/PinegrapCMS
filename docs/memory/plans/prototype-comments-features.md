---
name: prototype-comments-features
description: Plan prototype-comments, features P1 to P16: the link's "Allow comments", the viewer's comment mode, the guest's door, what a comment keeps, the designer's pins and list, the task
---

## Which links take comments?
- (P1) A share link has "Allow comments", off on every link there is; set in the Share prototype dialog and over the link routes, and shown in the links list.
- (P2) Only such a link's frame is drawn with node marks and the comment script. A link without it, the staff viewer and every served page stay as they are.

## What does a guest do in the viewer?
- (P3) A "Comment" button turns the mode on. In the mode a click in the frame follows no link: the frame tells the viewer the page, the node and the click's place inside the node as two percentages.
- (P4) The viewer opens a box at that place: Name (needed, at most 60, remembered in the browser) and Comment (plain text, 1 to 1,000). Send is the viewer's own request.
- (P5) Whoever opens the link sees its pins, numbered, on the pages, and on a pin its thread: the comments and the answers with names and times. A guest can answer in a thread and cannot change or delete anything.
- (P6) A pin whose node is not on the drawn page any more is listed under "Not on the page any more".

## What does the door check?
- (P7) New root file `prototype_comment.php` takes a POST only from the session that opened that link (after its password, when it has one), with the viewer's form token and from the same origin; the page must be one the link shows.
- (P8) Limits: 10 comments in 10 minutes per address, 200 open comments a link; a burst counts a WAF offence. The text is kept plain, the place's numbers are bounded.
- (P9) The link's token never enters the frame, a log or a comment. A message from the frame only proposes a place: nothing is written without the viewer's request.

## What does a comment keep?
- (P10) Its link, design, page, node and place, the name, the text, the comment it answers, when it was written, and when and by whom it was resolved. No address of the guest.
- (P11) Comments go with their design when it is deleted. A revoked or expired link keeps its comments for the designer; the links list has "Delete this link's comments".

## What does the designer see?
- (P12) In a part of its own, for roles 0 and 1: pins on the canvas of the open tab, found by node, and a Comments list by page with open and resolved ones.
- (P13) Answer, Resolve and Reopen, Delete. A pin whose node is gone stays in the list and says so; a page that went live keeps its comments.
- (P14) A new comment is signalled on the presence heartbeat that exists (the newest comment stamp of the open pages); the part fetches when it changes. No second loop.
- (P15) A bell line to the link's maker for a guest's comment, at most one an hour per link; it opens the design with the list.
- (P16) "Make a task", only with the Workspace on: a task opened as the designer with the page tagged and the comment's text quoted as data; a second comment on the same page opens a task of its own.
