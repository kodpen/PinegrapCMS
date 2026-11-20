<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the guide's designer topic (includes/fn/agent_guide_designer.php):
 * the built-in assistant's instructions stay word for word while their page
 * lines are shared with the topic, every operation, field and <pg-keep> kind
 * is in the text, the five worked lists run through pg_design_ai_apply_ops(),
 * the widget kinds exist, the design's colours and font families on
 * GET /design/designs, and the MCP prompt design_change.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

if (!defined('HOSTNAME_SETTING')) {
	define('HOSTNAME_SETTING', 'example.test');
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide_designer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/oauth/server.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/dry_run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/design.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/protocol.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/resources.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/prompts.php');

function agent_guide_designer_test_text()
{
	return pg_agent_guide_topic_text(pg_agent_guide_registry(), 'designer', array('store', 'workspace', 'erp'));
}

// The assistant's instructions up to its rules, as they read before their
// page lines moved into pg_design_ai_page_lines(): a selected element on a
// Bootstrap page, and a whole custom page.
function test_agent_guide_designer_assistant_prompt_stays_word_for_word()
{
	$cases = array(
		array(array('node_id' => 'sd_4'), array('page_name' => 'home', 'page_id' => 7, 'framework' => 'bootstrap5'), 'bbd226250a0d4bea76d5b9d7ed6b8afd1b4323bb0d926d1daa71935910aa1000'),
		array(array('node_id' => ''), array('page_name' => 'about', 'page_id' => 9, 'framework' => 'custom'), 'f3545de024f209c760a2f74528cd44c313520615b6cd20175cfce6a67c6a3c50')
	);

	foreach ($cases as $case) {
		$prompt = pg_design_ai_system_prompt($case[0], array('id' => 0), $case[1]);
		$head = substr($prompt, 0, strpos($prompt, "\nRules:\n"));

		pg_assert_same($case[2], hash('sha256', $head), $case[1]['page_name'] . ': the same words');

		foreach (pg_design_ai_page_lines() as $line) {
			pg_assert_contains("\n- " . $line . "\n", $prompt, 'the prompt reads the shared line');
		}
	}

	$text = agent_guide_designer_test_text();
	$lines = pg_design_ai_page_lines();

	pg_assert_contains($lines['html'], $text, 'the topic reads the same html line');
	pg_assert_contains($lines['bound'], $text, 'and the bound line');
}

// Every operation is a line of its own, in the order of pg_design_ai_op_names(),
// and every field an operation takes is named in the text.
function test_agent_guide_designer_every_operation_and_field_is_in_the_text()
{
	$guide = pg_design_ai_ops_guide();
	$text = agent_guide_designer_test_text();

	pg_assert_same(array_merge(pg_design_ai_op_names(), array('html')), array_keys($guide), 'one line per operation');

	foreach (pg_design_ai_op_names() as $op) {
		pg_assert_contains('{"op":"' . $op . '"', $text, $op);
	}

	foreach (array('node', 'parent', 'before', 'after', 'index', 'text', 'classes', 'add_classes', 'remove_classes', 'style', 'attrs', 'html', 'css', 'name', 'props') as $field) {
		pg_assert_contains('"' . $field . '"', $text, 'field ' . $field);
	}

	pg_assert_contains('design_proposals_create', $text);
	pg_assert_contains('design_pages_get', $text);
	pg_assert_contains('design_designs_list', $text);

	foreach (array('page', 'ops', 'classes', 'widgets', 'examples', 'mistakes') as $section) {
		pg_assert_contains('(#' . $section . ')', $text, 'section ' . $section);
	}
}

// Every <pg-keep> kind is one pg_design_ai_island_kind() gives, and is named
// in the text.
function test_agent_guide_designer_every_keep_kind_is_real_and_named()
{
	$text = agent_guide_designer_test_text();

	$nodes = array(
		'region'              => array('type' => 'region', 'props' => array()),
		'shared_ref'          => array('type' => 'shared_ref', 'props' => array()),
		'loop_area'           => array('type' => 'loop_area', 'props' => array()),
		'recipient_loop_area' => array('type' => 'recipient_loop_area', 'props' => array()),
		'component'           => array('type' => 'component', 'props' => array()),
		'locked'              => array('type' => 'semantic', 'props' => array('_locked' => true)),
		'bound'               => array('type' => 'semantic', 'props' => array('_bindings' => array('value' => 'title')))
	);

	pg_assert_same(array_keys($nodes), array_keys(pg_design_ai_keep_kinds()), 'the kinds');

	foreach ($nodes as $kind => $node) {
		pg_assert_same($kind, pg_design_ai_island_kind($node), $kind . ' is a kind the page shows');
		pg_assert_contains($kind . ' (', $text, $kind . ' is named');
	}
}

// The worked lists run against their page, and each is in the text as sent.
function test_agent_guide_designer_worked_lists_apply()
{
	$examples = pg_agent_guide_designer_examples();
	$text = agent_guide_designer_test_text();

	pg_assert_same(5, count($examples), 'five lists');

	foreach ($examples as $example) {
		$result = pg_design_ai_apply_ops(pg_agent_guide_designer_example_tree(), $example['ops']);

		pg_assert_true($result['ok'], $example['wish'] . ': ' . $result['error']);
		pg_assert_same(array(), $result['removed'], $example['wish'] . ': no part taken away');
		pg_assert_contains(json_encode($example['ops'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $text, $example['wish'] . ': in the text');
	}

	$moved = pg_design_ai_apply_ops(pg_agent_guide_designer_example_tree(), $examples[3]['ops']);
	$section = pg_design_ai_node($moved['tree'], 'sd_2');

	pg_assert_same('sd_5', $section['children'][0]['_id'], 'the menu is first');
}

function test_agent_guide_designer_widget_kinds_exist()
{
	$labels = pg_sw_type_labels();
	$text = agent_guide_designer_test_text();

	foreach (pg_agent_guide_designer_widgets() as $job => $kinds) {
		foreach ($kinds as $kind) {
			pg_assert_true(isset($labels[$kind]), $job . ': ' . $kind . ' is a widget kind');
			pg_assert_contains($kind, $text);
		}
	}
}

function test_agent_guide_designer_fonts_of_a_look()
{
	pg_assert_same(null, api_design_fonts_of_css(''), 'no stylesheet');
	pg_assert_same(null, api_design_fonts_of_css(':root{--pg-radius:4px;}'), 'no font');
	pg_assert_same(array('body' => '"Inter", sans-serif', 'heading' => 'Georgia, serif'), api_design_fonts_of_css(':root{--pg-font-body: "Inter", sans-serif; --PG-font-heading:Georgia, serif;}'));
	pg_assert_same(array('body' => 'Inter', 'heading' => 'Inter'), api_design_fonts_of_css(':root{--pg-font-body:Inter;--pg-font-heading:var(--pg-font-body);}'), 'a heading in the body font');
	pg_assert_same(array('body' => 'Inter', 'heading' => 'Inter'), api_design_fonts_of_css(':root{--pg-font-body:Inter;}'), 'no heading font');
}

function test_agent_guide_designer_design_values()
{
	pg_assert_same(array('colors' => null, 'fonts' => null), api_design_values('custom', 'elegant', 'ocean'), 'no Bootstrap, no theme');
	pg_assert_same(array('colors' => null, 'fonts' => null), api_design_values('legacy', '', ''), 'nor a moved classic style');

	$plain = api_design_values('bootstrap5', '', '');

	pg_assert_same(pg_design_bootstrap_colors(), $plain['colors'], 'Bootstrap\'s own colours');
	pg_assert_same(null, $plain['fonts'], 'no look, Bootstrap\'s fonts');

	$dressed = api_design_values('bootstrap5', 'elegant', 'ocean');
	$palettes = pg_design_palettes();

	pg_assert_same(array('primary' => $palettes['ocean']['primary'], 'secondary' => $palettes['ocean']['secondary']), $dressed['colors'], 'the palette');
	pg_assert_true(is_array($dressed['fonts']) && (strpos($dressed['fonts']['heading'], 'serif') !== false), 'the look\'s serif headings');

	$schema = api_design_schema();

	pg_assert_same('object?', $schema['colors']);
	pg_assert_same('object?', $schema['fonts']);
}

function test_agent_guide_designer_design_change_prompt()
{
	$designer = pg_mcp_routes_for(api_schema(), array('design:read', 'design:write'));

	pg_assert_true(pg_mcp_prompt_is_offered('design_change', $designer), 'design read and write');
	pg_assert_false(pg_mcp_prompt_is_offered('design_change', pg_mcp_routes_for(api_schema(), array('design:read'))), 'read only: nothing to propose with');
	pg_assert_false(pg_mcp_prompt_is_offered('design_change', pg_mcp_routes_for(api_schema(), array('forms:read'))), 'no design tools');

	$kind = pg_mcp_prompt_kinds()['design_change'];

	pg_assert_same(array('topic' => 'designer'), $kind['embed']['fixed'], 'it embeds the designer topic');
	pg_assert_same(array('page_id', 'wish'), array_column($kind['arguments'], 'name'));

	$checked = pg_mcp_prompt_arguments('design_change', array('page_id' => '12', 'wish' => 'A darker header'));

	pg_assert_same('', $checked['error']);

	$text = pg_mcp_prompt_result('design_change', $checked['values'], 'Example', $designer, null)['messages'][0]['content']['text'];

	foreach (array('page 12', 'A darker header', 'pinegrap://guide/designer', 'guide_read', 'design_pages_get', 'design_proposals_create', 'dry_run true', "Rules:\n") as $needle) {
		pg_assert_contains($needle, $text, $needle);
	}

	$attached = pg_mcp_prompt_result('design_change', $checked['values'], 'Example', $designer, array('uri' => 'pinegrap://guide/designer', 'text' => 'x'));

	pg_assert_contains('attached below', $attached['messages'][0]['content']['text']);
	pg_assert_same('resource', $attached['messages'][1]['content']['type'], 'the topic follows');
}
