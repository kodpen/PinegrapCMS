<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for shared components opened to the content level
 * (includes/designer_access.php): who may edit one, and what a content-level
 * save of a component's tree keeps.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');

// The common region's rule: managers edit every opened component, users the
// ones granted to them; nobody edits a closed one or a system widget.
function test_shared_component_content_rule_follows_the_common_region()
{
	pg_assert_true(pg_shared_component_content_rule(2, true, false, false), 'manager, opened');
	pg_assert_true(pg_shared_component_content_rule(3, true, false, true), 'user, opened, granted');
	pg_assert_false(pg_shared_component_content_rule(3, true, false, false), 'user, opened, not granted');
	pg_assert_false(pg_shared_component_content_rule(2, false, false, true), 'manager, closed');
	pg_assert_false(pg_shared_component_content_rule(3, false, false, true), 'user, closed, granted');
	pg_assert_false(pg_shared_component_content_rule(2, true, true, true), 'system widget');
	pg_assert_false(pg_shared_component_content_rule(4, true, false, true), 'no role');
	pg_assert_true(pg_shared_component_content_rule(1, true, false, false), 'designer');
}

// Without the schema step nobody is given a component.
function test_shared_component_content_needs_the_schema()
{
	pg_assert_same(array(), pg_shared_component_content_editable_ids(array('role' => 2, 'id' => 5), array(7)));
}

// A component's tree is merged as a page's is: text anywhere, the whole inside
// of an editable area, and nested components and code nodes as stored.
function test_shared_component_content_save_keeps_the_design()
{
	$stored = array('type' => 'root', '_id' => 'r', 'props' => array(), 'children' => array(
		array('type' => 'semantic', '_id' => 'h', 'props' => array('tag' => 'header', 'cssClass' => 'bg-dark'), 'children' => array(
			array('type' => 'content', '_id' => 't', 'props' => array('contentType' => 'heading', 'text' => 'Old title'), 'children' => array()),
			array('type' => 'shared_ref', '_id' => 's', 'props' => array('sharedId' => 3), 'children' => array()),
		)),
		array('type' => 'semantic', '_id' => 'e', 'props' => array('tag' => 'div', '_editable' => 1), 'children' => array(
			array('type' => 'content', '_id' => 'p', 'props' => array('contentType' => 'paragraph', 'text' => 'Old text'), 'children' => array()),
		)),
	));

	$submitted = $stored;
	$submitted['children'][0]['props']['cssClass'] = 'bg-danger';
	$submitted['children'][0]['children'][0]['props']['text'] = 'New title';
	$submitted['children'][0]['children'][1]['props']['sharedId'] = 9;
	$submitted['children'][1]['children'][0]['props']['text'] = 'New text';
	$submitted['children'][1]['children'][] = array('type' => 'content', '_id' => 'q', 'props' => array('contentType' => 'paragraph', 'text' => 'Added <?php echo 1; ?>'), 'children' => array());
	$submitted['children'][1]['children'][] = array('type' => 'content', '_id' => 'x', 'props' => array('contentType' => 'custom_php', 'code' => 'echo 1;'), 'children' => array());

	$merged = pg_designer_merge_restricted_tree($stored, $submitted);

	pg_assert_same('bg-dark', $merged['children'][0]['props']['cssClass'], 'layout outside the area');
	pg_assert_same('New title', $merged['children'][0]['children'][0]['props']['text'], 'text outside the area');
	pg_assert_same(3, $merged['children'][0]['children'][1]['props']['sharedId'], 'nested component');
	pg_assert_same('New text', $merged['children'][1]['children'][0]['props']['text'], 'text inside the area');
	pg_assert_same(2, count($merged['children'][1]['children']), 'added paragraph kept, added code node dropped');
	pg_assert_same('Added &lt;?php echo 1; ?>', $merged['children'][1]['children'][1]['props']['text'], 'PHP tag defused');
	pg_assert_same(1, $merged['children'][1]['props']['_editable'], 'area mark kept');
}
