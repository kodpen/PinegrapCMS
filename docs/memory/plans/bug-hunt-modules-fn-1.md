---
name: bug-hunt-modules-fn-1
description: Module list of plans/bug-hunt.md, part 1: includes/fn/ modules a11y to cloudflare_tools, one line each with its state
---

## Which includes/fn/ modules (a11y to cloudflare_tools) are hunted?
- `a11y`: done #5093 (a select or textarea named by its own options or text; rest reviewed: rules, idrefs, contrast, nightly pass, editor findings, report escaping)

- `ai_crawlers`: done #5109 (llms.txt title backslash; rest reviewed: robots groups and catch-all merge, Content-Signal, operator text precedence, settings row, llms page query, ranking, escaping)

- `ai_prompts`: clean (rule lines per profile, version hash and insert fragments with the column probe, version label, data block escaping of `<<<` and the name checked)

- `ai_write`: done #5126 (page regions read from the style's collection and into a designer tree's region tags; rest reviewed: targets, context cleaning, prompt, answer and tidy, cut, HTML escaping, daily counter, access, marks and fingerprints, browser path)

- `audit`: clean (secret masking checked against every column of the traced tables, diff, compaction, JSON masking, actor, request id, fetch-by-column guard, recycle-bin label)

- `audit_list`: clean (link query and date round trip, sort, search, value and changes escaping, row links and Open gate, paging queries; its user link fault fixed in audit_view #5145)

- `audit_view`: done #5145 (a username made of digits read as a user id by the User filter; rest reviewed: filters, sides and values, CSV formula guard, live records, labels, settings names)

- `auth`: done #4523

- `backup`: done #4563

- `backup_cloud`: clean (folder names and default folder, chunks and Content-Range, resume offsets of Drive and Graph, Drive query quoting, Dropbox argument header, account check, prune guard, the three upload loops checked)

- `backup_restore`: clean (archive entry filter, size-capped CRC-checked extraction, statement start and classification, renaming, partition fallback, line splitter against escaped dumps, session save and restore, recovery, lock and swap checked)

- `bundles`: clean

- `calendar`: done #4632 #4646 (recurring overnight/multi-day events and room availability, unpadded view date; rest reviewed: escaping, access, recurrence expansion)

- `captcha`: clean (siteverify outcomes, token, widget escaping, settings card and save order checked)

- `carrier_api`: done #5159 (FedEx weight under 0.005 lb sent as 0)

- `carrier_labels`: clean

- `carrier_webhook`: clean

- `cart_quotes`: clean (line cleaning, plan against stock/minimum/maximum/bin, create and open checks, apply and its repeat guard, completion, link page escaping checked)

- `classic_style_design`: clean

- `cloud_file_picker`: clean

- `cloudflare_tools`: done #5182 (cached bandwidth in MiB beside bandwidth in MB)
