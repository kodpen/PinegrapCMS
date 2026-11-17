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

// Only a designer or an administrator may turn the hold on or off: the
// Features category also opens for a Manager, whose saves the hold applies to.
function test_content_proposals_switch_is_designers_only()
{
	pg_assert_true(pg_content_proposals_may_set(array('role' => 0)), 'administrator');
	pg_assert_true(pg_content_proposals_may_set(array('role' => 1)), 'designer');
	pg_assert_false(pg_content_proposals_may_set(array('role' => 2)), 'manager');
	pg_assert_false(pg_content_proposals_may_set(array('role' => 3)), 'user');
	pg_assert_false(pg_content_proposals_may_set(null), 'nobody');

	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/features.php');
	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/features.save.php');
	$registry = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

	pg_assert_contains('if (pg_content_proposals_ready() && pg_content_proposals_may_set($user)) {', $card, 'the card is drawn for a designer only');
	pg_assert_contains("if (array_key_exists('content_approval_card', \$_POST) && pg_content_proposals_may_set(\$user)) {", $save, 'a Manager\'s post is ignored');
	pg_assert_contains("unset(\$categories['features']['sections']['pgset-content-approval'], \$categories['features']['keywords']['pgset-content-approval']);", $registry, 'no chip or search result for a Manager');
}

// The channel kept is one the card offers: 0, or an open public or private
// channel; anything else keeps the stored one.
function test_content_proposals_channel_offered()
{
	pg_assert_same("kind IN ('public', 'private') AND archived_at = 0", PG_CONTENT_PROPOSALS_CHANNELS);

	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/features.php');
	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/features.save.php');

	pg_assert_contains("FROM ws_channels WHERE kind IN ('public', 'private') AND archived_at = 0 ORDER BY name", $card, 'the select lists the same channels');
	pg_assert_contains('pg_content_proposals_channel_offered(', $save, 'the save checks the posted channel');
}

// A write of the waiting change that fails answers the save as failed, never
// as a change that waits.
function test_content_proposals_failed_write_fails_the_save()
{
	pg_assert_true(pg_content_proposals_written('insert', true));
	pg_assert_false(pg_content_proposals_written('insert', false), 'insert');
	pg_assert_false(pg_content_proposals_written('replace', false), 'replace');
	pg_assert_false(pg_content_proposals_written('withdraw', false), 'withdraw');
	pg_assert_true(pg_content_proposals_written('none', null), 'nothing to write');

	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');

	pg_assert_contains("if (!\$held['ok']) {", $designer, 'the save reads the hold\'s answer');
	pg_assert_contains("'errors' => array(\$held['error'])", $designer, 'and answers with its error');
	pg_assert_contains('if (!pg_content_proposals_written($action, $written)) {', $module, 'every write is checked');
}

// A8: the operations go on the page as it is now, and apply writes one
// revision through the design save, the gate run again over the result.
function test_content_proposals_apply_puts_each_op()
{
	$stored = _content_proposals_test_tree();
	$merged = $stored;
	$merged['children'][0]['props']['text'] = 'Hello';
	$merged['children'][1]['props']['src'] = '/c.jpg';
	$merged['children'][2]['props']['_attrs'][0]['value'] = '/d.jpg';
	$merged['children'][3]['children'][0]['props']['text'] = 'Changed';
	$fields = array('page_title' => 'Old');
	$ops = pg_content_proposals_ops($stored, $merged, $fields, array('page_title' => 'New'));

	$applied = pg_content_proposals_apply_ops($stored, $fields, $ops);

	pg_assert_true($applied['ok']);
	pg_assert_same($merged, $applied['tree'], 'the page as the editor saved it');
	pg_assert_same('New', $applied['fields']['page_title']);

	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');
	pg_assert_contains('$tree = pg_designer_merge_restricted_tree($page[\'tree\'], $applied[\'tree\']);', $module, 'the gate stays the authority');
	pg_assert_contains("if (!pg_collab_may_edit_page('', (int) \$row['page_id'])) {", $module, 'the page lock');
	pg_assert_true(substr_count($module, '$saved = pg_designer_save_page(') === 1, 'one save: one revision');
}

// A8: History names the designer who applied it and the editor.
function test_content_proposals_history_names_both()
{
	pg_assert_same('ayse', pg_content_proposals_revision_by('ayse', ''));
	pg_assert_contains('mehmet', pg_content_proposals_revision_by('ayse', 'mehmet'));
	pg_assert_contains('ayse', pg_content_proposals_revision_by('ayse', 'mehmet'));

	$revisions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_revisions.php');
	pg_assert_contains("\$by = pg_content_proposals_revision_by(\$by, \$editor);", $revisions);
}

