<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the reader behind the visual designer's History "Activity" tab
 * (includes/fn/designer_activity.php): rows of the stores a design already
 * keeps turned into events, their sentences, the cursor and the pages of
 * fifty, who sees which event.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/designer_activity.php');

// An event as the stores give them, only the fields the merge reads.
function _designer_activity_test_event($store, $id, $at)
{
	return array('store' => $store, 'id' => $id, 'at' => $at, 'key' => $store . ':' . $id);
}

function test_designer_activity_page_size_is_fifty()
{
	pg_assert_same(50, PG_DESIGNER_ACTIVITY_PAGE);
}

function test_designer_activity_cursor_round_trip()
{
	$event = _designer_activity_test_event('revision', 42, 1700000000);
	$cursor = pg_designer_activity_cursor($event);

	pg_assert_same('1700000000:revision:42', $cursor);
	pg_assert_same(array('at' => 1700000000, 'store' => 'revision', 'id' => 42), pg_designer_activity_cursor_parse($cursor));
}

function test_designer_activity_cursor_parse_refuses_junk()
{
	pg_assert_same(null, pg_designer_activity_cursor_parse(''));
	pg_assert_same(null, pg_designer_activity_cursor_parse('abc'));
	pg_assert_same(null, pg_designer_activity_cursor_parse('1700000000:nostore:4'));
	pg_assert_same(null, pg_designer_activity_cursor_parse("1700000000:revision:4' OR 1=1"));
	pg_assert_same(null, pg_designer_activity_cursor_parse('-5:revision:4'));
}

function test_designer_activity_after_sql_without_cursor()
{
	pg_assert_same('1', pg_designer_activity_after_sql('revision', null, 'r.created_at', 'r.revision_id'));
}

function test_designer_activity_after_sql_by_store_order()
{
	$cursor = array('at' => 100, 'store' => 'link', 'id' => 7);

	// The same store: same second only below the id.
	pg_assert_same("(link.created_at < 100 OR (link.created_at = 100 AND link.id < 7))",
		pg_designer_activity_after_sql('link', $cursor, 'link.created_at', 'link.id'));
	// A store sorted below the cursor's: the whole second still to come.
	pg_assert_same("(a.created_at <= 100)", pg_designer_activity_after_sql('audit', $cursor, 'a.created_at', 'a.id'));
	// A store sorted above it: that second is already shown.
	pg_assert_same("(r.created_at < 100)", pg_designer_activity_after_sql('revision', $cursor, 'r.created_at', 'r.revision_id'));
}

function test_designer_activity_merge_orders_newest_first_then_key()
{
	$lists = array(
		array(_designer_activity_test_event('revision', 1, 100), _designer_activity_test_event('revision', 3, 300)),
		array(_designer_activity_test_event('audit', 9, 300), _designer_activity_test_event('audit', 2, 200)),
	);

	list($events, $more, $next) = pg_designer_activity_merge($lists, 50);

	pg_assert_same(array('revision:3', 'audit:9', 'audit:2', 'revision:1'), array_column($events, 'key'));
	pg_assert_false($more);
	pg_assert_same('', $next);
}

function test_designer_activity_merge_cuts_a_page_and_gives_the_cursor()
{
	$list = array();
	for ($i = 1; $i <= 7; $i++) {
		$list[] = _designer_activity_test_event('verdict', $i, 1000 + $i);
	}

	list($events, $more, $next) = pg_designer_activity_merge(array($list), 5);

	pg_assert_same(5, count($events));
	pg_assert_true($more);
	pg_assert_same('1003:verdict:3', $next);
}

// Walking the pages with the SQL bound evaluated in PHP returns every
// event once, even when many share one second across stores.
function test_designer_activity_pages_walk_every_event_once()
{
	$all = array();
	foreach (array('audit', 'comment', 'link', 'revision', 'revoke', 'verdict') as $store) {
		for ($i = 1; $i <= 9; $i++) {
			$all[] = _designer_activity_test_event($store, $i, 500 + ($i % 2));
		}
	}

	$seen = array();
	$cursor = null;
	for ($round = 0; $round < 20; $round++) {
		$lists = array();
		foreach ($all as $event) {
			if (pg_designer_activity_is_after($event, $cursor)) {
				$lists[$event['store']][] = $event;
			}
		}
		list($events, $more, $next) = pg_designer_activity_merge(array_values($lists), 4);
		foreach ($events as $event) {
			$seen[] = $event['key'];
		}
		if (!$more) {
			break;
		}
		$cursor = pg_designer_activity_cursor_parse($next);
	}

	pg_assert_same(count($all), count($seen));
	pg_assert_same(count($all), count(array_unique($seen)));
}

