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

function test_email_designs_mail_blocks_family()
{
	require_once(__DIR__ . '/helpers/designer_family_run.php');

	// Every kind the family names has its look in the map.
	$file = pg_test_designer_family_file('mail');
	preg_match_all("/mail\\('[a-z0-9]+', '([a-z_]+)'/", $file, $m);
	pg_assert_true(count($m[1]) >= 10, 'the blocks name their kinds');
	foreach (array_unique($m[1]) as $kind) {
		pg_assert_true(array_key_exists($kind, pg_mail_block_styles()), $kind . ' has a look');
	}
	pg_assert_contains("cssClass: 'pg-mail-col'", $file, 'columns stack through the mail pass\'s head style');
	pg_assert_contains('.pg-mail-col', pg_mail_pass_head_style(), 'the head style knows the column');

	$got = pg_test_designer_family_run('mail');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('mail-', $got['prefix'], 'the family\'s prefix');
	pg_assert_same('E-mail', $got['group'], 'its own palette group');
	pg_assert_same(array('mail-frame', 'mail-intro', 'mail-button', 'mail-picture', 'mail-break', 'mail-header', 'mail-greeting', 'mail-product', 'mail-footer', 'mail-columns'), array_keys($got['blocks']), 'the ten blocks');
	pg_assert_contains('Hello ^^first_name^^,', implode('|', $got['blocks']['mail-greeting']['texts']), 'the greeting carries a merge field');
	pg_assert_same(array(array('name' => 'data-pg-mail-footer', 'value' => '')), $got['blocks']['mail-footer']['attrs'], 'the footer is marked for the mail pass');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
	pg_assert_same(array('semantic', 'table'), $got['blocks']['mail-frame']['root'], 'the frame is a table');
	pg_assert_same(array('semantic', 'div'), $got['blocks']['mail-intro']['root'], 'a part is one group');
	pg_assert_same(array('td:', 'td:'), $got['blocks']['mail-columns']['cells'], 'two cells');
}

function test_email_designs_palette_and_canvas_follow_the_root()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_contains("var isMail = (typeof tree !== 'undefined') && !!(tree && tree.props && tree.props.pgEmail);", $js, 'the palette asks the root');
	pg_assert_contains("return g.items.length && (isMail || g.match.indexOf('mail-') === -1);", $js, 'the mail group only in an e-mail design');
	pg_assert_contains("renderCanvas();\n        _sdEmailCanvasSync();", $js, 'the canvas width follows every draw');
	pg_assert_contains('data-w="600" title="\' + esc(_sdT(\'E-mail, 600 px wide\'))', $js, 'a 600 px width');
	pg_assert_contains('data-w="375" title="\' + esc(_sdT(\'Phone view, 375 px wide\'))', $js, 'a 375 px Phone view');
}

function test_email_designs_page_name_is_free_and_their_own()
{
	pg_assert_same('e-posta-tasarimi', pg_email_design_page_name('E-posta tasarımı', array()), 'the words as an address');
	pg_assert_same('e-posta-tasarimi-2', pg_email_design_page_name('E-posta tasarımı', array('E-Posta-Tasarimi')), 'taken in any case: the next number');
	pg_assert_same('e-posta-tasarimi-4', pg_email_design_page_name('E-posta tasarımı', array('e-posta-tasarimi', 'e-posta-tasarimi-2', 'e-posta-tasarimi-3')), 'the first free number');
	pg_assert_same('email-design', pg_email_design_page_name('***', array()), 'no letters: a plain name');
	pg_assert_same('e-posta-tasarimi', pg_email_design_new_page(3, 'e-posta-tasarimi')['page_name'], 'the tab carries it');
}

function test_email_designs_offer_nothing_a_mail_cannot_carry()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');

	pg_assert_contains("document.body.classList.toggle('sd-email-design', on);", $js, 'the editor knows it is an e-mail');
	foreach (array('#sd-vb-motion', '#sd-vb-proto', '#sd-as-sec-js') as $id) {
		pg_assert_contains('body.sd-email-design ' . $id, $css, $id . ' is not offered');
	}
	pg_assert_contains('body.sd-email-design .sd-as-mail-note { display:block; }', $css, 'the note on what reaches the mail');

	$card = pg_campaign_content_card(array('mode' => 'add'));
	pg_assert_contains('pg-campaign-phone disabled" aria-disabled="true"', $card, 'no phone preview before a page is chosen');
	pg_assert_contains('id="body_preview_phone" disabled>', $card, 'its switch waits too');
	pg_assert_false(strpos(pg_campaign_content_card(array('mode' => 'add', 'preview' => 'x.html')), 'aria-disabled="true"') !== false, 'a chosen page has it');
}

function test_email_designs_footer_is_filled_when_stored()
{
	$html = '<div data-pg-mail-footer=""><p><a style="color:#6c757d" href="https://shop.example/#pgmail-web">Web</a></p>'
		. '<p class="x" data-pg-mail-address="">Your address</p>'
		. '<p><a href="https://shop.example/?t=abc#pgmail-preferences">Preferences</a> <a href=\'#pgmail-unsubscribe\'>Unsubscribe</a></p></div>'
		. '<a href="https://shop.example/sale">Sale</a>';
	$out = pg_mail_footer_fill($html, 'https://shop.example/v?r=<reference_code></reference_code>', 'https://shop.example/p?id=<email_address_id></email_address_id>', 'Acme & Co, Main St 1');

	pg_assert_contains('href="https://shop.example/v?r=<reference_code></reference_code>">Web', $out, 'the web version');
	pg_assert_contains('href="https://shop.example/p?id=<email_address_id></email_address_id>">Preferences', $out, 'preferences, its tracking code gone');
	pg_assert_contains('href="https://shop.example/p?id=<email_address_id></email_address_id>">Unsubscribe', $out, 'unsubscribe');
	pg_assert_contains('<p class="x" data-pg-mail-address="">Acme &amp; Co, Main St 1</p>', $out, 'the address, escaped');
	pg_assert_contains('href="https://shop.example/sale"', $out, 'other links stay');
	pg_assert_same('plain', pg_mail_footer_fill('plain', 'a', 'b', 'c'), 'nothing to fill');
}

