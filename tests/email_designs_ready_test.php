<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ready e-mail designs (includes/fn/email_designs_ready.php):
 * five trees built from mail blocks only, each in the frame with the footer
 * last, and drawn through the mail pass as a campaign stores them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every node of a tree that is no mail block and no tbody or tr.
function email_designs_ready_test_strays($node)
{
	$out = array();
	foreach ((array) ($node['children'] ?? array()) as $kid) {
		$tag = (string) ($kid['props']['tag'] ?? '');
		if (empty($kid['props']['_mail']) && !in_array($tag, array('tbody', 'tr'), true)) {
			$out[] = $tag;
		}
		$out = array_merge($out, email_designs_ready_test_strays($kid));
	}
	return $out;
}

function test_email_designs_ready_five_designs()
{
	pg_assert_same(array('newsletter', 'sale', 'announcement', 'welcome', 'order'), array_keys(pg_email_ready_designs()), 'five, in their order');
	pg_assert_false(pg_email_ready_design_valid('nope'), 'an unknown key');
	pg_assert_false(pg_email_ready_design_valid(array('sale')), 'not a string');
	pg_assert_same(null, pg_email_ready_design_tree('nope'), 'no tree for it');
	pg_assert_same('', pg_email_ready_design_html('nope'), 'no preview for it');
}

function test_email_designs_ready_trees_are_mail_blocks_only()
{
	$styles = pg_mail_block_styles();

	foreach (array_keys(pg_email_ready_designs()) as $key) {
		$tree = pg_email_ready_design_tree($key);
		pg_assert_true(pg_email_design_tree_is($tree), $key . ' is an e-mail design');
		pg_assert_same(array(), email_designs_ready_test_strays($tree), $key . ': mail blocks only, so no check line');

		$frame = $tree['children'][0];
		pg_assert_same('frame', $frame['props']['_mail'], $key . ': in the frame');
		$cell = $frame['children'][0]['children'][0]['children'][0];
		$last = end($cell['children']);
		pg_assert_same('footer', $last['props']['_mail'], $key . ': the footer last');
		pg_assert_same('header', $cell['children'][0]['props']['_mail'], $key . ': the header first');

		array_walk_recursive($tree, function ($value, $name) use ($styles, $key) {
			if ($name === '_mail') {
				pg_assert_true(isset($styles[$value]), $key . ': ' . $value . ' has a look');
			}
		});
	}
}

function test_email_designs_ready_merge_fields_and_products()
{
	$json = function ($key) {
		return json_encode(pg_email_ready_design_tree($key));
	};

	pg_assert_contains('^^first_name^^', $json('welcome'), 'the welcome greets by name');
	pg_assert_contains('^^first_name^^', $json('order'), 'so does the thanks');
	pg_assert_same(2, substr_count($json('sale'), '"_mail":"product"'), 'two product cards on sale');
	pg_assert_contains('"_mail":"columns"', $json('sale'), 'side by side');
	pg_assert_same(1, substr_count($json('order'), '"_mail":"product"'), 'one after an order');
}

function test_email_designs_ready_preview_is_the_sent_html()
{
	$html = pg_email_ready_design_html('announcement');

	pg_assert_contains('class="pg-mail-frame"', $html, 'the frame');
	pg_assert_contains('role="presentation"', $html, 'a presentation table');
	pg_assert_contains('data-pg-mail-footer', $html, 'its own footer');
	pg_assert_contains('@media (max-width: 620px)', $html, 'the mail pass put its phone style in');
	pg_assert_false(strpos($html, 'var(--') !== false, 'colours written as hex');
	pg_assert_false(strpos($html, '<script') !== false, 'no script');
}
