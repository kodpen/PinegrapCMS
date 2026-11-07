<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Theatre design template's learning pages
 * (includes/design_templates/pinegrap-theatre/learning.php): the pages
 * and widgets, the learning page placing every youth theatre group and
 * school workshop card, the school group and youth theatre place forms
 * (the workshop from ?workshop= and the group from ?group= among the
 * answers the cards send, the date not before today, the office-only
 * status, consent linking the privacy page), each request's page for its
 * sender only with the messages mailed through email_reply, the visitor's
 * and the staff's lists, the e-mail pages, every page link names a
 * template page, ids and classes are the template's own, and every
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

// The page keys (_test_th_page_keys()) and the node helpers
// (_test_thx_nodes(), _test_thx_controls()).
require_once __DIR__ . '/design_template_theatre_productions_test.php';

// The values a card list sends to a page: ?$param= of every link to it.
function _test_thl_sent($cards, $page_key, $param)
{
	$out = array();
	foreach ($cards as $card) {
		$nodes = array();
		_test_thx_nodes($card, $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:' . $page_key . '}}?') !== 0) continue;
			parse_str((string)parse_url(substr($href, strlen('{{page:' . $page_key . '}}')), PHP_URL_QUERY), $q);
			$out[] = $q[$param] ?? '';
		}
	}
	return $out;
}

// The learning part as the template file reads it: parts.php, mail.php,
// hire.php (the request pieces), then learning.php in the same scope.
function _test_thl_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/hire.php';
	$part = include $dir . '/learning.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$groups = array();
	foreach (array_keys($youth_groups) as $key) $groups[] = $group_card($key);
	$cards = array();
	foreach (array_keys($workshops) as $key) $cards[] = $workshop_card($key);
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'group_titles' => $group_titles, 'workshop_choices' => $school_workshop_choices, 'year_groups' => $year_groups,
		'visit_days' => $visit_day_choices, 'school_statuses' => $school_group_statuses, 'place_statuses' => $place_statuses,
		'groups_sent' => _test_thl_sent($groups, 'youth_place', 'group'), 'workshops_sent' => _test_thl_sent($cards, 'school_group', 'workshop'),
		'group_count' => count($youth_groups), 'workshop_count' => count($workshops));
	return $out;
}

// The two requests: form page, view, the visitor's and the staff's list,
// the two e-mails.
function _test_thl_requests()
{
	return array(
		'school_group' => array('view' => 'school_group_view', 'mine' => 'my_school_groups', 'staff' => 'staff_school_groups',
			'new' => 'email_school_group_new', 'received' => 'email_school_group_received'),
		'youth_place'  => array('view' => 'youth_place_view', 'mine' => 'my_youth_places', 'staff' => 'staff_youth_places',
			'new' => 'email_youth_place_new', 'received' => 'email_youth_place_received'),
	);
}

function test_theatre_learning_pages_and_widgets()
{
	$d = _test_thl_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('learning', 'school_group', 'school_group_view', 'my_school_groups', 'staff_school_groups',
		'youth_place', 'youth_place_view', 'my_youth_places', 'staff_youth_places',
		'email_school_group_new', 'email_school_group_received', 'email_youth_place_new', 'email_youth_place_received'), array_keys($d['pages']));
	pg_assert_same(array('school_group_form', 'youth_place_form', 'school_group_detail', 'school_group_messages', 'my_school_groups_list',
		'staff_school_groups_list', 'youth_place_detail', 'youth_place_messages', 'my_youth_places_list', 'staff_youth_places_list',
		'email_school_group_admin', 'email_school_group_client', 'email_youth_place_admin', 'email_youth_place_client'), array_keys($d['widgets']));
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	$json = json_encode($d['pages']['learning']['tree']);
	pg_assert_same($d['group_count'], substr_count($json, '"_label":"Group Card"'), 'a card per youth theatre group');
	pg_assert_same($d['workshop_count'], substr_count($json, '"_label":"Workshop Card"'), 'a card per workshop');
	foreach (array('th-youth-theatre', 'th-school-workshops') as $id) pg_assert_contains('"id":"' . $id . '"', $json, "the learning page holds #$id");
	foreach (_test_thl_requests() as $form => $r) {
		pg_assert_same('public', $d['pages'][$form]['folder'], "$form: anyone may ask");
		pg_assert_same('registration', $d['pages'][$r['view']]['folder'], "$form: its page needs an account");
		pg_assert_same('registration', $d['pages'][$r['mine']]['folder'], "$form: the visitor's list needs an account");
		pg_assert_same('private', $d['pages'][$r['staff']]['folder'], "$form: only the staff see every request");
		foreach (array($r['new'], $r['received']) as $key) {
			pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
			pg_assert_contains('"_label":"E-mail Frame"', json_encode($d['pages'][$key]['tree']), "$key is drawn in the e-mail frame");
		}
	}
}

