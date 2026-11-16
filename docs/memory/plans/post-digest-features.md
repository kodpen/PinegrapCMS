---
name: post-digest-features
description: Plan post-digest, features D1 to D13: the flow box that reads a list's records, the mark of what was sent, the e-mail box's contact group as a campaign, the body, the ready flow, safety and rights
---

## What does the records box do?
- (D1) A flow box "Read the records of a list": it takes a page that has a form list and gives the count and the records that page's RSS feed would give (title, summary, picture, address), 50 at most. A general box: any flow can use it.
- (D2) The pick is the list page's own: its filters and its detail page's, complete records only, its order. So only what a visitor can see is read. A page that is not open to everyone is refused when the box is saved and again when it runs.
- (D3) Its option "Only records this flow has not sent yet": a record is given once per flow. The mark is a row per flow and record, so a record set live late is still sent once and nothing hangs on its date.
- (D4) A mark is taken with one conditional write per record before the mail is made; a run that does not get a record's mark leaves it out. A flow that fires twice sends once.
- (D5) The first run of a flow gives at most the newest 10 records and marks the older ones as sent: a new digest never mails the whole archive.
- (D6) The marks of a flow or record that is gone are removed by the general job's sweep. A mark holds no personal data.

## How is it sent?
- (D7) The flows' e-mail box gets the recipient "A contact group". Such a send is a campaign made through `pg_email_campaign_pick_recipients()` and `pg_email_campaign_insert()`: opt-in, bounced addresses, İYS and the unsubscribe footer are the campaign's, and it stands in the campaigns list with its counts.
- (D8) The body is the box's own text with the records drawn as a block in the campaigns' mail layout: picture, linked title, summary. Every text is escaped; a picture is one the feed gives, so one everybody may open.
- (D9) With no record the run ends before the e-mail box: no mail. The ready flow has that Condition.

## What does the ready flow do?
- (D10) A gallery flow "Digest of new posts": At a time (weekly, a day and hour in the site's time), Read the records (not sent yet), Condition (count is 0: end), E-mail to a contact group. Its set-up asks the list page, the group, the subject and the day.
- (D11) "Try" on the canvas shows the records it would send and mails only the person trying; it takes no mark.

## Who, and what stays?
- (D12) For flows of roles below 3; a personal flow of role 3 has no e-mail box, as today.
- (D13) A flow without the records box, and the e-mail box with its two recipients of today, work as before; nothing is sent by a site that set no such flow up.
