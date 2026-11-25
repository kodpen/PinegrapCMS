<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Page details" of a sketch page in the designer's Prototype view: the
 * pure values and checks (assets/js/pg_sketch_details.js,
 * window.PGSketchDetails), run in Node and skipped where Node cannot run,
 * and the menus the server lists for it (pg_designer_sketch_menus()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PGSketchDetails loaded (as D); its `out`
// comes back decoded. Null when Node cannot run.
function sketch_details_test_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_sketch_details.js') . "\n"
		. "var D = window.PGSketchDetails, out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgskd');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// The address a name makes: plain letters, digits and single hyphens.
function test_sketch_details_address()
{
	$out = sketch_details_test_node(<<<'JS'
out = [
    D.address('Yoga Sınıfları'),
    D.address('  Çok Güzel -- Şey!  '),
    D.address('Café & Crème'),
    D.address('?%+'),
    D.address(new Array(150).join('a'))
];
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same('yoga-siniflari', $out[0], 'Turkish letters folded');
	pg_assert_same('cok-guzel-sey', $out[1], 'runs of anything else are one hyphen, none at the ends');
	pg_assert_same('cafe-creme', $out[2], 'letters with a mark as the plain letter');
	pg_assert_same('', $out[3], 'nothing left of signs only');
	pg_assert_same(100, strlen($out[4]), 'at most the page_name column');
}

// The window's values: defaults that need no choice, what the sketch keeps,
// a folder no longer offered.
function test_sketch_details_values()
{
	$out = sketch_details_test_node(<<<'JS'
out = {
    fresh: D.values({ id: 7, name: '' }, { fallback: 'Page 7', folder: 3, folders: [3, 5] }),
    kept: D.values({ name: 'Classes', details: { address: 'yoga', typed: true, type: 'catalog', menu: 2, menuName: 'Main', after: 9, afterName: 'Home', title: 'T', description: 'D', folder: 5 } }, { folder: 3, folders: [3, 5] }),
    gone: D.values({ name: 'A', details: { folder: 8, type: 'nope' } }, { folder: 3, folders: [3, 5] }),
    follow: D.values({ name: 'New Name', details: { address: 'old', typed: false } }, { folder: 3 })
};
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array('name' => 'Page 7', 'address' => 'page-7', 'typed' => false, 'type' => 'standard', 'menu' => 0, 'menuName' => '', 'after' => 0, 'afterName' => '', 'title' => '', 'description' => '', 'folder' => 3), $out['fresh'], 'the defaults');
	pg_assert_same(array('Classes', 'yoga', true, 'catalog', 2, 9, 'T', 'D', 5), array($out['kept']['name'], $out['kept']['address'], $out['kept']['typed'], $out['kept']['type'], $out['kept']['menu'], $out['kept']['after'], $out['kept']['title'], $out['kept']['description'], $out['kept']['folder']), 'what the sketch keeps');
	pg_assert_same(array(3, 'standard'), array($out['gone']['folder'], $out['gone']['type']), 'a folder not offered and an unknown type give way to the defaults');
	pg_assert_same('new-name', $out['follow']['address'], 'an address not typed follows the name');
}

// The checks before a save, in the window's order.
function test_sketch_details_check()
{
	$out = sketch_details_test_node(<<<'JS'
var ok = { name: 'Classes', address: 'classes', title: '', description: '' };
out = {
    ok: D.check(ok, { used: [{ name: 'Home', address: 'home' }] }),
    empty: D.check({ name: ' ', address: '' }, {}),
    chars: D.check({ name: 'A', address: 'a?b' }, {}),
    mine: D.check(ok, { used: [{ name: 'Page 3', address: 'Classes' }] }),
    server: D.check(ok, { server: { used: 'classes', taken: false } }),
    taken: D.check(ok, { server: { used: '', taken: true } }),
    long: D.check({ name: 'A', address: 'a', title: new Array(300).join('x'), description: new Array(300).join('x') }, {})
};
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(), $out['ok'], 'right values pass');
	pg_assert_same(array(array('field' => 'name', 'code' => 'empty', 'page' => ''), array('field' => 'address', 'code' => 'empty', 'page' => '')), $out['empty'], 'a name and an address are needed');
	pg_assert_same('chars', $out['chars'][0]['code'], 'a ? in the address would not open');
	pg_assert_same(array('field' => 'address', 'code' => 'used', 'page' => 'Page 3'), $out['mine'][0], 'another page of the design has it, without case');
	pg_assert_same(array('field' => 'address', 'code' => 'used', 'page' => 'classes'), $out['server'][0], 'a page of the site has it');
	pg_assert_same('taken', $out['taken'][0]['code'], 'a file or a site address answers there');
	pg_assert_same(array('title', 'description'), array($out['long'][0]['field'], $out['long'][1]['field']), 'title and description at most 255 characters');
}

// What the sketch keeps after a save: a menu's item only with the menu, an
// address not typed made from the name.
function test_sketch_details_stored()
{
	$out = sketch_details_test_node(<<<'JS'
out = [
    D.stored({ name: ' Classes ', address: 'x', typed: false, type: 'blog', menu: 0, menuName: 'Main', after: 4, afterName: 'Home', title: '', description: '', folder: 3 }),
    D.stored({ name: 'A', address: 'own', typed: true, type: 'standard', menu: 2, menuName: 'Main', after: 4, afterName: 'Home', folder: 3 })
];
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same('Classes', $out[0]['name'], 'the name trimmed');
	pg_assert_same(array('classes', 0, '', 0, ''), array($out[0]['details']['address'], $out[0]['details']['menu'], $out[0]['details']['menuName'], $out[0]['details']['after'], $out[0]['details']['afterName']), 'no menu, no item; the address from the name');
	pg_assert_same(array('own', 2, 4, 'Home'), array($out[1]['details']['address'], $out[1]['details']['menu'], $out[1]['details']['after'], $out[1]['details']['afterName']), 'a typed address and a place in a menu kept');
}

// The menus the window lists: each with its top items in printing order.
function test_sketch_details_menus()
{
	$menus = array(array('id' => '2', 'name' => 'Footer'), array('id' => '1', 'name' => 'Main menu'));
	$items = array(
		array('id' => '10', 'menu_id' => '1', 'name' => 'Home'),
		array('id' => '11', 'menu_id' => '9', 'name' => 'Lost'),
		array('id' => '12', 'menu_id' => '1', 'name' => 'About'),
	);

	pg_assert_same(array(
		array('id' => 2, 'name' => 'Footer', 'items' => array()),
		array('id' => 1, 'name' => 'Main menu', 'items' => array(array('id' => 10, 'name' => 'Home'), array('id' => 12, 'name' => 'About'))),
	), pg_designer_sketch_menus($menus, $items), 'menus in order, an item of an unlisted menu left out');
	pg_assert_same(array(), pg_designer_sketch_menus(false, false), 'nothing read');
}
