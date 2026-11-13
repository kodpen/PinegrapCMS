<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer canvas's sample account (_sdAccountSample() in
 * style_designer.js, run under Node): the member's values agree with each
 * other and with the sample order, and the site's words come from the
 * editor's site settings.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs the sample account's part of the editor under Node with `$words` as
// sdDesign.siteWords; null when Node is not there.
function _canvas_account_sample_run($words, $script)
{
	if (!function_exists('exec')) return null;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) return null;
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$part = function ($start) use ($js) {
		$a = strpos($js, "\n    function " . $start . '(');
		pg_assert_true($a !== false, $start . ' is found');
		$b = strpos($js, "\n    }\n", $a) + 6;
		return substr($js, $a, $b - $a);
	};
	$a = strpos($js, "\n    var _SD_ACCOUNT_SAMPLE_REGIONS = {");
	pg_assert_true($a !== false, '_SD_ACCOUNT_SAMPLE_REGIONS is found');
	$b = strpos($js, "\n    function _sdAccountSample(", $a);
	$b = strpos($js, "\n    }\n", $b) + 6;
	$stubs = "function _sdT(s, v) { v = v || []; return String(s).replace(/\\{var(?::(\\d+))?\\}/g, function (m, n) { return v[(n || 1) - 1]; }); }\n"
		. "function _sdMoney(n) { return String(n); }\nfunction _sdDateSample() { return 'DAY'; }\n"
		. 'var sdDesign = { siteWords: ' . json_encode($words) . " };\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgacct');
	if ($file === false) return null;
	file_put_contents($file, $stubs . $part('_sdOrderAddress') . $part('_sdOrderAddressLine') . substr($js, $a, $b - $a) . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	return json_decode($printed, true);
}

// One person: the names, initials and user name agree; the recipient is the
// order's shipping address; the statement's open total is the order's.
function test_canvas_account_sample_agrees()
{
	$got = _canvas_account_sample_run(array(), "var p = _sdAccountSample();\n"
		. "console.log(JSON.stringify([p.__user_name === p.full_name && p.__display_name === p.full_name,\n"
		. "  p.full_name === p.first_name + ' ' + p.last_name && p.__user_first_name === p.first_name,\n"
		. "  p.__user_initials === (p.first_name.charAt(0) + p.last_name.charAt(0)),\n"
		. "  p.__username === p.username && p.__email === p.__user_email && p.__email === p.email_address,\n"
		. "  p.__recipient_full_name === p.__recipient_first_name + ' ' + p.__recipient_last_name,\n"
		. "  p.__recipient_address === [p.__recipient_address_1, p.__recipient_address_2, p.__recipient_zip + ' ' + p.__recipient_city, p.__recipient_state, p.__recipient_country].join(', '),\n"
		. "  p.__balance === p.__invoice_open && p.__overdue_total === p.__invoice_open && p.__invoice_total === '1183.9',\n"
		. "  p.__invoice_status === p.__invoice_overdue_days + ' days overdue',\n"
		. "  p.__site_name, p.__member_id_label]));\n");
	if ($got === null) return;
	pg_assert_same(array(true, true, true, true, true, true, true, true, 'Sample Store', 'Member ID'), $got);
}

// The site's own name and labels win over the fallbacks; no value is empty.
function test_canvas_account_sample_site_words()
{
	$got = _canvas_account_sample_run(
		array('site_name' => 'Kodpen Shop', 'opt_in_label' => 'Keep me posted', 'member_id_label' => 'Card number'),
		"var p = _sdAccountSample();\n"
		. "console.log(JSON.stringify([p.__site_name, p.__opt_in_label, p.__member_id_label,\n"
		. "  Object.keys(p).filter(function (k) { return p[k] === '' || p[k] == null; })]));\n");
	if ($got === null) return;
	pg_assert_same(array('Kodpen Shop', 'Keep me posted', 'Card number', array()), $got);
}
