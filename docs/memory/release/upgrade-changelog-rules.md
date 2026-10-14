---
name: upgrade-changelog-rules
description: Mandatory change log on every upgrade - changelog.txt vs docs/degisiklikler.md, tags, [ŞEMA] line, symptom wording, ordering inside the open version.
---

## Which files are updated for every new version number?
- `pinegrap/changelog.txt` (operator/customer): a few items per version. WHAT changed and what the user will notice. Newest version on top.
- `docs/degisiklikler.md` (developer): WHY it changed - measurement, root cause, rejected alternative, known trade-off.
- Not optional.

## Which tags does changelog.txt use?
- `[YENİ]` `[DÜZELTME]` `[HIZ]` `[ŞEMA]` `[GÜVENLİK]` `NOT`.
- Every version with a schema change must carry a `[ŞEMA]` line so the operator sees the upgrade will touch the DB.

## Why two files?
- `changelog.txt` ships with the product and goes to customers; never write there "MyISAM table lock was updating 946 MB of index".
- `docs/degisiklikler.md` stays in the repo and answers "why did we do this" six months later: memory files and skills hold lasting rules, that file holds the incident each rule came from. It is written to, never read routinely.

## How is a fix described?
- Write the symptom too. "IPv6 bans did not work" alone is not searchable; "dozens of duplicate ban rows for one address while the address is never blocked" is what someone seeing the symptom again will search for.

## How are items ordered inside the open version?
- In `changelog.txt` versions are newest first, but inside the open version items go under topic subsections (PANO, ERP ...) and within a subsection are ordered oldest to newest (e.g. VUK 509 fields and invoice PDF were added to the ERP subsection of 2026.4.4 this way).