function test_designer_activity_sentences()
{
	pg_assert_same('published Home', pg_designer_activity_sentence('publish', 'Home'));
	pg_assert_same('restored an earlier version of Prices', pg_designer_activity_sentence('restore', 'Prices'));
	pg_assert_same('imported About', pg_designer_activity_sentence('import', 'About'));
	pg_assert_same('changed Home with the assistant', pg_designer_activity_sentence('ai', 'Home'));
	pg_assert_same('published Home with a release bundle', pg_designer_activity_sentence('bundle', 'Home'));
	pg_assert_same('applied a content change to Home', pg_designer_activity_sentence('applied', 'Home'));
	pg_assert_same('moved Home to the Recycle Bin', pg_designer_activity_sentence('page_recycle', 'Home'));
	pg_assert_same('brought Home back from the Recycle Bin', pg_designer_activity_sentence('page_restore', 'Home'));
	pg_assert_same('deleted Home for good', pg_designer_activity_sentence('page_delete', 'Home'));
	pg_assert_same('made a share link to Home', pg_designer_activity_sentence('link_create', 'Home'));
	pg_assert_same('made a share link', pg_designer_activity_sentence('link_create', ''));
	pg_assert_same('revoked the share link to Home', pg_designer_activity_sentence('link_revoke', 'Home'));
	pg_assert_same('revoked a share link', pg_designer_activity_sentence('link_revoke', ''));
	pg_assert_same('approved Home', pg_designer_activity_sentence('approved', 'Home'));
	pg_assert_same('asked for changes to Home', pg_designer_activity_sentence('changes_requested', 'Home'));
	pg_assert_same('commented on Home', pg_designer_activity_sentence('comment', 'Home'));
	pg_assert_same('made the design file site.css', pg_designer_activity_sentence('file_create', 'site.css'));
	pg_assert_same('changed the design file site.css', pg_designer_activity_sentence('file_update', 'site.css'));
	pg_assert_same('deleted the design file site.css', pg_designer_activity_sentence('file_delete', 'site.css'));
	// An action the reader does not know is never shown as another one.
	pg_assert_same('took Home off the site', pg_designer_activity_sentence('page_unpublish', 'Home'));
	pg_assert_same('put Home on the site', pg_designer_activity_sentence('page_publish', 'Home'));
	pg_assert_same('saved the design Shop', pg_designer_activity_sentence('style_save', 'Shop'));
	pg_assert_same('renamed the design to Shop', pg_designer_activity_sentence('style_rename', 'Shop'));
	pg_assert_same('made the design Shop', pg_designer_activity_sentence('style_create', 'Shop'));
	pg_assert_same('duplicated the design as Shop', pg_designer_activity_sentence('style_copy', 'Shop'));
	pg_assert_same('moved the design Shop to the Recycle Bin', pg_designer_activity_sentence('style_recycle', 'Shop'));
	pg_assert_same('deleted the design Shop', pg_designer_activity_sentence('style_delete', 'Shop'));
	pg_assert_same('made the shared component Footer', pg_designer_activity_sentence('component_create', 'Footer'));
	pg_assert_same('saved the shared component Footer', pg_designer_activity_sentence('component_save', 'Footer'));
	pg_assert_same('renamed a shared component to Footer', pg_designer_activity_sentence('component_rename', 'Footer'));
	pg_assert_same('changed the settings of the shared component Footer', pg_designer_activity_sentence('component_settings', 'Footer'));
	pg_assert_same('deleted the shared component Footer', pg_designer_activity_sentence('component_delete', 'Footer'));
	pg_assert_same('', pg_designer_activity_sentence('dance', 'Home'));
}

function test_designer_activity_who()
{
	pg_assert_same('ayse', pg_designer_activity_who(5, 'ayse'));
	// A person erased or deleted: the row's id finds no user.
	pg_assert_same('A removed user', pg_designer_activity_who(5, null));
	pg_assert_same('A removed user', pg_designer_activity_who(5, ''));
	// No person (a guest's answer names itself, a job none).
	pg_assert_same('', pg_designer_activity_who(0, null));
}

