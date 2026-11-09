<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Home Services design template's estimate requests and shop
 * (includes/design_templates/pinegrap-homeservices/estimates.php and
 * shop.php): the pages and widgets, the estimate form reading what the
 * service page and the area cards send (?service=, ?area=), the photos
 * kept in the private folder, the office-only status, the request's page
 * and lists, the e-mails binding fields the form has and kept out of the
 * search; the shop's pages all needing the shop, the Care Plans group made
 * of the care plans at their prices and never shipped; every page link
 * naming a template page and no record page linking a shop page, ids and
 * classes the template's own, no fixed date, and every literal with its
 * Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template, the shop's pages, and the
// control and node readers of the visits' test.
require_once(__DIR__ . '/design_template_homeservices_visits_test.php');

// The two parts as the template file reads them: parts.php, mail.php,
// services.php and visits.php (their record pieces), then estimates.php
// and shop.php in the same scope. Read once per run.
function _test_hse_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$services_part = include $dir . '/services.php';
	include $dir . '/visits.php';
	$part = include $dir . '/estimates.php';
	$shop = include $dir . '/shop.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$shop_pages = array();
	foreach ($shop['pages'] as $p) $shop_pages[$p['key']] = $p;
	$area_trees = array();
	foreach (array_keys($areas) as $key) $area_trees[] = $area_card($key);
	return $out = array('pages' => $pages, 'widgets' => $part['widgets'], 'service_choices' => $service_choices,
		'area_names' => $area_names, 'property_types' => $property_types, 'property_sizes' => $property_sizes,
		'urgency_choices' => $urgency_choices, 'contact_choices' => $contact_choices, 'statuses' => $estimate_statuses,
		'area_cards' => $area_trees, 'service_detail' => $services_part['widgets']['service_detail'],
		'shop_pages' => $shop_pages, 'shop_widgets' => $shop['widgets'], 'catalog' => $shop['catalog'], 'care_plans' => $care_plans);
}

// The ?name= values of every {{page:estimate}} link of a tree.
function _test_hse_params($tree, $name)
{
	$nodes = array();
	_test_hsv_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:estimate}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:estimate}}?')), $q);
		if (isset($q[$name])) $out[] = $q[$name];
	}
	return $out;
}

function test_homeservices_estimates_pages_and_widgets()
{
	$d = _test_hse_load();
	pg_assert_same(array('estimate', 'estimate_view', 'my_estimates', 'staff_estimates', 'email_estimate_new', 'email_estimate_received'), array_keys($d['pages']));
	pg_assert_same(array('estimate_form', 'estimate_detail', 'estimate_messages', 'my_estimates_list', 'staff_estimates_list', 'email_estimate_admin', 'email_estimate_client'), array_keys($d['widgets']));
	pg_assert_same('public', $d['pages']['estimate']['folder']);
	pg_assert_same('registration', $d['pages']['estimate_view']['folder'], 'a request is read signed in');
	pg_assert_same('registration', $d['pages']['my_estimates']['folder']);
	pg_assert_same('private', $d['pages']['staff_estimates']['folder'], 'only the staff list every request');
	foreach (array('email_estimate_new', 'email_estimate_received') as $key) {
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
	pg_assert_same('submitter_only', $d['widgets']['estimate_detail']['config']['access_control'], 'a request is its sender\'s');
	pg_assert_true(!empty($d['widgets']['my_estimates_list']['config']['viewer_filter_submitter']), 'My Estimates lists the own requests');
	pg_assert_true(!empty($d['widgets']['staff_estimates_list']['config']['search_enabled']), 'the staff search');
}

// The form opens the request's page and mails both sides; its service and
// area start with what the links send, the photos go to the private
// folder, the pick lists are parts.php's and the status is the company's.
function test_homeservices_estimates_form()
{
	$d = _test_hse_load();
	$form = $d['pages']['estimate']['form'];
	pg_assert_same('{{tab:estimate_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_estimate_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_estimate_received}}', $form['confirm_page_id']);
	$c = _test_hsv_controls($d['widgets']['estimate_form']['tree']);
	pg_assert_same(array('service', 'area', 'details', 'photos', 'property_type', 'property_size', 'urgency', 'first_name', 'last_name', 'email', 'phone', 'contact_method', 'status', 'consent'), array_keys($c), 'the fields');
	pg_assert_same($d['service_choices'], $c['service']['options'], 'the services');
	pg_assert_same('service', $c['service']['cf']['url_param'] ?? '', 'the service reads ?service=');
	pg_assert_same($d['area_names'], $c['area']['options'], 'the areas');
	pg_assert_same('area', $c['area']['cf']['url_param'] ?? '', 'the area reads ?area=');
	pg_assert_same('file', $c['photos']['attrs']['type'] ?? '', 'the photos are a file field');
	pg_assert_same('{{folder:private}}', $c['photos']['cf']['upload_folder_id'] ?? '', 'the photos are kept private');
	pg_assert_contains('.jpg', $c['photos']['attrs']['accept'] ?? '', 'the photos take pictures');
	pg_assert_same($d['property_types'], $c['property_type']['options'], 'the property types');
	pg_assert_same($d['property_sizes'], $c['property_size']['options'], 'the property sizes');
	pg_assert_same($d['urgency_choices'], $c['urgency']['options'], 'how soon');
	pg_assert_same(array_column($d['contact_choices'], 0), $c['contact_method']['options'], 'how to reach the customer');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the company\'s');
	pg_assert_same('email_address', $c['email']['cf']['contact_field'] ?? '', 'the e-mail is the contact\'s');
}

