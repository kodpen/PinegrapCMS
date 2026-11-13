---
name: waf-signature-honest-corpus
description: tests/waf_rules_corpus_test.php: honest sentences stay under the Medium threshold, attack payloads reach it; how eleven 10-point rules were narrowed (#9125 A1) and the rule key 'unless'
---

## What does the corpus test hold?
- `tests/waf_rules_corpus_test.php` scans each value with `waf_scan_value()` (the per-value half of `waf_scan_request()`): honest values (Turkish and English prose, product names, addresses, searches; the refused sentences of #9125 first) as `BODY:message` and `ARGS:q` must score under 10 (Medium); attack payloads as `ARGS:q` must score 10 or more. A rule change adds its honest case and its attack case here.
- Thresholds and every rule's score stay (`decisions-made-waf.md`, `waf-score-calibration-and-bot-order.md`); the test checks the eleven rules still score 10 and that there are 31 rules.

## How were the rules narrowed?
- Owner (2026-11-13, #9125): a visitor's ordinary sentence scored 10 and was refused, six refusals banned the address. Each rule now needs the attack's own shape, not a word:
- `rce-php`: the call takes code (quote, `$`, `@`, backtick, cast, another call) or a bare name straight after the bracket (`system(id)`); "System (5.1)" passes. `rce-shell`: case-sensitive lower-case command after a separator; only `cat` and `ping`, which are also words, need a shell argument (option, path, file name, variable, quote, redirection, URL, IP, `passwd`/`config…`); "; CAT 320", "; ping atarsaniz", "| cat ve kopek" pass.
- `sqli-tauto`: OR/AND compares two literals (quoted, numbers, `x=x`), a comparison ended by a comment, or LIKE with `%`. `sqli-numeq`: the comparison ends the injection (end, comment, quote, bracket, next SQL word). `sqli-timing`: the call takes a number, string, call, `@`, comment or subquery. `sqli-union`: only what MySQL skips between UNION and SELECT (spacing incl. no-break space, `/*…*/`, `#…`/`--…` line comments, brackets, ALL/DISTINCT).
- `xss-tag`: iframe, object, embed, applet, svg, math, isindex, marquee, frameset count bare; meta, base, link, form need the name straight after `<` and an attribute or following markup (`< base rate`, a bare `<form>` pass). `xss-handler`: inside a tag any value; outside one it is given code.
- `lfi-windows`: `boot.ini`/`win.ini` end at a word boundary (edwin.iniesta@ passes), `cmd.exe` in a path, with `/c`, alone or before a separator. `lfi-wrapper`: `file://` only to this machine (`file:///`, a drive, `./`, localhost, 127.0.0.1). `lfi-traverse`: passes a value that is one web address.

## How is a narrowing checked against what was caught before?
- The merge train held #9238 back once: the first narrowing let `1 UNION#x\nSELECT 1`, a no-break space and `&& whoami ` through. Before a rule change, run the old `waf_rules()` (from `git show origin/development:pinegrap/waf.php`) and the new one over a few hundred attack probes and list what only the old one catches; each such form goes into the corpus's attack side. The one deliberate loss is a bare `<form>`.

## What is the 'unless' key?
- A rule may carry `'unless' => regex`: when it matches the value, that rule passes it by (`waf_scan_value()`). Used by `lfi-traverse` for a value that is a single `http(s)://` or `ftp://` address with no spaces.

## What passes on purpose?
- A bare `<form>`, `cat`/`ping` followed by an ordinary word, and `../` inside a value that is a single `http(s)://`/`ftp://` address (`/etc/passwd` in it is still caught by `lfi-unix`).

Related: docs/memory/waf/waf-score-calibration-and-bot-order.md
