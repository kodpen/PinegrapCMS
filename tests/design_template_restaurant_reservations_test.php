<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Restaurant design template's reservations and e-mail
 * builders (includes/design_templates/pinegrap-restaurant/mail.php and
 * reservations.php): the pages of the plan, the reservation form's fields
 * and the address values that fill them (?tasting=, ?event=, ?time=,
 * ?occasion=) matching what the tasting cards and event links send, the
 * office-only status, the request page, lists and e-mails reading the
 * form's fields, the chef's table on the booking widget's starter, the
 * template's ids and classes, and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php, mail.php, menu.php and the reservations in one scope, as the
// template file reads them. Read once per run.
function _test_rtr_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/parts.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/mail.php';
	$menu_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/menu.php';
	$part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/reservations.php';
	$tasting_links = array();
	foreach (array_keys($tastings) as $k) $tasting_links[] = json_encode($tasting_card($k));
	$out = array('part' => $part, 'menu' => $menu_part, 'tasting_links' => $tasting_links,
		'tasting_choices' => $tasting_choices, 'time_choices' => $time_choices, 'occasion_choices' => $occasion_choices,
		'guest_choices' => $guest_choices, 'statuses' => $reservation_statuses,
		'header' => $email_header_tree(), 'footer' => $email_footer_tree());
	return $out;
}

// Every node of a tree, depth first.
function _test_rtr_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_rtr_nodes($child, $out);
	}
}