function test_designer_activity_revision_event()
{
	$event = pg_designer_activity_from_revision(array(
		'revision_id' => 11, 'page_id' => 4, 'revision_no' => 3, 'source' => 'restore',
		'created_at' => 1700, 'user_id' => 2, 'user_username' => 'ayse', 'page_name' => 'Prices',
		'page_folder' => 9, 'editor' => '',
	));

	pg_assert_same('revision:11', $event['key']);
	pg_assert_same('version', $event['kind']);
	pg_assert_same('restore', $event['action']);
	pg_assert_same(4, $event['page_id']);
	pg_assert_same(3, $event['revision_no']);
	pg_assert_same('ayse', $event['who']);
	pg_assert_same('Prices', $event['name']);
	pg_assert_same('restored an earlier version of Prices', $event['sentence']);
	pg_assert_same(1700, $event['at']);

	// An applied content change: the designer's version, the editor named.
	$event = pg_designer_activity_from_revision(array(
		'revision_id' => 12, 'page_id' => 4, 'revision_no' => 4, 'source' => 'publish',
		'created_at' => 1800, 'user_id' => 2, 'user_username' => 'ayse', 'page_name' => 'Prices',
		'page_folder' => 9, 'editor' => 'mehmet',
	));
	pg_assert_same('applied', $event['action']);
	pg_assert_same('mehmet', $event['editor']);

	// An unknown source label reads as a publish, as the History does.
	$event = pg_designer_activity_from_revision(array(
		'revision_id' => 13, 'page_id' => 4, 'revision_no' => 5, 'source' => 'odd',
		'created_at' => 1900, 'user_id' => 0, 'user_username' => null, 'page_name' => 'Prices',
		'page_folder' => 9, 'editor' => '',
	));
	pg_assert_same('publish', $event['action']);
}

function test_designer_activity_audit_page_event_reads_the_line()
{
	// A page deleted for good: no page row; the line keeps its design,
	// folder and name.
	$line = array(
		'id' => 70, 'created_at' => 2000, 'user_id' => 3, 'user_username' => 'can', 'source' => 'panel',
		'action' => 'delete', 'entity_type' => 'page', 'entity_id' => 8, 'label' => 'Old offer',
		'before_json' => '{"page_id":"8","page_name":"Old offer","page_folder":"12","page_style":"6"}', 'after_json' => null,
		'page_folder' => null,
	);
	$event = pg_designer_activity_from_audit($line);

	pg_assert_same('audit:70', $event['key']);
	pg_assert_same('page', $event['kind']);
	pg_assert_same('page_delete', $event['action']);
	pg_assert_same(8, $event['page_id']);
	pg_assert_same(12, $event['folder_id']);
	pg_assert_same('Old offer', $event['name']);
	pg_assert_same('deleted Old offer for good', $event['sentence']);

	// A binned page: the folder it was in before, not the bin.
	$line['action'] = 'recycle';
	$line['page_folder'] = 99;
	$event = pg_designer_activity_from_audit($line);
	pg_assert_same('page_recycle', $event['action']);
	pg_assert_same(12, $event['folder_id']);

	// Brought back: the folder the page lives in now.
	$line['action'] = 'restore';
	$line['before_json'] = '{"page_folder":"99"}';
	$line['after_json'] = '{"page_folder":"12"}';
	$line['page_folder'] = 12;
	$event = pg_designer_activity_from_audit($line);
	pg_assert_same('page_restore', $event['action']);
	pg_assert_same(12, $event['folder_id']);
}

function test_designer_activity_audit_file_event()
{
	$event = pg_designer_activity_from_audit(array(
		'id' => 71, 'created_at' => 2100, 'user_id' => 3, 'user_username' => 'can', 'source' => 'panel',
		'action' => 'update', 'entity_type' => 'file', 'entity_id' => 30, 'label' => 'site.css',
		'before_json' => '{"content_sha1":"a"}', 'after_json' => '{"content_sha1":"b"}', 'page_folder' => null,
	));

	pg_assert_same('file', $event['kind']);
	pg_assert_same('file_update', $event['action']);
	pg_assert_same(0, $event['page_id']);
	pg_assert_same('changed the design file site.css', $event['sentence']);
	// The trail's own name column is never read: the list keeps no name of its own.
	pg_assert_same('can', $event['who']);
}

