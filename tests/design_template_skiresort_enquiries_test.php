<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Ski Resort design template's stay and group enquiries
 * (includes/design_templates/pinegrap-skiresort/enquiries.php): the pages
 * and widgets of the plan, each widget on its page, the enquiry form taking
 * the stay and the kind of group from the address in the lists' own words
 * with the status office-only and the arrival not before today, every link
 * that opens it using answers of those lists, the enquiry's page, lists and
 * e-mails reading the form, the template's ids and classes, no fixed dates
 * and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_skiresort_lessons_test.php');

// parts.php, cards.php, the public pages, mail.php, lessons.php and
// enquiries.php in one scope, as the template file reads them. Read once
// per run.
function _test_ske_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public = include $dir . '/pages_public.php';
	include $dir . '/mail.php';
	$lessons_part = include $dir . '/lessons.php';
	$part = include $dir . '/enquiries.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'public' => $public, 'lessons' => $lessons_part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'lists' => array('stay' => $stay_choices, 'group' => $group_kinds),
		'enquiry_statuses' => $enquiry_statuses, 'night_choices' => $night_choices);
	return $out;
}

function test_skiresort_enquiries_pages_and_widgets()
{
	$d = _test_ske_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('group_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries', 'email_enquiry_new', 'email_enquiry_received'),
		array_keys($d['pages']));
	pg_assert_same(array('enquiry_form', 'enquiry_detail', 'enquiry_messages', 'my_enquiries_list', 'staff_enquiries_list', 'email_enquiry_admin', 'email_enquiry_client'),
		array_keys($d['widgets']));
	pg_assert_same(array(), array_values(array_intersect(array_keys($d['widgets']), array_keys($d['lessons']['widgets']))), 'no widget key of the lessons is reused');
	$known = array_flip(_test_sk_page_keys());
	$folders = array('group_enquiry' => 'public', 'enquiry_view' => 'registration', 'my_enquiries' => 'registration', 'staff_enquiries' => 'private');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		pg_assert_same($folders[$key] ?? 'private', $p['folder'], "$key: its folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		if (strpos($key, 'email_') === 0) {
			pg_assert_true(!empty($p['noindex']) && $p['search'] === false && $p['sitemap'] === false, "$key: an e-mail page is out of the search");
		}
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	$all = json_encode($d['part']);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $all, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach (_test_sk_shop_keys() as $key) pg_assert_false(strpos($all, '{{page:' . $key . '}}') !== false, "no link to $key");
	pg_assert_same('{{tab:email_reply}}', $d['pages']['enquiry_view']['comments']['email_page'], 'messages are mailed with the reply e-mail of lessons.php');
}

// A stay is an enquiry: the form opens the enquiry's page, mails both
// sides and opens an account; it starts from the address in the lists'
// own words; the status is the office's.
function test_skiresort_enquiries_form_is_a_request()
{
	$d = _test_ske_load();
	$form = $d['pages']['group_enquiry']['form'];
	pg_assert_false(isset($form['collection']), 'an enquiry, not a collection');
	pg_assert_same('{{tab:enquiry_view}}', $form['confirmation_page_id'], 'what is sent opens its page');
	pg_assert_same('{{tab:email_enquiry_new}}', $form['notify_page_id'], 'the resort is mailed');
	pg_assert_same('{{tab:email_enquiry_received}}', $form['confirm_page_id'], 'the guest is mailed');
	pg_assert_same(1, $form['auto_registration'], 'a guest gets an account');
	pg_assert_contains('^^stay^^', $form['notify_subject'], 'the subject names the stay');
	$c = _test_skl_controls($d['widgets']['enquiry_form']['tree']);
	pg_assert_same(array('stay', 'group_kind', 'arrival', 'nights', 'guests', 'extras', 'notes', 'attachment',
		'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent'), array_keys($c), 'the enquiry\'s fields');
	foreach (array('stay' => 'stay', 'group_kind' => 'group') as $field => $param) {
		pg_assert_same($param, $c[$field]['cf']['url_param'] ?? '', "$field starts from ?$param=");
		pg_assert_same($d['lists'][$param], $c[$field]['options'], "$field offers its list");
	}
	pg_assert_same('today', $c['arrival']['cf']['rules']['min_date'] ?? '', 'the arrival is not before today');
	pg_assert_same($d['night_choices'], $c['nights']['options'], 'nights offer their list');
	pg_assert_same($d['enquiry_statuses'], $c['status']['options'], 'the status offers its list');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the office\'s');
	pg_assert_same('{{folder:private}}', $c['attachment']['cf']['upload_folder_id'] ?? '', 'a list of names is kept private');
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['enquiry_form']['tree']), 'the consent links the privacy page');
	pg_assert_contains('"section":"captcha"', json_encode($d['widgets']['enquiry_form']['tree']), 'the form has a CAPTCHA');
}

