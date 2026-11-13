---
name: waf-signature-honest-corpus
description: tests/waf_rules_corpus_test.php: honest sentences stay under the Medium threshold, attack payloads reach it; how eleven 10-point rules were narrowed (#9125 A1) and the rule key 'unless'
---

## What does the corpus test hold?
- `tests/waf_rules_corpus_test.php` scans each value with `waf_scan_value()` (the per-value half of `waf_scan_request()`): honest values (Turkish and English prose, product names, addresses, searches; the refused sentences of #9125 first) as `BODY:message` and `ARGS:q` must score under 10 (Medium); attack payloads as `ARGS:q` must score 10 or more. A rule change adds its honest case and its attack case here.
- Thresholds and every rule's score stay (`decisions-made-waf.md`, `waf-score-calibration-and-bot-order.md`); the test checks the eleven rules still score 10 and that there are 31 rules.

## How were the rules narrowed?
- Owner (2026-11-13, #9125): a visitor's ordinary sentence scored 10 and was refused, six refusals banned the address. Each rule now needs the attack's own shape, not a word:
- `rce-php`: the call takes code (a quote, `$`, a backtick or another call); "System (5.1)" passes. `rce-shell`: case-sensitive lower-case command with a shell argument (option, path, variable, quote, redirection, URL, IP); "; CAT 320", "; ping atarsaniz" pass.
- `sqli-tauto`: OR/AND compares two literals (quoted, numbers, or `x=x`). `sqli-numeq`: the comparison ends the injection (end, comment, quote, bracket, next SQL word). `sqli-timing`: the call takes a number, call, `@` or subquery. `sqli-union`: only spacing, comments, brackets, ALL/DISTINCT between UNION and SELECT.
- `xss-tag`: the name straight after `<` and an attribute (`< base rate`, a bare `<form>` pass). `xss-handler`: the handler is given code (call, assignment, index, template, `javascript:`, entity).
- `lfi-windows`: `boot.ini`/`win.ini` as whole words (edwin.iniesta@ passes), `cmd.exe` in a path or with `/c`. `lfi-wrapper`: `file://` only to this machine (`file:///`, localhost, 127.0.0.1). `lfi-traverse`: passes a value that is one web address.

## What is the 'unless' key?
- A rule may carry `'unless' => regex`: when it matches the value, that rule passes it by (`waf_scan_value()`). Used by `lfi-traverse` for a value that is a single `http(s)://` or `ftp://` address with no spaces.

## What is still not caught?
- A bare command with no argument (`; id`, `$(whoami)`, `` `id` ``) was not caught before and is not now: widening a rule is its own change, with its honest cases.

Related: docs/memory/waf/waf-score-calibration-and-bot-order.md