function test_designer_activity_audit_unknown_line_is_dropped()
{
	pg_assert_same(null, pg_designer_activity_from_audit(array(
		'id' => 72, 'created_at' => 2100, 'user_id' => 3, 'user_username' => 'can', 'source' => 'panel',
		'action' => 'rotate', 'entity_type' => 'page', 'entity_id' => 30, 'label' => 'x',
		'before_json' => null, 'after_json' => null, 'page_folder' => null,
	)));
}

// An audit line of the trail as the reader selects it.
function _designer_activity_test_line(array $fields)
{
	return array_merge(array(
		'id' => 80, 'created_at' => 2200, 'user_id' => 3, 'user_username' => 'can', 'source' => 'panel',
		'action' => 'update', 'entity_type' => 'page', 'entity_id' => 4, 'label' => 'Prices',
		'before_json' => null, 'after_json' => null, 'page_folder' => 12,
	), $fields);
}

function test_designer_activity_audit_page_on_and_off_the_site()
{
	$context = array('style_id' => 6, 'draft_folder' => 40);

	$off = pg_designer_activity_from_audit(_designer_activity_test_line(array(
		'before_json' => '{"page_folder":"12"}', 'after_json' => '{"page_folder":"40"}', 'page_folder' => 40,
	)), $context);
	pg_assert_same('page', $off['kind']);
	pg_assert_same('page_unpublish', $off['action']);
	pg_assert_same(4, $off['page_id']);
	pg_assert_same('took Prices off the site', $off['sentence']);

	$on = pg_designer_activity_from_audit(_designer_activity_test_line(array(
		'before_json' => '{"page_folder":"40"}', 'after_json' => '{"page_folder":"12","publish_at":"0"}',
	)), $context);
	pg_assert_same('page_publish', $on['action']);
	pg_assert_same(12, $on['folder_id']);
	pg_assert_same('put Prices on the site', $on['sentence']);

	// The scheduled job names no person; the list says the schedule did it.
	$job = pg_designer_activity_from_audit(_designer_activity_test_line(array(
		'user_id' => 0, 'user_username' => null, 'source' => 'job',
		'before_json' => '{"page_folder":"40"}', 'after_json' => '{"page_folder":"12"}',
	)), $context);
	pg_assert_same(0, $job['user_id']);
	pg_assert_same('The schedule', $job['who']);

	// Any other edit of the page, or a move between two other folders, is
	// not an event of the list: its version or the trail screen keeps it.
	pg_assert_same(null, pg_designer_activity_from_audit(_designer_activity_test_line(array(
		'before_json' => '{"page_folder":"12"}', 'after_json' => '{"page_folder":"13"}',
	)), $context));
	pg_assert_same(null, pg_designer_activity_from_audit(_designer_activity_test_line(array(
		'before_json' => '{"page_title":"A"}', 'after_json' => '{"page_title":"B"}',
	)), $context));
	// No drafts folder on the site: no line is a move on or off it.
	pg_assert_same(null, pg_designer_activity_from_audit(_designer_activity_test_line(array(
		'before_json' => '{"page_folder":"12"}', 'after_json' => '{"page_folder":"0"}',
	)), array('style_id' => 6, 'draft_folder' => 0)));
}

