---
name: decisions-made-schema-39
description: Decisions list, schema part 39 (2026-11-20 on): the three tables of the marketplace repricer: marketplace_price_rules, marketplace_price_state, marketplace_price_log
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-38.md`.

## Does the marketplace repricer get tables? (owner, 2026-11-20, agent channel poll 186, channel message 10696)
- "Evet, üç tablo eklensin": `marketplace_price_rules`, `marketplace_price_state`, `marketplace_price_log`. No column is added to `marketplace_product_map` or `marketplace_accounts`.
- The plan is the marketplace repricer's (PR #10746); the columns were given in channel message 10721.

## Which columns does marketplace_price_rules have? (architect, 2026-11-20)
- One row per account and scope. `id` INT UNSIGNED AUTO_INCREMENT; `account_id` INT UNSIGNED; `scope` TINYINT (0 the account, 1 a category, 2 a brand); `scope_id` INT UNSIGNED, 0 on the account's row.
- `percent` INT, signed hundredths of a percent; `fixed` INT, signed minor units; `rounding` VARCHAR(10).
- Read on the account's row only: `robot` TINYINT (0 off, 1 try first, 2 on); `step` INT UNSIGNED, minor units; `min_margin` INT UNSIGNED, hundredths of a percent; `grow_cap` INT UNSIGNED default 1000.
- `updated_at`, `updated_by` INT UNSIGNED.
- Keys: unique (`account_id`, `scope`, `scope_id`).

## Which columns does marketplace_price_state have? (architect, 2026-11-20)
- One row per listing, under the key a listing carries in `marketplace_product_map`; `account_id` INT UNSIGNED.
- `strategy` TINYINT; `own_price`, `own_floor`, `buybox_price` INT UNSIGNED, minor units; `buybox_ours` TINYINT; `buybox_at` INT UNSIGNED.
- `robot_price` INT UNSIGNED, the price the robot last sent; `lost_price`, `lost_at` INT UNSIGNED, the price and time the buybox was lost; `state` TINYINT; `changed_at`, `updated_at` INT UNSIGNED.
- Keys: primary, the listing; (`account_id`, `buybox_at`).

## Which columns does marketplace_price_log have? (architect, 2026-11-20)
- One row per price the robot or a rule set. `id` BIGINT UNSIGNED AUTO_INCREMENT; the listing's key; `account_id`, `product_id` INT UNSIGNED.
- `old_price`, `new_price`, `floor`, `buybox_price` INT UNSIGNED; `currency` VARCHAR(3); `reason` VARCHAR(20); `sent` TINYINT; `at` INT UNSIGNED.
- Keys: (the listing, `at`); (`account_id`, `at`); `at`.

## What else holds?
- Every column NOT NULL with its default. Money is integer minor units, 0 for none; times are INT UNSIGNED. No personal data is in any of the three.
- The listing's key is not spelled here: the worker of the plan's first step reads it from `marketplace_product_map`, writes the columns into its PR body and asks the architect before it is marked ready.
