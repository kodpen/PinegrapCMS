<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ActivityPub inbox (includes/fn/activitypub_inbox.php): each
 * activity Mastodon delivers (tests/fixtures/activitypub/) read into its
 * actions, a replayed delivery planning the same idempotent actions, the
 * key-to-actor check, blocks by actor and domain, and the follower row read
 * from Mastodon's actor document.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_inbox.php');

function ap_inbox_test_fixture($name)
{
	return json_decode(file_get_contents(dirname(__FILE__) . '/fixtures/activitypub/' . $name), true);
}

function ap_inbox_test_site()
{
	return array(
		'base'               => 'https://blog.example/',
		'host'               => 'blog.example',
		'handle'             => 'news',
		'name'               => 'Example News',
		'summary'            => '',
		'type'               => 'Organization',
		'icon'               => '',
		'public_key'         => '',
		'software_directory' => 'pinegrap',
		'version'            => '2026.11.0',
		'followers'          => 0,
	);
}

function ap_inbox_test_mastodon_key($key_id, $fresh)
{
	return pg_ap_key_from_document(ap_inbox_test_fixture('mastodon_actor.json'), $key_id);
}

function test_ap_inbox_follow_is_accepted()
{
	$follow = ap_inbox_test_fixture('follow.json');
	$plan = pg_ap_inbox_plan($follow, ap_inbox_test_site());

	pg_assert_same(1, count($plan));
	pg_assert_same('follow', $plan[0]['do']);
	pg_assert_same('https://mastodon.example/users/alice', $plan[0]['actor']);

	$accept = $plan[0]['accept'];
	pg_assert_same('Accept', $accept['type']);
	pg_assert_same('https://blog.example/@news', $accept['actor']);
	pg_assert_same($follow['id'], $accept['object']['id']);
	pg_assert_same('Follow', $accept['object']['type']);
	pg_assert_same('https://mastodon.example/users/alice', $accept['object']['actor']);
	pg_assert_same('https://blog.example/@news', $accept['object']['object']);
	pg_assert_same(0, strpos($accept['id'], 'https://blog.example/@news#accepts/'));
}

function test_ap_inbox_replayed_follow_plans_the_same_accept()
{
	$follow = ap_inbox_test_fixture('follow.json');

	pg_assert_same(pg_ap_inbox_plan($follow, ap_inbox_test_site()), pg_ap_inbox_plan($follow, ap_inbox_test_site()));
}

function test_ap_inbox_follow_of_another_actor_is_ignored()
{
	$follow = ap_inbox_test_fixture('follow.json');
	$follow['object'] = 'https://blog.example/@someone';
	$plan = pg_ap_inbox_plan($follow, ap_inbox_test_site());

	pg_assert_same('ignore', $plan[0]['do']);
}

function test_ap_inbox_undo_follow()
{
	$plan = pg_ap_inbox_plan(ap_inbox_test_fixture('undo_follow.json'), ap_inbox_test_site());

	pg_assert_same(array(array('do' => 'unfollow', 'actor' => 'https://mastodon.example/users/alice')), $plan);
}

function test_ap_inbox_undo_of_another_actors_follow_is_ignored()
{
	$undo = ap_inbox_test_fixture('undo_follow.json');
	$undo['object']['actor'] = 'https://mastodon.example/users/bob';
	$plan = pg_ap_inbox_plan($undo, ap_inbox_test_site());

	pg_assert_same('ignore', $plan[0]['do']);
}

function test_ap_inbox_like_and_announce_are_reactions()
{
	$like = pg_ap_inbox_plan(ap_inbox_test_fixture('like.json'), ap_inbox_test_site());
	pg_assert_same(array(array(
		'do'       => 'react',
		'kind'     => 'like',
		'actor'    => 'https://mastodon.example/users/alice',
		'object'   => 'https://blog.example/news/first-post',
		'activity' => 'https://mastodon.example/users/alice#likes/5678',
	)), $like);

	$announce = pg_ap_inbox_plan(ap_inbox_test_fixture('announce.json'), ap_inbox_test_site());
	pg_assert_same('react', $announce[0]['do']);
	pg_assert_same('announce', $announce[0]['kind']);
	pg_assert_same('https://blog.example/news/first-post', $announce[0]['object']);
}

