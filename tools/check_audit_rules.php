<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Checks that the audit rule registry (includes/fn/audit_rules.php) is whole.
 *
 * The visual editor, the SEO structure rules and the accessibility report
 * judge the same concepts under different codes; the registry pairs them. A
 * rule added to one engine without a registry row has no severity on the
 * other side and no row in the editor's audit panel, and a row whose check
 * was removed reports a concept nobody looks for. Three contracts:
 *   1. Every editor rule id in style_designer.src.js (validateTree() and the
 *      page-level and canvas checks) has a registry row, and a rule that
 *      carries an seo: code names the code of that row.
 *   2. Every SEO code in the registry is emitted by seo_structure.php
 *      ($add('code'), $findings['code'] or 'code' => 'code').
 *   3. Every accessibility code has its pg_a11y_rule_<name>() and every
 *      editor id named in the registry is still in style_designer.src.js.
 *
 * Static: it reads the files, needs no database and no site.
 *
 * Usage:  php tools/check_audit_rules.php [directory]   (default: pinegrap/)
 * Exit:   0 when the registry is whole, 1 otherwise.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Forbidden');
}

/**
 * The editor's rule ids, each with the seo: code written beside it ('' when
 * none), read from the part of style_designer.src.js that holds the checks:
 * from `var _validationRules = [` to the canvas badges that draw them.
 *
 * @param string $js
 * @return array<string,string>
 */
function pg_check_audit_editor_rules($js)
{
	$start = strpos($js, 'var _validationRules = [');
	if ($start === false) {
		return array();
	}
	$end = strpos($js, '// ── Canvas badges', $start);
	$part = ($end === false) ? substr($js, $start) : substr($js, $start, $end - $start);

	$rules = array();

	foreach (preg_split('/\R/', $part) as $line) {
		if (!preg_match("/(?:\\{\\s*id|\\brule):\\s*'([a-z0-9_]+)'/", $line, $m)) {
			continue;
		}
		$seo = preg_match("/\\bseo:\\s*'([a-z0-9_]+)'/", $line, $s) ? $s[1] : '';
		if (!isset($rules[$m[1]]) || $seo !== '') {
			$rules[$m[1]] = $seo;
		}
	}

	return $rules;
}

/**
 * What is missing, one sentence each; empty when the registry is whole.
 *
 * @param array    $registry      pg_audit_rules()
 * @param string   $js            style_designer.src.js
 * @param string   $seo_source    seo_structure.php
 * @param string[] $a11y_functions the pg_a11y_rule_*() functions that exist
 * @return string[]
 */
function pg_check_audit_rules_problems($registry, $js, $seo_source, $a11y_functions)
{
	$problems = array();
	$editor = pg_check_audit_editor_rules($js);

	foreach ($editor as $id => $seo) {
		$seo_codes = array();
		foreach ($registry as $rule) {
			if (in_array($id, $rule['editor'], true) && $rule['seo'] !== '') {
				$seo_codes[] = $rule['seo'];
			}
		}
		$known = false;
		foreach ($registry as $rule) {
			if (in_array($id, $rule['editor'], true)) {
				$known = true;
			}
		}
		if (!$known) {
			$problems[] = 'editor rule ' . $id . ' has no registry entry';
		} elseif ($seo !== '' && !in_array($seo, $seo_codes, true)) {
			$problems[] = 'editor rule ' . $id . ' says seo: ' . $seo . ', its registry entry says '
				. ($seo_codes ? implode(', ', $seo_codes) : 'none');
		}
	}

	foreach ($registry as $concept => $rule) {
		if ($rule['seo'] !== '') {
			$code = preg_quote($rule['seo'], '/');
			if (!preg_match('/(\$add\(|\$findings\[|\'code\'\s*=>\s*)\'' . $code . '\'/', $seo_source)) {
				$problems[] = 'SEO code ' . $rule['seo'] . ' (' . $concept . ') has no checker in seo_structure.php';
			}
		}
		if ($rule['a11y'] !== '') {
			$function = 'pg_a11y_rule_' . substr($rule['a11y'], 5);
			if (strpos($rule['a11y'], 'a11y_') !== 0 || !in_array($function, $a11y_functions, true)) {
				$problems[] = 'accessibility code ' . $rule['a11y'] . ' (' . $concept . ') has no ' . $function . '()';
			}
		}
		foreach ($rule['editor'] as $id) {
			if (!isset($editor[$id])) {
				$problems[] = 'editor rule ' . $id . ' (' . $concept . ') is not in style_designer.src.js';
			}
		}
	}

	return array_values(array_unique($problems));
}

/**
 * Reads the product tree under $root and checks the registry against it.
 *
 * @param string $root the pinegrap/ directory
 * @return string[]
 */
function pg_check_audit_rules_run($root)
{
	if (!defined('PG_FUNCTIONS_DIR')) {
		define('PG_FUNCTIONS_DIR', $root);
	}
	require_once($root . '/includes/fn/audit_rules.php');

	$a11y = (string) file_get_contents($root . '/includes/fn/a11y.php');
	preg_match_all('/function\s+(pg_a11y_rule_[a-z0-9_]+)\s*\(/', $a11y, $m);

	return pg_check_audit_rules_problems(
		pg_audit_rules(),
		(string) file_get_contents($root . '/assets/js/style_designer.src.js'),
		(string) file_get_contents($root . '/seo_structure.php'),
		$m[1]
	);
}

if (defined('PG_TEST_RUNNER')) {
	return;
}

$root = isset($argv[1]) ? rtrim($argv[1], '/\\') : 'pinegrap';

if (!is_file($root . '/includes/fn/audit_rules.php')) {
	fwrite(STDERR, "No audit rule registry under " . $root . "\n");
	exit(1);
}

$problems = pg_check_audit_rules_run($root);

foreach ($problems as $problem) {
	echo $problem, "\n";
}

if ($problems) {
	echo count($problems), " problem(s) in the audit rule registry\n";
	exit(1);
}

echo "Audit rule registry: every editor rule and code has its entry and checker\n";
exit(0);
