<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for My library's file (includes/fn/designer_library_io.php): the
 * address kinds an imported item may carry, the checks of a text and of an
 * item in each place it holds strings, the export file and its name, the
 * import's refusals with the item named, and what the report says of each
 * item kept.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/designer_library_io.php');

function _library_io_test_item($name = 'Hero band', $group = 'Heroes')
{
	return array(
		'format' => PG_LIBRARY_FORMAT, 'version' => PG_LIBRARY_VERSION, 'name' => $name, 'group' => $group,
		'tree' => array('type' => 'section', 'props' => array('cssClass' => 'hero-band'), 'children' => array(
			array('type' => 'a', 'props' => array('href' => '/about', 'text' => 'Learn javascript: the basics')),
			array('type' => 'img', 'props' => array('src' => 'data:image/png;base64,AAAA', 'srcset' => '/a.png 1x, /b.png 2x')),
		)),
		'rules' => array(array('media' => array(), 'selector' => '.hero-band', 'body' => 'background: url(/files/a.jpg)')),
		'variables' => array('--band-bg' => '#fafafa'),
		'widgets' => array('w41' => array('name' => 'Signup', 'kind' => 'custom_form',
			'tree' => array('type' => 'div', 'children' => array()), 'config' => array('page_id' => 12, 'redirect_url' => '/thanks'))),
		'shared' => array('s42' => array('name' => 'Footer note', 'tree' => array('type' => 'p', 'props' => array('text' => 'Hi')))),
		'refs' => array(),
	);
}

function _library_io_code($problem)
{
	return $problem ? $problem['code'] . ($problem['detail'] !== '' ? ' ' . $problem['detail'] : '') : '';
}

function test_library_io_address_kinds()
{
	foreach (array('', '/about', 'about.html', '#top', '?q=1', '//cdn.example.com/a.png', 'https://example.com',
		'http://x', 'mailto:a@b.c', 'tel:+90', 'sms:+90', '{{item_url}}', 'data:image/png;base64,AAAA', 'data:image/webp,xx') as $ok) {
		pg_assert_same('', pg_library_address_refused($ok), 'allowed: ' . $ok);
	}
	$bad = array('javascript:alert(1)' => 'javascript:', ' JavaScript:x' => 'javascript:', "java\tscript:x" => 'javascript:',
		'java&#115;cript:x' => 'javascript:', 'vbscript:x' => 'vbscript:', 'data:text/html,<b>' => 'data:',
		'data:image/svg+xml,<svg>' => 'data:', 'file:///etc/passwd' => 'file:', 'blob:x' => 'blob:');
	foreach ($bad as $url => $scheme) {
		pg_assert_same($scheme, pg_library_address_refused($url), 'refused: ' . $url);
	}
}

function test_library_io_text_problems()
{
	pg_assert_same('', _library_io_code(pg_library_text_problem('Learn javascript: the basics')), 'a sentence is not an address');
	pg_assert_same('address javascript:', _library_io_code(pg_library_text_problem('javascript:alert(1)')), 'a running scheme in any prop');
	pg_assert_same('address data:', _library_io_code(pg_library_text_problem('data:text/html,x', 'href')), 'an address field');
	pg_assert_same('', _library_io_code(pg_library_text_problem('data:text/html,x', 'text')), 'not an address field');
	pg_assert_same('script', _library_io_code(pg_library_text_problem('<p>a</p><script>x()</script>')));
	pg_assert_same('script', _library_io_code(pg_library_text_problem('<SCRIPT src="/a.js"></SCRIPT>')));
	pg_assert_same('event onerror', _library_io_code(pg_library_text_problem('<img src="a.png" onerror="x()">')));
	pg_assert_same('event onload', _library_io_code(pg_library_text_problem('<svg/onload=x()>')));
	pg_assert_same('address javascript:', _library_io_code(pg_library_text_problem('<a href="javascript:x()">a</a>')));
	pg_assert_same('address javascript:', _library_io_code(pg_library_text_problem('<a href=javascript:x()>a</a>')), 'an unquoted value');
	pg_assert_same('address javascript:', _library_io_code(pg_library_text_problem('<img srcset="a.png 1x, javascript:x 2x">')));
	pg_assert_same('script', _library_io_code(pg_library_text_problem('<iframe srcdoc="&lt;b&gt;"></iframe>')));
	pg_assert_same('', _library_io_code(pg_library_text_problem('<a href="/about" class="btn">About</a> <b>bold</b>')));
	pg_assert_same('', _library_io_code(pg_library_text_problem('<p>Tom &amp; Jerry: online now</p>')), 'a word before = is no event');
	pg_assert_same('address javascript:', _library_io_code(pg_library_text_problem('background: url("javascript:x")')));
	pg_assert_same('script', _library_io_code(pg_library_text_problem('width: expression(alert(1))')));
	pg_assert_same('', _library_io_code(pg_library_text_problem('background: url(/files/a.jpg) center / cover')));
	pg_assert_same('address vbscript:', _library_io_code(pg_library_text_problem('a.png 1x, vbscript:x 2x', 'srcset')));
}

