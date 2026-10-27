<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the catalog listing's filter chip default parts
 * (includes/fn/widgets_parts.php) and the catalog.active_filters list they
 * draw (widgets_lists_product.php): the two old tokens, their inner twins
 * and the two section bindings show the visitor the same words, links and
 * classes as the markup the listing built before; the editor builds the
 * same parts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The attributes the listing read, as the renderer keeps them.
function _parts_fc_attrs()
{
	return array(
		4 => array('attr_label' => 'Colour', 'options' => array(
			array('option_id' => 11, 'option_label' => 'Red & Blue'),
			array('option_id' => 12, 'option_label' => 'Green'),
		)),
		6 => array('attr_label' => 'Size', 'options' => array(
			array('option_id' => 21, 'option_label' => 'M'),
		)),
	);
}

function _parts_fc_rows()
{
	return pg_sw_list_active_filter_rows(array(4 => 11, 6 => 99, 8 => 3), _parts_fc_attrs(), function ($aid) {
		return '?pg_attr_' . $aid . '=&x=<1>';
	});
}

// The rows: one per active attribute filter, the option's label (else its
// number), the attribute's (else "Attribute"), the link that drops it.
function test_widget_parts_filter_chips_rows()
{
	pg_assert_same(array(
		pg_sw_list_active_filter_row('Colour: Red & Blue', '?pg_attr_4=&x=<1>'),
		pg_sw_list_active_filter_row('Size: #99', '?pg_attr_6=&x=<1>'),
		pg_sw_list_active_filter_row(lang('Attribute') . ': #3', '?pg_attr_8=&x=<1>'),
	), _parts_fc_rows(), 'one row per active filter');
	pg_assert_same(array(), pg_sw_list_active_filter_rows(array(), _parts_fc_attrs(), 'strval'), 'none');

	$d = pg_sw_list_sources();
	$tokens = array();
	foreach ($d['catalog.active_filters']['tokens'] as $pair) $tokens[] = $pair[0];
	pg_assert_same($tokens, array_keys(pg_sw_list_active_filter_row('a', 'b')), 'row keys are the tokens');
	$kind = pg_sw_kind_lists('catalog_listing');
	pg_assert_true(isset($kind['catalog.active_filters']), 'offered in the listing');
	$item = pg_sw_kind_lists('catalog_item_view');
	pg_assert_false(isset($item['catalog.active_filters']), 'not in the detail view');
}