// Every value a link sends is an answer of the form: the area cards'
// ?area=, and the service page's own link sends the record's name.
function test_homeservices_estimates_links_choose_in_the_form()
{
	$d = _test_hse_load();
	$c = _test_hsv_controls($d['widgets']['estimate_form']['tree']);
	$seen = array();
	foreach ($d['area_cards'] as $card) {
		foreach (_test_hse_params($card, 'area') as $v) {
			pg_assert_true(in_array($v, $c['area']['options'], true), "?area=$v is an answer");
			$seen[$v] = true;
		}
	}
	pg_assert_same(count($d['area_names']), count($seen), 'every area card asks for an estimate in its area');
	$json = json_encode($d['service_detail']['tree']);
	pg_assert_contains('"page_id":"{{tab:estimate}}","params":[{"name":"service","token":"name"}]', $json, 'the service page asks for an estimate for its service');
}

// The request's page carries its messages, with attachments for more
// photos, and mails each one; the e-mails and lists bind fields the form
// has; the photos are linked for the request's readers.
function test_homeservices_estimates_record_and_mail()
{
	$d = _test_hse_load();
	$comments = $d['pages']['estimate_view']['comments'];
	pg_assert_true($comments['allow_new'] && $comments['login'], 'messages from the signed-in sender');
	pg_assert_true($comments['attachments'], 'more photos as attachments');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
	pg_assert_same('{{site_email}}', $comments['notify_email'], 'the company hears every message');
	$fields = array_keys(_test_hsv_controls($d['widgets']['estimate_form']['tree']));
	foreach (array('estimate_detail', 'my_estimates_list', 'staff_estimates_list', 'email_estimate_admin', 'email_estimate_client') as $key) {
		$nodes = array();
		_test_hsv_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			$token = (string)($n['props']['_bindings']['text'] ?? '');
			if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments'), true)) continue;
			pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the form");
		}
		pg_assert_same('{{tab:estimate}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads the estimate form");
	}
	foreach ($d['widgets']['staff_estimates_list']['config']['search_fields'] as $name) {
		pg_assert_true(in_array($name, $fields, true), "the staff search reads $name");
	}
	$json = json_encode($d['widgets']['estimate_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the staff\'s Edit button');
	pg_assert_contains('"_bindings":{"href":"photos","text":"photos"}', $json, 'the photos are linked');
	foreach (array('email_estimate_admin', 'email_estimate_client') as $key) {
		pg_assert_contains('{{page:estimate_view}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree'], JSON_UNESCAPED_SLASHES), "$key opens the request");
	}
}