function test_library_io_item_problem_in_each_place()
{
	$item = _library_io_test_item();
	pg_assert_same('', _library_io_code(pg_library_item_problem($item)), 'a clean item passes');

	$bad = $item;
	$bad['tree']['children'][0]['children'][] = array('type' => 'content', 'props' => array('html' => '<script>x()</script>'));
	pg_assert_same('script', _library_io_code(pg_library_item_problem($bad)), 'a script in a custom HTML node');

	$bad = $item;
	$bad['tree']['props']['_attrs'] = array(array('name' => 'onclick', 'value' => 'x()'));
	pg_assert_same('event onclick', _library_io_code(pg_library_item_problem($bad)), 'an event attribute, named');

	$bad = $item;
	$bad['tree']['props']['_attrs'] = array(array('name' => 'href', 'value' => 'vbscript:x'));
	pg_assert_same('address vbscript:', _library_io_code(pg_library_item_problem($bad)), 'an attribute address');

	$bad = $item;
	$bad['tree']['children'][1]['type'] = 'script';
	pg_assert_same('script', _library_io_code(pg_library_item_problem($bad)), 'a script node');

	$bad = $item;
	$bad['shared']['s42']['tree']['props']['href'] = 'javascript:x';
	pg_assert_same('address javascript:', _library_io_code(pg_library_item_problem($bad)), 'inside a shared component tree');

	$bad = $item;
	$bad['widgets']['w41']['config']['redirect_url'] = 'javascript:x';
	pg_assert_same('address javascript:', _library_io_code(pg_library_item_problem($bad)), 'in a widget setting');

	$bad = $item;
	$bad['rules'][] = array('media' => array(), 'selector' => '.hero-band', 'body' => 'background:url(javascript:x)');
	pg_assert_same('address javascript:', _library_io_code(pg_library_item_problem($bad)), 'in a rule');

	$bad = $item;
	$bad['variables']['--bg'] = 'url(data:text/html,x)';
	pg_assert_same('address data:', _library_io_code(pg_library_item_problem($bad)), 'in a variable');

	$deep = array('type' => 'div', 'children' => array());
	for ($i = 0; $i < 90; $i++) $deep = array('type' => 'div', 'children' => array($deep));
	$bad = $item;
	$bad['tree'] = $deep;
	pg_assert_same('deep', _library_io_code(pg_library_item_problem($bad)), 'deeper than the editor draws');
}