// Every link that opens the enquiry (the stay cards, the group links)
// uses answers of the enquiry's lists, and both lists are reached.
function test_skiresort_enquiries_links_use_the_lists_words()
{
	$d = _test_ske_load();
	$trees = array();
	foreach ($d['public']['pages'] as $p) $trees[$p['key']] = $p['tree'];
	foreach ($d['lessons']['pages'] as $p) $trees[$p['key']] = $p['tree'];
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	$seen = array();
	foreach ($trees as $key => $tree) {
		foreach (_test_skl_nodes($tree) as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (!preg_match('/^\{\{page:group_enquiry\}\}\?(.*)$/', $href, $m)) continue;
			parse_str($m[1], $q);
			foreach ($q as $name => $value) {
				pg_assert_true(isset($d['lists'][$name]) && in_array($value, $d['lists'][$name], true), "$key: $name '$value' is an answer");
				$seen[$name] = true;
			}
		}
	}
	foreach (array('stay', 'group') as $param) pg_assert_true(isset($seen[$param]), "a link sends ?$param=");
}

// The enquiry's page reads the record (the sender's own); the lists read
// the form, the guest's own filtered to the viewer, the staff's
// searchable; the e-mails bind only the form's fields.
function test_skiresort_enquiries_view_lists_and_mails_read_the_form()
{
	$d = _test_ske_load();
	$w = $d['widgets'];
	pg_assert_same('submitter_only', $w['enquiry_detail']['config']['access_control'], 'the enquiry is the sender\'s');
	pg_assert_same('{{tab:group_enquiry}}', $w['enquiry_detail']['config']['custom_form_page_id'], 'of the Stay Enquiries form');
	pg_assert_same('comments', $w['enquiry_messages']['config']['regionType'], 'the messages are comments');
	pg_assert_true(!empty($w['my_enquiries_list']['config']['viewer_filter_submitter']), 'My Enquiries shows the guest\'s own');
	pg_assert_true(!empty($w['staff_enquiries_list']['config']['search_enabled']), 'the staff list is searchable');
	foreach (array('my_enquiries_list', 'staff_enquiries_list') as $key) {
		pg_assert_same('{{tab:enquiry_view}}', $w[$key]['config']['detail_page_id'], "$key opens the enquiry");
	}
	$fields = array_keys(_test_skl_controls($w['enquiry_form']['tree']));
	foreach ($w['staff_enquiries_list']['config']['search_fields'] as $f) pg_assert_true(in_array($f, $fields, true), "search field $f is a field of the form");
	foreach (array('enquiry_detail', 'my_enquiries_list', 'staff_enquiries_list', 'email_enquiry_admin', 'email_enquiry_client') as $key) {
		foreach (_test_skl_nodes($w[$key]['tree']) as $n) {
			$token = $n['props']['_bindings']['text'] ?? '';
			if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments'), true)) continue;
			pg_assert_true(in_array($token, $fields, true), "$key: $token is a field of the form");
		}
	}
	foreach (array('email_enquiry_admin', 'email_enquiry_client') as $key) {
		pg_assert_same('public', $w[$key]['config']['access_control'], "$key is drawn for the submission just sent");
		pg_assert_contains('{{page:enquiry_view}}?r=^^reference_code^^', json_encode($w[$key]['tree'], JSON_UNESCAPED_SLASHES), "$key links the enquiry");
	}
}

function test_skiresort_enquiries_ids_classes_and_headings()
{
	$d = _test_ske_load();
	$ids = array();
	foreach ($d['lessons']['widgets'] as $w) {
		foreach (_test_skl_nodes($w['tree']) as $n) {
			$id = (string)($n['props']['id'] ?? '');
			if ($id !== '' && strpos($id, 'comment_') !== 0) $ids[$id] = true;
		}
	}
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) $trees['widget ' . $key] = $w['tree'];
	foreach ($trees as $key => $tree) {
		foreach (_test_skl_nodes($tree) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pgl|pmz|pph|pvt|pre|pev|sk)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'skr-') === 0 || strpos($id, 'comment_') === 0, "$key: id $id is the template's");
			if (strpos($id, 'comment_') === 0) continue;
			pg_assert_false(isset($ids[$id]), "id $id once, beside the lessons' ids");
			$ids[$id] = true;
		}
	}
	foreach (array('group_enquiry', 'my_enquiries') as $key) {
		$levels = array();
		foreach (_test_skl_nodes($d['pages'][$key]['tree']) as $n) {
			if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
		}
		$set = array_values(array_unique($levels));
		sort($set);
		pg_assert_same(range(1, max($set)), $set, "$key: no heading level skipped");
	}
}

function test_skiresort_enquiries_no_fixed_dates()
{
	$json = rawurldecode(json_encode(_test_ske_load()['part'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	// The comment stars are SVG pictures: their namespace address is no date.
	$json = str_replace('http://www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

function test_skiresort_enquiries_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort/enquiries.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bstay enquir/i', $key)) pg_assert_true(mb_stripos($map[$key], 'konaklama tale', 0, 'UTF-8') !== false, "'$key' says konaklama talebi");
		if (preg_match('/\bski school\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'kayak okul', 0, 'UTF-8') !== false, "'$key' says kayak okulu");
	}
}
