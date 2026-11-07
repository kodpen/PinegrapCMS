<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Restaurant design template's private dining and shop
 * builders (includes/design_templates/pinegrap-restaurant/private.php and
 * shop.php): the pages of the plan, the enquiry form's fields and the
 * address values that fill them (?room=, ?kind=) matching what the room
 * and occasion cards send, the office-only status, the enquiry page, lists
 * and e-mails reading the form's fields; the catalog made from the
 * vouchers, every shop page and widget needing the shop and pointing at
 * each other; links to template pages only, the template's ids and
 * classes, and every literal in Turkish.
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
require_once(__DIR__ . '/design_template_restaurant_test.php');

// parts.php, mail.php, menu.php, the reservations, private dining and the
// shop in one scope, as the template file reads them. Read once per run.
function _test_rtpd_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$menu_part = include $dir . '/menu.php';
	$reservation_part = include $dir . '/reservations.php';
	$samples = $vouchers;
	$part = include $dir . '/private.php';
	$shop = include $dir . '/shop.php';
	$room_links = array();
	foreach (array_keys($rooms) as $k) $room_links[] = json_encode($room_card($k));
	$out = array('part' => $part, 'shop' => $shop, 'room_links' => $room_links, 'room_choices' => $room_choices,
		'kinds' => $enquiry_kinds, 'statuses' => $enquiry_statuses, 'tasting_choices' => $tasting_choices,
		'vouchers' => $samples, 'after' => $vouchers, 'money' => $money);
	return $out;
}