function test_library_io_export_and_import_round_trip()
{
	$item = _library_io_test_item();
	$item['id'] = 7;
	$item['file'] = 'library-hero-band.json';
	$item['order'] = 3;
	$text = pg_library_export_text(array($item, $item), '2026-11-25');
	$doc = json_decode($text, true);
	pg_assert_same(PG_LIBRARY_EXPORT_FORMAT, $doc['format']);
	pg_assert_same('2026-11-25', $doc['exported']);
	pg_assert_same(2, count($doc['items']));
	pg_assert_false(isset($doc['items'][0]['id']), 'the row id stays on this site');
	pg_assert_false(isset($doc['items'][0]['file']));
	pg_assert_false(isset($doc['items'][0]['order']));

	pg_assert_same('studio-calm-library.json', pg_library_export_file_name('Studio Calm'), 'named after the site');
	pg_assert_same('library.json', pg_library_export_file_name(''));
	pg_assert_same('musteri-hizmetleri-library.json', pg_library_export_file_name('Müşteri Hizmetleri'), 'a Turkish name keeps its letters as plain ones');
	pg_assert_same('cafe-isik-library.json', pg_library_export_file_name('Café IŞIK'), 'accents and the dotted capital');

	$read = pg_library_import_read($text);
	pg_assert_true(isset($read['items']), 'an export reads back');
	pg_assert_same(2, count($read['items']));
	pg_assert_same($item['tree'], $read['items'][0]['tree']);
	pg_assert_same($item['rules'], $read['items'][0]['rules']);

	$one = pg_library_import_read(pg_library_item_encode($item));
	pg_assert_same(1, count($one['items']), 'one item\'s own file is read as well');
}

function test_library_io_import_refusals()
{
	pg_assert_same('not_a_library', pg_library_import_read('  ')['error']);
	pg_assert_same('not_a_library', pg_library_import_read('{"a":1}')['error']);
	pg_assert_same('not_a_library', pg_library_import_read("\xFF\xD8\xFF photo")['error']);
	pg_assert_same('not_a_library', pg_library_import_read('{"format":"pinegrap-library","version":1,"items":[]}')['error']);
	pg_assert_same('version', pg_library_import_read('{"format":"pinegrap-library","version":99,"items":[]}')['error']);
	pg_assert_same('too_large', pg_library_import_read(str_repeat('x', PG_LIBRARY_EXPORT_MAX + 1))['error'], 'larger than 1 MB');

	$newer = _library_io_test_item();
	$newer['version'] = PG_LIBRARY_VERSION + 1;
	pg_assert_same('version', pg_library_import_read(json_encode($newer))['error'], 'an item of a newer Pinegrap');

	$item = _library_io_test_item();
	$bad = _library_io_test_item('Promo banner');
	$bad['tree']['props']['_attrs'] = array(array('name' => 'onclick', 'value' => 'x()'));
	$read = pg_library_import_read(pg_library_export_text(array($item, $bad), '2026-11-25'));
	pg_assert_same('event', $read['error'], 'one bad item refuses the file');
	pg_assert_same('Promo banner', $read['item'], 'and names it');
	pg_assert_same('onclick', $read['detail']);
	pg_assert_false(isset($read['items']), 'nothing of the file is kept');

	pg_assert_same('“old-kit.json” holds an event attribute (onclick) in the item “Promo banner”.',
		pg_library_import_message('event', 'old-kit.json', 'Promo banner', 'onclick'));
	pg_assert_same('“old-kit.json” holds an address that is not allowed (javascript:) in the item “Promo banner”.',
		pg_library_import_message('address', 'old-kit.json', 'Promo banner', 'javascript:'));
	pg_assert_same('“photo.json” is not a library file.', pg_library_import_message('not_a_library', 'photo.json', ''));
}

function test_library_io_import_plan_keeps_names_and_says_where()
{
	$existing = array(
		array('name' => 'Three plans', 'group' => 'Pricing', 'order' => 4),
		array('name' => 'Hero, centred', 'group' => 'Heroes', 'order' => 1),
	);
	$items = array(
		_library_io_test_item('Hero, split', 'heroes'),
		_library_io_test_item('three PLANS', 'Pricing'),
		_library_io_test_item('Team grid', 'People'),
		_library_io_test_item('Team list', 'People'),
		_library_io_test_item('Footer, two rows', ''),
	);
	$plan = pg_library_import_plan($items, $existing);
	$notes = array();
	$orders = array();
	foreach ($plan as $entry) {
		$notes[] = $entry['note'];
		$orders[] = $entry['item']['order'];
	}
	pg_assert_same(array('group', 'name_kept', 'new_group', 'group', 'no_group'), $notes);
	pg_assert_same('three PLANS', $plan[1]['item']['name'], 'a name in use is kept as it is: both are kept');
	pg_assert_same(array(5, 6, 7, 8, 9), $orders, 'after the library\'s last');
}

