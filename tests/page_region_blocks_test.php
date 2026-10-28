<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a page's own regions moved into its tree: one block marked
 * editable per page region node, in the order the page fills them
 * (includes/designer_region_import.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_region_import.php');

function test_page_region_blocks_one_per_node_in_order()
{
	$r = pg_di_page_region_blocks(array('<h2>First</h2>', '<p>Second</p>'), 2, null, array('php' => true));
	pg_assert_same(2, count($r['blocks']));
	foreach ($r['blocks'] as $b) {
		pg_assert_same('semantic', $b['type']);
		pg_assert_same(1, $b['props']['_editable'], 'each block is an editable area');
	}
	pg_assert_same('First', $r['blocks'][0]['children'][0]['props']['text']);
	pg_assert_same('Second', $r['blocks'][1]['children'][0]['props']['text']);
}

function test_page_region_blocks_missing_and_extra_rows()
{
	$r = pg_di_page_region_blocks(array('<p>Only</p>'), 3, null);
	pg_assert_same(3, count($r['blocks']), 'a node without a row still gets a block');
	pg_assert_same(array(), $r['blocks'][1]['children']);
	pg_assert_same(array(), $r['blocks'][2]['children']);

	$extra = pg_di_page_region_blocks(array('<p>A</p>', '<p>B</p>'), 1, null);
	pg_assert_same(1, count($extra['blocks']), 'a row no node draws is left out, as the page left it out');

	$none = pg_di_page_region_blocks(array(), 0, null);
	pg_assert_same(array(), $none['blocks']);
}

function test_page_region_blocks_php_warnings_numbered()
{
	$r = pg_di_page_region_blocks(array('<p>x</p>', '<?php echo 1; ?>'), 2, null, array('php' => true, 'hosted' => true));
	pg_assert_same('custom_php', $r['blocks'][1]['children'][0]['props']['contentType'], 'PHP is a code node inside the block');
	pg_assert_same(1, count($r['warnings']), 'only the region with PHP is warned about');
	pg_assert_true(strpos($r['warnings'][0], '2') !== false, 'the warning names the region');

	$off = pg_di_page_region_blocks(array('<?php echo 1; ?>'), 1, null, array('php' => false, 'hosted' => true));
	pg_assert_same(array(), $off['warnings'], 'PHP that does not run stays text, no warning');
}
