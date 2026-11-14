<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for e-mail designs (includes/fn/email_designs.php): the mail block
 * look written inline in front of a node's own style, its canvas twin in
 * style_designer.src.js, the starter tree and the editor's first tab.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_email_designs_inline_puts_the_block_look_first()
{
	$styles = pg_mail_block_styles();

	pg_assert_same($styles['text'], pg_mail_block_inline(array('_mail' => 'text')), 'the look alone');
	pg_assert_same($styles['text'] . ';color:#ff0000', pg_mail_block_inline(array('_mail' => 'text', 'inlineStyle' => 'color:#ff0000; ')), 'the own style after it, so it wins');
	pg_assert_same('color:red', pg_mail_block_inline(array('inlineStyle' => 'color:red;')), 'no block: the own style only');
	pg_assert_same('', pg_mail_block_inline(array('_mail' => 'nothing')), 'an unknown kind adds nothing');
	pg_assert_same('', pg_mail_block_inline(null), 'no props');
}

function test_email_designs_looks_are_mail_safe()
{
	foreach (pg_mail_block_styles() as $kind => $css) {
		pg_assert_true(strpos($css, '"') === false && strpos($css, "'") === false, $kind . ': no quotes, it sits in an attribute');
		pg_assert_true(!preg_match('/\b(flex|grid|float|position)\b/', $css), $kind . ': nothing Outlook ignores for layout');
		pg_assert_true(!preg_match('/var\(--[a-z-]+\)/', $css), $kind . ': every var() has its hex');
		pg_assert_same(pg_mail_pass_var_fallbacks($css), preg_replace('/var\(--[a-z-]+,(#[0-9a-f]+)\)/', '$1', $css), $kind . ': the mail pass writes the hex');
	}
}

function test_email_designs_canvas_twin_has_the_same_map()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$start = strpos($js, 'var _SD_MAIL_STYLES = {');
	$end = ($start === false) ? false : strpos($js, '};', $start);
	pg_assert_true($start !== false && $end !== false, 'the twin is in the designer');
	$body = (string) substr($js, (int) $start, (int) $end - (int) $start);

	preg_match_all("/^\\s*'?([a-z_]+)'?:\\s*'([^']*)',?\\s*$/m", $body, $m, PREG_SET_ORDER);
	$twin = array();
	foreach ($m as $row) {
		$twin[$row[1]] = $row[2];
	}

	pg_assert_same(pg_mail_block_styles(), $twin, 'kind by kind, the same declarations');
	pg_assert_contains("_inlineBase = node.type === 'semantic' ? _sdMailInline(node.props) : (node.props.inlineStyle || '')", $js, 'the canvas draws it');
	pg_assert_contains('inlineStyle: _sdMailInline(n.props)', $js, 'the editor\'s HTML writes it');
}

function test_email_designs_render_writes_the_look_inline()
{
	$html = _render_tree_node(pg_mail_block_node('p', 'text', 'Text', array(), array('text' => 'Hello', 'inlineStyle' => 'color:#123456')));
	$styles = pg_mail_block_styles();

	pg_assert_contains('<p style="' . h($styles['text'] . ';color:#123456') . '">Hello</p>', $html, 'look then own style');
}

function test_email_designs_starter_tree_is_an_email()
{
	$tree = pg_email_design_starter_tree();

	pg_assert_true(pg_email_design_tree_is($tree), 'the root carries pgEmail');
	pg_assert_false(pg_email_design_tree_is(array('type' => 'root', 'props' => array(), 'children' => array())), 'a page is not');
	pg_assert_false(pg_email_design_tree_is(null), 'nothing is not');

	$frame = $tree['children'][0];
	pg_assert_same(array('table', 'frame', 'pg-mail-frame'), array($frame['props']['tag'], $frame['props']['_mail'], $frame['props']['cssClass']), 'a frame table');
	$attrs = array();
	foreach ($frame['props']['_attrs'] as $a) {
		$attrs[$a['name']] = $a['value'];
	}
	pg_assert_same('presentation', $attrs['role'], 'a presentation table');
	pg_assert_same('600', $attrs['width'], '600 px wide');

	$cell = $frame['children'][0]['children'][0]['children'][0];
	pg_assert_same('cell', $cell['props']['_mail'], 'one cell');
	$kinds = array();
	foreach ($cell['children'] as $n) {
		$kinds[] = $n['props']['_mail'];
	}
	pg_assert_same(array('heading', 'text', 'button_row'), $kinds, 'heading, text, button');
	pg_assert_same('button', $cell['children'][2]['children'][0]['props']['_mail'], 'the button is a link');
	pg_assert_same('a', $cell['children'][2]['children'][0]['props']['tag'], 'drawn as a link');
}

function test_email_designs_new_page_stays_out_of_search()
{
	$page = pg_email_design_new_page(42);

	pg_assert_same(array(42, 0, 0, 1, 1, 0, 0), array($page['page_folder'], $page['page_search'], $page['page_sitemap'], $page['page_noindex'], $page['page_nofollow'], $page['page_draft'], $page['page_id']), 'folder, no search, no sitemap, noindex, not a draft, new');
	pg_assert_true(pg_email_design_tree_is($page['tree']), 'the starter tree');
	pg_assert_same(1, $page['converted'], 'the tab starts unsaved');
}