function test_ap_inbox_reaction_to_another_object_is_ignored()
{
	$like = ap_inbox_test_fixture('like.json');
	$like['object'] = 'https://elsewhere.example/post/1';
	pg_assert_same('ignore', pg_ap_inbox_plan($like, ap_inbox_test_site())[0]['do']);

	// A look-alike host that only starts with the site's name.
	$like['object'] = 'https://blog.example.evil/news/first-post';
	pg_assert_same('ignore', pg_ap_inbox_plan($like, ap_inbox_test_site())[0]['do']);

	$like['object'] = 'https://blog.example/@news';
	pg_assert_same('ignore', pg_ap_inbox_plan($like, ap_inbox_test_site())[0]['do']);
}

function test_ap_inbox_reaction_key_is_one_per_actor_kind_and_object()
{
	$actor = 'https://mastodon.example/users/alice';
	$object = 'https://blog.example/news/first-post';

	pg_assert_same(pg_ap_reaction_key('like', $actor, $object), pg_ap_reaction_key('like', $actor, $object));
	pg_assert_true(pg_ap_reaction_key('like', $actor, $object) !== pg_ap_reaction_key('announce', $actor, $object));
	pg_assert_same(40, strlen(pg_ap_reaction_key('like', $actor, $object)));
}

function test_ap_inbox_undo_like()
{
	$plan = pg_ap_inbox_plan(ap_inbox_test_fixture('undo_like.json'), ap_inbox_test_site());

	pg_assert_same(array(array(
		'do'       => 'unreact',
		'kind'     => 'like',
		'actor'    => 'https://mastodon.example/users/alice',
		'object'   => 'https://blog.example/news/first-post',
		'activity' => 'https://mastodon.example/users/alice#likes/5678',
	)), $plan);

	// An Undo that names the activity by its id alone still finds it.
	$undo = ap_inbox_test_fixture('undo_like.json');
	$undo['object'] = 'https://mastodon.example/users/alice#likes/5678';
	$plan = pg_ap_inbox_plan($undo, ap_inbox_test_site());
	pg_assert_same('unreact', $plan[0]['do']);
	pg_assert_same('', $plan[0]['kind']);
	pg_assert_same('https://mastodon.example/users/alice#likes/5678', $plan[0]['activity']);
}

function test_ap_inbox_delete_of_the_actor()
{
	$plan = pg_ap_inbox_plan(ap_inbox_test_fixture('delete_actor.json'), ap_inbox_test_site());

	pg_assert_same(array(array('do' => 'actor_gone', 'actor' => 'https://mastodon.example/users/alice')), $plan);
}

function test_ap_inbox_delete_of_a_note()
{
	$plan = pg_ap_inbox_plan(ap_inbox_test_fixture('delete_note.json'), ap_inbox_test_site());

	pg_assert_same('note_deleted', $plan[0]['do']);
	pg_assert_same('https://mastodon.example/users/alice/statuses/113000000000000001', $plan[0]['object']);

	// Another server's object cannot be deleted by this actor.
	$delete = ap_inbox_test_fixture('delete_note.json');
	$delete['object']['id'] = 'https://other.example/notes/1';
	pg_assert_same('ignore', pg_ap_inbox_plan($delete, ap_inbox_test_site())[0]['do']);
}

function test_ap_inbox_reply_is_handed_on()
{
	$create = ap_inbox_test_fixture('create_note.json');
	$plan = pg_ap_inbox_plan($create, ap_inbox_test_site());

	pg_assert_same('reply', $plan[0]['do']);
	pg_assert_same('https://mastodon.example/users/alice', $plan[0]['actor']);
	pg_assert_same($create['object']['id'], $plan[0]['note']['id']);
}

function test_ap_inbox_note_that_answers_none_of_ours_is_ignored()
{
	$create = ap_inbox_test_fixture('create_note.json');
	$create['object']['inReplyTo'] = 'https://mastodon.example/users/bob/statuses/1';
	pg_assert_same('ignore', pg_ap_inbox_plan($create, ap_inbox_test_site())[0]['do']);

	$create = ap_inbox_test_fixture('create_note.json');
	$create['object']['inReplyTo'] = null;
	pg_assert_same('ignore', pg_ap_inbox_plan($create, ap_inbox_test_site())[0]['do']);

	// A note attributed to someone other than the sender.
	$create = ap_inbox_test_fixture('create_note.json');
	$create['object']['attributedTo'] = 'https://mastodon.example/users/bob';
	pg_assert_same('ignore', pg_ap_inbox_plan($create, ap_inbox_test_site())[0]['do']);
}

