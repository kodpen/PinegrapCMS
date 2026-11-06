<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Gym design template's requests and e-mails
 * (includes/design_templates/pinegrap-gym/mail.php and classes.php): the
 * pages in their folders, the privacy page named as the booking widget
 * expects, both forms' fields and settings, the fields a link fills
 * (?class=, ?day=, ?time=, ?plan=) answering the values parts.php sends,
 * the record pages, lists and e-mails reading only fields their form has,
 * links to template pages, the template's ids and classes, and every
 * literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php, mail.php and the class part in one scope, as the template
// file reads them. Read once per run.
function _test_gyc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/parts.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/mail.php';
	$class_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/classes.php';
	$out = array('part' => $class_part, 'parts_json' => json_encode(array($header(), $footer(), $join_band(), $notice(),
		$class_card('hiit'), $plan_card('unlimited'), $timetable_day('mon'))));
	return $out;
}

// Every node of a tree, depth first.
function _test_gyc_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_gyc_nodes($child, $out);
	}
}

function _test_gyc_pages()
{
	$pages = array();
	foreach (_test_gyc_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The fields of a form widget: name => the node (its _cf and options).
function _test_gyc_form_fields($widget_key)
{
	$nodes = array();
	_test_gyc_nodes(_test_gyc_load()['part']['widgets'][$widget_key]['tree'], $nodes);
	$fields = array();
	foreach ($nodes as $n) {
		if (!in_array($n['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) continue;
		foreach ($n['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name' && !isset($fields[$a['value']])) $fields[$a['value']] = $n;
		}
	}
	return $fields;
}

// The answers of a pick list node.
function _test_gyc_options($node)
{
	$values = array();
	foreach ($node['children'] as $o) {
		foreach ($o['props']['_attrs'] as $a) {
			if ($a['name'] === 'value' && $a['value'] !== '') $values[] = $a['value'];
		}
	}
	return $values;
}

function test_gym_classes_pages_and_folders()
{
	$part = _test_gyc_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	$folders = array(
		'class_booking' => 'public', 'booking_view' => 'registration', 'my_bookings' => 'registration', 'staff_bookings' => 'private',
		'join' => 'public', 'join_view' => 'registration', 'staff_joins' => 'private', 'privacy' => 'public',
		'email_booking_new' => 'private', 'email_booking_received' => 'private', 'email_join_new' => 'private',
		'email_join_received' => 'private', 'email_reply' => 'private',
	);
	$pages = _test_gyc_pages();
	pg_assert_same(array_keys($folders), array_keys($pages), 'the pages');
	foreach ($folders as $key => $folder) {
		pg_assert_same($folder, $pages[$key]['folder'], "$key folder");
		pg_assert_false(isset($pages[$key]['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($pages[$key]['meta_description'] ?? '', 'UTF-8') <= 160, "$key: meta description fits");
		if ($folder !== 'public') pg_assert_false($pages[$key]['sitemap'] ?? true, "$key: not in the sitemap");
	}
	pg_assert_same(lang('privacy-policy'), $pages['privacy']['name'], 'the privacy page has the name the booking widget looks for');
	foreach (array('booking_view', 'join_view') as $key) {
		pg_assert_same('{{tab:email_reply}}', $pages[$key]['comments']['email_page'], "$key: messages are mailed");
	}
}

// Both forms: auto-registration, their own record page and e-mails, an
// office-only status starting with Request received, a consent box with
// the privacy link, a CAPTCHA.
function test_gym_classes_forms()
{
	$pages = _test_gyc_pages();
	$cases = array(
		'class_booking' => array('class_booking_form', 'booking_view', 'email_booking_new', 'email_booking_received',
			array('class', 'day', 'time', 'class_date', 'experience', 'notes', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent')),
		'join' => array('join_form', 'join_view', 'email_join_new', 'email_join_received',
			array('plan', 'goal', 'first_visit', 'experience', 'notes', 'health_note', 'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent')),
	);
	foreach ($cases as $key => $c) {
		$form = $pages[$key]['form'];
		pg_assert_same(1, $form['auto_registration'], "$key: an account for a new sender");
		pg_assert_same('{{tab:' . $c[1] . '}}', $form['confirmation_page_id'], "$key: opens its record");
		pg_assert_same('{{tab:' . $c[2] . '}}', $form['notify_page_id'], "$key: staff e-mail");
		pg_assert_same('{{tab:' . $c[3] . '}}', $form['confirm_page_id'], "$key: sender e-mail");
		$fields = _test_gyc_form_fields($c[0]);
		pg_assert_same($c[4], array_keys($fields), "$key: the fields");
		pg_assert_true(!empty($fields['status']['props']['_cf']['office_use_only']), "$key: status is the front desk's");
		$json = json_encode(_test_gyc_load()['part']['widgets'][$c[0]]['tree']);
		pg_assert_contains('{{page:privacy}}', $json, "$key: consent links the privacy page");
		pg_assert_contains('"section":"captcha"', $json, "$key: CAPTCHA");
	}
	$h = _test_gyc_form_fields('join_form')['health_note'];
	pg_assert_same('{{folder:private}}', $h['props']['_cf']['upload_folder_id'], 'health notes in the private folder');
}

// The links of parts.php fill fields whose answers hold what they send.
function test_gym_classes_links_fill_the_forms()
{
	$params = array('class_booking' => array('class' => 'class_booking_form', 'day' => 'class_booking_form', 'time' => 'class_booking_form'),
		'join' => array('plan' => 'join_form'));
	preg_match_all('/\{\{page:(class_booking|join)\}\}\?([^"]+)/', str_replace('\\/', '/', _test_gyc_load()['parts_json']), $m, PREG_SET_ORDER);
	pg_assert_true(count($m) >= 3, 'parts.php links the forms with a choice made');
	foreach ($m as $hit) {
		parse_str(html_entity_decode(json_decode('"' . $hit[2] . '"')), $q);
		foreach ($q as $name => $value) {
			pg_assert_true(isset($params[$hit[1]][$name]), "?$name is a field of $hit[1]");
			$fields = _test_gyc_form_fields($params[$hit[1]][$name]);
			pg_assert_same($name, $fields[$name]['props']['_cf']['url_param'] ?? '', "$name starts with ?$name");
			pg_assert_true(in_array($value, _test_gyc_options($fields[$name]), true), "'$value' is an answer of $name");
		}
	}
}

// Record pages, lists and e-mails read only fields of their form.
function test_gym_classes_widgets_read_the_form()
{
	$w = _test_gyc_load()['part']['widgets'];
	$forms = array('{{tab:class_booking}}' => 'class_booking_form', '{{tab:join}}' => 'join_form');
	$system = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found', 'section',
		'reference_code', 'number_of_comments', '__new_account_email', '__new_account_set_password_url', 'has_set_password_link');
	foreach ($w as $key => $widget) {
		$form_page = $widget['config']['custom_form_page_id'] ?? '';
		if (!isset($forms[$form_page]) || $widget['config']['regionType'] === 'custom_form') continue;
		$fields = _test_gyc_form_fields($forms[$form_page]);
		$nodes = array();
		_test_gyc_nodes($widget['tree'], $nodes);
		$read = 0;
		foreach ($nodes as $n) {
			foreach ($n['props']['_bindings'] ?? array() as $what => $token) {
				if ($what === 'eo_visible_if' || in_array($token, $system, true)) continue;
				pg_assert_true(isset($fields[$token]), "$key reads $token, a field of its form");
				$read++;
			}
		}
		pg_assert_true($read >= 3, "$key reads the request");
		foreach ($widget['config']['search_fields'] ?? array() as $s) pg_assert_true(isset($fields[$s]), "$key: search field $s");
	}
	pg_assert_same('submitter_only', $w['booking_detail']['config']['access_control'], 'a booking is its sender\'s');
	pg_assert_same('submitter_only', $w['join_detail']['config']['access_control'], 'a join request is its sender\'s');
	pg_assert_true($w['my_bookings_list']['config']['viewer_filter_submitter'], 'My Class Bookings shows one\'s own');
	foreach ($w as $key => $widget) pg_assert_true(isset(_test_gyc_pages()[$widget['page']]), "$key sits on a page of the part");
}

// Every link names a template page and no shop page.
function test_gym_classes_links()
{
	$json = json_encode(array(_test_gyc_pages(), _test_gyc_load()['part']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	$known = array_flip(array('home', 'classes', 'timetable', 'plans', 'facilities', 'about', 'faq', 'location',
		'trainers', 'trainer', 'trainer_new', 'book_online', 'class_booking', 'booking_view', 'join', 'join_view', 'privacy', 'contact',
		'my_account', 'staff', 'staff_bookings', 'staff_joins', 'blog_new', 'inbox', 'my_bookings', 'blog', 'newsletter',
		'forgot_password', 'email_booking_new', 'email_booking_received', 'email_join_new', 'email_join_received', 'email_reply'));
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
}

// Ids carry gy-, classes are Bootstrap's or the template's, no faded text.
function test_gym_classes_ids_and_classes()
{
	$nodes = array();
	foreach (_test_gyc_pages() as $p) _test_gyc_nodes($p['tree'], $nodes);
	foreach (_test_gyc_load()['part']['widgets'] as $w) _test_gyc_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		// The comment form's own ids are the Comments widget's.
		if ($id !== '' && strpos($id, 'comment_') !== 0) pg_assert_true(strpos($id, 'gy-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a class reads "ders".
function test_gym_classes_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'classes.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
			if (!isset($map[$key])) continue;
			if (preg_match('/\bClass(es)?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'ders', 0, 'UTF-8') !== false, "'$key' says ders");
		}
	}
}
