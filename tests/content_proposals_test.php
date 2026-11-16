<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for content changes that wait for approval
 * (includes/fn/content_proposals.php): the switch, which saves are held,
 * the operations a held save is kept as, one waiting change per page and
 * editor, and the assistant's readers of design_proposals never returning
 * an editor's row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');

// A save that would be held: every condition met.
function _content_proposals_test_ctx($over = array())
{
	return array_merge(array(
		'on'          => true,
		'editor_save' => true,
		'restricted'  => true,
		'page_id'     => 7,
		'visual'      => true,
		'has_tree'    => true,
		'draft_was'   => false,
		'draft_want'  => false,
	), $over);
}

// A small page: a heading, a picture, a marked area with a paragraph, a
// shared component.
function _content_proposals_test_tree()
{
	return array('type' => 'root', 'props' => array(), 'children' => array(
		array('_id' => 'n1', 'type' => 'content', 'props' => array('contentType' => 'heading', 'text' => 'Welcome'), 'children' => array()),
		array('_id' => 'n2', 'type' => 'content', 'props' => array('contentType' => 'image', 'src' => '/a.jpg', 'alt' => 'A'), 'children' => array()),
		array('_id' => 'n3', 'type' => 'semantic', 'props' => array('tag' => 'img', '_attrs' => array(array('name' => 'src', 'value' => '/b.jpg'))), 'children' => array()),
		array('_id' => 'n4', 'type' => 'container', 'props' => array('_editable' => 1), 'children' => array(
			array('_id' => 'n5', 'type' => 'content', 'props' => array('contentType' => 'text', 'text' => 'Inside'), 'children' => array()),
		)),
		array('_id' => 'n6', 'type' => 'shared_ref', 'props' => array('ref' => 3), 'children' => array()),
	));
}

// The switch is off on every site: nothing stored, or anything unreadable.
function test_content_proposals_switch_off_by_default()
{
	pg_assert_same(array('on' => false, 'channel_id' => 0), pg_content_proposals_settings_read(''));
	pg_assert_same(array('on' => false, 'channel_id' => 0), pg_content_proposals_settings_read('not json'));
	pg_assert_same(array('on' => false, 'channel_id' => 0), pg_content_proposals_settings_read(null));
	pg_assert_same(array('on' => true, 'channel_id' => 12), pg_content_proposals_settings_read(pg_content_proposals_settings_encode(true, 12)));
	pg_assert_same(array('on' => false, 'channel_id' => 0), pg_content_proposals_settings_read(pg_content_proposals_settings_encode(false, -4)));
}

// A1, A16: with the switch off no save is held.
function test_content_proposals_switch_off_holds_nothing()
{
	pg_assert_true(pg_content_proposals_applies(_content_proposals_test_ctx()), 'every condition met');
	pg_assert_false(pg_content_proposals_applies(_content_proposals_test_ctx(array('on' => false))), 'switch off');
}

// A2: the saves left alone.
function test_content_proposals_saves_left_alone()
{
	$cases = array(
		'a designer\'s or administrator\'s save' => array('restricted' => false),
		'the API, the assistant, any other caller' => array('editor_save' => false),
		'a new page'                              => array('page_id' => 0),
		'a classic page'                          => array('visual' => false),
		'a page with no stored tree'              => array('has_tree' => false),
		'a draft'                                 => array('draft_was' => true),
		'a page being made a draft'               => array('draft_want' => true),
	);

	foreach ($cases as $label => $over) {
		pg_assert_false(pg_content_proposals_applies(_content_proposals_test_ctx($over)), $label);
	}
}

// A3: the save is kept as one waiting change per page and editor; a second
// save replaces it, an unchanged save withdraws it.
function test_content_proposals_one_waiting_change()
{
	pg_assert_same('insert', pg_content_proposals_plan(false, true), 'the first change');
	pg_assert_same('replace', pg_content_proposals_plan(true, true), 'a second save');
	pg_assert_same('withdraw', pg_content_proposals_plan(true, false), 'the page saved as it is');
	pg_assert_same('none', pg_content_proposals_plan(false, false), 'nothing waiting, nothing changed');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');
	pg_assert_contains("WHERE page_id = ? AND agent = ? AND requested_by = ? AND status = 'pending'", $source, 'the waiting change is the page\'s and the editor\'s');
	pg_assert_contains("SET status = 'dismissed', decided_by = requested_by", $source, 'withdrawn is dismissed by the editor');
}

