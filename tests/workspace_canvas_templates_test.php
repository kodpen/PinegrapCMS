<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for canvas templates (includes/fn/canvas_templates.php and
 * includes/workspace/canvas_template_fill.php): the stored list, a
 * template's summary, a fill laid into its named layers and its refusals,
 * the settings card's keep rule and the route rows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/canvas_templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_templates_card.php');

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_canvas.php');

// A post template: a background, a title, a photo and a logo icon.
function workspace_canvas_templates_doc()
{
	return array(
		'v'        => 1,
		'artboard' => array('width' => 1080, 'height' => 1080, 'background' => '#ffffff'),
		'picture'  => 'pending',
		'by'       => array('id' => 3, 'kind' => 'app', 'handle' => 'maker'),
		'layers'   => array(
			array('id' => 'l0', 'type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 1080, 'h' => 1080, 'locked' => true),
			array('id' => 'l1', 'type' => 'text', 'name' => 'title', 'text' => 'Title', 'x' => 40, 'y' => 40, 'w' => 600, 'h' => 90, 'locked' => true),
			array('id' => 'l2', 'type' => 'image', 'name' => 'photo', 'src' => '/old.png', 'fileId' => 4, 'fileName' => 'old.png', 'x' => 40, 'y' => 200, 'w' => 500, 'h' => 500),
			array('id' => 'l3', 'type' => 'glyph', 'name' => 'logo', 'set' => 'bi', 'code' => 'f111', 'x' => 900, 'y' => 900, 'w' => 100, 'h' => 100),
		),
	);
}

function workspace_canvas_templates_resolver()
{
	return function ($op) {
		if (($op['file_id'] ?? 0) === 9) {
			return array('id' => 9, 'url' => '/new.png');
		}

		return (($op['file'] ?? '') === '/new.png') ? array('id' => 9, 'url' => '/new.png') : null;
	};
}

function test_workspace_canvas_templates_list()
{
	pg_assert_same(array(3, 1, 7), pg_canvas_templates_parse('[3, 1, 3, "7", -2, 0, 7]'), 'whole positive ids, each once, in order');
	pg_assert_same(array(), pg_canvas_templates_parse('nope'), 'not JSON');
	pg_assert_same(array(), pg_canvas_templates_parse(''), 'nothing stored');
	pg_assert_same(PG_CANVAS_TEMPLATES_MAX, count(pg_canvas_templates_parse(json_encode(range(1, 80)))), 'capped');

	pg_assert_same(array(1, 3, 5), pg_canvas_templates_toggle(array(1, 3), 5, true), 'put in at the end');
	pg_assert_same(array(1, 3, 5), pg_canvas_templates_toggle(array(1, 5, 3), 5, true), 'put in again moves to the end');
	pg_assert_same(array(1), pg_canvas_templates_toggle(array(1, 5), 5, false), 'taken out');
	pg_assert_same(null, pg_canvas_templates_toggle(range(1, PG_CANVAS_TEMPLATES_MAX), 999, true), 'full');
	pg_assert_same(range(1, PG_CANVAS_TEMPLATES_MAX - 1), pg_canvas_templates_toggle(range(1, PG_CANVAS_TEMPLATES_MAX), PG_CANVAS_TEMPLATES_MAX, false), 'a full list still lets one go');
}

function test_workspace_canvas_templates_summary()
{
	pg_assert_same(array(
		'width'      => 1080,
		'height'     => 1080,
		'background' => '#ffffff',
		'layers'     => array(
			array('name' => 'title', 'kind' => 'text', 'text' => 'Title'),
			array('name' => 'photo', 'kind' => 'picture', 'text' => null),
			array('name' => 'logo', 'kind' => 'icon', 'text' => null),
		),
	), pg_canvas_template_summary(workspace_canvas_templates_doc()), 'only named layers, with their kinds');
}

function test_workspace_canvas_templates_fill()
{
	$done = ws_canvas_template_fill(workspace_canvas_templates_doc(), array('title' => 'Summer sale', 'photo' => 9), workspace_canvas_templates_resolver());

	pg_assert_same('', $done['error']);
	pg_assert_same('Summer sale', $done['doc']['layers'][1]['text'], 'a locked text layer is still filled');
	pg_assert_same(array('/new.png', 9, false), array($done['doc']['layers'][2]['src'], $done['doc']['layers'][2]['fileId'], isset($done['doc']['layers'][2]['fileName'])), 'the picture by its id');
	pg_assert_same(array(40, 200, 500, 500), array($done['doc']['layers'][2]['x'], $done['doc']['layers'][2]['y'], $done['doc']['layers'][2]['w'], $done['doc']['layers'][2]['h']), 'its box kept');
	pg_assert_same(array(false, false), array(isset($done['doc']['picture']), isset($done['doc']['by'])), 'the template\'s marks go');

	pg_assert_same(9, ws_canvas_template_fill(workspace_canvas_templates_doc(), array('photo' => '/new.png'), workspace_canvas_templates_resolver())['doc']['layers'][2]['fileId'], 'by its address');
	pg_assert_same(9, ws_canvas_template_fill(workspace_canvas_templates_doc(), array('photo' => array('file_id' => 9)), workspace_canvas_templates_resolver())['doc']['layers'][2]['fileId'], 'as an object');
	pg_assert_same('', ws_canvas_template_fill(workspace_canvas_templates_doc(), null)['error'], 'no fill copies it');

	$refused = array(
		'name'    => array('subtitle' => 'x'),
		'kind'    => array('logo' => 'x'),
		'text'    => array('title' => 5),
		'picture' => array('photo' => 12),
		'fill'    => array('just words'),
	);

	foreach ($refused as $error => $fill) {
		$result = ws_canvas_template_fill(workspace_canvas_templates_doc(), $fill, workspace_canvas_templates_resolver());

		pg_assert_same($error, $result['error'], 'refused: ' . $error);
		pg_assert_same(null, $result['doc']);
	}

	$missing = ws_canvas_template_fill(workspace_canvas_templates_doc(), array('subtitle' => 'x'), workspace_canvas_templates_resolver());

	pg_assert_same(array('title (text)', 'photo (picture)', 'logo (icon)'), $missing['names']);
	pg_assert_true(strpos(ws_canvas_template_fill_error_text($missing), 'title (text), photo (picture), logo (icon)') !== false, 'the refusal lists the names');

	$twice = workspace_canvas_templates_doc();
	$twice['layers'][3]['name'] = 'title';

	pg_assert_same('name', ws_canvas_template_fill($twice, array('title' => 'x'))['error'], 'a name two layers share names neither');
}

function test_workspace_canvas_templates_settings_keep()
{
	pg_assert_same(array(1, 5), ws_canvas_templates_settings_keep(array(1, 3, 5), array(1, 3, 5), array('1', '5')), 'an unticked one goes');
	pg_assert_same(array(1), ws_canvas_templates_settings_keep(array(1, 4), array(1), array('1')), 'one not shown goes');
	pg_assert_same(array(), ws_canvas_templates_settings_keep(array(1), array(1), null), 'nothing ticked');
	pg_assert_same(array(1), ws_canvas_templates_settings_keep(array(1), array(1, 2), array('1', '2')), 'a ticked id not stored is not added');
}

function test_workspace_canvas_templates_routes()
{
	$routes = array_column(ws_canvas_api_routes(), null, 'id');
	$create = array_column($routes['workspace.channels.canvases.create']['params'], null, 'name');

	pg_assert_same(array('GET', '/workspace/canvas/templates', 'workspace:read'), array($routes['workspace.canvas.templates']['method'], $routes['workspace.canvas.templates']['path'], $routes['workspace.canvas.templates']['scope']));
	pg_assert_true(empty($create['svg']['required']), 'svg is no longer required');
	pg_assert_same(array('int', 'object'), array($create['template']['type'], $create['fill']['type']));
}
