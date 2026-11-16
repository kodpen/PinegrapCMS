<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the share card (includes/fn/share_card.php): the title's wrap by
 * measured width, the card's document, the signature and the address, which
 * request is answered with a card, when a host cannot draw one, the file's
 * place and the sweep of cards not asked for.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/share_card.php');

/**
 * A measure where every letter is 10 pixels wide.
 *
 * @return callable
 */
function share_card_test_measure()
{
	return function ($text) {
		return 10.0 * mb_strlen((string) $text);
	};
}

/**
 * The state of a site the card is signed against.
 *
 * @return array
 */
function share_card_test_state()
{
	return array('logo' => 'logo.png|100|200', 'background' => '#112233', 'color' => '#ffffff', 'name' => 'Example Shop');
}

function test_share_card_wrap_short_title()
{
	pg_assert_same(array('Hello world'), pg_share_card_wrap('Hello world', share_card_test_measure(), 200));
	pg_assert_same(array('Hello world'), pg_share_card_wrap("  Hello \n\t world  ", share_card_test_measure(), 200), 'white space is one space');
	pg_assert_same(array(), pg_share_card_wrap('   ', share_card_test_measure(), 200));
}

function test_share_card_wrap_three_full_lines()
{
	// 10 letters a line: "aaaa bbbb" fits (9), a third word does not.
	$lines = pg_share_card_wrap('aaaa bbbb cccc dddd eeee ffff', share_card_test_measure(), 100);
	pg_assert_same(array('aaaa bbbb', 'cccc dddd', 'eeee ffff'), $lines, 'three lines that fit stay whole');

	$cut = pg_share_card_wrap('aaaa bbbb cccc dddd eeee ffff gggg', share_card_test_measure(), 100);
	pg_assert_same(3, count($cut), 'never more than three lines');
	pg_assert_same(array('aaaa bbbb', 'cccc dddd'), array_slice($cut, 0, 2));
	pg_assert_same('eeee ffff…', $cut[2], 'the last line ends with an ellipsis');

	$tight = pg_share_card_wrap('aaaa bbbb cccc dddd eeeee fffff gggg', share_card_test_measure(), 100);
	pg_assert_same('eeeee…', $tight[2], 'letters go until the ellipsis fits, no space before it');
	pg_assert_true(10 * mb_strlen($tight[2]) <= 100);

	pg_assert_same(array('one', 'two'), pg_share_card_wrap('one two', share_card_test_measure(), 30, 2));
}

function test_share_card_wrap_long_word()
{
	$lines = pg_share_card_wrap('abcdefghijklmnopqrstuvwxy', share_card_test_measure(), 100);
	pg_assert_same(array('abcdefghij', 'klmnopqrst', 'uvwxy'), $lines, 'a word longer than a line is cut into lines');

	$after = pg_share_card_wrap('go abcdefghijkl', share_card_test_measure(), 100);
	pg_assert_same(array('go', 'abcdefghij', 'kl'), $after, 'the long word starts its own line');

	$many = pg_share_card_wrap(str_repeat('x', 45), share_card_test_measure(), 100);
	pg_assert_same(3, count($many));
	pg_assert_same(str_repeat('x', 9) . '…', $many[2]);

	pg_assert_same(array('ççç', 'ğğ'), pg_share_card_wrap('çççğğ', share_card_test_measure(), 30), 'letters, not bytes');
}

function test_share_card_signature_changes_with_title()
{
	$state = share_card_test_state();
	$one = pg_share_card_signature('page', 12, 'Spring sale', $state, 'key');

	pg_assert_true((bool) preg_match('/^[0-9a-f]{32}$/', $one));
	pg_assert_same($one, pg_share_card_signature('page', 12, 'Spring sale', $state, 'key'), 'the same item signs the same');
	pg_assert_false($one === pg_share_card_signature('page', 12, 'Summer sale', $state, 'key'), 'a changed title is a new address');
	pg_assert_false($one === pg_share_card_signature('page', 13, 'Spring sale', $state, 'key'));
	pg_assert_false($one === pg_share_card_signature('product', 12, 'Spring sale', $state, 'key'));
	pg_assert_false($one === pg_share_card_signature('page', 12, 'Spring sale', $state, 'other'));

	foreach (array('logo' => '', 'background' => '#000000', 'color' => '#000001', 'name' => 'Other') as $field => $value) {
		pg_assert_false($one === pg_share_card_signature('page', 12, 'Spring sale', array($field => $value) + $state, 'key'), $field . ' is signed');
	}

	pg_assert_same('', pg_share_card_signature('page', 12, 'Spring sale', $state, ''), 'no key, no card');
	pg_assert_same('', pg_share_card_signature('other', 12, 'Spring sale', $state, 'key'));
	pg_assert_same('', pg_share_card_signature('page', 0, 'Spring sale', $state, 'key'));
	pg_assert_same('', pg_share_card_signature('page', 12, '', $state, 'key'));
}

