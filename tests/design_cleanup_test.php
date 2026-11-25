<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the clean-up finder (includes/fn/design_cleanup.php): the
 * classes a design uses, gathered from trees, markup and scripts, and the
 * style rules of a stylesheet that none of them uses, with every case the
 * finder cannot tell left unlisted.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/design_cleanup.php');

function _dc_test_selectors($rows)
{
	$out = array();
	foreach ($rows as $row) {
		$out[] = $row['selector'];
	}
	return $out;
}

function _dc_test_tree()
{
	return array('_id' => 'sd_0', 'type' => 'root', 'props' => array('cssClass' => 'page-root'), 'children' => array(
		array('_id' => 'sd_1', 'type' => 'semantic', 'props' => array('tag' => 'section', 'cssClass' => 'hero  py-5', 'text' => 'The card-glow of summer'), 'children' => array(
			array('_id' => 'sd_2', 'type' => 'semantic', 'props' => array('tag' => 'div', '_attrs' => array(array('name' => 'class', 'value' => 'attr-class')), 'cssClass' => ''), 'children' => array()),
			array('_id' => 'sd_3', 'type' => 'html', 'props' => array('html' => '<div class="from-markup other"><span class=\'single-q\'>x</span></div>'), 'children' => array()),
		)),
	));
}

function test_design_cleanup_tree_gathers_classes_attrs_and_markup()
{
	$state = pg_cleanup_state();
	pg_cleanup_add_tree($state, _dc_test_tree());
	foreach (array('page-root', 'hero', 'py-5', 'attr-class', 'from-markup', 'other', 'single-q') as $class) {
		pg_assert_true(pg_cleanup_class_used($state, $class), $class . ' is used');
	}
	pg_assert_true(!pg_cleanup_class_used($state, 'card-glow'), 'a word of a text is no class');
	pg_assert_true(!pg_cleanup_class_used($state, 'unused-thing'), 'an absent class is unused');
}

function test_design_cleanup_tree_reads_interactions_conditions_and_settings()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('_fx' => array('effect' => 'fade', 'toggle' => 'is-open')), 'children' => array()),
		array('type' => 'semantic', 'props' => array('_bindings' => array('show_if' => '__visit_signed_in'), '_bindParams' => array('show_if' => array('op' => 'eq', 'value' => 'member-tier'))), 'children' => array()),
		array('type' => 'component', 'props' => array('componentType' => 'tabs', 'activeClass' => 'tab-on'), 'children' => array()),
	));
	$state = pg_cleanup_state();
	pg_cleanup_add_tree($state, $tree);
	pg_assert_true(pg_cleanup_class_used($state, 'is-open'), 'a word of an interaction');
	pg_assert_true(pg_cleanup_class_used($state, 'member-tier'), 'a word of a condition');
	pg_assert_true(pg_cleanup_class_used($state, 'tab-on'), 'a word of a component setting');
	pg_assert_same('', $state['blocked'], 'nothing blocks');
}

function test_design_cleanup_class_bound_to_a_value_blocks_everything()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('cssClass' => 'card', '_bindings' => array('cssClass' => '__item_badge')), 'children' => array()),
	));
	$state = pg_cleanup_state();
	pg_cleanup_add_tree($state, $tree, 'page 4');
	pg_assert_same('bound_class', $state['blocked'], 'a class from a record value cannot be told');
	$found = pg_cleanup_unused_rules('.nobody { color: red; }', $state);
	pg_assert_same(array(), $found['rows'], 'nothing is listed');
	pg_assert_same('bound_class', $found['blocked'], 'the reason is passed on');
}

function test_design_cleanup_class_attribute_with_a_token_gives_a_prefix()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('_attrs' => array(array('name' => 'class', 'value' => 'badge-{__item_kind} solid'))), 'children' => array()),
	));
	$state = pg_cleanup_state();
	pg_cleanup_add_tree($state, $tree);
	pg_assert_true(pg_cleanup_class_used($state, 'solid'), 'the plain class');
	pg_assert_true(pg_cleanup_class_used($state, 'badge-sale'), 'a class the token may make');
	pg_assert_true(!pg_cleanup_class_used($state, 'badges'), 'another name is not covered');
	$state2 = pg_cleanup_state();
	pg_cleanup_add_markup($state2, '<div class="<?php echo $c; ?>">');
	pg_assert_same('dynamic_class', $state2['blocked'], 'a class attribute made all by code blocks');
}

function test_design_cleanup_script_words_and_built_names()
{
	$js = "document.querySelector('.menu-open');\n"
		. "el.classList.toggle(\"is-stuck\");\n"
		. "el.className = 'tone-' + tone;\n"
		. "el.classList.add(`size-\${n}`);\n"
		. "var wideMode = true; // stray-comment-word\n";
	$state = pg_cleanup_state();
	pg_cleanup_add_script($state, $js, 'site.js');
	pg_assert_true(pg_cleanup_class_used($state, 'menu-open'), 'a selector in a script');
	pg_assert_true(pg_cleanup_class_used($state, 'is-stuck'), 'a toggled class');
	pg_assert_true(pg_cleanup_class_used($state, 'wideMode'), 'any word of a script counts');
	pg_assert_true(pg_cleanup_class_used($state, 'tone-dark'), 'a name built with +');
	pg_assert_true(pg_cleanup_class_used($state, 'size-lg'), 'a name built in a template');
	pg_assert_true(!pg_cleanup_class_used($state, 'shade-dark'), 'an unrelated name');
}

