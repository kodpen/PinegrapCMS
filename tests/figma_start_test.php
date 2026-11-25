<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Figma start's import in includes/fn/figma_start.php: which
 * components become shared, the shared_ref that places them, the variables
 * found in the frames, a fetched picture put into the tree, the type of a
 * downloaded file, the file names and the report.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/figma.php');

function figma_start_test_frame($id)
{
	$answer = json_decode(file_get_contents(dirname(__FILE__) . '/fixtures/figma/landing_nodes.json'), true);
	$frame = pg_figma_answer_frame($answer, $id);
	return pg_figma_convert_frame($frame['document'], $frame['styles'], $frame['components']);
}

function test_figma_import_start_lists_the_start()
{
	pg_assert_true(function_exists('pg_figma_import_start'), 'figma.php loads the import');
	pg_assert_true(pg_designer_figma_start_on_for_test(), 'the New design window lists the Figma start');
}

function pg_designer_figma_start_on_for_test()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	return pg_designer_figma_start_on();
}

function test_figma_shared_plan_needs_two_frames()
{
	$frame = figma_start_test_frame('12:3');
	pg_assert_true(count($frame['instances']) >= 2, 'the fixture places one component twice');
	pg_assert_same(array(), pg_figma_shared_plan(array($frame)), 'twice on one frame is not shared');

	$plan = pg_figma_shared_plan(array($frame, $frame));
	pg_assert_same(array('3:10'), array_keys($plan));
	pg_assert_same(2, $plan['3:10']['frames']);
	pg_assert_true($plan['3:10']['name'] !== '', 'named');
}

function test_figma_shared_ref_places_the_component()
{
	$frame = figma_start_test_frame('12:3');
	$plan = pg_figma_shared_plan(array($frame, $frame));
	$trees = pg_figma_shared_trees(array($frame, $frame), $plan);
	pg_assert_same(0, $trees['3:10']['frame'], 'taken from the first frame');
	pg_assert_same('root', $trees['3:10']['tree']['type']);
	pg_assert_same(1, count($trees['3:10']['tree']['children']));

	$tree = pg_figma_place_shared($frame['tree'], $frame['instances'], array('3:10' => array('id' => 41, 'name' => 'Button')));
	foreach ($frame['instances'] as $inst) {
		$node = pg_figma_tree_node($tree, $inst['node']);
		pg_assert_same('shared_ref', $node['type']);
		pg_assert_same(41, $node['props']['sharedId']);
		pg_assert_same('Button', $node['props']['sharedName']);
		pg_assert_same(array(), $node['children'], 'a stored shared_ref has no children');
	}
	pg_assert_same($frame['tree'], pg_figma_place_shared($frame['tree'], $frame['instances'], array()), 'no rows, no change');
}

function test_figma_found_variables()
{
	$frames = array(
		array('colors' => array(
			array('value' => '#ffffff', 'style' => '', 'count' => 9),
			array('value' => '#ff6600', 'style' => '', 'count' => 5),
			array('value' => '#2952f5', 'style' => 'Brand/Primary', 'count' => 2),
			array('value' => '#101828', 'style' => 'Ink', 'count' => 7),
		), 'text_styles' => array(
			array('style' => 'H1', 'font_family' => 'Poppins', 'font_size' => 56, 'count' => 1),
			array('style' => '', 'font_family' => 'Inter', 'font_size' => 16, 'count' => 8),
		)),
		array('colors' => array(array('value' => '#22aa55', 'style' => '', 'count' => 1)), 'text_styles' => array()),
	);
	$found = pg_figma_found_variables($frames);
	pg_assert_same(array('value' => '#2952f5', 'source' => 'Brand/Primary'), $found['brand'], 'a style named brand comes first');
	pg_assert_same(array('value' => '#ff6600', 'source' => ''), $found['accent'], 'then the most used hue');
	pg_assert_same(array(array('value' => '#22aa55', 'source' => '')), $found['more'], 'white and near black are no brand colour');
	pg_assert_same(array('value' => 'Poppins', 'source' => 'H1'), $found['heading']);
	pg_assert_same(array('value' => 'Inter', 'source' => ''), $found['body']);

	$none = pg_figma_found_variables(array(array('colors' => array(), 'text_styles' => array())));
	pg_assert_same(null, $none['brand']);
	pg_assert_same(null, $none['heading']);
	pg_assert_same(array('model' => null, 'css' => ''), pg_figma_variables_start($none, '', ''));
}