function test_share_card_href_and_request()
{
	$sig = str_repeat('a', 32);
	$href = pg_share_card_href('https://example.com/', 'page', 12, $sig);
	pg_assert_same('https://example.com/get_share_card.php?k=page&i=12&s=' . $sig, $href);
	pg_assert_false(strpos($href, 'Spring') !== false, 'no text travels in the address');

	pg_assert_same(array('kind' => 'page', 'id' => 12, 'signature' => $sig), pg_share_card_request(array('k' => 'page', 'i' => '12', 's' => $sig)));
	pg_assert_same(array('kind' => 'record', 'id' => 7, 'signature' => $sig), pg_share_card_request(array('k' => 'record', 'i' => '7', 's' => $sig)));
	pg_assert_same(null, pg_share_card_request(array('k' => 'user', 'i' => '12', 's' => $sig)));
	pg_assert_same(null, pg_share_card_request(array('k' => 'page', 'i' => '012', 's' => $sig)));
	pg_assert_same(null, pg_share_card_request(array('k' => 'page', 'i' => '12', 's' => strtoupper($sig))));
	pg_assert_same(null, pg_share_card_request(array('k' => 'page', 'i' => '12')));
	pg_assert_same(null, pg_share_card_request(array('k' => array('page'), 'i' => '12', 's' => $sig)));
}

function test_share_card_find_answers_only_the_current_signature()
{
	$state = share_card_test_state();
	$sig = pg_share_card_signature('page', 12, 'Spring sale', $state, 'key');
	$order = pg_share_card_order_put(array(), 'en', 'Spring sale', $sig, 3, 1000);
	$request = array('kind' => 'page', 'id' => 12, 'signature' => $sig);

	$found = pg_share_card_find($request, $order, true, $state, 'key');
	pg_assert_same('Spring sale', $found['title']);
	pg_assert_same(3, $found['page']);

	pg_assert_same(null, pg_share_card_find($request, $order, false, $state, 'key'), 'a private item answers 404');

	// The page printed a new title in the same language: the old address
	// names nothing any more.
	$new = pg_share_card_signature('page', 12, 'Summer sale', $state, 'key');
	$renamed = pg_share_card_order_put($order, 'en', 'Summer sale', $new, 3, 2000);
	pg_assert_same(null, pg_share_card_find($request, $renamed, true, $state, 'key'), 'a stale signature answers 404');
	pg_assert_same('Summer sale', pg_share_card_find(array('signature' => $new) + $request, $renamed, true, $state, 'key')['title']);

	// Another language keeps its own entry.
	$both = pg_share_card_order_put($order, 'tr', 'Bahar indirimi', pg_share_card_signature('page', 12, 'Bahar indirimi', $state, 'key'), 3, 2000);
	pg_assert_same('Spring sale', pg_share_card_find($request, $both, true, $state, 'key')['title']);

	pg_assert_same(null, pg_share_card_find($request, $order, true, array('logo' => 'new.png|1|2') + $state, 'key'), 'a new logo makes the old address stale');
	pg_assert_same(null, pg_share_card_find($request, $order, true, array('name' => 'Renamed') + $state, 'key'), 'so does a new site name');
	pg_assert_same(null, pg_share_card_find(array('id' => 13) + $request, $order, true, $state, 'key'), 'an entry belongs to its own item');
	pg_assert_same(null, pg_share_card_find($request, array(), true, $state, 'key'), 'a title no page printed is never drawn');

	pg_assert_same($order, pg_share_card_order_put($order, 'en', 'Spring sale', $sig, 3, 1500), 'printing the same title again writes nothing new within a day');
}

