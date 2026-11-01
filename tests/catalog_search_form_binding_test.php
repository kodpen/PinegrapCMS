<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The catalog listing's bound search form (search_form / search_query
 * bindings): the text box gets an accessible name when the layout gave it
 * none, and the hidden inputs leave out the router's page name on a pretty
 * address, as the widget's own search box does.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _catalog_search_form_input($attrs)
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'form',
		'_bindings' => array('action' => 'search_form')), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'input', '_attrs' => $attrs,
			'_bindings' => array('value' => 'search_query')), 'children' => array()),
	));
	_apply_catalog_listing_bindings($tree, array(
		'search_action_url'  => '/magaza',
		'search_query_param' => 'query',
		'search_query'       => 'lamp',
		'search_label'       => 'Search products',
	));
	$out = array();
	foreach ($tree['children'] as $child) {
		if (isset($child['props']['tag']) && $child['props']['tag'] === 'input') {
			foreach ($child['props']['_attrs'] as $a) $out[$a['name']] = $a['value'];
		}
	}
	return $out;
}

// A saved box with only a placeholder is named.
function test_catalog_search_box_gets_a_name()
{
	$attrs = _catalog_search_form_input(array(array('name' => 'placeholder', 'value' => 'Search…')));
	pg_assert_same('Search products', isset($attrs['aria-label']) ? $attrs['aria-label'] : null, 'aria-label added');
	pg_assert_same('query', $attrs['name'], 'name');
	pg_assert_same('lamp', $attrs['value'], 'value');
}

// A name the layout gave stays as it is.
function test_catalog_search_box_keeps_its_own_name()
{
	$attrs = _catalog_search_form_input(array(array('name' => 'aria-label', 'value' => 'Find a book')));
	pg_assert_same('Find a book', $attrs['aria-label'], 'own aria-label kept');
	$attrs = _catalog_search_form_input(array(array('name' => 'aria-labelledby', 'value' => 'lbl')));
	pg_assert_false(isset($attrs['aria-label']), 'aria-labelledby is a name');
}

// The bound form's hidden inputs come from the shared helper, which drops
// the router's page name on a pretty address; the box is named by lang().
function test_catalog_bound_search_form_uses_the_shared_hidden_inputs()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');
	pg_assert_contains('$_pg_search_hidden = _pg_sw_hidden_query_inputs(array((string)$query_param, (string)$page_param));', $src);
	pg_assert_contains("'search_label'         => (string)lang('Search products'),", $src);
}

// The editor's catalog starter names its search box too.
function test_catalog_starter_search_box_is_named()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$start = strpos($js, "customName: _sdT('Search Form')");
	pg_assert_true($start !== false, 'starter search form found');
	$chunk = substr($js, (int) $start, 600);
	pg_assert_contains("{ name: 'aria-label', value: _sdT('Search products') }", $chunk);
}
