<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bakery design template's cake orders and class place
 * requests (includes/design_templates/pinegrap-bakery/mail.php and
 * requests.php): the pages and widgets, the two forms (their fields, pick
 * lists, the cake and occasion read from ?cake= and ?occasion= as every
 * cake card sends them, the class from ?class= as the class page sends it,
 * no day before today, the address asked for a delivery only, the
 * office-only status, the private picture), the record pages with their
 * messages, the lists and e-mails binding fields their form has, every
 * page link naming a template page, ids and classes the template's own,
 * the e-mail colours the palette's, no fixed date and every literal with
 * its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_bk_page_keys()).
require_once(__DIR__ . '/design_template_bakery_test.php');

// The requests' part as the template file reads it: parts.php, mail.php,
// the public pages, the classes, then requests.php in the same scope. Read
// once per run.
function _test_bkr_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$public = include $dir . '/pages_public.php';
	$classes = include $dir . '/classes.php';
	$part = include $dir . '/requests.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$cake_cards = array();
	foreach (array_keys($cakes) as $key) $cake_cards[] = $cake_card($key);
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'occasions' => $occasion_choices, 'cakes' => $cake_choices, 'sizes' => $size_choices, 'flavours' => $flavour_choices,
		'fillings' => $filling_choices, 'class_names' => $class_names, 'cake_statuses' => $cake_statuses,
		'booking_statuses' => $booking_statuses, 'fulfilment' => $fulfilment_choices, 'contact_choices' => $contact_choices,
		'public' => $public, 'classes' => $classes, 'cake_cards' => $cake_cards,
		'shared' => array($email_header_tree(), $email_footer_tree()));
	return $out;
}

function _test_bkr_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_bkr_nodes($c, $out);
}

// The name, options, type and settings of every form control of a tree.
function _test_bkr_controls($node)
{
	$nodes = array();
	_test_bkr_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		$value = '';
		$type = $tag;
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
			if ($a['name'] === 'value') $value = $a['value'];
			if ($a['name'] === 'type') $type = $a['value'];
		}
		if (!isset($out[$name])) $out[$name] = array('type' => $type, 'options' => array(), 'cf' => $n['props']['_cf'] ?? array());
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $out[$name]['options'][] = $a['value'];
			}
		}
		// A radio group: each button's value is an answer.
		if ($tag === 'input' && $value !== '' && $name !== 'consent') $out[$name]['options'][] = $value;
	}
	return $out;
}

function test_bakery_requests_pages_and_widgets()
{
	$d = _test_bkr_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('cake_order', 'cake_order_view', 'my_cake_orders', 'staff_cake_orders',
		'class_booking', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy',
		'email_cake_order_new', 'email_cake_order_received', 'email_booking_new', 'email_booking_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('cake_order_form', 'cake_order_detail', 'cake_order_messages', 'my_cake_orders_list', 'staff_cake_orders_list',
		'class_booking_form', 'booking_detail', 'booking_messages', 'my_bookings_list', 'staff_bookings_list',
		'email_cake_order_admin', 'email_cake_order_client', 'email_booking_admin', 'email_booking_client', 'email_comment'), array_keys($d['widgets']));
	$known = array_flip(_test_bk_page_keys());
	foreach (array_keys($d['pages']) as $key) pg_assert_true(isset($known[$key]), "$key is a page of the finished template");
	foreach (array('cake_order', 'class_booking', 'privacy') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('cake_order_view', 'my_cake_orders', 'booking_view', 'my_bookings') as $key) {
		pg_assert_same('registration', $d['pages'][$key]['folder'], "$key is read signed in");
	}
	foreach (array('staff_cake_orders', 'staff_bookings', 'email_cake_order_new', 'email_cake_order_received', 'email_booking_new', 'email_booking_received', 'email_reply') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true($d['pages'][$key]['search'] === false, "$key is out of the search");
	}
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	foreach (array('cake_order_detail', 'booking_detail') as $key) {
		pg_assert_same('submitter_only', $d['widgets'][$key]['config']['access_control'], "$key is its sender's");
	}
	foreach (array('my_cake_orders_list', 'my_bookings_list') as $key) {
		pg_assert_true(!empty($d['widgets'][$key]['config']['viewer_filter_submitter']), "$key lists the own records");
	}
	foreach (array('staff_cake_orders_list', 'staff_bookings_list') as $key) {
		pg_assert_true(!empty($d['widgets'][$key]['config']['search_enabled']), "$key: the staff search");
	}
}

