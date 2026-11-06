<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Photography Studio design template's parts
 * (includes/design_templates/pinegrap-photo/): the studio's data hangs
 * together (a sample gallery per category, packages with a price and a
 * deposit smaller than it, add-ons, the steps of a shoot, gift vouchers
 * and testimonials with what they need, the forms' pick lists without an
 * answer twice), a price is written as the shop writes it, every page link
 * of the shared parts names a page of the finished template and none names
 * a shop page, the footer opens the gallery list filtered, the cards read
 * their form's fields, a package card opens the booking request with it
 * chosen, the photo grid reads the six photo fields, a proof carries its
 * frame number and a download, the classes are the template's own, the
 * sample carries no fixed date and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The parts as the template file reads them: parts.php first, then each
// page file in the same scope. Read once per run.
function _test_ph_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-photo/parts.php';
	$gallery_cards = array();
	foreach (array_keys($galleries) as $key) $gallery_cards[$key] = $gallery_card($key);
	$package_cards = array();
	foreach (array_keys($packages) as $key) $package_cards[$key] = $package_card($key);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'studio'            => $studio,
		'categories'        => $categories,
		'galleries'         => $galleries,
		'packages'          => $packages,
		'package_prices'    => $package_prices,
		'package_deposits'  => $package_deposits,
		'money'             => $money,
		'price_text'        => $price_text,
		'images_text'       => $images_text,
		'addons'            => $addons,
		'shoot_steps'       => $shoot_steps,
		'why_us'            => $why_us,
		'gift_vouchers'     => $gift_vouchers,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'lists'             => array(
			'category_titles'         => $category_titles,
			'gallery_titles'          => $gallery_titles,
			'package_titles'          => $package_titles,
			'booking_package_choices' => $booking_package_choices,
			'time_choices'            => $time_choices,
			'people_choices'          => $people_choices,
			'booking_statuses'        => $booking_statuses,
			'print_choices'           => $print_choices,
			'selection_statuses'      => $selection_statuses,
		),
		'radios'            => array('place_choices' => $place_choices, 'contact_choices' => $contact_choices),
		'gallery_cards'     => $gallery_cards,
		'package_cards'     => $package_cards,
		'testimonial_cards' => $testimonial_cards,
		'photo_grid'        => $photo_grid('park'),
		'proofs'            => array($proof_frame(1), $proof_frame(12)),
		'header'            => $header(),
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $notice(), $booking_band(), $staff_bar(), $post_card(), $ai_summary()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_ph_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_ph_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_ph_trees()
{
	$d = _test_ph_load();
	return array_merge(array($d['header'], $d['footer'], $d['photo_grid']), $d['shared'], $d['proofs'],
		array_values($d['gallery_cards']), array_values($d['package_cards']), $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_ph_page_keys()
{
	return array_merge(array(
		'home', 'packages', 'experience', 'about', 'faq',
		'galleries', 'gallery', 'gallery_new', 'book_online',
		'book', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy', 'email_reply',
		'client_area', 'proofing_sample', 'selection', 'selection_view', 'my_selections', 'staff_selections',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_ph_shop_keys());
}

// The pages that exist only with the shop.
function _test_ph_shop_keys()
{
	return array('vouchers', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_photo_data_hangs_together()
{
	$d = _test_ph_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'promise') as $f) pg_assert_true(trim($d['studio'][$f]) !== '', "studio: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['studio']['phone']), 'studio: phone number');
	pg_assert_true(strpos($d['studio']['email'], '@example.com') !== false, 'studio: a sample address');
	pg_assert_same(6, count($d['categories']), 'six categories');
	$seen = array();
	$featured = 0;
	foreach ($d['galleries'] as $key => $g) {
		pg_assert_true(isset($d['categories'][$g['category']]), "gallery $key: known category");
		foreach (array('title', 'location', 'summary', 'story') as $f) pg_assert_true(trim($g[$f]) !== '', "gallery $key: $f");
		$seen[$g['category']] = true;
		if ($g['featured']) $featured++;
	}
	pg_assert_same(count($d['categories']), count($seen), 'a sample gallery in every category');
	pg_assert_same(3, $featured, 'three galleries on the home');
	pg_assert_same(6, count($d['packages']), 'six packages');
	$popular = 0;
	foreach ($d['packages'] as $key => $p) {
		pg_assert_true(isset($d['categories'][$p['category']]), "package $key: known category");
		pg_assert_true(is_int($p['images']) && $p['images'] > 0, "package $key: photos is a number");
		foreach (array('title', 'length', 'text') as $f) pg_assert_true(trim($p[$f]) !== '', "package $key: $f");
		pg_assert_true(count($p['includes']) >= 3, "package $key: what it includes");
		pg_assert_true(isset($d['package_prices'][$key]['USD']), "package $key: has a price");
		pg_assert_true(isset($d['package_deposits'][$key]['USD']), "package $key: has a deposit");
		foreach ($d['package_prices'][$key] as $cur => $amount) {
			pg_assert_true(isset($d['package_deposits'][$key][$cur]) && $d['package_deposits'][$key][$cur] < $amount, "package $key: the deposit is less than the price in $cur");
		}
		if ($p['popular']) $popular++;
	}
	pg_assert_same(2, $popular, 'two popular packages');
	pg_assert_same(array_keys($d['packages']), array_keys($d['package_prices']), 'a price per package and no other');
	pg_assert_same(array_keys($d['packages']), array_keys($d['package_deposits']), 'a deposit per package and no other');
	foreach ($d['addons'] as $n => $a) pg_assert_true(trim($a['title']) !== '' && isset($a['price']['USD']), "add-on $n");
	pg_assert_same(5, count($d['shoot_steps']), 'five steps of a shoot');
	pg_assert_same(4, count($d['why_us']), 'four reasons');
	$skus = array();
	foreach ($d['gift_vouchers'] as $key => $g) {
		pg_assert_true((bool)preg_match('/^PHOTO-[A-Z0-9-]+$/', $g['sku']), "gift voucher $key: the template's SKU");
		pg_assert_true(isset($g['price']['USD']) && $g['price']['USD'] > 0, "gift voucher $key: a price");
		$skus[$g['sku']] = true;
	}
	pg_assert_same(count($d['gift_vouchers']), count($skus), 'no SKU twice');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['packages'][$t['package']]), "testimonial $n: about a package");
		pg_assert_contains($d['packages'][$t['package']]['title'], $t['role'], "testimonial $n: names the package");
	}
	$l = $d['lists'];
	pg_assert_same(count($d['categories']), count($l['category_titles']), 'an answer per category');
	pg_assert_same(count($d['galleries']), count($l['gallery_titles']), 'an answer per gallery');
	pg_assert_same(count($d['packages']) + 1, count($l['booking_package_choices']), 'booking: an answer per package and not decided');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(5, count($l['booking_statuses']), 'five states of a booking request');
	pg_assert_same(3, count($l['selection_statuses']), 'three states of a proof selection');
}

// A package's price shows one amount: the base currency's figure, the USD
// figure for a currency the table does not list.
function test_photo_price_is_written_as_the_shop_writes_it()
{
	$d = _test_ph_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['package_prices']['family'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	pg_assert_same(sprintf(lang('%s per session'), pg_money_text($amount, $symbol)), call_user_func($d['price_text'], 'family'), 'the price line');
	pg_assert_same(sprintf(lang('%d edited photos'), 40), call_user_func($d['images_text'], 'family'), 'the photos line');
}

function test_photo_page_links_name_template_pages()
{
	$known = array_flip(_test_ph_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_ph_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_photo_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_ph_trees());
	foreach (_test_ph_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_ph_load();
	pg_assert_contains('{{page:book}}', json_encode($d['header']), 'the header books a shoot');
	pg_assert_contains('{{page:client_area}}', json_encode($d['header']), 'the header opens the client area');
	$nodes = array();
	_test_ph_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer opens the gallery list filtered on each category, in the
// words the Category list uses.
function test_photo_footer_filters_the_galleries()
{
	$d = _test_ph_load();
	$nodes = array();
	_test_ph_nodes($d['footer'], $nodes);
	$seen = array();
	$prefix = '{{page:galleries}}?';
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		pg_assert_true(isset($q['category']) && in_array($q['category'], $d['lists']['category_titles'], true), 'the link names a category answer');
		$seen[$q['category']] = true;
	}
	pg_assert_same(count($d['categories']), count($seen), 'a link per category');
}

// The values a card reads from its form's fields, and its texts.
function _test_ph_card_reads($card)
{
	$nodes = array();
	_test_ph_nodes($card, $nodes);
	$tokens = array();
	$texts = array();
	foreach ($nodes as $node) {
		if (!empty($node['props']['_bindings'])) {
			foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
		}
		if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
	}
	return array($tokens, $texts);
}

// A gallery card reads every value from the Galleries form's fields, with
// the sample's own words on the canvas; the photo grid reads the six
// photo fields, each column leaving when its photo is empty.
function test_photo_cards_read_their_records()
{
	$d = _test_ph_load();
	foreach ($d['gallery_cards'] as $key => $card) {
		list($tokens, $texts) = _test_ph_card_reads($card);
		foreach (array('cover', 'title', 'form_item_view', 'category', 'location', 'summary') as $t) {
			pg_assert_true(isset($tokens[$t]), "gallery $key: card reads $t");
		}
		$g = $d['galleries'][$key];
		pg_assert_true(in_array($g['title'], $texts, true), "gallery $key: sample title on the canvas");
		pg_assert_true(in_array($d['categories'][$g['category']]['title'], $texts, true), "gallery $key: sample category on the canvas");
	}
	list($tokens) = _test_ph_card_reads($d['photo_grid']);
	for ($n = 1; $n <= 6; $n++) pg_assert_true(isset($tokens['photo_' . $n]), "the grid reads photo_$n");
	$nodes = array();
	_test_ph_nodes($d['photo_grid'], $nodes);
	$hiding = 0;
	foreach ($nodes as $node) {
		if ($node['type'] === 'col' && strpos($node['props']['cssClass'], 'pg-hide-if-empty') !== false) $hiding++;
	}
	pg_assert_same(6, $hiding, 'every photo column leaves when empty');
}

// A package card opens the booking request with the package chosen, in
// the words the Package list uses (the form starts from ?package=), and
// shows the package's price.
function test_photo_package_card_chooses_in_the_booking_request()
{
	$d = _test_ph_load();
	foreach ($d['package_cards'] as $key => $card) {
		$nodes = array();
		_test_ph_nodes($card, $nodes);
		$hrefs = array();
		foreach ($nodes as $node) {
			if (isset($node['props']['href']) && strpos($node['props']['href'], '{{page:book}}?') === 0) $hrefs[] = $node['props']['href'];
		}
		pg_assert_same(1, count($hrefs), "package $key: one booking link");
		parse_str(substr($hrefs[0], strlen('{{page:book}}?')), $q);
		pg_assert_true(in_array($q['package'], $d['lists']['booking_package_choices'], true), "package $key: the link names an answer");
		list(, $texts) = _test_ph_card_reads($card);
		pg_assert_true(in_array(call_user_func($d['price_text'], $key), $texts, true), "package $key: its price");
	}
}

// A proof shows its frame number and downloads its own picture.
function test_photo_proof_frame_downloads()
{
	$d = _test_ph_load();
	foreach (array(1 => $d['proofs'][0], 12 => $d['proofs'][1]) as $n => $proof) {
		$nodes = array();
		_test_ph_nodes($proof, $nodes);
		$number = sprintf(lang('Frame %02d'), $n);
		$download = null;
		$texts = array();
		foreach ($nodes as $node) {
			if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
			foreach ((array)($node['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'download') $download = $node;
			}
		}
		pg_assert_true(in_array($number, $texts, true), "proof $n: its frame number");
		pg_assert_true($download !== null && strpos($download['props']['href'], '-' . $n . '/') !== false, "proof $n: downloads its own picture");
	}
}

// Classes the template adds of its own start with pph-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with ph-.
function test_photo_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_ph_trees() as $t) _test_ph_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pph-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'ph-') === 0, "id $id is the template's");
	}
	foreach (array('pph-brand', 'pph-display', 'pph-eyebrow', 'pph-price', 'pph-lift', 'pph-quote') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_photo_no_fixed_dates()
{
	$d = _test_ph_load();
	$json = json_encode(array($d['galleries'], $d['packages'], $d['shoot_steps'], $d['gift_vouchers'], _test_ph_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a gallery is "galeri", a package "paket", a
// shoot "çekim", a selection "seçim".
function test_photo_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-photo/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bGaller(y|ies)\b/', $key)) pg_assert_true(mb_stripos($tr, 'galeri', 0, 'UTF-8') !== false, "'$key' says galeri");
		if (preg_match('/\bPackages?\b/', $key)) pg_assert_true(mb_stripos($tr, 'paket', 0, 'UTF-8') !== false, "'$key' says paket");
		if (preg_match('/\bShoot\b/', $key)) pg_assert_true(mb_stripos($tr, 'çekim', 0, 'UTF-8') !== false, "'$key' says çekim");
		if (preg_match('/\bSelections?\b/', $key)) pg_assert_true(mb_stripos($tr, 'seçim', 0, 'UTF-8') !== false, "'$key' says seçim");
	}
}
