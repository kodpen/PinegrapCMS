---
name: audit-rule-registry
description: Audit rule registry (includes/fn/audit_rules.php): per concept the SEO and a11y codes, WCAG criterion, severities, editor rule ids, document/browser flags; tools/check_audit_rules.php
module: audit_rules
---

## What is it?
- `pg_audit_rules()`: concept → `seo` code, `a11y` code, `severity` per scope (`seo`, `a11y`), WCAG `criterion` and `level` (with an a11y code only), `editor` rule ids of `validateTree()` in `style_designer.src.js`, `document` (the scopes in which only a whole page is judged), `browser` (only a browser measures it in full: contrast, target size).
- Accessibility rows come first, in the report's checklist order; then SEO-only rows, then editor-only rows (`dup_id`, `nested_interactive`, `form_no_submit`, `form_no_action`, `cf_field_unnamed`, `redundant_entry`).
- One editor id may serve two concepts: `empty_btn` is `link_name` and `button_name`.
- Lookups: `pg_audit_rule_concept($scope, $code)`, `pg_audit_rule_concepts_of_editor($id)`.

## Who reads it?
- `pg_seo_structure_severities()` and `pg_seo_document_only_codes()` (`seo_structure.php`), `pg_a11y_rules()` (`includes/fn/a11y.php`); each loads the module with `require_once`, `functions.php` does not.
- A severity, criterion or document flag is changed in the registry only; the three readers keep their old shape and order (`tests/audit_rules_test.php` pins them).

## How is it kept whole?
- `php tools/check_audit_rules.php` (CI and `git push`): every editor rule id between `var _validationRules = [` and `// ── Canvas badges` has a row, and its `seo:` code is its row's; every SEO code is emitted in `seo_structure.php` (`$add('code'`, `$findings['code']` or `'code' => 'code'`); every a11y code has `pg_a11y_rule_<name>()`; every editor id in a row is still in the script.
- A new editor rule, SEO rule or report check therefore needs its registry row in the same PR.

## Which pairs did the registry add?
- `title_tag_missing` with `a11y_page_title` (`page_title`). The editor rules `img_alt`, `empty_btn` and `heading_skip` still carry no `seo:` code in the script although their rows have one.