// The cake order: its fields, the cake and occasion from the address as
// every cake card sends them, no day before today, the address asked for
// and required for a delivery only, the private picture, the office-only
// status, the contact fields.
function test_bakery_requests_cake_order_form()
{
	$d = _test_bkr_load();
	$form = $d['pages']['cake_order']['form'];
	pg_assert_same('{{tab:cake_order_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_cake_order_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_cake_order_received}}', $form['confirm_page_id']);
	$c = _test_bkr_controls($d['widgets']['cake_order_form']['tree']);
	pg_assert_same(array('occasion', 'cake', 'size', 'flavour', 'filling', 'cake_words', 'notes', 'inspiration', 'allergies',
		'pickup_date', 'fulfilment', 'address', 'first_name', 'last_name', 'email', 'phone', 'contact_method', 'status', 'consent'), array_keys($c));
	pg_assert_same($d['occasions'], $c['occasion']['options'], 'the occasions');
	pg_assert_same($d['cakes'], $c['cake']['options'], 'the cakes and a design of one\'s own');
	pg_assert_same($d['sizes'], $c['size']['options'], 'the sizes');
	pg_assert_same($d['flavours'], $c['flavour']['options'], 'the sponges');
	pg_assert_same($d['fillings'], $c['filling']['options'], 'the fillings');
	pg_assert_same('occasion', $c['occasion']['cf']['url_param'] ?? '', 'the occasion reads ?occasion=');
	pg_assert_same('cake', $c['cake']['cf']['url_param'] ?? '', 'the cake reads ?cake=');
	pg_assert_same('date', $c['pickup_date']['type'], 'the day is a day picker');
	pg_assert_same('today', $c['pickup_date']['cf']['rules']['min_date'] ?? '', 'no day before today');
	pg_assert_same(array_column($d['fulfilment'], 0), $c['fulfilment']['options'], 'collect or deliver');
	$deliver = array('field' => 'fulfilment', 'answers' => array($d['fulfilment'][1][0]));
	pg_assert_same($deliver, $c['address']['cf']['rules']['required_when'] ?? array(), 'the address is required for a delivery');
	pg_assert_same($deliver, $c['address']['cf']['rules']['shown_when'] ?? array(), 'the address is asked for a delivery only');
	pg_assert_same('file', $c['inspiration']['type'], 'the picture is a file');
	pg_assert_same('{{folder:private}}', $c['inspiration']['cf']['upload_folder_id'] ?? '', 'the picture is private');
	pg_assert_same($d['cake_statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the bakery\'s');
	pg_assert_same(array_column($d['contact_choices'], 0), $c['contact_method']['options'], 'the ways to reach the customer');
	foreach (array('first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone') as $name => $contact) {
		pg_assert_same($contact, $c[$name]['cf']['contact_field'] ?? '', "$name fills the contact");
	}
	$json = json_encode($d['widgets']['cake_order_form']['tree']);
	pg_assert_contains('{{page:privacy}}', $json, 'the consent links the privacy page');
	pg_assert_contains('"section":"captcha"', $json, 'the CAPTCHA');
	// Every ?cake= and ?occasion= a link sends is an answer.
	$trees = $d['cake_cards'];
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	$seen = 0;
	foreach ($trees as $tree) {
		$nodes = array();
		_test_bkr_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:cake_order}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:cake_order}}?')), $q);
			pg_assert_same(array('cake', 'occasion'), array_keys($q), 'the order link sends the cake and the occasion');
			pg_assert_true(in_array($q['cake'], $c['cake']['options'], true), "?cake={$q['cake']} is an answer");
			pg_assert_true(in_array($q['occasion'], $c['occasion']['options'], true), "?occasion={$q['occasion']} is an answer");
			$seen++;
		}
	}
	pg_assert_true($seen >= count($d['cake_cards']), 'every cake card orders its cake');
}