// A9: a change whose node is gone, or whose node a designer changed since,
// is stale and cannot be applied.
function test_content_proposals_stale_refused()
{
	$stored = _content_proposals_test_tree();
	$merged = $stored;
	$merged['children'][0]['props']['text'] = 'Hello';
	$ops = pg_content_proposals_ops($stored, $merged, array(), array());

	$gone = $stored;
	array_shift($gone['children']);
	pg_assert_same('gone', pg_content_proposals_apply_ops($gone, array(), $ops)['error'], 'node gone');

	$changed = $stored;
	$changed['children'][0]['props']['text'] = 'A designer\'s words';
	pg_assert_same('changed', pg_content_proposals_apply_ops($changed, array(), $ops)['error'], 'value changed');

	$fields_ops = pg_content_proposals_ops($stored, $stored, array('page_title' => 'Old'), array('page_title' => 'New'));
	pg_assert_same('changed', pg_content_proposals_apply_ops($stored, array('page_title' => 'Other'), $fields_ops)['error'], 'setting changed');

	$no_ids = array('type' => 'root', 'props' => array(), 'children' => array(array('type' => 'content', 'props' => array('text' => 'a'), 'children' => array())));
	$tree_ops = array(array('op' => 'tree', 'tree' => $no_ids, 'base_hash' => 'not this page'));
	pg_assert_same('changed', pg_content_proposals_apply_ops($no_ids, array(), $tree_ops)['error'], 'whole tree, page changed');

	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');
	pg_assert_contains("UPDATE design_proposals SET status = 'stale'", $module, 'marked stale');
}

// A10: dismiss takes a reason of at most 300 characters; a closed change
// stays in the list for 30 days.
function test_content_proposals_dismiss_reason()
{
	pg_assert_same('Wrong price', pg_content_proposals_reason("  Wrong \n price "));
	pg_assert_same('', pg_content_proposals_reason(''));
	pg_assert_same(str_repeat('ş', 300), pg_content_proposals_reason(str_repeat('ş', 300)), '300 characters, not bytes');
	pg_assert_same(null, pg_content_proposals_reason(str_repeat('a', 301)), 'over 300 refused');

	$now = 1800000000;
	pg_assert_true(pg_content_proposals_listed(array('status' => 'pending', 'decided_at' => 0), $now));
	pg_assert_true(pg_content_proposals_listed(array('status' => 'dismissed', 'decided_at' => $now - 29 * 86400), $now), '29 days');
	pg_assert_false(pg_content_proposals_listed(array('status' => 'dismissed', 'decided_at' => $now - 31 * 86400), $now), '31 days');
}

// A11: take back by the person who applied it, refused after a later save.
function test_content_proposals_take_back()
{
	$row = array('status' => 'applied', 'applied_to' => 'page', 'before_tree' => '{}', 'decided_by' => 5, 'after_hash' => 'abc');

	pg_assert_same('', pg_content_proposals_revert_check($row, 5, 'abc'), 'as the apply left it');
	pg_assert_same('saved', pg_content_proposals_revert_check($row, 5, 'later'), 'saved since');
	pg_assert_same('other', pg_content_proposals_revert_check($row, 6, 'abc'), 'another designer');
	pg_assert_same('closed', pg_content_proposals_revert_check(array('status' => 'pending') + $row, 5, 'abc'), 'not applied');
}

// A7: the list is the module's own screen, for roles 0 and 1 only.
function test_content_proposals_list_screen()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/content_changes.php');

	pg_assert_contains("validate_area_access(\$user, 'designer');", $screen);
	pg_assert_contains('validate_token_field();', $screen);
	pg_assert_false(pg_content_proposals_may_decide(array('role' => 2)), 'a Manager decides nothing');
	pg_assert_true(pg_content_proposals_may_decide(array('role' => 1)));
}

// The list's order: waiting first, then stale, then the closed ones of the
// last 30 days; each newest first.
function test_content_proposals_list_order()
{
	$now = 1800000000;
	$rows = array(
		array('id' => 1, 'status' => 'dismissed', 'decided_at' => $now - 86400, 'created_at' => $now - 9000),
		array('id' => 2, 'status' => 'pending', 'decided_at' => 0, 'created_at' => $now - 5000),
		array('id' => 3, 'status' => 'stale', 'decided_at' => 0, 'created_at' => $now - 7000),
		array('id' => 4, 'status' => 'pending', 'decided_at' => 0, 'created_at' => $now - 100),
		array('id' => 5, 'status' => 'applied', 'decided_at' => $now - 40 * 86400, 'created_at' => $now - 41 * 86400),
		array('id' => 6, 'status' => 'reverted', 'decided_at' => $now - 60, 'created_at' => $now - 8000),
	);

	pg_assert_same(array(4, 2, 3, 6, 1), array_map(function ($row) { return $row['id']; }, pg_content_proposals_order($rows, $now)));
}