function test_ap_inbox_other_types_are_ignored()
{
	$update = array('id' => 'https://mastodon.example/users/alice#updates/1', 'type' => 'Update', 'actor' => 'https://mastodon.example/users/alice', 'object' => array('id' => 'https://mastodon.example/users/alice', 'type' => 'Person'));
	pg_assert_same('ignore', pg_ap_inbox_plan($update, ap_inbox_test_site())[0]['do']);

	pg_assert_same('ignore', pg_ap_inbox_plan(array('type' => 'Follow'), ap_inbox_test_site())[0]['do']);
}

function test_ap_inbox_signed_delivery_speaks_for_its_actor()
{
	$request = ap_inbox_test_fixture('signed_request.json');
	$verified = pg_ap_verify($request, array('now' => strtotime('2026-11-03 10:05:00 UTC'), 'key' => 'ap_inbox_test_mastodon_key'));
	$activity = json_decode($request['body'], true);

	pg_assert_true($verified['ok']);
	pg_assert_true(pg_ap_inbox_authorized($activity, $verified));

	$activity['actor'] = 'https://mastodon.example/users/bob';
	pg_assert_false(pg_ap_inbox_authorized($activity, $verified), 'a key of another actor');

	pg_assert_false(pg_ap_inbox_authorized(json_decode($request['body'], true), array('ok' => false, 'owner' => '', 'key_id' => '')), 'not verified');

	// No owner in the key document: the key id without its fragment.
	pg_assert_true(pg_ap_inbox_authorized(json_decode($request['body'], true), array('ok' => true, 'owner' => '', 'key_id' => 'https://mastodon.example/users/alice#main-key')));
}

function test_ap_inbox_replayed_request_outside_the_window_is_refused()
{
	$request = ap_inbox_test_fixture('signed_request.json');
	$verified = pg_ap_verify($request, array('now' => strtotime('2026-11-03 12:00:00 UTC'), 'key' => 'ap_inbox_test_mastodon_key'));

	pg_assert_false($verified['ok']);
}

function test_ap_inbox_blocks()
{
	$blocks = array(
		array('kind' => 'actor', 'value' => 'https://mastodon.example/users/bob'),
		array('kind' => 'domain', 'value' => 'spam.example'),
	);

	pg_assert_true(pg_ap_blocked('https://mastodon.example/users/bob', $blocks));
	pg_assert_true(pg_ap_blocked('https://mastodon.example/users/bob#main-key', $blocks), 'by the key id');
	pg_assert_false(pg_ap_blocked('https://mastodon.example/users/alice', $blocks));
	pg_assert_true(pg_ap_blocked('https://spam.example/users/x', $blocks));
	pg_assert_true(pg_ap_blocked('https://social.SPAM.example/users/x', $blocks), 'a subdomain');
	pg_assert_false(pg_ap_blocked('https://notspam.example/users/x', $blocks), 'not a suffix without a dot');
	pg_assert_false(pg_ap_blocked('', $blocks));
}

function test_ap_inbox_follower_from_mastodon_actor()
{
	$row = pg_ap_follower_from_document(ap_inbox_test_fixture('mastodon_actor.json'));

	pg_assert_same(array(
		'inbox'        => 'https://mastodon.example/users/alice/inbox',
		'shared_inbox' => 'https://mastodon.example/inbox',
		'name'         => 'Alice',
		'avatar'       => 'https://files.mastodon.example/accounts/avatars/alice.png',
	), $row);

	pg_assert_false(pg_ap_follower_from_document(array('id' => 'x', 'inbox' => 'http://plain.example/inbox')), 'no https inbox');
}

function test_ap_inbox_request_headers()
{
	$headers = pg_ap_request_headers(array(
		'HTTP_HOST'      => 'blog.example',
		'HTTP_SIGNATURE' => 'keyId="k"',
		'CONTENT_TYPE'   => 'application/activity+json',
		'REQUEST_URI'    => '/pinegrap/ap_inbox.php',
	));

	pg_assert_same(array('host' => 'blog.example', 'signature' => 'keyId="k"', 'content-type' => 'application/activity+json'), $headers);
}