function test_design_cleanup_markup_scripts_count_as_scripts()
{
	$state = pg_cleanup_state();
	pg_cleanup_add_markup($state, '<p class="lead">Hi</p><script>document.body.classList.add("js-ready")</script>');
	pg_assert_true(pg_cleanup_class_used($state, 'lead'), 'the class of an element');
	pg_assert_true(pg_cleanup_class_used($state, 'js-ready'), 'a word of an inline script');
	pg_assert_true(!pg_cleanup_class_used($state, 'Hi'), 'a word of a text is no class');
}

function test_design_cleanup_framework_and_state_classes_are_never_listed()
{
	$state = pg_cleanup_state(array('known' => array('btn', 'col-md-6')));
	$css = ".btn { padding: 0; }\n.col-md-6 { flex: 1; }\n.active { color: red; }\n.pg-note { x: y; }\n.bi-star { a: b; }\n.lonely { a: b; }";
	$found = pg_cleanup_unused_rules($css, $state);
	pg_assert_same(array('.lonely'), _dc_test_selectors($found['rows']), 'only the design\'s own unused class');
}

function test_design_cleanup_rules_matched_against_the_classes()
{
	$state = pg_cleanup_state();
	pg_cleanup_add_classes($state, array('hero', 'card'));
	$css = "/* site */\n"
		. ".hero { color: red; }\n"
		. ".ghost { color: blue; }\n"
		. ".hero .ghost { margin: 0; }\n"
		. ".ghost, .card { padding: 1px; }\n"
		. "h1, .ghost { font: inherit; }\n"
		. "a:hover { color: red; }\n"
		. "#main { x: y; }\n"
		. "[class*=\"ghost\"] { x: y; }\n"
		. ".card:not(.ghost) { x: y; }\n"
		. ".ghost::after { content: \"}\"; }\n";
	$found = pg_cleanup_unused_rules($css, $state);
	pg_assert_same(array('.ghost', '.hero .ghost', '.ghost::after'), _dc_test_selectors($found['rows']), 'every alternative needs a class nothing uses');
	$row = $found['rows'][0];
	pg_assert_same(array('ghost'), $row['classes'], 'the missing class');
	pg_assert_same('.ghost { color: blue; }', substr($css, $row['start'], $row['end'] - $row['start']), 'the offsets cut the whole rule');
	pg_assert_same($row['end'] - $row['start'], $row['bytes'], 'its size');
	pg_assert_same('', $row['media'], 'outside any at-rule');
}

function test_design_cleanup_rules_inside_media_and_supports()
{
	$state = pg_cleanup_state();
	pg_cleanup_add_classes($state, array('used'));
	$css = "@media (min-width: 768px) { .used { a: b; } .gone { a: b; } }\n"
		. "@supports (display: grid) { @media print { .gone2 { a: b; } } }\n"
		. "@keyframes spin { from { a: b; } to { a: c; } }\n"
		. "@font-face { font-family: x; }\n"
		. "@import url(\"x.css\");\n";
	$found = pg_cleanup_unused_rules($css, $state);
	pg_assert_same(array('.gone', '.gone2'), _dc_test_selectors($found['rows']), 'rules in nested blocks; keyframes and font faces skipped');
	pg_assert_same('@media (min-width: 768px)', $found['rows'][0]['media'], 'the at-rule chain');
	pg_assert_same('@supports (display: grid) @media print', $found['rows'][1]['media'], 'a nested chain');
}

function test_design_cleanup_keep_comment_protects_a_rule_or_a_block()
{
	$state = pg_cleanup_state();
	$css = "/* keep: used by the booking app */\n.app-a { a: b; }\n"
		. ".plain { a: b; }\n"
		. "/* Keep */ @media print { .app-b { a: b; } }\n"
		. "/* kept for later? no: a note */ .app-c { a: b; }\n";
	$found = pg_cleanup_unused_rules($css, $state);
	pg_assert_same(array('.plain', '.app-c'), _dc_test_selectors($found['rows']), 'a keep comment before a rule or a block holds it; "kept" in a note does not');
}

function test_design_cleanup_prefix_and_escaped_names()
{
	$state = pg_cleanup_state();
	pg_cleanup_add_script($state, "el.className = 'tone-' + t;");
	pg_cleanup_add_classes($state, array('md:flex'));
	$css = ".tone-dark { a: b; }\n.md\\:flex { a: b; }\n.lg\\:flex { a: b; }";
	$found = pg_cleanup_unused_rules($css, $state);
	pg_assert_same(array('.lg\\:flex'), _dc_test_selectors($found['rows']), 'a built name and an escaped one are used');
	pg_assert_same(array('lg:flex'), $found['rows'][0]['classes'], 'the name unescaped');
}

function test_design_cleanup_unreadable_sheet_lists_nothing()
{
	$state = pg_cleanup_state();
	$found = pg_cleanup_unused_rules(".a { color: red;\n.b { x: y; }", $state);
	pg_assert_same(array(), $found['rows'], 'an unclosed block lists nothing');
	pg_assert_same('unreadable', $found['blocked'], 'and says why');
	$found = pg_cleanup_unused_rules(".a } .b { x: y; }", $state);
	pg_assert_same('unreadable', $found['blocked'], 'a stray closing brace too');
}

function test_design_cleanup_total_bytes()
{
	$state = pg_cleanup_state();
	$found = pg_cleanup_unused_rules(".a { x: y; }\n.bb { x: y; }", $state);
	pg_assert_same(2, count($found['rows']), 'both unused');
	pg_assert_same(12 + 13, $found['bytes'], 'the size it would free');
}
