<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for ActivityPub publishing (includes/fn/activitypub_outbox.php): the
 * Article of a page and of a record against the fixture
 * tests/fixtures/activitypub/blog_create_article.json, the activities around
 * them, the sweep's plan, the fan-out by shared inbox and the delivery
 * ladder.
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

function activitypub_outbox_test_site()
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
		'followers'          => 2,
	);
}

function activitypub_outbox_test_record()
{
	return array(
		'id'          => 42,
		'url'         => 'https://blog.example/articles/first-light',
		'title'       => 'First light',
		'description' => '<p>The telescope saw <a href="/stars">its first star</a>.</p><img src="files/star.jpg"><script>alert(1)</script>',
		'images'      => array('https://blog.example/star.jpg', 'files/relative.png'),
		'published'   => 1793865600,
		'updated'     => 1793869200,
	);
}

function test_activitypub_outbox_create_of_a_record_matches_the_fixture()
{
	$site = activitypub_outbox_test_site();
	$article = pg_ap_object_for_record(activitypub_outbox_test_record(), $site);
	$expected = json_decode(file_get_contents(dirname(__FILE__) . '/fixtures/activitypub/blog_create_article.json'), true);

	pg_assert_same($expected, pg_ap_activity($site, 'Create', $article, 1793869200));
}

function test_activitypub_outbox_article_content_is_sanitized_and_absolute()
{
	$article = pg_ap_object_for_record(activitypub_outbox_test_record(), activitypub_outbox_test_site());

	pg_assert_false(strpos($article['content'], '<script') !== false, 'no script');
	pg_assert_true(strpos($article['content'], 'href="https://blog.example/stars"') !== false, 'a root-relative link made absolute');
	pg_assert_true(strpos($article['content'], 'src="https://blog.example/files/star.jpg"') !== false, 'a page-relative picture made absolute');
	pg_assert_false(isset($article['summary']), 'no summary: Mastodon would show it as a content warning');
	pg_assert_same(1, count($article['attachment']), 'only absolute pictures are attached');
	pg_assert_same('image/jpeg', $article['attachment'][0]['mediaType']);
}

function test_activitypub_outbox_article_of_a_page()
{
	$site = activitypub_outbox_test_site();
	$article = pg_ap_object_for_page(array(
		'page_id'               => 7,
		'page_name'             => 'about-us',
		'page_title'            => '',
		'page_meta_description' => 'Who we are & what we do',
		'published'             => 1793865600,
		'updated'               => 1793865600,
	), $site);

	pg_assert_same('https://blog.example/@news/p/7', $article['id']);
	pg_assert_same('https://blog.example/about-us', $article['url']);
	pg_assert_same('about-us', $article['name'], 'no title: the page name');
	pg_assert_same('<p>Who we are &amp; what we do</p><p><a href="https://blog.example/about-us">https://blog.example/about-us</a></p>', $article['content']);
	pg_assert_false(isset($article['updated']), 'not changed since it was published');
	pg_assert_false(isset($article['attachment']), 'no pictures');
	pg_assert_same(array('https://www.w3.org/ns/activitystreams#Public'), $article['to']);
	pg_assert_same(array('https://blog.example/@news/followers'), $article['cc']);
}

function test_activitypub_outbox_delete_carries_a_tombstone()
{
	$site = activitypub_outbox_test_site();
	$delete = pg_ap_activity($site, 'Delete', 'https://blog.example/@news/r/42', 1793872800);

	pg_assert_same('https://blog.example/@news/r/42#delete-1793872800', $delete['id']);
	pg_assert_same(array('id' => 'https://blog.example/@news/r/42', 'type' => 'Tombstone'), $delete['object']);
	pg_assert_same('https://blog.example/@news', $delete['actor']);
}

function test_activitypub_outbox_activity_key_reads_the_address_back()
{
	pg_assert_same(array('object' => 'https://blog.example/@news/p/7', 'type' => 'update', 'stamp' => 1793869200), pg_ap_activity_key('https://blog.example/@news/p/7#update-1793869200'));
	pg_assert_same(null, pg_ap_activity_key('https://blog.example/@news#accept-5'), 'not an outbox activity');
	pg_assert_same(null, pg_ap_activity_key('https://blog.example/@news/x/7#create-1'));
}

