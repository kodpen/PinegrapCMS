<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Photography Studio design template's client area and
 * proof selections (includes/design_templates/pinegrap-photo/clients.php):
 * the pages with their folders (the sample proofing gallery in its own
 * private folder), the selection form reading the gallery its button sends
 * (?gallery=), the office-only status, the selection's page with files in
 * its messages, the lists, the e-mail pages kept out of the search, the
 * proofs and their downloads, every page link naming a template page and
 * none a shop page, ids and classes the template's own, and every literal
 * with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_photo_test.php');

// The client area as the template file reads it: parts.php, mail.php, then
// clients.php in the same scope. Read once per run.
function _test_phc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-photo';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/clients.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('pages' => $pages, 'widgets' => $part['widgets'], 'statuses' => $selection_statuses,
		'print_choices' => $print_choices, 'contact_choices' => $contact_choices, 'sample_gallery' => $sel_sample_gallery);
	return $out;
}

function _test_phc_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_phc_nodes($c, $out);
}

// The name, values and settings of every form control of a tree; radios
// of one name gather their values.
function _test_phc_controls($node)
{
	$nodes = array();
	_test_phc_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$attrs = array();
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) $attrs[$a['name']] = $a['value'];
		$name = $attrs['name'] ?? '';
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		if (($attrs['type'] ?? '') === 'radio') {
			$out[$name]['tag'] = 'radio';
			$out[$name]['options'][] = $attrs['value'];
			continue;
		}
		$out[$name] = array('tag' => $tag, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array(), 'attrs' => $attrs);
	}
	return $out;
}

function test_photo_clients_pages_and_widgets()
{
	$d = _test_phc_load();
	pg_assert_same(array('client_area', 'proofing_sample', 'selection', 'selection_view', 'my_selections', 'staff_selections', 'email_selection_new', 'email_selection_received'), array_keys($d['pages']));
	pg_assert_same(array('selection_form', 'selection_detail', 'selection_messages', 'my_selections_list', 'staff_selections_list', 'email_selection_admin', 'email_selection_client'), array_keys($d['widgets']));
	pg_assert_same('public', $d['pages']['client_area']['folder']);
	pg_assert_same('client_sample', $d['pages']['proofing_sample']['folder'], 'the sample gallery has the folder the studio duplicates');
	pg_assert_true($d['pages']['proofing_sample']['search'] === false && !empty($d['pages']['proofing_sample']['noindex']), 'a client gallery is out of the search');
	foreach (array('selection', 'selection_view', 'my_selections') as $key) {
		pg_assert_same('registration', $d['pages'][$key]['folder'], "$key is read signed in");
	}
	pg_assert_same('private', $d['pages']['staff_selections']['folder'], 'only the staff list every selection');
	foreach (array('email_selection_new', 'email_selection_received') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true(!empty($d['pages'][$key]['noindex']) && $d['pages'][$key]['search'] === false, "$key is out of the search");
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
	pg_assert_same('submitter_only', $d['widgets']['selection_detail']['config']['access_control'], 'a selection is its sender\'s');
	pg_assert_true(!empty($d['widgets']['my_selections_list']['config']['viewer_filter_submitter']), 'My Selections lists the own selections');
	pg_assert_true(!empty($d['widgets']['staff_selections_list']['config']['search_enabled']), 'the staff search');
}

// The sample gallery shows numbered proofs with downloads, and its buttons
// open the selection form with its own name as the gallery.
function test_photo_clients_proofing_sample()
{
	$d = _test_phc_load();
	$nodes = array();
	_test_phc_nodes($d['pages']['proofing_sample']['tree'], $nodes);
	$downloads = 0;
	$sends = 0;
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'download') $downloads++;
		}
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:selection}}?') === 0) {
			parse_str(substr($href, strlen('{{page:selection}}?')), $q);
			pg_assert_same($d['sample_gallery'], $q['gallery'] ?? '', 'the button sends the gallery\'s name');
			$sends++;
		}
	}
	pg_assert_same(12, $downloads, 'every proof downloads');
	pg_assert_true($sends > 0, 'the gallery opens the selection form');
	pg_assert_same($d['sample_gallery'], $d['pages']['proofing_sample']['title'], 'the page is named as its button says');
}

