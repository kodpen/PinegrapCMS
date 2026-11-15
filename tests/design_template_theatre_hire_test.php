<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Theatre design template's venue hire and e-mail builders
 * (includes/design_templates/pinegrap-theatre/hire.php and mail.php): the
 * pages and widgets, the hire enquiry form (the space from ?space= among
 * the answers the space cards send, the date not before today, the
 * office-only status, consent linking the privacy page), the enquiry's
 * page for its sender only with the messages mailed through email_reply,
 * the visitor's and the staff's lists, the e-mail pages drawn in the
 * e-mail frame, every page link names a template page, ids and classes
 * are the template's own, and every literal has its Turkish.
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

// The hire part as the template file reads it: parts.php, mail.php, then
// hire.php in the same scope. Read once per run.
function _test_thh_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/hire.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$space_titles_sent = array();
	foreach (array_keys($spaces) as $key) {
		$nodes = array();
		_test_thx_nodes($space_card($key), $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:hire}}?') !== 0) continue;
			parse_str((string)parse_url(substr($href, strlen('{{page:hire}}')), PHP_URL_QUERY), $q);
			$space_titles_sent[] = $q['space'] ?? '';
		}
	}
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'space_choices' => $space_choices,
		'space_titles' => $space_titles, 'sent' => $space_titles_sent, 'hire_kinds' => $hire_kinds, 'guest_counts' => $guest_counts,
		'statuses' => $hire_statuses, 'mail' => array($email_header_tree(), $email_footer_tree(), $mail_reference(), $mail_new_account(), $mail_comment_tree));
	return $out;
}

function test_theatre_hire_pages_and_widgets()
{
	$d = _test_thh_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('hire', 'hire_view', 'my_hire', 'staff_hire', 'privacy', 'email_hire_new', 'email_hire_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('hire_form', 'hire_detail', 'hire_messages', 'my_hire_list', 'staff_hire_list', 'email_hire_admin', 'email_hire_client', 'email_comment'),
		array_keys($d['widgets']));
	pg_assert_same('public', $d['pages']['hire']['folder'], 'anyone may send an enquiry');
	pg_assert_same('public', $d['pages']['privacy']['folder'], 'the privacy page is public');
	pg_assert_same('registration', $d['pages']['hire_view']['folder'], 'an enquiry needs an account');
	pg_assert_same('registration', $d['pages']['my_hire']['folder'], 'my enquiries need an account');
	pg_assert_same('private', $d['pages']['staff_hire']['folder'], 'only the staff see every enquiry');
	foreach (array('email_hire_new', 'email_hire_received', 'email_reply') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true(!empty($d['pages'][$key]['noindex']) && $d['pages'][$key]['sitemap'] === false, "$key is out of the search");
		pg_assert_contains('"_label":"E-mail Frame"', json_encode($d['pages'][$key]['tree']), "$key is drawn in the e-mail frame");
	}
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	// The staff bar, the footer, the FAQ and the space cards reach the hire
	// pages; the hire page holds the form at #th-hire-form.
	pg_assert_contains('"id":"th-hire-form"', json_encode($d['pages']['hire']['tree']), 'the form section is the space cards\' anchor');
}