function test_figma_variables_start_model()
{
	$found = array('brand' => array('value' => '#2952f5', 'source' => ''), 'accent' => null, 'more' => array(),
		'heading' => array('value' => 'Poppins', 'source' => 'H1'), 'body' => null);
	$start = pg_figma_variables_start($found, '', '');
	pg_assert_same(array('light' => '#2952f5', 'dark' => '#2952f5'), $start['model']['values']['brand']);
	pg_assert_same('"Poppins", system-ui, sans-serif', $start['model']['values']['font_heading']);
	pg_assert_contains('pg-theme', $start['css']);
	pg_assert_contains('#2952f5', $start['css']);
}

function test_figma_set_picture()
{
	$tree = array('_id' => 'sd_1', 'type' => 'root', 'props' => array(), 'children' => array(
		array('_id' => 'sd_2', 'type' => 'content', 'props' => array('contentType' => 'image', 'src' => ''), 'children' => array()),
		array('_id' => 'sd_3', 'type' => 'semantic', 'props' => array('inlineStyle' => 'background-size:cover'), 'children' => array()),
	));
	$tree = pg_figma_set_picture($tree, array('node' => 'sd_2', 'kind' => 'image'), '/hero.png');
	$tree = pg_figma_set_picture($tree, array('node' => 'sd_3', 'kind' => 'background'), '/b"g.png');
	pg_assert_same('/hero.png', $tree['children'][0]['props']['src']);
	pg_assert_same('background-size:cover;background-image:url("/bg.png")', $tree['children'][1]['props']['inlineStyle'], 'a quote cannot leave the url');
}

function test_figma_picture_type_and_name()
{
	pg_assert_same('png', pg_figma_picture_type("\x89PNG\r\n"));
	pg_assert_same('jpg', pg_figma_picture_type("\xFF\xD8\xFF\xE0"));
	pg_assert_same('webp', pg_figma_picture_type('RIFF1234WEBPVP8'));
	pg_assert_same('svg', pg_figma_picture_type('<?xml version="1.0"?><svg width="1">'));
	pg_assert_same('', pg_figma_picture_type('<?php echo 1;'), 'only picture types');
	pg_assert_same('', pg_figma_picture_type('<html><svg>'));
	pg_assert_same('home-desktop-hero-photo.png', pg_figma_picture_name('Home / Desktop', 'Hero photo', 'png'));
	pg_assert_same('figma.svg', pg_figma_picture_name('', '★', 'svg'));
}

function test_figma_report_frame()
{
	$whole = pg_figma_report_frame(array('name' => 'Home', 'report' => array()));
	pg_assert_same('whole', $whole['state']);
	$notes = pg_figma_report_frame(array('name' => 'Home', 'report' => array(array('figma' => '1:2', 'name' => 'Glass', 'code' => 'effect'))));
	pg_assert_same('notes', $notes['state']);
	pg_assert_same('Glass', $notes['notes'][0]['layer']);
	pg_assert_true($notes['notes'][0]['text'] !== '', 'worded');
	$failed = pg_figma_report_frame(array('name' => 'Home', 'failed' => true, 'report' => array()));
	pg_assert_same('failed', $failed['state']);
	pg_assert_true(pg_figma_report_words('type') !== pg_figma_report_words('nonsense'), 'each code its sentence');
}

function test_figma_import_path_is_the_sessions()
{
	pg_assert_same('', pg_figma_import_path('../etc'), 'only an import id');
	pg_assert_contains('/data/temp/figma_', pg_figma_import_path('0123456789abcdef'));
}