// The form opens the selection's page and mails both sides; the gallery
// starts with what its button sends and the status is the studio's.
function test_photo_clients_form()
{
	$d = _test_phc_load();
	$form = $d['pages']['selection']['form'];
	pg_assert_same('{{tab:selection_view}}', $form['confirmation_page_id']);
	pg_assert_same('{{tab:email_selection_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_selection_received}}', $form['confirm_page_id']);
	pg_assert_contains('^^gallery^^', $form['notify_subject'], 'the studio\'s subject names the gallery');
	$c = _test_phc_controls($d['widgets']['selection_form']['tree']);
	foreach (array('gallery', 'frames', 'retouch', 'prints', 'first_name', 'last_name', 'email', 'phone', 'reach_by', 'status', 'consent') as $name) {
		pg_assert_true(isset($c[$name]), "the form has $name");
	}
	pg_assert_same('gallery', $c['gallery']['cf']['url_param'] ?? '', 'the gallery reads ?gallery=');
	pg_assert_same('textarea', $c['frames']['tag'], 'the frames are written');
	pg_assert_same($d['print_choices'], $c['prints']['options'], 'the prints');
	pg_assert_same(array_column($d['contact_choices'], 0), $c['reach_by']['options'], 'e-mail or phone');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the studio\'s');
}

// The selection's page carries messages with files; the widgets bind fields
// the form has.
function test_photo_clients_record_and_mail()
{
	$d = _test_phc_load();
	$comments = $d['pages']['selection_view']['comments'];
	pg_assert_true($comments['allow_new'] && $comments['login'], 'messages from the signed-in sender');
	pg_assert_true($comments['attachments'], 'the studio attaches the finished files');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
	$fields = array_keys(_test_phc_controls($d['widgets']['selection_form']['tree']));
	foreach (array('selection_detail', 'my_selections_list', 'staff_selections_list', 'email_selection_admin', 'email_selection_client') as $key) {
		$nodes = array();
		_test_phc_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			$token = (string)($n['props']['_bindings']['text'] ?? '');
			if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments'), true)) continue;
			pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the form");
		}
	}
	foreach ($d['widgets']['staff_selections_list']['config']['search_fields'] as $name) {
		pg_assert_true(in_array($name, $fields, true), "the staff search reads $name");
	}
	$json = json_encode($d['widgets']['selection_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
}

function test_photo_clients_links_ids_and_classes()
{
	$d = _test_phc_load();
	$known = array_flip(_test_ph_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_ph_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($trees as $t) _test_phc_nodes(is_array($t) && isset($t['tree']) ? $t['tree'] : $t, $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^opacity-/', $c), "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'ph-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a gallery reads "galeri", a selection
// "seçim", a shoot "çekim" and a package "paket".
function test_photo_clients_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-photo/clients.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		// A ^^field^^ token and a page slug are names, not words.
		if (preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
		if (preg_match('/\b[Gg]aller(y|ies)\b/', $words)) pg_assert_true(mb_stripos($tr, 'galeri', 0, 'UTF-8') !== false, "'$key' says galeri");
		if (preg_match('/\b[Ss]elections?\b/', $words)) pg_assert_true(mb_stripos($tr, 'seçim', 0, 'UTF-8') !== false, "'$key' says seçim");
		if (preg_match('/\b[Ss]hoots?\b/', $words)) pg_assert_true(mb_stripos($tr, 'çekim', 0, 'UTF-8') !== false, "'$key' says çekim");
		if (preg_match('/\b[Pp]ackages?\b/', $words)) pg_assert_true(mb_stripos($tr, 'paket', 0, 'UTF-8') !== false, "'$key' says paket");
	}
}