function test_designer_activity_audit_design_events()
{
	$context = array('style_id' => 6, 'draft_folder' => 40);
	$line = array('entity_type' => 'style', 'entity_id' => 6, 'label' => 'Shop', 'page_folder' => null);

	$save = pg_designer_activity_from_audit(_designer_activity_test_line($line + array(
		'before_json' => '{"style_custom_css":"a"}', 'after_json' => '{"style_custom_css":"b","style_palette":"sea"}',
	)), $context);
	pg_assert_same('design', $save['kind']);
	pg_assert_same('style_save', $save['action']);
	pg_assert_same(0, $save['page_id']);
	pg_assert_same('saved the design Shop', $save['sentence']);

	$rename = pg_designer_activity_from_audit(_designer_activity_test_line($line + array(
		'before_json' => '{"style_name":"Old"}', 'after_json' => '{"style_name":"Shop"}',
	)), $context);
	pg_assert_same('style_rename', $rename['action']);
	pg_assert_same('renamed the design to Shop', $rename['sentence']);

	$made = pg_designer_activity_from_audit(_designer_activity_test_line($line + array(
		'action' => 'create', 'after_json' => '{"style_name":"Shop"}',
	)), $context);
	pg_assert_same('style_create', $made['action']);
	pg_assert_same('made the design Shop', $made['sentence']);

	// A copy's line in the list of the design it was made from.
	$copy = pg_designer_activity_from_audit(_designer_activity_test_line(array(
		'action' => 'create', 'entity_type' => 'style', 'entity_id' => 9, 'label' => 'Shop (2)', 'page_folder' => null,
		'after_json' => '{"style_name":"Shop (2)","copied_from":"6"}',
	)), $context);
	pg_assert_same('style_copy', $copy['action']);
	pg_assert_same('duplicated the design as Shop (2)', $copy['sentence']);

	// ... and in the copy's own list: made, as any new design.
	$own = pg_designer_activity_from_audit(_designer_activity_test_line(array(
		'action' => 'create', 'entity_type' => 'style', 'entity_id' => 9, 'label' => 'Shop (2)', 'page_folder' => null,
		'after_json' => '{"style_name":"Shop (2)","copied_from":"6"}',
	)), array('style_id' => 9, 'draft_folder' => 40));
	pg_assert_same('style_create', $own['action']);

	$binned = pg_designer_activity_from_audit(_designer_activity_test_line($line + array('action' => 'recycle')), $context);
	pg_assert_same('moved the design Shop to the Recycle Bin', $binned['sentence']);
	$gone = pg_designer_activity_from_audit(_designer_activity_test_line($line + array('action' => 'delete')), $context);
	pg_assert_same('deleted the design Shop', $gone['sentence']);

	// Another design's line is not this list's.
	pg_assert_same(null, pg_designer_activity_from_audit(_designer_activity_test_line(array(
		'entity_type' => 'style', 'entity_id' => 7, 'label' => 'Blog', 'after_json' => '{"style_name":"Blog"}',
	)), $context));
}

function test_designer_activity_audit_shared_component_events()
{
	$context = array('style_id' => 6, 'draft_folder' => 40);
	$line = array('entity_type' => 'shared_component', 'entity_id' => 15, 'label' => 'Footer', 'page_folder' => null);
	$event = function (array $fields) use ($line, $context) {
		return pg_designer_activity_from_audit(_designer_activity_test_line($line + $fields), $context);
	};

	$saved = $event(array('after_json' => '{"tree_json":{"truncated":true,"length":9000,"sha1":"x","start":"{"},"tree_hash":"b"}'));
	pg_assert_same('component', $saved['kind']);
	pg_assert_same('component_save', $saved['action']);
	pg_assert_same('saved the shared component Footer', $saved['sentence']);

	pg_assert_same('renamed a shared component to Footer', $event(array('after_json' => '{"name":"Footer"}'))['sentence']);
	pg_assert_same('changed the settings of the shared component Footer', $event(array('after_json' => '{"content_editable":"1"}'))['sentence']);
	pg_assert_same('made the shared component Footer', $event(array('action' => 'create', 'after_json' => '{"name":"Footer"}'))['sentence']);
	pg_assert_same('deleted the shared component Footer', $event(array('action' => 'delete', 'before_json' => '{"name":"Footer"}'))['sentence']);

	// A side too large to keep lists the names of its fields.
	$cut = $event(array('after_json' => '{"truncated":true,"fields":["tree_json","tree_hash"]}'));
	pg_assert_same('component_save', $cut['action']);
}

function test_designer_activity_audit_changed_fields()
{
	pg_assert_same(array('page_folder'), pg_designer_activity_line_fields('{"page_folder":"1"}', null));
	pg_assert_same(array('name', 'tree_json'), pg_designer_activity_line_fields('{"name":"a"}', '{"tree_json":"b"}'));
	pg_assert_same(array('tree_json'), pg_designer_activity_line_fields(null, '{"truncated":true,"fields":["tree_json"]}'));
	pg_assert_same(array(), pg_designer_activity_line_fields(null, 'not json'));
}

function test_designer_activity_link_events()
{
	$link = array(
		'id' => 5, 'created_at' => 3000, 'created_by' => 2, 'creator_username' => 'ayse',
		'revoked_at' => 3500, 'revoked_by' => 9, 'revoker_username' => null, 'start_page_name' => 'Home',
	);

	$made = pg_designer_activity_from_link($link, false);
	pg_assert_same('link:5', $made['key']);
	pg_assert_same('share', $made['kind']);
	pg_assert_same('link_create', $made['action']);
	pg_assert_same(3000, $made['at']);
	pg_assert_same('ayse', $made['who']);
	pg_assert_same('made a share link to Home', $made['sentence']);

	$revoked = pg_designer_activity_from_link($link, true);
	pg_assert_same('revoke:5', $revoked['key']);
	pg_assert_same(3500, $revoked['at']);
	pg_assert_same('A removed user', $revoked['who']);
	pg_assert_same('link_revoke', $revoked['action']);
}