function _test_rtr_pages()
{
	$pages = array();
	foreach (_test_rtr_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The fields of a form widget: name => the node (its _cf and options).
function _test_rtr_form_fields($widget_key)
{
	$nodes = array();
	_test_rtr_nodes(_test_rtr_load()['part']['widgets'][$widget_key]['tree'], $nodes);
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
function _test_rtr_options($node)
{
	$values = array();
	foreach ($node['children'] as $o) {
		foreach ($o['props']['_attrs'] as $a) {
			if ($a['name'] === 'value' && $a['value'] !== '') $values[] = $a['value'];
		}
	}
	return $values;
}

// The tokens a tree binds.
function _test_rtr_tokens($tree)
{
	$nodes = array();
	_test_rtr_nodes($tree, $nodes);
	$tokens = array();
	foreach ($nodes as $n) {
		foreach ($n['props']['_bindings'] ?? array() as $t) $tokens[$t] = true;
	}
	return $tokens;
}

function test_restaurant_reservation_pages_are_the_plans()
{
	$part = _test_rtr_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('reserve', 'reservation_view', 'my_reservations', 'staff_reservations', 'book_online', 'privacy',
		'email_reservation_new', 'email_reservation_received', 'email_reply'), array_keys(_test_rtr_pages()), 'the plan\'s pages');
	$folders = array('reserve' => 'public', 'reservation_view' => 'registration', 'my_reservations' => 'registration',
		'staff_reservations' => 'private', 'book_online' => 'public', 'privacy' => 'public');
	foreach (_test_rtr_pages() as $key => $p) {
		foreach (array('name', 'folder', 'title', 'tree') as $field) pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		pg_assert_same($folders[$key] ?? 'private', $p['folder'], "$key: its folder");
		if (isset($p['meta_description'])) pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		if (strpos($key, 'email_') === 0) pg_assert_true(!empty($p['noindex']) && $p['search'] === false, "$key: kept out of search");
	}
	// Every widget sits on a page of this part, and every widget a page
	// places is defined here.
	$placed = array();
	foreach (_test_rtr_pages() as $key => $p) {
		$nodes = array();
		_test_rtr_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $placed[$n['props']['templateWidget']] = $key;
		}
	}
	foreach ($placed as $w => $key) pg_assert_true(isset($part['widgets'][$w]), "$key places $w, defined here");
	foreach ($part['widgets'] as $w => $def) pg_assert_same($def['page'], $placed[$w] ?? '', "$w is placed on its page");
	$view = _test_rtr_pages()['reservation_view'];
	pg_assert_same('{{tab:email_reply}}', $view['comments']['email_page'], 'messages are mailed with the reply e-mail');
	pg_assert_true($view['comments']['login'], 'only a signed-in guest writes');
}

// The form: its fields, the address values that fill them, the status the
// guest cannot set, and the e-mails and page it opens.
function test_restaurant_reservation_form_fields()
{
	$d = _test_rtr_load();
	$form = _test_rtr_pages()['reserve']['form'];
	pg_assert_same('{{tab:reservation_view}}', $form['confirmation_page_id'], 'what is sent opens its page');
	pg_assert_same('{{tab:email_reservation_new}}', $form['notify_page_id'], 'the restaurant\'s e-mail');
	pg_assert_same('{{tab:email_reservation_received}}', $form['confirm_page_id'], 'the guest\'s e-mail');
	pg_assert_same(1, $form['auto_registration'], 'a guest without an account gets one');
	$f = _test_rtr_form_fields('reservation_form');
	foreach (array('reservation_date', 'time', 'guests', 'tasting', 'occasion', 'event', 'seating', 'notes',
		'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($f[$name]), "the form asks for $name");
	}
	pg_assert_same(array('min_date' => 'today'), $f['reservation_date']['props']['_cf']['rules'], 'no date in the past');
	pg_assert_same($d['time_choices'], _test_rtr_options($f['time']), 'the sittings\' times');
	pg_assert_same($d['guest_choices'], _test_rtr_options($f['guests']), 'the guest counts');
	pg_assert_same($d['tasting_choices'], _test_rtr_options($f['tasting']), 'à la carte or a tasting menu');
	pg_assert_same($d['occasion_choices'], _test_rtr_options($f['occasion']), 'the occasions');
	pg_assert_same($d['statuses'], _test_rtr_options($f['status']), 'the statuses');
	pg_assert_true($f['status']['props']['_cf']['office_use_only'], 'the status is the staff\'s');
	foreach (array('tasting', 'event', 'time', 'occasion') as $name) {
		pg_assert_same($name, $f[$name]['props']['_cf']['url_param'], "$name is filled from ?$name=");
		pg_assert_same($name, pg_sw_url_param_name($name), "?$name= is a usable name");
	}
	pg_assert_same('input', $f['event']['props']['tag'], 'the event is text, so a new event needs no new answer');
	pg_assert_false(isset($f['status']['props']['_cf']['url_param']), 'no link presets the status');
	// Each tasting card sends a Menu answer.
	foreach ($d['tasting_links'] as $n => $json) {
		pg_assert_true((bool)preg_match('/\{\{page:reserve\}\}\?tasting=([^"]+)"/', $json, $m), "tasting card $n opens the form");
		pg_assert_true(in_array(rawurldecode($m[1]), $d['tasting_choices'], true), "tasting card $n sends a Menu answer");
	}
}

// The request page, the lists and the e-mails read the form's own fields.
function test_restaurant_reservation_views_read_the_form()
{
	$w = _test_rtr_load()['part']['widgets'];
	$fields = array_keys(_test_rtr_form_fields('reservation_form'));
	$system = array('reference_code', 'number_of_comments', 'form_item_view', 'not_found', 'record_found', 'record_not_found',
		'submitted_this_session', 'has_new_account', '__new_account_email', '__new_account_set_password_url', 'has_set_password_link',
		'__edit_url', 'can_edit');
	foreach (array('reservation_detail', 'my_reservations_list', 'staff_reservations_list', 'email_reservation_admin', 'email_reservation_client') as $key) {
		foreach (array_keys(_test_rtr_tokens($w[$key]['tree'])) as $t) {
			pg_assert_true(in_array($t, $fields, true) || in_array($t, $system, true), "$key reads $t, a field of the form");
		}
		pg_assert_same('{{tab:reserve}}', $w[$key]['config']['custom_form_page_id'], "$key reads the Reservations form");
	}
	pg_assert_same('submitter_only', $w['reservation_detail']['config']['access_control'], 'a guest sees only their own');
	pg_assert_true($w['my_reservations_list']['config']['viewer_filter_submitter'], 'My Reservations lists the guest\'s own');
	pg_assert_true($w['staff_reservations_list']['config']['search_enabled'], 'the staff search');
	foreach ($w['staff_reservations_list']['config']['search_fields'] as $s) {
		pg_assert_true(in_array($s, $fields, true), "the staff search $s, a field");
	}
	foreach (array('email_reservation_admin', 'email_reservation_client') as $key) {
		pg_assert_contains('{{page:reservation_view}}?r=^^reference_code^^', json_encode($w[$key]['tree'], JSON_UNESCAPED_SLASHES), "$key links the request");
	}
}

// The chef's table: the booking widget's starter beside the request form,
// the privacy page the consent box links.
function test_restaurant_chefs_table_on_the_booking_widget()
{
	$w = _test_rtr_load()['part']['widgets'];
	$p = _test_rtr_pages();
	pg_assert_same(array('regionType' => 'booking'), $w['booking_slots']['config'], 'the booking widget');
	pg_assert_same('starter', $w['booking_slots']['tree'], 'its starter layout');
	pg_assert_same(lang('book-online'), $p['book_online']['name'], 'the shared booking page name');
	pg_assert_same(lang('privacy-policy'), $p['privacy']['name'], 'the privacy page the booking widget links');
	pg_assert_contains('{{page:reserve}}', json_encode($p['book_online']['tree']), 'the chef\'s table links the request form');
	pg_assert_contains('{{page:book_online}}', json_encode($p['reserve']['tree']), 'the request form links the chef\'s table');
}

// Every link names a page the finished template carries; ids and classes
// are the template's own; the e-mails write their colours inline.
function test_restaurant_reservation_links_ids_and_classes()
{
	$known = array_flip(array(
		'home', 'tasting', 'wine', 'about', 'faq', 'location', 'menu', 'dish', 'dish_new', 'events', 'event', 'event_new',
		'reserve', 'reservation_view', 'my_reservations', 'book_online', 'private_dining', 'private_enquiry', 'privacy', 'contact', 'blog',
		'newsletter', 'my_account', 'staff', 'staff_reservations', 'staff_enquiries', 'blog_new', 'inbox', 'forgot_password',
		'email_reply', 'email_reservation_new', 'email_reservation_received',
	));
	$d = _test_rtr_load();
	$json = json_encode(array($d['part'], $d['header'], $d['footer']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach (array('vouchers', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no page here links $key");
	}
	$trees = array();
	foreach ($d['part']['pages'] as $p) $trees[] = $p['tree'];
	foreach ($d['part']['widgets'] as $w) if (is_array($w['tree'])) $trees[] = $w['tree'];
	foreach ($trees as $tree) {
		$nodes = array();
		_test_rtr_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pai|pmz)-/', $c), "class $c is not another template's");
				pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			// The comment form's controls keep the ids the Comments widget reads.
			if ($id !== '' && strpos($id, 'comment_') !== 0) pg_assert_true(strpos($id, 'rt-') === 0, "id $id is the template's");
		}
	}
	pg_assert_contains('var(--bs-primary,#9f1239)', json_encode($d['part']['widgets']['email_reservation_admin']['tree']), 'the e-mail button keeps the palette\'s colour inline');
	foreach (array('mail.php', 'reservations.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/' . $file);
		pg_assert_false((bool)preg_match("/lang\\('[^']*\\b20[0-9]{2}\\b/", $src), "$file: no year in the words");
	}
}

// Every literal has its Turkish; reservation words say rezervasyon; shared
// keys whose Turkish names another thing are not used.
function test_restaurant_reservation_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'reservations.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (isset($map[$key]) && preg_match('/\bReservations?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'rezervasyon', 0, 'UTF-8') !== false, "'$key' says rezervasyon");
		}
		foreach (array('Starters', 'Course', 'Courses', 'Glass', 'Dish', 'Find Us', 'Choose a course…') as $k) {
			pg_assert_false(strpos($src, "lang('" . $k . "')") !== false, "$file does not use lang('$k')");
		}
	}
}