function test_activitypub_outbox_logged_objects_keep_the_last_activity()
{
	$logged = pg_ap_logged_objects(array(
		'https://b/@n/p/1#create-100',
		'https://b/@n/p/1#update-200',
		'https://b/@n/r/2#create-150',
		'https://b/@n/r/2#delete-300',
		'garbage',
	));

	pg_assert_same(array(
		'https://b/@n/p/1' => array('type' => 'update', 'stamp' => 200),
		'https://b/@n/r/2' => array('type' => 'delete', 'stamp' => 300),
	), $logged);
}

function test_activitypub_outbox_sweep_plan()
{
	$logged = array(
		'o/p/1' => array('type' => 'create', 'stamp' => 100),
		'o/p/2' => array('type' => 'update', 'stamp' => 200),
		'o/p/3' => array('type' => 'create', 'stamp' => 100),
		'o/r/4' => array('type' => 'delete', 'stamp' => 300),
		'o/r/5' => array('type' => 'delete', 'stamp' => 300),
	);
	$current = array(
		'o/p/1' => 100, // unchanged
		'o/p/2' => 250, // changed
		'o/r/4' => 120, // published again
		'o/r/6' => 50,  // new
	);

	pg_assert_same(array(
		array('object' => 'o/p/2', 'type' => 'Update'),
		array('object' => 'o/r/4', 'type' => 'Create'),
		array('object' => 'o/r/6', 'type' => 'Create'),
		array('object' => 'o/p/3', 'type' => 'Delete'),
	), pg_ap_sweep_plan($current, $logged, 20));

	pg_assert_same(2, count(pg_ap_sweep_plan($current, $logged, 2)), 'at most the limit');
	pg_assert_same(array(), pg_ap_sweep_plan(array('o/p/1' => 100), array('o/p/1' => array('type' => 'create', 'stamp' => 100))), 'nothing changed');
}

function test_activitypub_outbox_fan_out_by_shared_inbox()
{
	pg_assert_same(array(
		'https://mastodon.example/inbox',
		'https://solo.example/users/a/inbox',
	), pg_ap_delivery_inboxes(array(
		array('inbox' => 'https://mastodon.example/users/a/inbox', 'shared_inbox' => 'https://mastodon.example/inbox'),
		array('inbox' => 'https://mastodon.example/users/b/inbox', 'shared_inbox' => 'https://mastodon.example/inbox'),
		array('inbox' => 'https://solo.example/users/a/inbox', 'shared_inbox' => ''),
		array('inbox' => 'http://plain.example/inbox', 'shared_inbox' => ''),
		array('inbox' => '', 'shared_inbox' => ''),
	)));
}

function test_activitypub_outbox_backoff_follows_the_mail_ladder()
{
	pg_assert_same(60, pg_ap_backoff(1));
	pg_assert_same(86400, pg_ap_backoff(6));
	pg_assert_same(0, pg_ap_backoff(7), 'given up after six');

	if (function_exists('pg_mail_backoff')) {
		for ($attempt = 1; $attempt <= 7; $attempt++) {
			pg_assert_same(pg_mail_backoff($attempt), pg_ap_backoff($attempt), 'attempt ' . $attempt);
		}
	}
}

function test_activitypub_outbox_delivery_outcome_and_update()
{
	pg_assert_same('sent', pg_ap_delivery_outcome(202));
	pg_assert_same('failed', pg_ap_delivery_outcome(410), 'gone for good');
	pg_assert_same('retry', pg_ap_delivery_outcome(429), 'too many requests');
	pg_assert_same('retry', pg_ap_delivery_outcome(408));
	pg_assert_same('retry', pg_ap_delivery_outcome(503));
	pg_assert_same('retry', pg_ap_delivery_outcome(0), 'no answer');

	pg_assert_same(array('state' => 'queued', 'attempts' => 1, 'next_at' => 1060), pg_ap_delivery_update('retry', 0, 1000));
	pg_assert_same(array('state' => 'queued', 'attempts' => 3, 'next_at' => 2800), pg_ap_delivery_update('retry', 2, 1000));
	pg_assert_same(array('state' => 'failed', 'attempts' => 6, 'next_at' => 0), pg_ap_delivery_update('retry', 5, 1000), 'the sixth failure gives up');
	pg_assert_same(array('state' => 'sent', 'attempts' => 1, 'next_at' => 0), pg_ap_delivery_update('sent', 0, 1000));
	pg_assert_same(array('state' => 'failed', 'attempts' => 1, 'next_at' => 0), pg_ap_delivery_update('failed', 0, 1000));
}
