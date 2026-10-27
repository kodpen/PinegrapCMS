<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for validate_password_strength() in includes/fn/auth.php and its
 * panel copy assets/js/pg_password_strength.js, run on the same cases: the
 * PHP function here, the script under Node (skipped only when there is no
 * Node). Also the meter's markup (pg_password_strength_meter()) and the score
 * the script gives Bootstrap's Strength when "Require Strong Password" is on.
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
		. '  rules: win.pgPasswordStrength.rules,' . "\n"
		. '  level: cases.map(function (p) { var s = win.pgPasswordStrength; return s.level(s.score(p), true); }),' . "\n"
		. '  advisory: ["", "abc", "abcdefgh", "Abcdefgh1!", "Abcdefgh12!@xyzw"].map(function (p) { var s = win.pgPasswordStrength; return s.level(s.advisoryScore(p), false); }),' . "\n"
		. '  scores: ["abc", "Abc", "Abc1", "Abc12", "Abc12!", "Abcdefg12!"].map(function (p) { return win.pgPasswordStrength.score(p); }),' . "\n"
		. '  thresholds: win.pgPasswordStrength.thresholds' . "\n"
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

// With "Require Strong Password" on, the meter reads strong exactly when the
// server accepts the password, and fills one segment per rule met.
function test_password_strength_js_meter_score()
{
	$r = password_strength_test_node();

	if ($r === null) {
		return;
	}

	if (isset($r['error'])) {
		pg_assert_same('', $r['error'], 'pg_password_strength.js under Node');
		return;
	}

	pg_assert_same(array(2, 3, 4), $r['thresholds'], 'thresholds: weak up to one rule, then one level per rule');

	$i = 0;

	foreach (password_strength_test_cases() as $password => $strong) {
		$level = $r['level'][$i];
		pg_assert_same($strong, $level === 'strong', 'meter strong only when the server accepts: ' . $password);
		pg_assert_same((string) $password === '', $level === null, 'no level only for no password: ' . $password);
		$i++;
	}

	pg_assert_same(array(1, 2, 2, 3, 4, 5), $r['scores'], 'one point for typing, one per rule met');
	// Bootstrap's default scoring: 1, 2, 5 and 8 points.
	pg_assert_same(array(null, 'weak', 'weak', 'good', 'strong'), $r['advisory'], 'advisory levels with the setting off');
}

function test_password_strength_meter_markup()
{
	$html = pg_password_strength_meter('new_password', true);

	pg_assert_same(4, substr_count($html, 'class="strength-segment"'), 'four segments');
	pg_assert_true(strpos($html, 'class="strength"') !== false, 'Bootstrap 6 Strength root');
	pg_assert_true(strpos($html, 'class="strength-text') !== false, 'message line');
	pg_assert_true(strpos($html, 'data-pg-strength-input="#new_password"') !== false, 'names its input');
	pg_assert_true(strpos($html, 'data-pg-strength-strict="1"') !== false, 'strict when the setting is on');
	pg_assert_true(strpos($html, 'data-bs-strength') === false, 'no data API: Bootstrap would score with its defaults first');

	$advisory = pg_password_strength_meter('admin_password', false);
	pg_assert_true(strpos($advisory, 'data-pg-strength-strict="0"') !== false, 'advisory when the setting is off');

	$messages = array();
	preg_match('/data-pg-strength-messages="([^"]*)"/', $advisory, $messages);
	$decoded = isset($messages[1]) ? json_decode(html_entity_decode($messages[1], ENT_QUOTES, 'UTF-8'), true) : null;
	pg_assert_same(array('weak', 'fair', 'good', 'strong'), is_array($decoded) ? array_keys($decoded) : null, 'a message for each level');

	$odd = pg_password_strength_meter('a"b', false);
	pg_assert_true(strpos($odd, 'a"b') === false, 'input id escaped');
}
