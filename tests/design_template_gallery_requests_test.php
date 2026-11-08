<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Art Gallery design template's requests and e-mails
 * (includes/design_templates/pinegrap-gallery/requests.php and mail.php):
 * the pages and their folders, the two forms wired to their pages and
 * e-mails, the fields with the pick lists of parts.php and the address
 * parameters the work, advisory and exhibition pages send, the widgets
 * reading only their form's fields, the private record pages, the e-mail
 * frame, the links, the ids and classes and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page and shop keys of the finished template (_test_gl_page_keys(),
// _test_gl_shop_keys()) live in the parts test.
require_once __DIR__ . '/design_template_gallery_test.php';

// parts.php, cards.php, mail.php and the requests part in one scope, as
// the template file reads them. Read once per run.
function _test_glr_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/parts.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/cards.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/mail.php';
	$part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/requests.php';
	$out = array('part' => $part, 'gallery' => $gallery, 'work_choices' => $work_choices, 'enquiry_choices' => $enquiry_choices,
		'exhibition_choices' => $exhibition_choices, 'time_choices' => $time_choices, 'party_choices' => $party_choices,
		'contact_choices' => $contact_choices, 'enquiry_statuses' => $enquiry_statuses, 'viewing_statuses' => $viewing_statuses,
		'email_header' => $email_header_tree(), 'email_footer' => $email_footer_tree());
	return $out;
}