function test_share_card_document()
{
	$doc = pg_share_card_document(array('Spring', 'sale'), 'Example Shop', array('background' => '#112233', 'color' => '#ffffff'), null);
	pg_assert_same(array('width' => 1200, 'height' => 630, 'background' => '#112233'), $doc['artboard']);
	pg_assert_same(2, count($doc['layers']), 'title and site name, no logo');
	pg_assert_same("Spring\nsale", $doc['layers'][0]['text']);
	pg_assert_same('g:Roboto', $doc['layers'][0]['font']);
	pg_assert_same('#ffffff', $doc['layers'][0]['color']);
	pg_assert_same('Example Shop', $doc['layers'][1]['text']);

	$logo = pg_share_card_document(array('Spring sale'), 'Example Shop', array('background' => '#112233', 'color' => '#ffffff'), array('width' => 400, 'height' => 100));
	pg_assert_same('image', $logo['layers'][0]['type']);
	pg_assert_same(pg_share_card_logo_src(), $logo['layers'][0]['src']);
	pg_assert_same(96.0, (float) $logo['layers'][0]['h']);
	pg_assert_same(384.0, (float) $logo['layers'][0]['w'], 'the logo keeps its proportions');

	$wide = pg_share_card_document(array('A'), 'B', array('background' => '#000000', 'color' => '#ffffff'), array('width' => 4000, 'height' => 100));
	pg_assert_true($wide['layers'][0]['w'] <= 480, 'a wide logo is held to its box');

	// Every layer is one the server's renderer knows.
	$plan = pg_image_draw_plan($doc, array('shapes' => true, 'text' => true, 'icons' => false, 'svg' => false, 'pictures' => array('png')));
	pg_assert_same('', $plan['error']);
}

function test_share_card_host_that_cannot_draw()
{
	$all = array('shapes' => true, 'text' => true, 'icons' => true, 'svg' => true, 'pictures' => array('png'), 'paused' => 0, 'engine' => 'test');

	pg_assert_same('', pg_share_card_unable($all, 'Spring sale', 'Example Shop'));
	pg_assert_same('', pg_share_card_unable($all, 'Çok güzel bir başlık – €5', 'Örnek Dükkân'));
	pg_assert_same('server', pg_share_card_unable(array('text' => false) + $all, 'Spring sale', 'Example Shop'), 'no text drawing on this host');
	pg_assert_same('server', pg_share_card_unable(array('shapes' => false) + $all, 'Spring sale', 'Example Shop'));
	pg_assert_same('server', pg_share_card_unable(array(), 'Spring sale', 'Example Shop'), 'no Imagick');
	pg_assert_same('letters', pg_share_card_unable($all, 'سلام', 'Example Shop'), 'a script that needs shaping');
	pg_assert_same('letters', pg_share_card_unable($all, 'Spring sale', '日本の店'), 'letters the font files lack');
	pg_assert_same('letters', pg_share_card_unable($all, 'Sale 🎉', 'Example Shop'));
}

function test_share_card_path()
{
	$sig = str_repeat('b', 32);
	pg_assert_same('/x/cache/share/page-12-' . $sig . '.png', pg_share_card_path('/x/cache/share', 'page', 12, $sig));
	pg_assert_same('/x/cache/share/page-12.json', pg_share_card_order_path('/x/cache/share', 'page', 12));
	pg_assert_same('', pg_share_card_path('', 'page', 12, $sig));
}

function test_share_card_sweep()
{
	$dir = sys_get_temp_dir() . '/pg_share_card_test_' . bin2hex(random_bytes(4));
	@mkdir($dir, 0755, true);
	$now = time();
	$old = $now - 31 * 86400;
	$files = array('page-1-' . str_repeat('a', 32) . '.png' => $old, 'page-2-' . str_repeat('b', 32) . '.png' => $now - 86400,
		'page-1.json' => $old, 'page-2.json' => $now, 'page-3-' . str_repeat('c', 32) . '.fail' => $now - 7200, 'keep.txt' => $old);

	foreach ($files as $name => $time) {
		file_put_contents($dir . '/' . $name, 'x');
		touch($dir . '/' . $name, $time);
	}

	pg_assert_same(3, pg_share_card_sweep($dir, $now), 'an old card, an old order and a spent failure mark');
	pg_assert_false(is_file($dir . '/page-1-' . str_repeat('a', 32) . '.png'));
	pg_assert_true(is_file($dir . '/page-2-' . str_repeat('b', 32) . '.png'), 'a card asked for lately stays');
	pg_assert_false(is_file($dir . '/page-1.json'));
	pg_assert_true(is_file($dir . '/page-2.json'));
	pg_assert_true(is_file($dir . '/keep.txt'), 'only the card files are touched');

	pg_assert_same(0, pg_share_card_sweep($dir . '/missing', $now));

	array_map('unlink', (array) glob($dir . '/*'));
	@rmdir($dir);
}

