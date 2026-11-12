---
name: memory-cluster-check
description: The memory index tool refuses a cluster of ten memory files or more with no start file (<prefix>-overview.md or <prefix>.md); plans left out; what to do when it fails
---

## What does the tool refuse?
- `tools/memory_index.php` (every run, `--check` included, so `git push` and CI too) stops when a folder has a cluster of `MIN_OVERVIEW_FILES` (ten) files or more and no start file. The error names the folder, the prefix, the count and the file to add (#8760).
- A cluster is what the folder README lists under "Clusters": files sharing their first two name tokens, a plural s aside (`memory_index_clusters()`, at least `MIN_CLUSTER_FILES`).
- The check is `memory_index_cluster_errors($folder, $clusters)` (pure, `tests/memory_index_test.php`).

## Which file counts as the start?
- `<prefix>-overview.md`, or `<prefix>.md` as the older clusters have it (`order-returns.md`, `bootstrap-6.md` …): kept, not renamed, so their links stay. Either form of the prefix counts (`custom-forms-overview.md` for `custom-form-*`).
- What an overview holds, at most 4 KB: [[memory-writing]], "When does a cluster get an overview?"

## Why are plans left out?
- A plan is deleted when it is finished; its files are no lasting topic, and a plan cut into many step files would stop every push.

## What do I do when it fails?
- The PR that adds a cluster's tenth file writes its overview from the cluster's files, then runs `php tools/memory_index.php`; the README then names the start.
- The overviews of 2026-11-11 (#8738, #8740, #8743) gave every cluster of ten a start before the check came.