// Every node of a tree, depth first.
function _test_glr_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_glr_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_glr_pages()
{
	$pages = array();
	foreach (_test_glr_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The fields of a form widget by name: its tag, _cf settings and options.
function _test_glr_form_fields($widget_key)
{
	$nodes = array();
	_test_glr_nodes(_test_glr_load()['part']['widgets'][$widget_key]['tree'], $nodes);
	$fields = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		foreach ($n['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
		}
		if ($name === '' || isset($fields[$name])) continue;
		$options = array();
		foreach ($n['children'] as $o) {
			foreach ($o['props']['_attrs'] ?? array() as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		$fields[$name] = array('tag' => $tag, 'cf' => $n['props']['_cf'] ?? array(), 'options' => $options);
	}
	return $fields;
}

function test_gallery_requests_pages_and_folders()
{
	$part = _test_glr_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	$pages = _test_glr_pages();
	$want = array('enquire' => 'public', 'enquiry_view' => 'registration', 'my_enquiries' => 'registration',
		'staff_enquiries' => 'private', 'viewing' => 'public', 'viewing_view' => 'registration', 'my_viewings' => 'registration',
		'staff_viewings' => 'private', 'privacy' => 'public', 'email_enquiry_new' => 'private',
		'email_enquiry_received' => 'private', 'email_viewing_new' => 'private', 'email_viewing_received' => 'private',
		'email_reply' => 'private');
	$keys = array_keys($pages);
	sort($keys);
	$expected = array_keys($want);
	sort($expected);
	pg_assert_same($expected, $keys, 'the pages of the step');
	$all = array_flip(_test_gl_page_keys());
	$names = array();
	foreach ($want as $key => $folder) {
		pg_assert_true(isset($all[$key]), "$key is a page of the template");
		pg_assert_same($folder, $pages[$key]['folder'], "$key folder");
		pg_assert_false(isset($pages[$key]['requires']), "$key stands without the shop");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $pages[$key]['name']), "$key: the page name is an address");
		pg_assert_false(isset($names[$pages[$key]['name']]), "$key: no page name twice");
		$names[$pages[$key]['name']] = true;
		if (isset($pages[$key]['meta_description'])) {
			pg_assert_true(mb_strlen($pages[$key]['meta_description'], 'UTF-8') <= 160, "$key: meta description fits");
		}
		if ($folder !== 'public') pg_assert_false($pages[$key]['search'] ?? true, "$key: not in the search");
	}
	pg_assert_same(lang('privacy-policy'), $pages['privacy']['name'], 'the privacy page has its shared name');
	foreach (array('enquiry_view', 'viewing_view') as $key) {
		pg_assert_same('{{tab:email_reply}}', $pages[$key]['comments']['email_page'], "$key: answers mailed with the reply e-mail");
		pg_assert_true($pages[$key]['comments']['login'], "$key: messages need an account");
	}
}

// The forms: what is sent opens its own page and mails the gallery and the
// sender through the template's e-mail pages.
function test_gallery_requests_forms_are_wired()
{
	$pages = _test_glr_pages();
	foreach (array('enquire' => array('enquiry_view', 'email_enquiry_new', 'email_enquiry_received', 'enquiry_form'),
		'viewing' => array('viewing_view', 'email_viewing_new', 'email_viewing_received', 'viewing_form')) as $key => $to) {
		$f = $pages[$key]['form'];
		pg_assert_same('{{tab:' . $to[0] . '}}', $f['confirmation_page_id'], "$key: opens its page");
		pg_assert_same('{{tab:' . $to[1] . '}}', $f['notify_page_id'], "$key: the gallery's e-mail");
		pg_assert_same('{{tab:' . $to[2] . '}}', $f['confirm_page_id'], "$key: the sender's e-mail");
		pg_assert_same(1, $f['auto_registration'], "$key: opens an account");
		pg_assert_same('{{site_email}}', $f['notify_email'], "$key: mails the gallery");
		pg_assert_same(1, substr_count(json_encode($pages[$key]['tree']), '"templateWidget":"' . $to[3] . '"'), "$key: places its form once");
		preg_match_all('/\^\^([a-z_]+)\^\^/', $f['notify_subject'], $m);
		$fields = array_keys(_test_glr_form_fields($to[3]));
		foreach ($m[1] as $t) pg_assert_true(in_array($t, $fields, true), "$key: the subject reads $t, a field");
	}
}

// The enquiry: the works and kinds of parts.php, the work from ?artwork=
// (the work page sends its title) and the kind from ?kind= (the advisory
// cards send a kind); the office-only status.
function test_gallery_requests_enquiry_form_fields()
{
	$d = _test_glr_load();
	$f = _test_glr_form_fields('enquiry_form');
	pg_assert_same(array('artwork', 'kind', 'message', 'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent'),
		array_keys($f), 'the form fields');
	pg_assert_same($d['work_choices'], $f['artwork']['options'], 'the works of parts.php');
	pg_assert_same($d['enquiry_choices'], $f['kind']['options'], 'the kinds of parts.php');
	pg_assert_same('artwork', $f['artwork']['cf']['url_param'] ?? '', 'work from ?artwork=');
	pg_assert_same('kind', $f['kind']['cf']['url_param'] ?? '', 'kind from ?kind=');
	pg_assert_true($f['status']['cf']['office_use_only'] ?? false, 'status is the gallery\'s');
	pg_assert_same($d['enquiry_statuses'], $f['status']['options'], 'the statuses of parts.php');
	foreach (array('first_name', 'last_name', 'email') as $n) pg_assert_true(isset($f[$n]['cf']['contact_field']), "$n fills the contact");
}

// The private viewing: the exhibitions of parts.php from ?exhibition= (the
// exhibition page sends its title), a day not before today, the times and
// party sizes of parts.php.
function test_gallery_requests_viewing_form_fields()
{
	$d = _test_glr_load();
	$f = _test_glr_form_fields('viewing_form');
	pg_assert_same(array('exhibition', 'viewing_date', 'party', 'time_of_day', 'notes', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent'),
		array_keys($f), 'the form fields');
	pg_assert_same($d['exhibition_choices'], $f['exhibition']['options'], 'the exhibitions of parts.php');
	pg_assert_same('exhibition', $f['exhibition']['cf']['url_param'] ?? '', 'exhibition from ?exhibition=');
	pg_assert_same('today', $f['viewing_date']['cf']['rules']['min_date'] ?? '', 'no day in the past');
	pg_assert_same($d['party_choices'], $f['party']['options'], 'the party sizes of parts.php');
	pg_assert_true($f['status']['cf']['office_use_only'] ?? false, 'status is the gallery\'s');
	pg_assert_same($d['viewing_statuses'], $f['status']['options'], 'the statuses of parts.php');
	$times = array();
	$nodes = array();
	_test_glr_nodes(_test_glr_load()['part']['widgets']['viewing_form']['tree'], $nodes);
	foreach ($nodes as $n) {
		$a = array();
		foreach ($n['props']['_attrs'] ?? array() as $x) $a[$x['name']] = $x['value'];
		if (($a['name'] ?? '') === 'time_of_day') $times[] = $a['value'];
	}
	pg_assert_same($d['time_choices'], $times, 'the times of parts.php');
}

// The record pages, lists and e-mails read only fields their form has, and
// name their form; an enquiry and a viewing are their sender's own.
function test_gallery_requests_widgets_read_their_form()
{
	$w = _test_glr_load()['part']['widgets'];
	$system = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found', 'reference_code',
		'number_of_comments', '__new_account_email', '__new_account_set_password_url', 'section');
	$times = array();
	foreach (_test_glr_load()['time_choices'] as $t) $times[] = $t;
	$forms = array('enquire' => array_keys(_test_glr_form_fields('enquiry_form')),
		'viewing' => array_keys(_test_glr_form_fields('viewing_form')));
	$readers = array(
		'enquire' => array('enquiry_detail', 'my_enquiries_list', 'staff_enquiries_list', 'email_enquiry_admin', 'email_enquiry_client'),
		'viewing' => array('viewing_detail', 'my_viewings_list', 'staff_viewings_list', 'email_viewing_admin', 'email_viewing_client'),
	);
	foreach ($readers as $form => $keys) {
		foreach ($keys as $key) {
			pg_assert_same('{{tab:' . $form . '}}', $w[$key]['config']['custom_form_page_id'], "$key: its form");
			$nodes = array();
			_test_glr_nodes($w[$key]['tree'], $nodes);
			$read = 0;
			foreach ($nodes as $n) {
				foreach ($n['props']['_bindings'] ?? array() as $what => $token) {
					if ($what === 'eo_visible_if' || in_array($token, $system, true)) continue;
					pg_assert_true(in_array($token, $forms[$form], true), "$key reads $token, a field of the form");
					$read++;
				}
			}
			pg_assert_true($read >= 3, "$key reads the request");
			foreach ($w[$key]['config']['search_fields'] ?? array() as $s) pg_assert_true(in_array($s, $forms[$form], true), "$key: search field $s");
		}
	}
	pg_assert_same('submitter_only', $w['enquiry_detail']['config']['access_control'], 'the visitor\'s own enquiry');
	pg_assert_same('submitter_only', $w['viewing_detail']['config']['access_control'], 'the visitor\'s own viewing');
	pg_assert_true($w['my_enquiries_list']['config']['viewer_filter_submitter'], 'My Enquiries shows the visitor\'s');
	pg_assert_true($w['my_viewings_list']['config']['viewer_filter_submitter'], 'My Private Viewings shows the visitor\'s');
	pg_assert_same('email_reply', $w['email_comment']['page'], 'the reply e-mail draws the comment');
	foreach ($w as $key => $x) {
		if ($key === 'email_comment') continue;
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode(_test_glr_pages()[$x['page']]['tree']), "$key is placed on its page");
	}
}

// The enquiry page says an enquiry commits to nothing.
function test_gallery_requests_no_sale_online()
{
	$json = json_encode(_test_glr_pages()['enquire']['tree']);
	pg_assert_contains(json_encode(lang('An enquiry commits you to nothing')), $json, 'the no obligation box');
	pg_assert_contains('{{page:advisory}}', $json, 'links how buying goes');
}

// The e-mail frame: header and footer as shared trees with the gallery's
// address, and every e-mail page in the frame.
function test_gallery_requests_mail_frame()
{
	$d = _test_glr_load();
	pg_assert_contains('{{site_name}}', json_encode($d['email_header']), 'the header names the site');
	pg_assert_contains($d['gallery']['address'] . ' · ' . $d['gallery']['phone'], $d['email_footer']['children'][1]['props']['text'], 'the footer gives the address');
	foreach (_test_glr_pages() as $key => $p) {
		if (strpos($key, 'email_') !== 0) continue;
		$json = json_encode($p['tree']);
		pg_assert_contains('"templateShared":"email_header"', $json, "$key: the e-mail header");
		pg_assert_contains('"templateShared":"email_footer"', $json, "$key: the e-mail footer");
		pg_assert_true($p['noindex'] && !$p['sitemap'], "$key: out of the sitemap");
	}
}

// Every link names a template page and no shop page.
function test_gallery_requests_links()
{
	$json = json_encode(array(_test_glr_pages(), _test_glr_load()['part']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	$known = array_flip(_test_gl_page_keys());
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
	foreach (_test_gl_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no link to $key");
}

// Ids carry gl- (the comment form's own aside), classes are Bootstrap's or
// the template's, no faded text, no year.
function test_gallery_requests_ids_and_classes()
{
	$nodes = array();
	foreach (_test_glr_pages() as $p) _test_glr_nodes($p['tree'], $nodes);
	foreach (_test_glr_load()['part']['widgets'] as $w) {
		if (is_array($w['tree'] ?? null)) _test_glr_nodes($w['tree'], $nodes);
	}
	pg_assert_true(count($nodes) > 200, 'nodes found');
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev|pth)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		// The comment thread's form fields keep the ids the Comments widget reads.
		if ($id !== '' && strpos($id, 'comment_') !== 0) pg_assert_true(strpos($id, 'gl-') === 0, "id $id is the template's");
	}
	$json = json_encode(array(_test_glr_pages(), _test_glr_load()['part']['widgets']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', str_replace(array('{{year}}', 'www.w3.org/2000/svg'), '', $json)), 'no year in the sample');
}

// Every literal has its Turkish (page addresses excepted from the words):
// a work is "eser", a gallery "galeri", a private viewing "özel gösterim".
function test_gallery_requests_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('requests.php', 'mail.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
			if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
			$tr = $map[$key];
			if (preg_match('/\bgallery\b/i', $key)) pg_assert_true(mb_stripos($tr, 'galeri', 0, 'UTF-8') !== false, "'$key' says galeri");
			if (preg_match('/\bprivate viewings?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'özel gösterim', 0, 'UTF-8') !== false, "'$key' says özel gösterim");
		}
	}
}