function test_share_card_wrap_with_the_bundled_font()
{
	$limits = pg_share_card_limits();
	$width = $limits['width'] - 2 * $limits['pad'];
	$measure = pg_share_card_measure($limits['title']['size'], $limits['title']['weight']);
	$lines = pg_share_card_wrap(str_repeat('Autumn collection of handmade ceramic cups ', 6), $measure, $width, $limits['title']['lines']);

	pg_assert_same(3, count($lines));
	pg_assert_same('…', mb_substr($lines[2], -1));

	foreach ($lines as $line) {
		pg_assert_true($measure($line) <= $width, 'every line fits the card: ' . $line);
	}

	pg_assert_true($measure('WWW') > $measure('iii'), 'letters are measured by their own widths');
}

function test_share_card_public_queries()
{
	$page = pg_share_card_page_sql(12);
	pg_assert_contains('page.page_folder', $page);
	pg_assert_contains('folder.folder_archived', $page, 'an archived folder is read with the page');
	pg_assert_contains('LEFT JOIN folder ON page.page_folder = folder.folder_id', $page);
	pg_assert_contains("WHERE page.page_id = '12'", $page);

	pg_assert_same("SELECT id FROM forms WHERE id = '7' AND page_id <> '0'", pg_share_card_item_sql('record', 7), 'records live in forms');
	pg_assert_same("SELECT id FROM products WHERE id = '7' AND enabled = '1'", pg_share_card_item_sql('product', '7x'));
	pg_assert_same('', pg_share_card_item_sql('page', 7), 'a page needs no item row');

	// The live check builds its queries from these two and nothing else.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/share_card.php');
	pg_assert_false(strpos($source, 'submitted_forms') !== false, 'no table of that name');
}

function test_share_card_page_open()
{
	$row = array('page_folder' => '5', 'folder_archived' => '0');

	pg_assert_true(pg_share_card_page_open($row, 'public', false));
	pg_assert_false(pg_share_card_page_open(array('folder_archived' => '1') + $row, 'public', false), 'an archived page answers 410, so no card');
	pg_assert_false(pg_share_card_page_open($row, 'private', false), 'a private folder');
	pg_assert_false(pg_share_card_page_open($row, 'registration', false), 'a folder only members open');
	pg_assert_false(pg_share_card_page_open($row, 'public', true), 'the Recycle Bin');
	pg_assert_false(pg_share_card_page_open(null, 'public', false), 'no such page');
	pg_assert_false(pg_share_card_page_open(array('page_folder' => '') + $row, 'public', false));
}

/**
 * The facts of a plain page drawn at its own address with the switch on.
 *
 * @return array
 */
function share_card_test_facts()
{
	return array('on' => true, 'email' => false, 'custom_image' => false, 'page_id' => 4, 'page_type' => 'standard',
		'own' => true, 'status' => 200, 'record' => 0, 'product' => 0, 'item' => false);
}

function test_share_card_target_switch_off()
{
	pg_assert_same(array('kind' => 'page', 'id' => 4), pg_share_card_target(share_card_test_facts()));
	pg_assert_same(null, pg_share_card_target(array('on' => false) + share_card_test_facts()), 'no card with the switch off');
	pg_assert_same(null, pg_share_card_target(array('on' => false, 'record' => 9) + share_card_test_facts()));
	pg_assert_false(pg_share_card_switch(''), 'off unless set');
	pg_assert_false(pg_share_card_switch('0'));
	pg_assert_true(pg_share_card_switch('1'));
}

function test_share_card_target_items()
{
	pg_assert_same(array('kind' => 'record', 'id' => 9), pg_share_card_target(array('record' => 9, 'own' => false, 'item' => true) + share_card_test_facts()), 'a record page');
	pg_assert_same(array('kind' => 'product', 'id' => 3), pg_share_card_target(array('product' => 3, 'own' => false, 'item' => true) + share_card_test_facts()), 'a product page');
	pg_assert_same(null, pg_share_card_target(array('item' => true, 'own' => false) + share_card_test_facts()), 'a product group or a filtered list keeps the default');
	pg_assert_same(null, pg_share_card_target(array('own' => false) + share_card_test_facts()), 'an address with a page number or query');
	pg_assert_same(null, pg_share_card_target(array('status' => 404) + share_card_test_facts()));
	pg_assert_same(null, pg_share_card_target(array('page_type' => 'error') + share_card_test_facts()));
	pg_assert_same(null, pg_share_card_target(array('email' => true) + share_card_test_facts()), 'never in an e-mail');
	pg_assert_same(null, pg_share_card_target(array('record' => 9, 'status' => 404) + share_card_test_facts()), 'a record that is not there');
}

