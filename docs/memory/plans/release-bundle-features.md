---
name: release-bundle-features
description: Plan release-bundle, features B1 to B18: a bundle and its pages, the hold, the checks before a publish, the write, take back, the time, the preview and its share link, the API, what stays as it is
---

## What is a bundle, and what is in it?
- (B1) A bundle has a name, a status (open, scheduled, published, partly published, taken back), an optional publish time, and who made and who published it. Made and published by roles 0 and 1.
- (B2) Its pages are drafts of a visual design: a new page, or a draft that stands for a live page (a replacement). A draft is in one open bundle only, and a live page has one replacement in one open bundle.
- (B3) "Add a copy of a page" copies a live designer page into Drafts as an experiment copies a variant, and keeps which page it stands for and that page's state at that moment. A classic page cannot be added, nor a page whose experiment is open.
- (B4) "Add a draft" takes a draft that is not an open experiment's variant; a publish time of its own is cleared, and the screen says so.
- (B5) While the bundle is open its drafts cannot be published alone, binned, deleted over the API or made a variant; the refusal names the bundle, and the Publish sheet shows them as held.
- (B6) Deleting an open bundle releases its drafts: they stay drafts.

## How is it published?
- (B7) First the checks, for every page: its lock, the save's dry run, the home page rule. A replacement whose live page was published by hand since the copy is listed "changed since", and the person chooses per page: "Replace anyway" or "Leave out".
- (B8) Then the writes: a new page moves out of Drafts; a replacement's tree, title and description are saved onto its live page through the page save, one revision each with the source `bundle`. The item keeps the revision before and the state written; the used replacement goes to the Recycle Bin.
- (B9) A failure while writing takes back at once what this publish wrote, by B11's way: the bundle stays open and names the page that failed, so a timed publish never leaves half a campaign live. Only when that take-back fails too is the bundle "partly published": it lists what stands and offers "Publish the rest" and "Take back".
- (B10) The Site Log names the bundle on every line; a page's History shows the bundle's name beside the source.

## How is it taken back?
- (B11) A replaced page returns to the revision it had before, as a new publish, only while its state still equals the one written and that revision is still kept; a new page goes back to Drafts.
- (B12) What cannot be taken back is listed with its reason. Nothing is taken back silently and nothing half.

## What does a time do?
- (B13) A bundle with a publish time is published once by the general job, in the name of the person who set the time, after B7's checks. One that cannot go out whole (a locked page, a page changed since) stays open and tells its maker by a bell line.

## How is it looked at before?
- (B14) "Preview the bundle" opens the prototype viewer on the bundle: its drafts, a link to a replaced page opening its replacement with the replacement's own title; other links open the live page.
- (B15) A share link of a bundle's preview, with the audience, expiry and password of a prototype share link.

## What else?
- (B16) The API reads bundles, adds and removes pages, publishes (the dry run answers B7's list) and takes back, under `design:write`; 409 while a page is open in the editor.
- (B17) A live page's own Publish still goes live at once, with or without a replacement waiting.
- (B18) A shared component saved from any tab is live at once, as today; menus and collection records are not in a bundle.
