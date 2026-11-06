<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the public outbox collection's items (pg_ap_outbox_visible(),
 * includes/fn/activitypub_outbox.php): an object shows only its newest
 * logged activity, so the Create or Update of a page or record taken off
 * the site (private folder, recycle bin, filtered out) or rewritten since
 * no longer hands out the content it carried.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_outbox.php');

function activitypub_outbox_visible_test_activity($id, $type, $content = '')
{
	$object_id = substr($id, 0, strpos($id, '#'));

	return array(
		'id'     => $id,
		'type'   => $type,
		'object' => ($type === 'Delete')
			? array('id' => $object_id, 'type' => 'Tombstone')
			: array('id' => $object_id, 'type' => 'Article', 'content' => $content),
	);
}

function test_activitypub_outbox_visible_drops_the_create_of_a_deleted_object()
{
	$delete = activitypub_outbox_visible_test_activity('https://b/@n/p/1#delete-300', 'Delete');
	$other = activitypub_outbox_visible_test_activity('https://b/@n/r/2#create-200', 'Create', 'still public');

	$visible = pg_ap_outbox_visible(array(
		$delete,
		$other,
		activitypub_outbox_visible_test_activity('https://b/@n/p/1#create-100', 'Create', 'now private'),
	), 20);

	pg_assert_same(array($delete, $other), $visible);
}

function test_activitypub_outbox_visible_keeps_only_the_newest_update()
{
	$update = activitypub_outbox_visible_test_activity('https://b/@n/p/1#update-200', 'Update', 'new text');

	$visible = pg_ap_outbox_visible(array(
		$update,
		activitypub_outbox_visible_test_activity('https://b/@n/p/1#create-100', 'Create', 'removed text'),
	), 20);

	pg_assert_same(array($update), $visible);
}

function test_activitypub_outbox_visible_shows_a_republished_object_once()
{
	$create = activitypub_outbox_visible_test_activity('https://b/@n/p/1#create-400', 'Create', 'back');

	$visible = pg_ap_outbox_visible(array(
		$create,
		activitypub_outbox_visible_test_activity('https://b/@n/p/1#delete-300', 'Delete'),
		activitypub_outbox_visible_test_activity('https://b/@n/p/1#create-100', 'Create', 'first'),
	), 20);

	pg_assert_same(array($create), $visible);
}

function test_activitypub_outbox_visible_keeps_the_limit_and_unknown_ids()
{
	$unknown = array('id' => 'https://b/@n#accept-5', 'type' => 'Accept', 'object' => array());
	$a = activitypub_outbox_visible_test_activity('https://b/@n/p/1#create-100', 'Create', 'a');
	$b = activitypub_outbox_visible_test_activity('https://b/@n/p/2#create-100', 'Create', 'b');

	pg_assert_same(array($unknown, $a), pg_ap_outbox_visible(array($unknown, $a, $b, 'not an activity'), 2));
	pg_assert_same(array(), pg_ap_outbox_visible(array($a), 0));
}
