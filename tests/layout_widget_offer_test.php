<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the widget offered beside a classic page's custom layout
 * (pg_layout_widget_kinds(), pg_layout_widget_seed()): which page types get
 * a widget and of which kind, and which type settings the widget's config
 * carries over.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

function test_layout_widget_kinds()
{
	$kinds = pg_layout_widget_kinds();
	$labels = pg_sw_type_labels();

	foreach ($kinds as $type => $kind) {
		pg_assert_true(check_if_page_type_supports_layout($type), $type . ': draws a layout');
		pg_assert_true(isset($labels[$kind]), $type . ': ' . $kind . ' is a registered kind');
	}

	pg_assert_true(!isset($kinds['custom form']), 'a custom form page converts its layout instead');
	pg_assert_true(!isset($kinds['order receipt']), 'no widget for the order receipt');
	pg_assert_same('catalog_listing', $kinds['catalog']);
	pg_assert_same('form_list_view', $kinds['form list view']);

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("'layoutWidgets' => pg_layout_widget_offers(\$layout_pages)", $screen, 'the editor gets the offers of its layout pages');

	// Every widget the account module names for a layout type is offered.
	foreach (pg_sw_page_type_widgets() as $type => $kind) {
		if (check_if_page_type_supports_layout($type)) {
			pg_assert_same($kind, isset($kinds[$type]) ? $kinds[$type] : '', $type . ': the same kind as pg_sw_page_type_widgets()');
		}
	}
}

function test_layout_widget_seed_catalog()
{
	$cfg = pg_layout_widget_seed('catalog', array('product_group_id' => '12', 'catalog_detail_page_id' => '40', 'search' => '1', 'number_of_columns' => '3'));
	pg_assert_same(array('product_group_id' => 12, 'detail_page_id' => 40, 'search_enabled' => true), $cfg);

	pg_assert_same(array('next_page_id' => 7), pg_layout_widget_seed('catalog detail', array('next_page_id' => '7')));
	pg_assert_same(array(), pg_layout_widget_seed('catalog detail', array('next_page_id' => '0')), 'no next page: the widget\'s own');
	pg_assert_same(array('product_group_id' => 0, 'detail_page_id' => 0, 'search_enabled' => false), pg_layout_widget_seed('catalog', array()), 'a page without its type row');
}

function test_layout_widget_seed_form_views()
{
	$row = array(
		'custom_form_page_id'                => '5',
		'form_item_view_page_id'             => '6',
		'maximum_number_of_results'          => '0',
		'maximum_number_of_results_per_page' => '25',
		'search'                             => '1',
		'search_label'                       => 'Find',
		'viewer_filter'                      => '1',
		'viewer_filter_submitter'            => '1',
		'viewer_filter_watcher'              => '0',
		'viewer_filter_editor'               => '0',
		'order_by_1_standard_field'          => '',
		'order_by_1_form_field_id'           => '31',
		'order_by_1_type'                    => 'descending_alphabetical',
		'order_by_2_standard_field'          => 'submitted_date_and_time',
		'order_by_2_type'                    => 'ascending_alphabetical',
	);
	$cfg = pg_layout_widget_seed('form list view', $row, array(31 => 'title'));
	pg_assert_same(5, $cfg['form_page_id']);
	pg_assert_same(6, $cfg['detail_page_id']);
	pg_assert_same(0, $cfg['max_results']);
	pg_assert_same(25, $cfg['items_per_page']);
	pg_assert_same(true, $cfg['search_enabled']);
	pg_assert_same('Find', $cfg['search_label']);
	pg_assert_same(true, $cfg['viewer_filter']);
	pg_assert_same(true, $cfg['viewer_filter_submitter']);
	pg_assert_same(false, $cfg['viewer_filter_watcher']);
	pg_assert_same('title', $cfg['order_by_field'], 'a form field by its name');
	pg_assert_same('DESC', $cfg['order_by_direction']);
	pg_assert_same('submitted_date_and_time', $cfg['order_by_2_field']);
	pg_assert_same('ASC', $cfg['order_by_2_direction']);

	$row['order_by_1_standard_field'] = 'random';
	$row['order_by_2_form_field_id'] = '99';
	$row['order_by_2_standard_field'] = '';
	$row['maximum_number_of_results_per_page'] = '0';
	$row['search_label'] = '';
	$cfg = pg_layout_widget_seed('form list view', $row, array(31 => 'title'));
	pg_assert_true(!isset($cfg['order_by_field']), 'random: the widget\'s own order');
	pg_assert_true(!isset($cfg['order_by_2_field']), 'a field the form no longer has');
	pg_assert_true(!isset($cfg['items_per_page']), 'no page size: the widget\'s own');
	pg_assert_true(!isset($cfg['search_label']), 'no label: the widget\'s own');

	pg_assert_same(array('form_page_id' => 5), pg_layout_widget_seed('form item view', array('custom_form_page_id' => '5', 'layout' => 'x')));
}