function test_share_card_custom_meta_tag_wins()
{
	pg_assert_same(null, pg_share_card_target(array('custom_image' => true) + share_card_test_facts()), 'a custom og:image tag wins');
	pg_assert_true(pg_share_card_meta_has_image(array(array('name' => 'og:image', 'content' => 'x.png'))));
	pg_assert_true(pg_share_card_meta_has_image(array(), array(array('name' => 'OG:Image', 'content' => 'x.png'))), 'the page list or the design list');
	pg_assert_false(pg_share_card_meta_has_image(array(array('name' => 'og:image:alt', 'content' => 'x'))), 'only the picture itself');
	pg_assert_false(pg_share_card_meta_has_image(array(), array()));
}

function test_share_card_item_picture_wins()
{
	$own = array('url' => 'https://example.com/cup.jpg', 'width' => 800, 'height' => 800, 'alt' => '');
	$card = pg_share_card_og_image_value('https://example.com/get_share_card.php?k=page&i=4&s=' . str_repeat('a', 32));

	pg_assert_same($own, pg_share_card_pick($own, $card), "an item's own picture wins");
	pg_assert_same($card, pg_share_card_pick(null, $card), 'the card where the default would stand');
	pg_assert_same(null, pg_share_card_pick(null, null), 'no card: the default is chosen after');
	pg_assert_same(array('url' => $card['url'], 'width' => 1200, 'height' => 630, 'type' => 'image/png', 'alt' => ''), $card, 'width, height and type for the page (S11)');
	pg_assert_same(null, pg_share_card_og_image_value(''));
}

function test_share_card_served_language_title()
{
	$state = share_card_test_state();
	$en = pg_share_card_signature('page', 4, 'Spring sale', $state, 'key');
	$tr = pg_share_card_signature('page', 4, 'Bahar indirimi', $state, 'key');
	pg_assert_false($en === $tr, 'each served title signs its own address');

	$order = pg_share_card_order_put(pg_share_card_order_put(array(), 'en', 'Spring sale', $en, 4, 100), 'tr', 'Bahar indirimi', $tr, 4, 100);
	$request = array('kind' => 'page', 'id' => 4, 'signature' => $tr);
	pg_assert_same(array('title' => 'Bahar indirimi', 'page' => 4, 'language' => 'tr'), pg_share_card_find($request, $order, true, $state, 'key'), 'the Turkish page draws the Turkish title');
	pg_assert_same('en', pg_share_card_find(array('signature' => $en) + $request, $order, true, $state, 'key')['language']);
}

function test_share_card_settings_values()
{
	pg_assert_same('#1f2937', pg_share_card_color_value('', '#1f2937'));
	pg_assert_same('#112233', pg_share_card_color_value(' #112233 ', '#1f2937'));
	pg_assert_same('#aabbcc', pg_share_card_color_value('#AABBCC', '#1f2937'), 'kept in lower case');
	pg_assert_same('#1f2937', pg_share_card_color_value('red', '#1f2937'), 'only #rrggbb');
	pg_assert_same('#1f2937', pg_share_card_color_value('#abc', '#1f2937'), 'the colour field sends six digits');
	pg_assert_same('#1f2937', pg_share_card_color_value(array('#112233'), '#1f2937'));
}

function test_share_card_covers_default_warning()
{
	$all = array('shapes' => true, 'text' => true, 'icons' => true, 'svg' => true, 'pictures' => array('png'), 'paused' => 0, 'engine' => 'test');

	pg_assert_true(pg_share_card_active(true, $all), 'a drawn card is a share picture: the record warning goes');
	pg_assert_false(pg_share_card_active(false, $all), 'switch off: the warning stays');
	pg_assert_false(pg_share_card_active(true, array('text' => false) + $all), 'a host that cannot draw: the warning stays');
	pg_assert_same('', pg_share_card_reason_text(''));
	pg_assert_true(pg_share_card_reason_text('server') !== '');
	pg_assert_true(pg_share_card_reason_text('letters') !== '');
	pg_assert_false(pg_share_card_reason_text('server') === pg_share_card_reason_text('letters'), 'the part says which of the two applies');
}

function test_share_card_root_is_the_software_folder()
{
	pg_assert_same('https://example.com/pinegrap/', pg_share_card_root('https://example.com', '/', 'pinegrap'), 'beside get_file.php, not at the site root');
	pg_assert_same('https://example.com/shop/cms/', pg_share_card_root('https://example.com', '/shop/', 'cms'));
	pg_assert_same('https://example.com/pinegrap/get_share_card.php?k=page&i=4&s=' . str_repeat('a', 32),
		pg_share_card_href(pg_share_card_root('https://example.com', '/', 'pinegrap'), 'page', 4, str_repeat('a', 32)));
}
