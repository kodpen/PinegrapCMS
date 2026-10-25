---
name: owner-rules
description: Standing rules from the owner (Erdal) on docs, how changes are recorded, pages, plans, where area rules live (memory; vendor Bootstrap skills aside), language, the mobile app, who edits which notes
---

## Where does user documentation go?
- Only on pinegrap.com, in English, written with the visual designer; never in the repo. Cloud sessions cannot reach pinegrap.com: list the doc text in the final reply and the coordinator relays it.

## What may page content never contain?
- Placeholders such as `^^__x^^` or `__tokenname`. Template pages start as drafts.

## How are plans kept?
- As English memory files in `docs/memory/plans/` (owner rule 2026-10-14): remaining work, decisions made, open questions, where to start; not a full plan text. A finished plan is deleted after its lasting decisions move to the topic folder. The old Turkish plans are in git history (`git show 41aea113:docs/_plan_<name>.md`). A plan gets no changelog line. `docs/_erp_devir_notu.md` is updated by the owner only.

## Where do the area rules live?
- Only in `docs/memory`: each folder README's key rules and its topic files. `.claude/skills` was removed and holds no project rule (owner decision 2026-10-22, PR #1052). `CLAUDE.md` lists which memory folder to read for which work; a sub-agent's task names that folder README, not a skill path.
- One exception (owner request 2026-10-25): `.claude/skills/bootstrap-*` hold unchanged upstream Bootstrap 6 skills as vendor references (`ui/bootstrap-6.md`). They are never edited, and project rules never go there.

## Which language?
- Talk to the owner in Turkish. Code comments in English. Memory files in English (fewer tokens).
- PR descriptions in English (owner rule 2026-10-21); the PR title stays the one-line Turkish summary that becomes the squash commit title. Review comments and take-over notes on PRs are English too, so every session reads them the same way.

## Is there a mobile app?
- No (owner decision 2026-10-14). The API endpoints built for it stay; docs never say "mobile app"; do not re-ask the mobile questions. Restart point: docs/memory/plans/mobile-api-shelved.md

## How are changes recorded?
- As memory, not in a change log (owner rule 2026-10-14; `docs/degisiklikler.md` and its archive were removed and are never recreated). A PR that changes behaviour adds or updates the matching `docs/memory/` topic file: what it does now, why, the symptom it fixed (searchable words), the PR number. No per-version or dated list; stale lines are corrected in place.
- The old developer log is in git history only: every version through #456 `git show 6930e15f:docs/degisiklikler.md`, the last 2026.4.10 state (through #460) `git show f2b14157:docs/degisiklikler.md`.
- `pinegrap/changelog.txt` is English and carries only plan-driven major features as one-line [NEW] entries; all other changes share the version's single [FIX] / [SECURITY] / [SCHEME] lines (owner rule 2026-10-14). Format: docs/memory/release/upgrade-changelog-rules.md

## How big is a task?
- One task = one small PR. Many small threads, not one long one.