function test_layout_widget_seed_types_reading_their_row()
{
	foreach (array('shopping cart', 'express order', 'billing information', 'order form', 'photo gallery', 'login') as $type) {
		pg_assert_same(array(), pg_layout_widget_seed($type, array('next_page_id' => '3')), $type . ': the widget reads its page itself');
	}
}

// One function of style_designer.js, from its declaration to its closing
// brace; '' when it is not there.
function _layout_widget_js_function($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

function test_layout_widget_editor()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("_sdLayoutPropsHtml() + _sdLayoutWidgetPropsHtml(n)", $js, 'offered in the system region\'s panel');
	pg_assert_contains("_sdLegacyRegionOwn(n)) return null;", $js, 'only for the page\'s own region');
	pg_assert_contains("_sdLayoutWidgetPlace(selectedNode)", $js, 'the offer button is wired');
	pg_assert_contains("_sdLayoutRegionRemove(selectedNode)", $js, 'the removal button is wired');
	pg_assert_contains("parent.children.splice(idx + 1, 0, ref);", _layout_widget_js_function($js, '_sdLayoutWidgetPlace'), 'placed after the region, which stays');
	pg_assert_contains("window.confirm(", _layout_widget_js_function($js, '_sdLayoutRegionRemove'), 'the region goes only after asking');

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}

	$src = '';
	foreach (array('_sdLayoutWidgetPlaced', '_sdLayoutWidgetCfg') as $name) {
		$body = _layout_widget_js_function($js, $name);
		pg_assert_true($body !== '', 'style_designer.js has ' . $name . '()');
		$src .= $body . "\n";
	}
	$script = $src . <<<'JS'
function _swTypeDefaults(cfg, type) { cfg.regionType = type; if (cfg.max_results === undefined) cfg.max_results = 50; return cfg; }
var kinds = { 1: 'catalog_listing', 2: 'form_list_view' };
var kindOf = function (sid) { return kinds[sid] || ''; };
var tree = { type: 'root', children: [
    { type: 'region', props: { regionType: 'system' }, children: [] },
    { type: 'container', props: {}, children: [ { type: 'shared_ref', props: { sharedId: 2 }, children: [] } ] }
] };
var seed = { kind: 'form_list_view', cfg: { form_page_id: 5, max_results: 0, ids: [1, 2] } };
var cfg = _sdLayoutWidgetCfg(seed);
cfg.ids.push(3);
console.log(JSON.stringify({
    nested: _sdLayoutWidgetPlaced(tree, 'form_list_view', kindOf),
    other: _sdLayoutWidgetPlaced(tree, 'catalog_listing', kindOf),
    empty: _sdLayoutWidgetPlaced(null, 'catalog_listing', kindOf),
    cfg: _sdLayoutWidgetCfg(seed),
    copied: seed.cfg.ids.length,
    emptyCfg: _sdLayoutWidgetCfg({ kind: 'catalog_listing', cfg: [] })
}));
JS;
	$file = tempnam(sys_get_temp_dir(), 'pglwo');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);
	if (!pg_assert_true(is_array($res), 'the script answers')) {
		return;
	}
	pg_assert_same(true, $res['nested'], 'a widget nested in a container counts');
	pg_assert_same(false, $res['other'], 'a widget of another kind does not');
	pg_assert_same(false, $res['empty']);
	pg_assert_same(array('form_page_id' => 5, 'max_results' => 0, 'ids' => array(1, 2), 'regionType' => 'form_list_view'), $res['cfg'], 'the carried settings win over the defaults');
	pg_assert_same(2, $res['copied'], 'the offer is not changed by the widget made from it');
	pg_assert_same(array('regionType' => 'catalog_listing', 'max_results' => 50), $res['emptyCfg'], 'no settings (an empty PHP array): the defaults');
}
