<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_cf_with_role() in includes/fn/custom_form.php: the RSS role a
 * design template puts on a control of its form tree, which the page save
 * then writes to the form's field (_pg_cf_controls()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The role lands on props._cf; what _cf already held stays.
function test_custom_form_role_is_set_and_merged()
{
	$file = array('type' => 'semantic', 'props' => array('tag' => 'input', '_cf' => array('upload_folder_id' => 7)), 'children' => array());
	$out = pg_cf_with_role($file, 'media');

	pg_assert_same('media', $out['props']['_cf']['rss_field'], 'role');
	pg_assert_same(7, $out['props']['_cf']['upload_folder_id'], 'upload folder kept');
	pg_assert_same('input', $out['props']['tag'], 'node kept');

	$bare = pg_cf_with_role(array('type' => 'semantic', 'props' => array('tag' => 'textarea')), 'description');
	pg_assert_same(array('rss_field' => 'description'), $bare['props']['_cf'], 'no _cf before');
}

// The control the template builds is read back with the role.
function test_custom_form_role_reaches_the_field()
{
	$control = pg_cf_with_role(array('type' => 'semantic', 'props' => array(
		'tag' => 'input',
		'_attrs' => array(array('name' => 'type', 'value' => 'text'), array('name' => 'name', 'value' => 'title')),
	), 'children' => array()), 'title');
	$fields = _pg_cf_controls(array('type' => 'semantic', 'props' => array('tag' => 'form'), 'children' => array($control)));

	pg_assert_same('title', $fields['title']['rss_field'], 'rss_field of the field');
}

// An unknown role, or something that is not a node, comes back unchanged.
function test_custom_form_role_refuses_unknown()
{
	$node = array('type' => 'semantic', 'props' => array('tag' => 'input'));

	pg_assert_same($node, pg_cf_with_role($node, 'subject'), 'unknown role');
	pg_assert_same($node, pg_cf_with_role($node, ''), 'empty role');
	pg_assert_same('x', pg_cf_with_role('x', 'title'), 'not a node');
}
