---
name: owner-rules
description: Standing rules from the owner (Erdal) on docs, pages, plans, templates, language, the shelved mobile app and who edits which notes
---

## Where does user documentation go?
- Only on pinegrap.com, in English, written with the visual designer; never in the repo. Cloud sessions cannot reach pinegrap.com: list the doc text in the final reply and the coordinator relays it.

## What may page content never contain?
- Placeholders such as `^^__x^^` or `__tokenname`. Template pages start as drafts.

## How are plans kept?
- As `docs/_plan_<name>.md` with frontmatter (they enter the memory index). A plan file gets no changelog line. `docs/_erp_devir_notu.md` is updated by the owner only.

## Which language?
- Talk to the owner in Turkish. Code comments in English. Memory files in English (fewer tokens).

## Is there a mobile app?
- No (owner decision 2026-10-14). The API endpoints built for it stay; docs never say "mobile app"; do not re-ask the mobile questions. Restart point: `docs/_plan_mobil_api_claude_kanali.md`.

## How are changes logged?
- Under the open version in `docs/degisiklikler.md` (detail and reasons; append only, never read it) and `pinegrap/changelog.txt` (short operator summary).
- In the open version the changelog describes the final state: a fix to something added in the same version updates its [YENİ] item instead of adding [DÜZELTME]; internal addresses added and removed during development are not mentioned.

## How big is a task?
- One task = one small PR. Many small threads, not one long one.