// The class place request: the class from ?class= as the class page sends
// the record's name, no day before today, the office-only status.
function test_bakery_requests_class_booking_form()
{
	$d = _test_bkr_load();
	$form = $d['pages']['class_booking']['form'];
	pg_assert_same('{{tab:booking_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_booking_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_booking_received}}', $form['confirm_page_id']);
	pg_assert_true($form['form_name'] !== $d['pages']['cake_order']['form']['form_name'], 'a form of its own');
	$c = _test_bkr_controls($d['widgets']['class_booking_form']['tree']);
	pg_assert_same(array('class', 'class_date', 'places', 'notes', 'first_name', 'last_name', 'email', 'phone',
		'contact_method', 'status', 'consent'), array_keys($c));
	pg_assert_same($d['class_names'], $c['class']['options'], 'the classes');
	pg_assert_same('class', $c['class']['cf']['url_param'] ?? '', 'the class reads ?class=');
	pg_assert_same('date', $c['class_date']['type'], 'the day is a day picker');
	pg_assert_same('today', $c['class_date']['cf']['rules']['min_date'] ?? '', 'no day before today');
	pg_assert_same($d['booking_statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the bakery\'s');
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['class_booking_form']['tree']), 'the consent links the privacy page');
	// The class page's button sends the record's name as ?class=, and the
	// sample classes' names are the answers.
	$nodes = array();
	_test_bkr_nodes($d['classes']['widgets']['class_detail']['tree'], $nodes);
	$sent = array();
	foreach ($nodes as $n) {
		$spec = $n['props']['_bindParams']['href'] ?? null;
		if (!$spec || $spec['page_id'] !== '{{tab:class_booking}}') continue;
		foreach ($spec['params'] as $p) $sent[$p['name']] = $p['token'];
	}
	pg_assert_same(array('class' => 'name'), $sent, 'the class page sends ?class= with the class name');
	foreach ($d['classes']['pages'] as $p) {
		if ($p['key'] !== 'class_new') continue;
		foreach ($p['form']['sample_records'] as $r) pg_assert_true(in_array($r['name'], $c['class']['options'], true), "{$r['name']} is an answer");
	}
}

// The record pages carry their messages and mail each one; the pages, the
// lists and the e-mails bind fields their form has.
function test_bakery_requests_record_and_mail()
{
	$d = _test_bkr_load();
	$cases = array(
		'cake_order_form'    => array('cake_order_view', array('cake_order_detail', 'my_cake_orders_list', 'staff_cake_orders_list', 'email_cake_order_admin', 'email_cake_order_client'), 'staff_cake_orders_list'),
		'class_booking_form' => array('booking_view', array('booking_detail', 'my_bookings_list', 'staff_bookings_list', 'email_booking_admin', 'email_booking_client'), 'staff_bookings_list'),
	);
	foreach ($cases as $form => $c) {
		list($view, $widgets, $staff) = $c;
		$comments = $d['pages'][$view]['comments'];
		pg_assert_true($comments['allow_new'] && $comments['login'], "$view: messages from the signed-in sender");
		pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
		pg_assert_same('{{site_email}}', $comments['notify_email'], "$view: the bakery hears of every message");
		$fields = array_keys(_test_bkr_controls($d['widgets'][$form]['tree']));
		foreach ($widgets as $key) {
			$nodes = array();
			_test_bkr_nodes($d['widgets'][$key]['tree'], $nodes);
			$read = 0;
			foreach ($nodes as $n) {
				foreach (array('text', 'href', 'src') as $prop) {
					$token = (string)($n['props']['_bindings'][$prop] ?? '');
					if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments', 'form_item_view'), true)) continue;
					pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the form");
					$read++;
				}
			}
			pg_assert_true($read >= 3, "$key reads the record");
		}
		foreach ($d['widgets'][$staff]['config']['search_fields'] as $name) {
			pg_assert_true(in_array($name, $fields, true), "$staff searches $name");
		}
		$json = json_encode($d['widgets'][$widgets[0]]['tree']);
		pg_assert_contains('"eo_visible_if":"record_found"', $json, "$view: the record");
		pg_assert_contains('"eo_visible_if":"record_not_found"', $json, "$view: the not-found note");
		pg_assert_contains('"eo_visible_if":"has_new_account"', $json, "$view: the account the form opened");
		foreach (array($widgets[3], $widgets[4]) as $key) {
			pg_assert_same('public', $d['widgets'][$key]['config']['access_control'], "$key is drawn for the submission just sent");
			pg_assert_contains('{{page:' . $view . '}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree'], JSON_UNESCAPED_SLASHES), "$key opens the record");
		}
		pg_assert_contains('"text":"reference_code"', json_encode($d['widgets'][$widgets[3]]['tree']), "{$widgets[3]}: the reference line");
		pg_assert_contains('"eo_visible_if":"has_new_account"', json_encode($d['widgets'][$widgets[4]]['tree']), "{$widgets[4]}: the new-account note");
	}
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the fixed comment');
	pg_assert_contains('"href":"__comment_url"', json_encode($d['widgets']['email_comment']['tree']), 'the comment card opens the comment');
}