// A picture's small image is drawn only for the site's own files.
function test_content_proposals_local_picture()
{
	pg_assert_true(pg_content_proposals_local_picture('/files/team.jpg'));
	pg_assert_true(pg_content_proposals_local_picture('files/team.jpg'));
	pg_assert_false(pg_content_proposals_local_picture('https://cdn.example.com/a.jpg'), 'another site');
	pg_assert_false(pg_content_proposals_local_picture('//cdn.example.com/a.jpg'), 'protocol-relative');
	pg_assert_false(pg_content_proposals_local_picture('javascript:alert(1)'), 'a scheme');
	pg_assert_false(pg_content_proposals_local_picture('data:image/png;base64,AAA'), 'data');
	pg_assert_false(pg_content_proposals_local_picture(''), 'empty');
}

// A12: the designers are told when a change starts waiting, at most once an
// hour per page.
function test_content_proposals_bell_once_an_hour()
{
	$now = 1800000000;

	pg_assert_true(pg_content_proposals_bell_due(0, $now), 'never told');
	pg_assert_false(pg_content_proposals_bell_due($now - 600, $now), '10 minutes ago');
	pg_assert_false(pg_content_proposals_bell_due($now - 3599, $now), 'not yet an hour');
	pg_assert_true(pg_content_proposals_bell_due($now - 3600, $now), 'an hour');
}

// A12: the bell lines are addressed rows of the panel's own notifications,
// one action per outcome, shown with the Workspace on or off.
function test_content_proposals_bell_lines()
{
	pg_assert_same(array('content_change', 'content_applied', 'content_dismissed', 'content_stale'), pg_content_proposals_bell_actions());

	foreach (pg_content_proposals_bell_actions() as $action) {
		pg_assert_true(pg_notification_visible(array('action' => $action, 'target_user_id' => 5), array('id' => 5, 'role' => 1)), $action . ': its addressee');
		pg_assert_false(pg_notification_visible(array('action' => $action, 'target_user_id' => 5), array('id' => 6, 'role' => 0)), $action . ': another person');
		pg_assert_false(pg_notification_visible(array('action' => $action, 'target_user_id' => 0), array('id' => 6, 'role' => 0)), $action . ': never unaddressed');
		pg_assert_same('content', pg_notification_module($action), $action);
	}

	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');
	pg_assert_contains("pg_content_proposals_bell_editor(\$row, 'applied');", $module, 'the editor told on apply');
	pg_assert_contains("pg_content_proposals_bell_editor(\$row, 'stale');", $module, 'the editor told when stale');
	pg_assert_contains("pg_content_proposals_bell_editor(\$row, 'dismissed');", $module, 'the editor told on dismiss');
	pg_assert_contains('pg_content_proposals_bell_designers($page_id, $name);', $module, 'the designers told when a change starts waiting');
}

// The row keeps data, not a sentence: the page's name as the title, the
// reason read from the change; the words are made when the line is read.
function test_content_proposals_bell_words()
{
	foreach (pg_content_proposals_bell_actions() as $action) {
		$words = pg_content_proposals_bell_words($action, 'about-us', 'Too long');
		pg_assert_true($words['short'] !== '' && strpos($words['short'], ':') === false, $action . ': a short title without a colon');
		pg_assert_contains('about-us', $words['brief'], $action . ': the page under it');
		pg_assert_contains('about-us', $words['title'], $action . ': the push sentence');
	}

	pg_assert_contains('Too long', pg_content_proposals_bell_words('content_dismissed', 'about-us', 'Too long')['brief'], 'the reason, whole');
	pg_assert_contains(str_repeat('a', 300), pg_content_proposals_bell_words('content_dismissed', 'p', str_repeat('a', 300))['title'], 'never cut to 250');
	pg_assert_same('Change applied', pg_content_proposals_bell_words('content_applied', 'p')['short']);
	pg_assert_same('Change dismissed', pg_content_proposals_bell_words('content_dismissed', 'p')['short']);
	pg_assert_same('Change can no longer be applied', pg_content_proposals_bell_words('content_stale', 'p')['short']);
	pg_assert_same('Content change waiting', pg_content_proposals_bell_words('content_change', 'p')['short']);

	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');
	pg_assert_contains("array('content_' . \$kind, mb_substr(\$page, 0, 250)", $module, 'the title is the page name');
}

// A7: the count of waiting changes above the page list, for designers.
function test_content_proposals_count_on_page_list()
{
	$pages = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_pages.php');

	pg_assert_contains('pg_content_proposals_waiting_count()', $pages);
	pg_assert_contains('content_changes.php', $pages);
	pg_assert_contains('pg_content_proposals_may_decide($user)', $pages, 'designers only');
	pg_assert_contains("'One content change waits for approval.'", $pages, 'a sentence of its own for one');
}