function _test_rtpd_pages($which = 'part')
{
	$pages = array();
	foreach (_test_rtpd_load()[$which]['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The fields of a form widget: name => the node.
function _test_rtpd_form_fields($widget_key)
{
	$nodes = array();
	_test_rt_nodes(_test_rtpd_load()['part']['widgets'][$widget_key]['tree'], $nodes);
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
function _test_rtpd_options($node)
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
function _test_rtpd_tokens($tree)
{
	$nodes = array();
	_test_rt_nodes($tree, $nodes);
	$tokens = array();
	foreach ($nodes as $n) {
		foreach ($n['props']['_bindings'] ?? array() as $t) $tokens[$t] = true;
	}
	return $tokens;
}

// Widget => the page that places it.
function _test_rtpd_placed($pages)
{
	$placed = array();
	foreach ($pages as $p) {
		preg_match_all('/"templateWidget":"([a-z_]+)"/', json_encode($p['tree']), $m);
		foreach ($m[1] as $k) $placed[$k] = $p['key'];
	}
	return $placed;
}

function test_restaurant_private_pages_are_the_plans()
{
	$part = _test_rtpd_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('private_dining', 'private_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'email_enquiry_new', 'email_enquiry_received'), array_keys(_test_rtpd_pages()), 'the plan\'s pages');
	$folders = array('private_dining' => 'public', 'private_enquiry' => 'public', 'enquiry_view' => 'registration',
		'my_enquiries' => 'registration', 'staff_enquiries' => 'private');
	foreach (_test_rtpd_pages() as $key => $p) {
		foreach (array('name', 'folder', 'title', 'tree') as $field) pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		pg_assert_same($folders[$key] ?? 'private', $p['folder'], "$key: its folder");
		if (isset($p['meta_description'])) pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		if (strpos($key, 'email_') === 0) pg_assert_true(!empty($p['noindex']) && $p['search'] === false, "$key: kept out of search");
	}
	$placed = _test_rtpd_placed($part['pages']);
	foreach ($placed as $w => $key) pg_assert_true(isset($part['widgets'][$w]), "$key places $w, defined here");
	foreach ($part['widgets'] as $w => $def) pg_assert_same($def['page'], $placed[$w] ?? '', "$w is placed on its page");
	$view = _test_rtpd_pages()['enquiry_view'];
	pg_assert_same('{{tab:email_reply}}', $view['comments']['email_page'], 'messages are mailed with the reply e-mail');
	pg_assert_true($view['comments']['login'], 'only a signed-in guest writes');
	$dining = json_encode(_test_rtpd_pages()['private_dining']['tree']);
	foreach (array('cellar', 'garden', 'whole') as $n => $room) {
		pg_assert_contains(json_encode(_test_rtpd_load()['room_choices'][$n]), $dining, "the page shows the room $room");
	}
}

// The form: its fields, the address values that fill them, the status the
// guest cannot set, and the e-mails and page it opens.
function test_restaurant_private_enquiry_form_fields()
{
	$d = _test_rtpd_load();
	$form = _test_rtpd_pages()['private_enquiry']['form'];
	pg_assert_same('{{tab:enquiry_view}}', $form['confirmation_page_id'], 'what is sent opens its page');
	pg_assert_same('{{tab:email_enquiry_new}}', $form['notify_page_id'], 'the restaurant\'s e-mail');
	pg_assert_same('{{tab:email_enquiry_received}}', $form['confirm_page_id'], 'the guest\'s e-mail');
	pg_assert_same(1, $form['auto_registration'], 'a guest without an account gets one');
	pg_assert_contains('^^event_kind^^', $form['notify_subject'], 'the subject names a field of the form');
	$f = _test_rtpd_form_fields('enquiry_form');
	foreach (array('event_kind', 'room', 'event_date', 'guest_count', 'other_dates', 'tasting', 'notes', 'documents',
		'contact_method', 'first_name', 'last_name', 'email', 'phone', 'company', 'status', 'consent') as $name) {
		pg_assert_true(isset($f[$name]), "the form asks for $name");
	}
	pg_assert_same(array('min_date' => 'today'), $f['event_date']['props']['_cf']['rules'], 'no date in the past');
	pg_assert_same('number', $f['guest_count']['props']['_cf']['kind'], 'the guests are a number');
	pg_assert_same($d['kinds'], _test_rtpd_options($f['event_kind']), 'the occasions');
	pg_assert_same(array_merge(array(lang('Not sure yet')), $d['room_choices']), _test_rtpd_options($f['room']), 'the rooms, or not sure yet');
	pg_assert_same($d['tasting_choices'], _test_rtpd_options($f['tasting']), 'à la carte or a tasting menu');
	pg_assert_same($d['statuses'], _test_rtpd_options($f['status']), 'the statuses');
	pg_assert_true($f['status']['props']['_cf']['office_use_only'], 'the status is the staff\'s');
	pg_assert_false(isset($f['status']['props']['_cf']['url_param']), 'no link presets the status');
	pg_assert_same('{{folder:private}}', $f['documents']['props']['_cf']['upload_folder_id'], 'a guest\'s file is private');
	foreach (array('event_kind' => 'kind', 'room' => 'room') as $name => $param) {
		pg_assert_same($param, $f[$name]['props']['_cf']['url_param'], "$name is filled from ?$param=");
		pg_assert_same($param, pg_sw_url_param_name($param), "?$param= is a usable name");
	}
	// Each room card and occasion card sends an answer of the form.
	foreach ($d['room_links'] as $n => $json) {
		pg_assert_true((bool)preg_match('/\{\{page:private_enquiry\}\}\?room=([^"]+)"/', $json, $m), "room card $n opens the form");
		pg_assert_true(in_array(rawurldecode($m[1]), $d['room_choices'], true), "room card $n sends a Room answer");
	}
	preg_match_all('/\{\{page:private_enquiry\}\}\?kind=([^"]+)"/', json_encode(_test_rtpd_pages()['private_dining']['tree']), $m);
	pg_assert_true(count($m[1]) >= 3, 'occasion cards open the form');
	foreach ($m[1] as $kind) pg_assert_true(in_array(rawurldecode($kind), $d['kinds'], true), 'an occasion card sends an Occasion answer');
}

// The enquiry page, the lists and the e-mails read the form's own fields.
function test_restaurant_private_views_read_the_form()
{
	$w = _test_rtpd_load()['part']['widgets'];
	$fields = array_keys(_test_rtpd_form_fields('enquiry_form'));
	$system = array('reference_code', 'number_of_comments', 'form_item_view', 'not_found', 'record_found', 'record_not_found',
		'submitted_this_session', 'has_new_account', '__new_account_email', '__new_account_set_password_url', 'has_set_password_link',
		'__edit_url', 'can_edit');
	foreach (array('enquiry_detail', 'my_enquiries_list', 'staff_enquiries_list', 'email_enquiry_admin', 'email_enquiry_client') as $key) {
		foreach (array_keys(_test_rtpd_tokens($w[$key]['tree'])) as $t) {
			pg_assert_true(in_array($t, $fields, true) || in_array($t, $system, true), "$key reads $t, a field of the form");
		}
		pg_assert_same('{{tab:private_enquiry}}', $w[$key]['config']['custom_form_page_id'], "$key reads the enquiry form");
	}
	pg_assert_same('submitter_only', $w['enquiry_detail']['config']['access_control'], 'a guest sees only their own');
	pg_assert_true($w['my_enquiries_list']['config']['viewer_filter_submitter'], 'My Enquiries lists the guest\'s own');
	pg_assert_same('{{tab:enquiry_view}}', $w['my_enquiries_list']['config']['detail_page_id'], 'a row opens the enquiry');
	pg_assert_true($w['staff_enquiries_list']['config']['search_enabled'], 'the staff search');
	foreach ($w['staff_enquiries_list']['config']['search_fields'] as $s) {
		pg_assert_true(in_array($s, $fields, true), "the staff search $s, a field");
	}
	foreach (array('email_enquiry_admin', 'email_enquiry_client') as $key) {
		pg_assert_contains('{{page:enquiry_view}}?r=^^reference_code^^', json_encode($w[$key]['tree'], JSON_UNESCAPED_SLASHES), "$key links the enquiry");
	}
	pg_assert_same('comments', $w['enquiry_messages']['config']['regionType'], 'the messages are the page\'s comments');
}

// The shop: a product for each voucher at its price, every page and widget
// needing the shop, the widgets pointing at each other.
function test_restaurant_shop_catalog_and_pages()
{
	$d = _test_rtpd_load();
	pg_assert_same($d['vouchers'], $d['after'], 'the parts leave the samples as they are');
	$catalog = $d['shop']['catalog'];
	pg_assert_same('pinegrap_restaurant_catalog', $catalog['folder']);
	pg_assert_same(array('vouchers'), array_keys($catalog['groups']), 'one group');
	pg_assert_same('root', $catalog['groups']['vouchers']['parent'], 'under the site\'s top group');
	$want = array();
	foreach ($d['vouchers'] as $v) $want[$v['sku']] = $v['price'];
	$got = array();
	$featured = 0;
	foreach ($catalog['groups']['vouchers']['products'] as $p) {
		$got[$p['sku']] = $p['price'];
		pg_assert_same(false, $p['shippable'], $p['sku'] . ': sent by e-mail, not shipped');
		if (!empty($p['featured'])) $featured++;
	}
	pg_assert_same($want, $got, 'a product for each voucher at its price');
	pg_assert_same(1, $featured, 'one voucher featured');

	$pages = _test_rtpd_pages('shop');
	pg_assert_same(_test_rt_shop_keys(), array_keys($pages), 'the shop\'s pages');
	foreach ($pages as $key => $p) pg_assert_same('ecommerce', $p['requires'] ?? '', "$key needs the shop");
	$w = $d['shop']['widgets'];
	foreach ($w as $wk => $def) pg_assert_same('ecommerce', $def['requires'], "widget $wk needs the shop");
	$placed = _test_rtpd_placed($pages);
	foreach ($w as $wk => $def) {
		if ($def['page'] !== '') pg_assert_same($def['page'], $placed[$wk] ?? '', "widget $wk sits on its page");
	}
	pg_assert_same('private', $pages['email_order']['folder'], 'the receipt is private');
	pg_assert_false($pages['product']['noindex'], 'product pages are indexed');
	pg_assert_same('{{product_group:vouchers}}', $w['voucher_catalog']['config']['product_group_id']);
	pg_assert_same('{{tab:cart}}', $w['voucher_catalog']['config']['add_to_cart_next_page_id'], 'the notice names this shop\'s cart');
	pg_assert_contains(json_encode(call_user_func($d['money'], $d['vouchers']['gift100']['price'])), json_encode($w['voucher_catalog']['tree']), 'the sample card\'s price');
	pg_assert_same('{{tab:vouchers}}', $w['product_detail']['config']['catalog_listing_page_id'], 'the breadcrumb starts at the vouchers page');
	pg_assert_same('{{tab:order}}', $w['checkout']['config']['next_page_id']);
	pg_assert_same('{{tab:email_order}}', $w['checkout']['config']['order_receipt_email_page_id']);
	$receipt = json_encode($w['email_order']['tree']);
	foreach (array('__order_no', '__order_total', '__billing_address_html', '__item_short_description') as $t) {
		pg_assert_contains('"' . $t . '"', $receipt, "receipt shows $t");
	}
	pg_assert_false(strpos($receipt, '__shipping_address_html') !== false, 'a voucher has no shipping address');
}

// Every link names a page the finished template carries; only the shop
// links the shop; ids and classes are the template's own.
function test_restaurant_private_and_shop_links_ids_and_classes()
{
	$d = _test_rtpd_load();
	$known = array_flip(_test_rt_page_keys());
	foreach (array('part', 'shop') as $which) {
		preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d[$which]), $m);
		foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "$which: link {{page:$key}} names a template page");
	}
	$private = json_encode($d['part']);
	foreach (_test_rt_shop_keys() as $key) {
		pg_assert_false(strpos($private, '{{page:' . $key . '}}') !== false, "private dining does not link $key");
	}
	$trees = array();
	foreach (array('part', 'shop') as $which) {
		foreach ($d[$which]['pages'] as $p) $trees[] = $p['tree'];
		foreach ($d[$which]['widgets'] as $w) if (is_array($w['tree'])) $trees[] = $w['tree'];
	}
	foreach ($trees as $tree) {
		$nodes = array();
		_test_rt_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pai|pmz)-/', $c), "class $c is not another template's");
				pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id !== '' && strpos($id, 'comment_') !== 0) pg_assert_true(strpos($id, 'rt-') === 0, "id $id is the template's");
		}
	}
	foreach (array('private.php', 'shop.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/' . $file);
		pg_assert_false((bool)preg_match("/lang\\('[^']*\\b20[0-9]{2}\\b/", $src), "$file: no year in the words");
	}
}

// Every literal has its Turkish; private dining says özel; shared keys
// whose Turkish names another thing are not used.
function test_restaurant_private_and_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('private.php', 'shop.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (isset($map[$key]) && preg_match('/\bPrivate Dining\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'özel', 0, 'UTF-8') !== false, "'$key' says özel");
			if (isset($map[$key]) && strpos($key, ' ') !== false && preg_match('/\bvouchers?\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'çek', 0, 'UTF-8') !== false, "'$key' says hediye çeki");
		}
		foreach (array('Starters', 'Course', 'Courses', 'Glass', 'Dish', 'Find Us', 'Choose a course…', 'Time') as $k) {
			pg_assert_false(strpos($src, "lang('" . $k . "')") !== false, "$file does not use lang('$k')");
		}
	}
}
