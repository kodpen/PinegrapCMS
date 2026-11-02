---
name: decisions-made-product-20
description: Decisions list, product part 20 (2026-11-02 on): voice to assistants, transactional campaigns and opt-in, converted regions, event copies, till dates, country names, bank statements
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## Voice to assistants: the sender's browser writes the recording out, a voice ask is answered by voice (2026-11-02, #5390)
- Owner: without a site key the sender's browser writes the recording out on the device and stores it as a machine transcript, Turkish tried first; where the browser's recognition proves unusable the site's key stays the way, and a Pinegrap AI gateway preset follows once the gateway offers audio. An ask that was a recording is answered by voice: by the speech service when one is set, else read aloud by the asker's browser even with the read-aloud switch off; a text ask gets text. Reason: Claude's API takes no sound, so the recording is written out before the request, and the person who spoke expects to hear the answer. Plan: `plans/voice-to-assistants.md`.

## Transactional e-mail campaigns reach opted-out contacts (2026-11-02, #5441)
- Owner: a campaign with the transactional purpose goes to every contact of the chosen groups that has an address, opted in or not, as its label and the automatic campaign profiles already do; the group list shows both counts and the purpose card warns that promotional text goes commercial. The bounce block stays for every purpose; the İYS refusal and the unsubscribe footer stay commercial-only. Reason: a relationship notice must reach every customer, and the administrator who picks the purpose answers for it.

## A converted common region is not deleted while its component exists (2026-11-02, #5516)
- Owner: the region screen's Delete refuses while the shared component made from the region exists and names it with a link; deleting the component first brings the row's content back, then the region deletes as before. Reason: the row is the tag's only link to the component and its undo (#3156); one click must not break it.

## A duplicated calendar event starts unpublished (2026-11-02, #5582)
- Owner: the copy is unpublished, named "… (copy)", with its locations copied; the conflict check asks on its first save. Reason: a copy must not show twice on the site before it is edited, and a copy without its places is not a copy.

## Till movements refuse a future date (2026-11-02, #5581)
- Owner: a receipt, payment, transfer or expense payment dated after today is refused with a message pointing at cheques, notes and due dates; existing future-dated rows stay. Reason: the till ledger holds money that moved (#4460); future money belongs to the cheque and due-date screens.

## The country name an administrator edited wins (2026-11-02, #5650)
- Owner: a stored country name that differs from the code's seed or intl English name shows and is searched as stored; unedited rows keep the translated name; shared labels get " (CODE)". Reason: #2657 says a wrong country is corrected on the Countries screen, so the correction must show.

## An imported bank statement can be deleted while nothing is recorded (2026-11-02, #5649)
- Owner: a statement with no recorded or matched line deletes with its new and ignored lines; one with a recorded line refuses and names them; a file with no dated amount is refused at upload. Reason: a wrong upload must be cleared without touching recorded movements.