function test_email_designs_footer_address_skips_empty_parts()
{
	pg_assert_same('Acme, Main St 1, Ankara 06000 Türkiye', pg_mail_footer_address(array('name' => 'Acme', 'address_1' => 'Main St 1', 'city' => 'Ankara', 'zip_code' => '06000', 'country' => 'Türkiye')), 'the empty parts left out');
	pg_assert_same('', pg_mail_footer_address(null), 'nothing');
}

function test_email_designs_check_line_for_ordinary_blocks()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_contains('_sdMailCheckIssues(root).forEach(function (mi) { issues.push(mi); });', $js, 'the validation runs it');
	pg_assert_contains("if (!root || !root.props || !root.props.pgEmail) return out;", $js, 'only in an e-mail design');
	pg_assert_contains("rule: 'mail_block',", $js, 'its own rule');
}

function test_email_designs_palette_values()
{
	pg_assert_same(array(), pg_email_palette_vars_from('', ''), 'plain Bootstrap: nothing, the fallbacks stay');

	$vars = pg_email_palette_vars_from('#047857', '#1f2937');
	pg_assert_same('#047857', $vars['--bs-primary'], 'the primary');
	pg_assert_same('#1f2937', $vars['--bs-secondary'], 'the secondary');
	pg_assert_same('#ffffff', $vars['--pg-on-primary'], 'white text on a dark colour');
	pg_assert_true(isset($vars['--bs-link-color']), 'the link colour');
	foreach ($vars as $name => $value) {
		pg_assert_true((bool) preg_match('/^--[a-z0-9-]+$/', $name), 'a name: ' . $name);
		pg_assert_true((bool) preg_match('/^#[0-9a-f]{6}$/', $value), 'a hex colour only: ' . $name);
	}

	$pale = pg_email_palette_vars_from('#fde047', '#1f2937');
	pg_assert_false($pale['--pg-on-primary'] === '#ffffff', 'dark text on a pale colour');
	pg_assert_contains('color:var(--pg-on-primary,#ffffff)', pg_mail_block_styles()['button'], 'the button text follows the colour');
}

function test_email_designs_copy_tree()
{
	$tree = array('type' => 'root', 'props' => array('cssClass' => ''), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'p', 'text' => 'Hi'), 'children' => array()),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 4), 'children' => array()),
	));
	$copy = pg_email_design_copy_tree($tree);
	pg_assert_same(1, $copy['props']['pgEmail'], 'the copy is an e-mail design');
	pg_assert_same('', $copy['props']['cssClass'], 'the root keeps its props');
	pg_assert_same($tree['children'], $copy['children'], 'the content as it was');
	pg_assert_same(null, pg_email_design_copy_tree(array('type' => 'semantic')), 'not a page tree');
	pg_assert_same(null, pg_email_design_copy_tree('x'), 'nothing');
	pg_assert_same(array(4), pg_email_design_copy_shared_ids($tree), 'the shared components it places');

	$pages = array(
		array('page_id' => '9', 'page_name' => 'email-welcome'),
		array('page_id' => '10', 'page_name' => 'email-welcome-2'),
		array('page_id' => '11', 'page_name' => 'email-welcomer'),
		array('page_id' => '12', 'page_name' => 'home'),
	);
	pg_assert_same(array(9 => 'Welcome E-mail', 10 => 'Welcome E-mail'), pg_email_design_template_matches(array('email-welcome' => 'Welcome E-mail'), $pages), 'a template page by its name, a taken name numbered');
}

// Collects the _id of every node of a tree.
function _email_designs_test_ids($node, &$ids)
{
	$ids[] = isset($node['_id']) ? (string) $node['_id'] : '';
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) {
			_email_designs_test_ids($child, $ids);
		}
	}
}

function test_email_designs_new_page_gives_every_node_an_id()
{
	$ids = array();
	_email_designs_test_ids(pg_email_design_new_page(1)['tree'], $ids);

	pg_assert_same(9, count($ids), 'the root and the eight starter elements');
	pg_assert_same(array(), array_values(array_filter($ids, function ($id) { return !preg_match('/^sd_\d+$/', $id); })), 'each in the editor\'s form');
	pg_assert_same(count($ids), count(array_unique($ids)), 'no two alike');
}

function test_email_designs_new_page_keeps_ids_and_fills_a_ready_tree()
{
	$tree = array('type' => 'root', 'props' => array('pgEmail' => 1), 'children' => array(
		array('type' => 'semantic', '_id' => 'sd_7', 'props' => array('tag' => 'p'), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'p'), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'a'), 'children' => array()),
		)),
	));
	$page = pg_email_design_new_page(1, '', $tree);

	pg_assert_same('sd_7', $page['tree']['children'][0]['_id'], 'an id already there is kept');
	$ids = array();
	_email_designs_test_ids($page['tree'], $ids);
	pg_assert_same(array('sd_8', 'sd_7', 'sd_9', 'sd_10'), $ids, 'the missing ones count on past the highest');
}
