---
name: messages-area-look
description: Communication area look (MA12 #9363): pill tab strip, filter segments, notices under the strip, Sends figures and pills, Channels grid, old screens left inside
module: messages_area
---

## How is the tab strip drawn?
- As the segmented control the flow window has for Canvas and List (owner, #9295: "tablar boyle olsa ya"): `nav.pg-messages-tabs` > `.pg-seg` > `a.pg-seg-item`, each with its icon and `span.pg-messages-tab-label`.
- One line at any width: under 576 px only the open tab shows its label; the others show their icon, the label kept for screen readers and as `title`. Before MA12 the `nav-tabs` strip broke into three lines at 390 px.

## How are the filters and notices drawn?
- A filter of few choices is `pg_messages_seg_filter()`: links keeping the other filters, the default value left out of the address, the chosen one `is-active` with `aria-current="true"`. Several sit in `div.pg-messages-filters`, one wrapping row. Campaigns' channel and state; Sends' channel (source, state and period stay selects: too many choices).
- A notice is `pg_messages_note()`: an inner card row with a tone tile, the sentence and a quiet button to fix it, always under the strip, never above it. Campaigns' "SMS is not set up" (button "Set up SMS" for role <= 2), Sends' PHP mail() warning.

## How does Sends look?
- Figures are the report screens' figure cards (`pg_messages_sends_figures_html()`, `.pg-report-figs`), each with its tone tile: Queued blue, Sending violet, Sent teal, Failed rose, Outcome unknown amber.
- States are look A pills (`pg_messages_sends_pill()`: info violet, success teal, danger rose, warning amber, else gray); a row's channel is its tile and name; Retry and Delete are small icon buttons; Run now is a quiet button at the toolbar's right. The status column and filter read "Status": `lang('State')` is the geographic state in Turkish.

## How do the Channels cards stay even?
- `div.pg-messages-channels` is a grid, two columns and `grid-auto-rows: 1fr` from 768 px (one column, rows as tall as their text, below), the pill on the title line, inside a padded `pg-card pg-messages-card` (before, the heading sat on the card's border). Each card is an `a.pg-card-inner.pg-messages-channel`.

## Which old screens are still inside the area?
- Never carried over as they are; each is redrawn from `plans/messages-area/messages-area.html` by the step named.
- Campaign add and edit (`add_email_campaign.php`, `edit_email_campaign.php`, `view_email_campaign.php`, `send_email_campaign.php`): MA5, as the content step of New campaign.
- SMS campaign (`edit_sms_campaign.php`): MA5 beside the e-mail one; DL5 of `panel-design-language-steps.md` if MA5 leaves it.
- Profiles (`view_email_campaign_profiles.php`, `add_`/`edit_`/`duplicate_`/`import_email_campaign_profiles.php`): turned into flows by MA9 and removed by MA9f; not redrawn.
- Automatic sends' body (place rows): MA8. Campaigns stays on the table driver; the drawing's progress meter per campaign row is not drawn yet.
- On a 390 px window the list driver's card view clips a Sends row's card (seen before and after MA12): a list driver matter, not this area's.

Related: docs/memory/ui/messages-area-shell.md, docs/memory/ui/messages-area-campaigns-tab.md
