<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Charity design template's volunteers
 * (includes/design_templates/pinegrap-charity/volunteer.php, with forms.php
 * and mail.php): the file carries the volunteer, record, list, staff,
 * privacy and e-mail pages, each widget on one of them; every page link
 * names a page of the finished template and none needs the shop; the offer
 * opens its own page, seen only by its sender and the staff; the cause list
 * answers with the causes' titles and the Status is the staff's; every
 * literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');
// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_charity_test.php');

// The volunteer file as the template file reads it: after parts.php,
// mail.php and forms.php in one scope.
function _test_charity_volunteer()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/forms.php';
	$out = include $dir . '/volunteer.php';
	$out['causes'] = $causes;
	return $out;
}

function test_charity_volunteer_pages_and_widgets()
{
	$v = _test_charity_volunteer();
	$keys = array();
	foreach ($v['pages'] as $p) $keys[] = $p['key'];
	pg_assert_same(array('volunteer', 'volunteer_view', 'my_volunteering', 'staff_volunteers', 'privacy',
		'email_volunteer_new', 'email_volunteer_received', 'email_reply'), $keys);
	foreach ($v['widgets'] as $name => $w) {
		pg_assert_true(in_array($w['page'], $keys, true), "widget $name sits on a page of the file");
	}
	$known = array_flip(_test_charity_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($v), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_charity_shop_keys() as $key) {
		pg_assert_false(strpos(json_encode($v), '{{page:' . $key . '}}') !== false, "volunteering does not link to $key");
	}
	$by_key = array();
	foreach ($v['pages'] as $p) $by_key[$p['key']] = $p;
	pg_assert_same('{{tab:volunteer_view}}', $by_key['volunteer']['form']['confirmation_page_id']);
	pg_assert_same('private', $by_key['staff_volunteers']['folder'], 'the staff list is the staff\'s');
	pg_assert_same('submitter_only', $v['widgets']['volunteer_detail']['config']['access_control']);
}

// The cause pick list answers with the causes' titles, so a cause page's
// ?cause= link chooses it; the Status is the staff's.
function test_charity_volunteer_form_fields()
{
	$v = _test_charity_volunteer();
	$nodes = array();
	_test_charity_nodes($v['widgets']['volunteer_form']['tree'], $nodes);
	$cause = $status = null;
	foreach ($nodes as $n) {
		if (isset($n['props']['id']) && $n['props']['id'] === 'ch-vo-cause') $cause = $n;
		if (isset($n['props']['id']) && $n['props']['id'] === 'ch-vo-status') $status = $n;
	}
	pg_assert_true($cause !== null && $status !== null, 'cause and status fields');
	pg_assert_same('cause', $cause['props']['_cf']['url_param']);
	$values = array();
	foreach ($cause['children'] as $o) $values[] = $o['props']['text'];
	foreach ($v['causes'] as $c) pg_assert_true(in_array($c['title'], $values, true), "the cause list has {$c['title']}");
	pg_assert_true(!empty($status['props']['_cf']['office_use_only']), 'the status is office use only');
}

function test_charity_volunteer_turkish()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity/volunteer.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (preg_match('/\bvolunteer(s|ing)?\b/i', $key) && isset($map[$key])) {
			pg_assert_true(mb_stripos($map[$key], 'gönüllü', 0, 'UTF-8') !== false || mb_stripos($map[$key], 'gonullu', 0, 'UTF-8') !== false, "'$key' says gönüllü");
		}
	}
}