function test_library_io_hunt_event_attribute_in_capitals()
{
	pg_assert_same('event onerror', _library_io_code(pg_library_text_problem('<img src="/a.png" ONERROR="alert(1)">', 'html')),
		'an attribute name in capitals is the same attribute');
	pg_assert_same('event onload', _library_io_code(pg_library_text_problem('<svg/OnLoad=alert(1)>', 'html')));
}

function test_library_io_hunt_tag_read_whole()
{
	pg_assert_same('event onerror', _library_io_code(pg_library_text_problem('<img alt=">" onerror="alert(1)" src="/a.png">', 'html')),
		'a quoted > does not end the tag');
	pg_assert_same('address javascript:', _library_io_code(pg_library_text_problem("<a title='>' href=\"java\tscript:x\">x</a>", 'html')));
	pg_assert_same('event onerror', _library_io_code(pg_library_text_problem('<p>Hi</p><img src=x onerror=alert(1)//', 'html')),
		'a tag left open at the end is closed by the markup after it');
	pg_assert_same('event onclick', _library_io_code(pg_library_text_problem("<a title=it's onclick=x>y</a>", 'html')),
		'an unquoted value with a quote mark');
	pg_assert_same('', _library_io_code(pg_library_text_problem('Price < 5 and 3 > 1, one = two', 'text')));
}

function test_library_io_hunt_reference_without_semicolon()
{
	pg_assert_same('javascript:', pg_library_address_refused('java&#115cript:alert(1)'), 'a numeric reference without its ;');
	pg_assert_same('javascript:', pg_library_address_refused('&#x6A;avascript&#58alert(1)'));
	pg_assert_same('address javascript:', _library_io_code(pg_library_text_problem('<a href="&#106avascript:x">y</a>', 'html')));
	pg_assert_same('', pg_library_address_refused('/a?b=1&#38c=2'));
}

function test_library_io_hunt_style_url()
{
	pg_assert_same('address javascript:', _library_io_code(pg_library_css_problem('a{background:url("javascript:alert(1)")}')),
		'a quoted address holding )');
	pg_assert_same('address data:', _library_io_code(pg_library_css_problem("a{background:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E\")}")),
		'a quoted address holding the other quote');
	pg_assert_same('address data:', _library_io_code(pg_library_css_problem('a{background:url(\\64 ata:text/html,x)}')),
		'a CSS hex escape');
	pg_assert_same('script', _library_io_code(pg_library_css_problem('a{width:expr\\65 ssion(alert(1))}')));
	pg_assert_same('', _library_io_code(pg_library_css_problem("a{background:url('/files/a b.jpg')} b{content:\"\\201C\"}")));
}

function test_library_io_hunt_svg_animation()
{
	pg_assert_same('address javascript:', _library_io_code(pg_library_text_problem(
		'<svg><a><animate attributeName="href" values="javascript:alert(1)"/><text>x</text></a></svg>', 'html')));
	pg_assert_same('address javascript:', _library_io_code(pg_library_text_problem(
		'<svg><a><set attributeName="href" to="javascript:alert(1)"/></a></svg>', 'html')));
	pg_assert_same('address javascript:', _library_io_code(pg_library_text_problem(
		'<svg><animate attributeName="href" values="/a;javascript:alert(1)"/></svg>', 'html')));
	pg_assert_same('', _library_io_code(pg_library_text_problem('<svg><animate attributeName="opacity" values="0;1" from="0" to="1"/></svg>', 'html')));
}

function test_library_io_hunt_style_not_utf8_still_read()
{
	pg_assert_same('address javascript:', _library_io_code(pg_library_css_problem("a{content:'\xff'} b{background:url(\"javascript:x\")}")));
}