function test_designer_activity_answers_name_the_customer()
{
	$verdict = pg_designer_activity_from_verdict(array(
		'id' => 8, 'page_id' => 4, 'verdict' => 'changes_requested', 'author_name' => 'Zeynep',
		'created_at' => 4000, 'page_name' => 'Home', 'page_folder' => 9,
	));
	pg_assert_same('verdict:8', $verdict['key']);
	pg_assert_same('share', $verdict['kind']);
	pg_assert_same('Zeynep', $verdict['who']);
	pg_assert_same('asked for changes to Home', $verdict['sentence']);

	// An erased guest name ({name}) or none: "The customer".
	$verdict = pg_designer_activity_from_verdict(array(
		'id' => 9, 'page_id' => 4, 'verdict' => 'approved', 'author_name' => '{name}',
		'created_at' => 4001, 'page_name' => 'Home', 'page_folder' => 9,
	));
	pg_assert_same('The customer', $verdict['who']);
	pg_assert_same('approved Home', $verdict['sentence']);

	pg_assert_same(null, pg_designer_activity_from_verdict(array(
		'id' => 10, 'page_id' => 4, 'verdict' => 'maybe', 'author_name' => '',
		'created_at' => 4002, 'page_name' => 'Home', 'page_folder' => 9,
	)));

	// A staff answer names the user by id, a guest's by the typed name.
	$comment = pg_designer_activity_from_comment(array(
		'id' => 20, 'page_id' => 4, 'user_id' => 2, 'user_username' => 'ayse', 'author_name' => 'Ayşe Y.',
		'created_at' => 4100, 'page_name' => 'Home', 'page_folder' => 9,
	));
	pg_assert_same('comment:20', $comment['key']);
	pg_assert_same('ayse', $comment['who']);
	pg_assert_same('commented on Home', $comment['sentence']);

	$comment = pg_designer_activity_from_comment(array(
		'id' => 21, 'page_id' => 4, 'user_id' => 0, 'user_username' => null, 'author_name' => 'Zeynep',
		'created_at' => 4101, 'page_name' => 'Home', 'page_folder' => 9,
	));
	pg_assert_same('Zeynep', $comment['who']);
}

function test_designer_activity_rights()
{
	$mine = function ($page_id, $folder_id) {
		return $page_id === 4;
	};

	$events = array(
		array('key' => 'revision:1', 'kind' => 'version', 'page_id' => 4, 'folder_id' => 9),
		array('key' => 'revision:2', 'kind' => 'version', 'page_id' => 5, 'folder_id' => 9),
		array('key' => 'link:1', 'kind' => 'share', 'page_id' => 0, 'folder_id' => 0),
		array('key' => 'verdict:1', 'kind' => 'share', 'page_id' => 4, 'folder_id' => 9),
		array('key' => 'audit:1', 'kind' => 'file', 'page_id' => 0, 'folder_id' => 0),
		array('key' => 'audit:2', 'kind' => 'page', 'page_id' => 4, 'folder_id' => 9),
	);

	// The designer roles see every event of the design.
	pg_assert_same(6, count(pg_designer_activity_visible($events, true, $mine)));

	// A content editor: the page events of pages that are theirs, no
	// design-level event (a share link, a design file).
	pg_assert_same(array('revision:1', 'verdict:1', 'audit:2'),
		array_column(pg_designer_activity_visible($events, false, $mine), 'key'));
}

function test_designer_activity_panel_action()
{
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');

	pg_assert_contains("'import_fragment', 'activity_list',", $panel, 'content-level operators may ask');
	pg_assert_contains("case 'activity_list':", $panel, 'the sub action');
	pg_assert_contains('pg_designer_activity_read($ac_style, $user, $ac_before)', $panel, 'answered by the reader');
	pg_assert_true(in_array('activity_list', pg_page_cache_panel_sub_actions()['designer']['clean'], true), 'a read: the page cache stays');
}
