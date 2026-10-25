<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for validate_password_strength() in includes/fn/auth.php and its
 * panel copy assets/js/pg_password_strength.js, run on the same cases: the
 * PHP function here, the script under Node (skipped only when there is no
 * Node).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Password => whether it is strong. Each weak one misses one rule.
function password_strength_test_cases()
{
	return array(
		'' => false,
		'Abcdefg12!' => true,
		'Abcdef12!' => false,
		'abcdefgh12!' => false,
		'ABCDEFGH12!' => true,
		'Abcdefghi1!' => false,
		'Abcdefghi12' => false,
		'Abcdefgh 12' => true,
		'1234567890A!' => true,
		// Turkish letters are not capitals, but count as non-alphanumeric.
		'şifreşifre12!' => false,
		'Sifreşifre12' => true,
		'Abcdefgh1ş2' => true,
		// Characters, not bytes: 9 characters in 13 bytes.
		'Ab12!çççç' => false,
		// An emoji is one character (two UTF-16 units in JavaScript).
		"Abcdef12\xF0\x9F\x98\x80" => false,
		"Abcdefg12\xF0\x9F\x98\x80" => true,
		// Digits outside ASCII are not numbers.
		"Abcdefgh1\xD9\xA2!" => false,
	);
}

function test_password_strength_php_cases()
{
	foreach (password_strength_test_cases() as $password => $strong) {
		pg_assert_same($strong, validate_password_strength((string) $password), 'PHP: ' . $password);
	}
}

// Runs the script under Node on the cases; null when there is no Node,
// array('error' => output) when the run fails or prints no JSON.
function password_strength_test_node()
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgpw');

	if ($file === false) {
		return null;
	}

	$cases = array();

	foreach (password_strength_test_cases() as $password => $strong) {
		$cases[] = (string) $password;
	}

	$script = 'var source = require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_password_strength.js') . ', "utf8");' . "\n"
		. 'var win = {};' . "\n"
		. 'new Function("window", source)(win);' . "\n"
		. 'var cases = ' . json_encode($cases) . ';' . "\n"
		. 'process.stdout.write(JSON.stringify({' . "\n"
		. '  strong: cases.map(function (p) { return win.pgPasswordStrength.isStrong(p); }),' . "\n"
		. '  check: win.pgPasswordStrength.check("abc1"),' . "\n"
		. '  empty: [win.pgPasswordStrength.isStrong(null), win.pgPasswordStrength.isStrong(undefined)],' . "\n"
		. '  rules: win.pgPasswordStrength.rules' . "\n"
		. '}));' . "\n";
	file_put_contents($file, $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$text = implode("\n", $out);
	$json = json_decode($text, true);

	if ($code !== 0 || !is_array($json)) {
		return array('error' => $text);
	}

	return $json;
}

function test_password_strength_js_cases()
{
	$r = password_strength_test_node();

	if ($r === null) {
		return;
	}

	if (isset($r['error'])) {
		pg_assert_same('', $r['error'], 'pg_password_strength.js under Node');
		return;
	}

	$i = 0;

	foreach (password_strength_test_cases() as $password => $strong) {
		pg_assert_same($strong, $r['strong'][$i], 'JS: ' . $password);
		$i++;
	}

	pg_assert_same(array('length' => false, 'capital' => false, 'numbers' => false, 'symbol' => false), $r['check'], 'each rule reported');
	pg_assert_same(array(false, false), $r['empty'], 'no password is not strong');
	pg_assert_same(array('length' => 10, 'capital' => 1, 'numbers' => 2, 'symbol' => 1), $r['rules'], 'the numbers get_strong_password_requirements() lists');
}