// The form: an enquiry that opens its page and an account, mailed to the
// theatre and to the hirer.
function test_theatre_hire_form()
{
	$d = _test_thh_load();
	$form = $d['pages']['hire']['form'];
	pg_assert_same('{{tab:hire_view}}', $form['confirmation_page_id'], 'what is sent opens its page');
	pg_assert_same(1, $form['auto_registration'], 'a hirer without an account gets one');
	pg_assert_same('{{tab:email_hire_new}}', $form['notify_page_id'], 'the theatre is told');
	pg_assert_same('{{tab:email_hire_received}}', $form['confirm_page_id'], 'the hirer is answered');
	pg_assert_false(isset($form['collection']), 'requests are not a collection');
	$controls = _test_thx_controls($d['widgets']['hire_form']['tree']);
	pg_assert_same(array('space', 'kind', 'event_name', 'event_date', 'guests', 'timing', 'notes', 'first_name', 'last_name', 'email', 'phone', 'company', 'reply_by', 'status', 'consent'),
		array_values(array_filter(array_keys($controls), function ($n) { return $n !== ''; })), 'the fields of the form');
	pg_assert_same($d['space_choices'], $controls['space']['options'], 'every space and not sure');
	pg_assert_same('space', $controls['space']['cf']['url_param'] ?? '', 'the space comes from ?space=');
	pg_assert_same(count($d['space_titles']), count($d['sent']), 'a link per space card');
	foreach ($d['sent'] as $title) pg_assert_true(in_array($title, $controls['space']['options'], true), "?space=$title is an answer");
	pg_assert_same('today', $controls['event_date']['cf']['rules']['min_date'] ?? '', 'no date before today');
	pg_assert_same($d['hire_kinds'], $controls['kind']['options'], 'the kinds of event');
	pg_assert_same($d['guest_counts'], $controls['guests']['options'], 'the guest counts');
	pg_assert_same($d['statuses'], $controls['status']['options'], 'the hire states');
	pg_assert_true(!empty($controls['status']['cf']['office_use_only']), 'the status is the theatre\'s');
	$json = json_encode($d['widgets']['hire_form']['tree']);
	pg_assert_contains('"href":"{{page:privacy}}"', $json, 'consent links the privacy page');
	pg_assert_contains('"id":"th-hire-consent"', $json, 'the consent box');
}

// The enquiry's page is its sender's (and the staff's), its messages are
// mailed through email_reply; the lists, the e-mail widgets.
function test_theatre_hire_request_pages()
{
	$d = _test_thh_load();
	pg_assert_same('submitter_only', $d['widgets']['hire_detail']['config']['access_control'], 'only the sender reads the enquiry');
	pg_assert_same('{{tab:hire}}', $d['widgets']['hire_detail']['config']['custom_form_page_id'], 'the Hire Enquiries form');
	$c = $d['pages']['hire_view']['comments'];
	pg_assert_true($c['allow_new'] && $c['login'], 'signed-in senders write');
	pg_assert_same('{{tab:email_reply}}', $c['email_page'], 'answers are mailed');
	pg_assert_same('{{site_email}}', $c['notify_email'], 'the theatre is told');
	$mine = $d['widgets']['my_hire_list']['config'];
	pg_assert_true(!empty($mine['viewer_filter']) && !empty($mine['viewer_filter_submitter']), 'my list shows my enquiries');
	$staff = $d['widgets']['staff_hire_list']['config'];
	pg_assert_true(!empty($staff['search_enabled']), 'the staff search');
	pg_assert_false(isset($staff['viewer_filter']), 'the staff see every enquiry');
	foreach (array('my_hire_list', 'staff_hire_list') as $key) {
		pg_assert_same('{{tab:hire_view}}', $d['widgets'][$key]['config']['detail_page_id'], "$key opens the enquiry");
	}
	foreach (array('email_hire_admin', 'email_hire_client') as $key) {
		pg_assert_same('public', $d['widgets'][$key]['config']['access_control'], "$key is drawn for the submission just sent");
		pg_assert_contains('{{page:hire_view}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree']), "$key opens the enquiry");
	}
	pg_assert_same('comments', $d['widgets']['email_comment']['config']['regionType'], 'the reply e-mail draws its comment');
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps its System region');
}

function test_theatre_hire_links_ids_and_classes()
{
	$d = _test_thh_load();
	$known = array_flip(_test_th_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$trees[] = $d['mail'];
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('tickets', 'friends', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	pg_assert_false(strpos($json, '#9f1239') !== false, 'no other template\'s e-mail colour');
	pg_assert_contains('#b91c1c', $json, 'the e-mails carry the crimson');
	$nodes = array();
	foreach ($trees as $t) _test_thx_nodes($t, $nodes);
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

// Every literal has its Turkish; hire reads "kiralama".
function test_theatre_hire_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('hire.php', 'mail.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
			if (!isset($map[$key]) || strpos($key, '-') !== false) continue;
			$tr = $map[$key];
			if (preg_match('/^Hire\b|\bHire (a Space|Enquir)/', $key)) pg_assert_true(mb_stripos($tr, 'kirala', 0, 'UTF-8') !== false, "'$key' says kiralama");
			if (preg_match('/\bPerformances?\b/', $key)) pg_assert_true(mb_stripos($tr, 'temsil', 0, 'UTF-8') !== false, "'$key' says temsil");
		}
	}
}
