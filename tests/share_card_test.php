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