// Each form opens its page and an account and mails the theatre and the
// sender; the card values are answers; the status is the team's; consent
// links the privacy page.
function test_theatre_learning_forms()
{
	$d = _test_thl_load();
	foreach (_test_thl_requests() as $form => $r) {
		$f = $d['pages'][$form]['form'];
		pg_assert_same('{{tab:' . $r['view'] . '}}', $f['confirmation_page_id'], "$form: what is sent opens its page");
		pg_assert_same(1, $f['auto_registration'], "$form: a sender without an account gets one");
		pg_assert_same('{{tab:' . $r['new'] . '}}', $f['notify_page_id'], "$form: the theatre is told");
		pg_assert_same('{{tab:' . $r['received'] . '}}', $f['confirm_page_id'], "$form: the sender is answered");
		$json = json_encode($d['widgets'][$form . '_form']['tree']);
		pg_assert_contains('"href":"{{page:privacy}}"', $json, "$form: consent links the privacy page");
	}
	$c = _test_thx_controls($d['widgets']['school_group_form']['tree']);
	pg_assert_same(array('workshop', 'school', 'year_group', 'pupils', 'adults', 'preferred_date', 'visit_time', 'notes',
		'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent'),
		array_values(array_filter(array_keys($c), function ($n) { return $n !== ''; })), 'the school group fields');
	pg_assert_same('workshop', $c['workshop']['cf']['url_param'] ?? '', 'the workshop comes from ?workshop=');
	pg_assert_same($d['workshop_choices'], $c['workshop']['options'], 'every workshop and not sure');
	pg_assert_same($d['workshop_count'], count($d['workshops_sent']), 'a link per workshop card');
	foreach ($d['workshops_sent'] as $t) pg_assert_true(in_array($t, $c['workshop']['options'], true), "?workshop=$t is an answer");
	pg_assert_same('today', $c['preferred_date']['cf']['rules']['min_date'] ?? '', 'no date before today');
	pg_assert_same($d['year_groups'], $c['year_group']['options'], 'the year groups');
	pg_assert_same($d['visit_days'], $c['visit_time']['options'], 'the times of day');
	pg_assert_same($d['school_statuses'], $c['status']['options'], 'the school group states');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the school group status is the team\'s');
	$c = _test_thx_controls($d['widgets']['youth_place_form']['tree']);
	pg_assert_same(array('group', 'participant', 'age', 'notes', 'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent'),
		array_values(array_filter(array_keys($c), function ($n) { return $n !== ''; })), 'the youth place fields');
	pg_assert_same('group', $c['group']['cf']['url_param'] ?? '', 'the group comes from ?group=');
	pg_assert_same($d['group_titles'], $c['group']['options'], 'every group');
	pg_assert_same($d['group_count'], count($d['groups_sent']), 'a link per group card');
	foreach ($d['groups_sent'] as $t) pg_assert_true(in_array($t, $c['group']['options'], true), "?group=$t is an answer");
	pg_assert_same('number', $c['age']['cf']['kind'] ?? '', 'the age is a number');
	pg_assert_same($d['place_statuses'], $c['status']['options'], 'the place states');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the place status is the team\'s');
}

// Each request's page is its sender's, its messages are mailed; the lists
// and the e-mail widgets.
function test_theatre_learning_request_pages()
{
	$d = _test_thl_load();
	foreach (_test_thl_requests() as $form => $r) {
		$detail = $d['widgets'][$form . '_detail']['config'];
		pg_assert_same('submitter_only', $detail['access_control'], "$form: only the sender reads it");
		pg_assert_same('{{tab:' . $form . '}}', $detail['custom_form_page_id'], "$form: its form");
		$c = $d['pages'][$r['view']]['comments'];
		pg_assert_same('{{tab:email_reply}}', $c['email_page'], "$form: answers are mailed");
		pg_assert_same('{{site_email}}', $c['notify_email'], "$form: the theatre is told");
		$mine = $d['widgets'][$r['mine'] . '_list']['config'];
		pg_assert_true(!empty($mine['viewer_filter_submitter']), "$form: my list shows my requests");
		$staff = $d['widgets'][$r['staff'] . '_list']['config'];
		pg_assert_true(!empty($staff['search_enabled']) && !isset($staff['viewer_filter']), "$form: the staff search every request");
		foreach (array('_admin', '_client') as $end) {
			$w = $d['widgets']['email_' . $form . $end];
			pg_assert_same('public', $w['config']['access_control'], "email_$form$end is drawn for the submission just sent");
			pg_assert_contains('{{page:' . $r['view'] . '}}?r=^^reference_code^^', json_encode($w['tree']), "email_$form$end opens the request");
		}
	}
}

function test_theatre_learning_links_ids_and_classes()
{
	$d = _test_thl_load();
	$known = array_flip(_test_th_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('tickets', 'friends', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_thx_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_thx_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph|pcn|pmu|psc|psh|pmz|pai)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'th-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; youth theatre reads "gençlik tiyatrosu".
function test_theatre_learning_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre/learning.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || strpos($key, '-') !== false) continue;
		$tr = $map[$key];
		if (preg_match('/\bYouth Theatre\b/i', $key)) pg_assert_true(mb_stripos($tr, 'gençlik tiyatro', 0, 'UTF-8') !== false, "'$key' says gençlik tiyatrosu");
		if (preg_match('/\bPerformances?\b/', $key)) pg_assert_true(mb_stripos($tr, 'temsil', 0, 'UTF-8') !== false, "'$key' says temsil");
	}
}