function test_bakery_requests_links_ids_and_classes()
{
	$d = _test_bkr_load();
	$known = array_flip(_test_bk_page_keys());
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_bk_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['shared'] as $t) _test_bkr_nodes($t, $nodes);
	foreach ($d['pages'] as $p) _test_bkr_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_bkr_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(p[a-z]{2,3}|pf|pm)-/', $c) && strpos($c, 'pbk-') !== 0 && strpos($c, 'pg-') !== 0, "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'bky-') === 0, "id $id is the template's");
	}
}

// The e-mails keep the palette's colour when the stylesheet is dropped:
// each fixed colour is the coral palette's rose family or a neutral, and
// every use of the palette's colour is followed by its var() with the same
// fallback.
function test_bakery_requests_mail_colours()
{
	$mail = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery/mail.php');
	preg_match_all('/#[0-9a-f]{6}\b/i', $mail, $colors);
	foreach (array_unique($colors[0]) as $hex) {
		pg_assert_true(in_array(strtolower($hex), array('#e11d48', '#fecdd3', '#fff1f2', '#ffffff', '#1f2937', '#4b5563', '#b45309'), true), "e-mail colour $hex is the template's");
	}
	preg_match_all('/([a-z-]+):#e11d48;\1:var\(--[a-z-]+,#e11d48\)/', $mail, $pairs);
	preg_match_all('/[a-z-]+:#e11d48/', $mail, $plain);
	pg_assert_true(count($pairs[0]) >= 3, 'the button, the header and the file link');
	pg_assert_same(count($plain[0]), count($pairs[0]), 'every palette colour keeps its var()');
	pg_assert_contains('var(--bs-primary,#e11d48)', $mail, 'the coral fallback');
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_bakery_requests_no_fixed_dates()
{
	$d = _test_bkr_load();
	$json = json_encode(array($d['pages'], $d['widgets'], $d['shared']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
	pg_assert_false((bool)preg_match('/\b[0-9]{4}-[0-9]{2}-[0-9]{2}\b/', $json), 'no ISO date in the sample');
}

// Every literal has its Turkish; a class reads "atölye", a cake order
// "pasta", and the shared keys whose Turkish names another thing are not
// used.
function test_bakery_requests_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'requests.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			// Page names are addresses without accents; only words are checked.
			if (strpos($key, ' ') === false) continue;
			if (preg_match('/\b(Baking )?Class(es)?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'atölye', 0, 'UTF-8') !== false, "'$key' says atölye");
			if (preg_match('/\bcake orders?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'pasta', 0, 'UTF-8') !== false, "'$key' says pasta");
		}
		foreach (array('Classes', 'Class', 'Bookings', 'Booking') as $k) pg_assert_false(strpos($src, "lang('$k')") !== false, "$file: no lang('$k')");
	}
}