// A3: what the gate takes outside a marked area is kept per node with the
// value it replaces.
function test_content_proposals_ops_per_node()
{
	$stored = _content_proposals_test_tree();
	$merged = $stored;
	$merged['children'][0]['props']['text'] = 'Hello';
	$merged['children'][1]['props']['src'] = '/c.jpg';
	$merged['children'][2]['props']['_attrs'][0]['value'] = '/d.jpg';
	$merged['children'][0]['props']['_notes'] = 'Check the wording';

	$ops = pg_content_proposals_ops($stored, $merged, array(), array());

	pg_assert_same(array(
		array('op' => 'text', 'node' => 'n1', 'text' => 'Hello', 'was' => 'Welcome'),
		array('op' => 'note', 'node' => 'n1', 'note' => 'Check the wording', 'was' => ''),
		array('op' => 'src', 'node' => 'n2', 'src' => '/c.jpg', 'was' => '/a.jpg'),
		array('op' => 'src', 'node' => 'n3', 'src' => '/d.jpg', 'was' => '/b.jpg'),
	), $ops);
}

// A3: a marked area is kept whole, with the fingerprint of the stored one.
function test_content_proposals_ops_area_whole()
{
	$stored = _content_proposals_test_tree();
	$merged = $stored;
	$merged['children'][3]['children'][0]['props']['text'] = 'Changed';
	$merged['children'][3]['children'][] = array('_id' => 'n9', 'type' => 'content', 'props' => array('contentType' => 'text', 'text' => 'New'), 'children' => array());

	$ops = pg_content_proposals_ops($stored, $merged, array(), array());

	pg_assert_same(1, count($ops), 'one operation for the area');
	pg_assert_same('area', $ops[0]['op']);
	pg_assert_same('n4', $ops[0]['node']);
	pg_assert_same($merged['children'][3], $ops[0]['tree']);
	pg_assert_same(pg_content_proposals_hash($stored['children'][3]), $ops[0]['was']);
}

// An unchanged save gives no operation; a shared component is never the
// page's to change.
function test_content_proposals_ops_unchanged_and_shared()
{
	$stored = _content_proposals_test_tree();
	pg_assert_same(array(), pg_content_proposals_ops($stored, $stored, array(), array()), 'unchanged');

	$merged = $stored;
	$merged['children'][4]['props']['ref'] = 9;
	pg_assert_same(array(), pg_content_proposals_ops($stored, $merged, array(), array()), 'shared component');
}

// A tree whose nodes carry no ids is kept whole against the page it was
// made from.
function test_content_proposals_ops_tree_without_ids()
{
	$stored = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'content', 'props' => array('contentType' => 'heading', 'text' => 'Old'), 'children' => array()),
	));
	$merged = $stored;
	$merged['children'][0]['props']['text'] = 'New';

	pg_assert_false(pg_content_proposals_tree_has_ids($stored));
	pg_assert_true(pg_content_proposals_tree_has_ids(_content_proposals_test_tree()));
	pg_assert_same(array(array('op' => 'tree', 'tree' => $merged, 'base_hash' => pg_content_proposals_hash($stored))),
		pg_content_proposals_ops($stored, $merged, array(), array()));
	pg_assert_same(array(), pg_content_proposals_ops($stored, $stored, array(), array()), 'unchanged');
}

