---
name: audiences-interest
description: Contact group rule conditions on the visitor journey's interest (LJ4b): interest score at least / at most, interested in a folder or a product group, how each reads contact_scores
module: audiences
---

## Which conditions read the interest?
- Family "Interest" in the rule editor (`pg_audience_conditions()`): "Interest score" (`interest_score`, at least / at most, 0 to 100), "Interested in a folder" (`interest_folder`, yes/no, folder picker), "Interested in a product group" (`interest_product_group`, yes/no, product group picker).
- A score range is two "Interest score" rows in one "all" level. A value over 100 is dropped by `pg_audience_condition_clean()`.

## What does each read?
- `contact_scores.interest`, written by the scoring pass (`visitors/lead-score.md`). "At least" is IN those at or above n; "at most" is NOT IN those above n, so a contact never scored counts as 0, as "Number of orders" counts one who never ordered.
- A topic is one of the contact's top five interests (`contact_scores.topics` JSON). Matched with `LIKE` on the start of its object, `{"id":n,"kind":"folder",` or `"kind":"category",` (`pg_audience_topic_pattern()`): the pass writes id and kind first with `json_encode()`, so a name's escaping never matters. A product group is a `category` topic.
- The folder picker (`pg_audience_picker_rows()` kind `folder`) leaves out the site's root folder, which is never a topic. `assets/js/pg_audience_editor.js` lists `folder` among its pickers.

Tests: `tests/audiences_rules_test.php` (`test_audiences_interest_*`).
Related: docs/memory/core/audiences-rules.md, docs/memory/visitors/lead-score.md