// Every shop page and widget needs the shop; the catalog and the receipt
// are wired to the template's pages.
function test_homeservices_shop_pages_need_the_shop()
{
	$d = _test_hse_load();
	pg_assert_same(_test_hs_shop_keys(), array_keys($d['shop_pages']), 'the shop\'s pages');
	foreach ($d['shop_pages'] as $key => $p) {
		pg_assert_same('ecommerce', $p['requires'] ?? '', "$key needs the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['shop_widgets'] as $key => $w) {
		pg_assert_same('ecommerce', $w['requires'] ?? '', "$key needs the shop");
		if ($w['page'] === '') continue;
		pg_assert_true(isset($d['shop_pages'][$w['page']]), "$key sits on a shop page");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['shop_pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_true(!empty($d['shop_pages']['email_order']['noindex']) && $d['shop_pages']['email_order']['search'] === false, 'the receipt is out of the search');
	pg_assert_same('{{tab:email_order}}', $d['shop_widgets']['checkout']['config']['order_receipt_email_page_id']);
	pg_assert_same('{{tab:plans}}', $d['shop_widgets']['product_detail']['config']['catalog_listing_page_id']);
	pg_assert_same('{{product_group:plans}}', $d['shop_widgets']['plan_catalog']['config']['product_group_id']);
	pg_assert_same('{{tab:cart}}', $d['shop_widgets']['plan_catalog']['config']['add_to_cart_next_page_id'], 'the notice links the cart');
	pg_assert_contains('{{page:book}}', json_encode($d['shop_widgets']['email_order']['tree']), 'the receipt leads to the visit booking');
}

// One group: the care plans and the gift card at parts.php's prices, every
// SKU the template's own and once, nothing shipped.
function test_homeservices_shop_catalog()
{
	$d = _test_hse_load();
	$groups = $d['catalog']['groups'];
	pg_assert_same(array('plans'), array_keys($groups), 'one group');
	pg_assert_same('root', $groups['plans']['parent'], 'under the site\'s top group');
	$want = array();
	foreach ($d['care_plans'] as $p) $want[$p['sku']] = $p['price'];
	$got = array();
	foreach ($groups['plans']['products'] as $p) {
		pg_assert_false(isset($got[$p['sku']]), "{$p['sku']} once");
		pg_assert_true(strpos($p['sku'], 'HOMESVC-') === 0, "{$p['sku']} is the template's");
		pg_assert_false($p['shippable'], "{$p['sku']} is not shipped");
		pg_assert_true(trim($p['title']) !== '' && trim($p['description']) !== '', "{$p['sku']}: name and words");
		$got[$p['sku']] = $p['price'];
	}
	pg_assert_same($want, $got, 'the products and their prices');
	pg_assert_true(count($d['catalog']['details']) >= 2, 'the details tab');
}

function test_homeservices_estimates_links_ids_and_classes()
{
	$d = _test_hse_load();
	$known = array_flip(_test_hs_page_keys());
	$json = json_encode(array($d['pages'], $d['widgets'], $d['shop_pages'], $d['shop_widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	$record = json_encode(array($d['pages'], $d['widgets']));
	foreach (_test_hs_shop_keys() as $key) {
		pg_assert_false(strpos($record, '{{page:' . $key . '}}') !== false, "no estimate tree links to $key");
	}
	$trees = array();
	foreach (array($d['pages'], $d['shop_pages']) as $set) foreach ($set as $p) $trees[] = $p['tree'];
	foreach (array($d['widgets'], $d['shop_widgets']) as $set) foreach ($set as $w) $trees[] = $w['tree'];
	$nodes = array();
	foreach ($trees as $t) _test_hsv_nodes($t, $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pbk|psh|pwd|pmu)-/', $c), "class $c is not another template's");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'hs-') === 0, "id $id is the template's");
	}
}

// The sample is never dated.
function test_homeservices_estimates_no_fixed_dates()
{
	$d = _test_hse_load();
	$json = json_encode(array($d['pages'], $d['widgets'], $d['shop_pages'], $d['shop_widgets'], $d['catalog']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace(array('www.w3.org/2000/svg', '{{year}}'), '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; an estimate reads "teklif", a service
// "hizmet", a visit "ziyaret", a care plan "bakım planı", and none says
// "rezervasyon".
function test_homeservices_estimates_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('estimates.php', 'shop.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			// A ^^field^^ token is the field's name, not a word.
			$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
			if (preg_match('/\b[Vv]isits?\b/', $words)) pg_assert_true(mb_stripos($tr, 'ziyaret', 0, 'UTF-8') !== false, "'$key' says ziyaret");
			if (preg_match('/\b[Ss]ervices?\b/', $words)) pg_assert_true(mb_stripos($tr, 'hizmet', 0, 'UTF-8') !== false, "'$key' says hizmet");
			if (preg_match('/\b[Ee]stimates?\b/', $words)) pg_assert_true(mb_stripos($tr, 'teklif', 0, 'UTF-8') !== false, "'$key' says teklif");
			if (preg_match('/\b[Cc]are [Pp]lans?\b/', $words)) pg_assert_true(mb_stripos($tr, 'bakım plan', 0, 'UTF-8') !== false, "'$key' says bakım planı");
			pg_assert_false(mb_stripos($tr, 'rezervasyon', 0, 'UTF-8') !== false, "'$key' never says rezervasyon");
		}
		foreach (array("lang('Service')", "lang('Services')", "lang('Booking')", "lang('book')") as $k) {
			pg_assert_false(strpos($src, $k) !== false, "$file does not use $k");
		}
	}
}