// The page settings a content level writes are kept with their stored
// values; a switch sent as true and stored as 1 is no change, and the
// name, folder and draft state are never among them.
function test_content_proposals_ops_fields()
{
	$stored = array('page_title' => 'Old', 'page_search' => 1, 'page_meta_tags' => array(), 'page_name' => 'a', 'page_draft' => 0);
	$sent = array('page_title' => 'New', 'page_search' => true, 'page_meta_tags' => array(), 'page_name' => 'b', 'page_draft' => 1, 'page_meta_description' => '');

	pg_assert_same(array('fields' => array('page_title' => 'New'), 'was' => array('page_title' => 'Old')),
		pg_content_proposals_field_changes($stored, $sent));
	pg_assert_false(in_array('page_name', pg_content_proposals_field_keys(), true));
	pg_assert_false(in_array('page_folder', pg_content_proposals_field_keys(), true));
	pg_assert_false(in_array('page_draft', pg_content_proposals_field_keys(), true));

	$tree = _content_proposals_test_tree();
	$ops = pg_content_proposals_ops($tree, $tree, $stored, $sent);
	pg_assert_same(array(array('op' => 'fields', 'fields' => array('page_title' => 'New'), 'was' => array('page_title' => 'Old'))), $ops);
	pg_assert_same(array('fields: page_title'), pg_content_proposals_lines($ops));
}

// A1, A2, A4: the save hands the gate's result to the module only with the
// switch on, from the Studio's own save, and returns without writing; the
// Studio's save marks itself and tells the editor.
function test_content_proposals_save_wiring()
{
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

	pg_assert_contains('if (pg_content_proposals_on() && pg_content_proposals_editor_save() && $page_id > 0) {', $designer, 'the setting is read first');
	pg_assert_contains('$held = pg_content_proposals_hold($user, $page_id, $style_id, $tree, $page);', $designer, 'the gate\'s result is held');
	pg_assert_true(strpos($designer, '$held = pg_content_proposals_hold(') < strpos($designer, 'pg_stamp_note_authors($tree, $page_id, $user);'), 'held before anything is written');
	pg_assert_contains('pg_content_proposals_editor_save(true);', $screen, 'the Studio\'s save marks itself');
	pg_assert_contains("if (\$r['ok'] && !empty(\$r['held'])) {", $screen, 'a held page is not treated as written');
	pg_assert_contains("\$warnings[] = \$r['held_message'];", $screen, 'the answer says the change waits');
}

// None of the assistant's readers returns an editor's row.
function test_content_proposals_assistant_readers_skip_editor_rows()
{
	$ai = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_ai.php');
	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/design.php');
	$threads = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/threads.php');

	pg_assert_contains("define('PG_DESIGN_AI_ROWS', \"agent <> 'editor'\");", $ai);

	$readers = array(
		'pg_design_ai_proposal()'         => array($ai, "WHERE id = '\" . (int) \$id . \"' AND \" . PG_DESIGN_AI_ROWS"),
		'pg_design_ai_request_present()'  => array($ai, "WHERE request_id = '\" . (int) \$row['id'] . \"' AND \" . PG_DESIGN_AI_ROWS"),
		'pg_design_ai_attach()'           => array($ai, "AND message_id = 0 AND \" . PG_DESIGN_AI_ROWS"),
		'pg_design_ai_proposals_map()'    => array($ai, "WHERE message_id IN (\" . implode(',', \$ids) . \") AND \" . PG_DESIGN_AI_ROWS"),
		'api_design_proposals_list()'     => array($api, "\$where = \"page_id = '\" . (int)\$page['page_id'] . \"' AND \" . PG_DESIGN_AI_ROWS;"),
		'pending_proposals'               => array($api, "design_proposals.status = 'pending' AND design_proposals.agent <> 'editor'"),
		'the conversation cleanup'        => array($threads, "((\$table === 'design_proposals') ? \" AND agent <> 'editor'\" : '')"),
	);

	foreach ($readers as $label => $check) {
		pg_assert_contains($check[1], $check[0], $label);
	}

	// Every other query of the table in those files goes through one of them.
	pg_assert_same(4, substr_count($ai, 'FROM design_proposals') + substr_count($ai, 'UPDATE design_proposals SET channel_id'), 'the readers of designer_ai.php');
	pg_assert_same(2, substr_count($api, 'FROM design_proposals'), 'the readers of the API');
}
