<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Fediverse replies as comments (includes/fn/activitypub_comments.php):
 * what a note's inReplyTo names on the site, the note's HTML as comment
 * text, the sender's name and handle, the Note a reply written on the site
 * goes out as, and the wiring of a Delete of the note to the comment's
 * removal.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_comments.php');

function ap_comments_test_site()
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

function ap_comments_test_fixture($name)
{
	return json_decode(file_get_contents(dirname(__FILE__) . '/fixtures/activitypub/' . $name), true);
}

function test_ap_comments_reply_ref_reads_object_addresses()
{
	$site = ap_comments_test_site();

	pg_assert_same(array('kind' => 'p', 'id' => 12), pg_ap_reply_ref('https://blog.example/@news/p/12', $site), 'page');
	pg_assert_same(array('kind' => 'r', 'id' => 7), pg_ap_reply_ref('https://blog.example/@news/r/7', $site), 'record');
	pg_assert_same(array('kind' => 'c', 'id' => 40), pg_ap_reply_ref('https://blog.example/@news/c/40', $site), 'reply written on the site');
	pg_assert_same(array('kind' => 'url', 'id' => 'first-post'), pg_ap_reply_ref('https://blog.example/first-post', $site), 'an Article\'s url');
}

function test_ap_comments_reply_ref_refuses_anything_else()
{
	$site = ap_comments_test_site();

	pg_assert_same(null, pg_ap_reply_ref('https://mastodon.example/users/alice/statuses/1', $site), 'another server');
	pg_assert_same(null, pg_ap_reply_ref('https://blog.example/@news', $site), 'the actor itself');
	pg_assert_same(null, pg_ap_reply_ref('https://blog.example/@news/p/0', $site), 'id 0');
	pg_assert_same(null, pg_ap_reply_ref('https://blog.example/@news/p/12/x', $site), 'trailing path');
	pg_assert_same(null, pg_ap_reply_ref('https://blog.example/@news/x/12', $site), 'unknown kind');
	pg_assert_same(null, pg_ap_reply_ref('https://blog.example/news/first-post', $site), 'a nested path');
	pg_assert_same(null, pg_ap_reply_ref('https://blog.example/page?x=1', $site), 'a query');
	pg_assert_same(null, pg_ap_reply_ref('https://blog.example/', $site), 'the home address');
	pg_assert_same(null, pg_ap_reply_ref('', $site), 'empty');
}

function test_ap_comments_note_text_drops_the_mention_and_tags()
{
	$note = ap_comments_test_fixture('create_note.json');

	pg_assert_same('Great post, thanks!', pg_ap_note_text($note['object']['content'], ap_comments_test_site()));
}

function test_ap_comments_note_text_keeps_paragraphs_and_breaks()
{
	$site = ap_comments_test_site();
	$html = '<p>First line<br>second &amp; <a href="https://x.example/">link</a></p><p>Next <script>x</script>paragraph</p>';

	pg_assert_same("First line\nsecond & link\n\nNext xparagraph", pg_ap_note_text($html, $site));
	pg_assert_same('', pg_ap_note_text('<p> </p>', $site), 'blank');
	pg_assert_same(PG_AP_REPLY_MAX_TEXT, strlen(pg_ap_note_text(str_repeat('a', PG_AP_REPLY_MAX_TEXT + 50), $site)), 'cut');
}

function test_ap_comments_note_text_keeps_a_mention_of_someone_else()
{
	$html = '<p><span class="h-card"><a href="https://mastodon.example/@bob" class="u-url mention">@<span>bob</span></a></span> agreed</p>';

	pg_assert_same('@bob agreed', pg_ap_note_text($html, ap_comments_test_site()));
}

function test_ap_comments_actor_handle_and_name()
{
	pg_assert_same('@alice@mastodon.example', pg_ap_actor_handle('https://mastodon.example/users/alice'));
	pg_assert_same('@bob@social.example', pg_ap_actor_handle('https://Social.example/@bob'));
	pg_assert_same('@carol@pixel.example', pg_ap_actor_handle('https://pixel.example/u/123', 'carol'));
	pg_assert_same('', pg_ap_actor_handle('not an address'));

	pg_assert_same('Alice (@alice@mastodon.example)', pg_ap_reply_name(" Alice\n <b></b>", '@alice@mastodon.example'));
	pg_assert_same('@alice@mastodon.example', pg_ap_reply_name('', '@alice@mastodon.example'));
	pg_assert_same(100, strlen(pg_ap_reply_name(str_repeat('n', 120), '@a@b.example')), 'cut to the column');
}

function test_ap_comments_local_reply_is_a_note_to_the_sender()
{
	$site = ap_comments_test_site();
	$activity = pg_ap_local_reply_activity($site, array(
		'id'                => 41,
		'message'           => "Thanks <Alice>!\nSee you\n\nBye",
		'created_timestamp' => 1793700000,
		'url'               => 'https://blog.example/first-post?comment=41#c-41',
	), array(
		'remote_actor' => 'https://mastodon.example/users/alice',
		'remote_id'    => 'https://mastodon.example/users/alice/statuses/113000000000000001',
	));

	pg_assert_same('Create', $activity['type']);
	pg_assert_same('https://blog.example/@news', $activity['actor']);
	pg_assert_same('https://blog.example/@news/c/41#create', $activity['id']);

	$note = $activity['object'];
	pg_assert_same('Note', $note['type']);
	pg_assert_same('https://blog.example/@news/c/41', $note['id']);
	pg_assert_same('https://mastodon.example/users/alice/statuses/113000000000000001', $note['inReplyTo']);
	pg_assert_same(array(PG_AP_PUBLIC), $note['to']);
	pg_assert_same(array('https://blog.example/@news/followers', 'https://mastodon.example/users/alice'), $note['cc']);
	pg_assert_same(array(array('type' => 'Mention', 'href' => 'https://mastodon.example/users/alice', 'name' => '@alice@mastodon.example')), $note['tag']);
	pg_assert_same('<p><span class="h-card"><a href="https://mastodon.example/users/alice" class="u-url mention">@alice@mastodon.example</a></span> Thanks &lt;Alice&gt;!<br>' . "\n" . 'See you</p><p>Bye</p>', $note['content']);

	// A remote reply to it names the comment, so the thread continues there.
	pg_assert_same(array('kind' => 'c', 'id' => 41), pg_ap_reply_ref($note['id'], $site));
}

function test_ap_comments_delete_of_the_note_removes_its_comment()
{
	$plan = pg_ap_inbox_plan(ap_comments_test_fixture('delete_note.json'), ap_comments_test_site());

	pg_assert_same('note_deleted', $plan[0]['do']);
	pg_assert_same('https://mastodon.example/users/alice/statuses/113000000000000001', $plan[0]['object']);

	// The removal selects by the note and its sender, never by the note alone.
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_comments.php');
	pg_assert_contains('return pg_ap_remove_comments("remote_id = ? AND remote_actor = ?", array((string) $note_id, (string) $actor));', $source);

	$inbox = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_inbox.php');
	pg_assert_contains('pg_ap_reply_deleted($action[\'object\'], $action[\'actor\'])', $inbox);
	pg_assert_contains('pg_ap_reply_receive($action[\'note\'], $action[\'actor\'], $site)', $inbox);
	pg_assert_contains("activitypub_comments.php", file_get_contents(PG_FUNCTIONS_DIR . '/ap_inbox.php'), 'the inbox loads the module');
}

function test_ap_comments_a_site_reply_is_sent_only_when_published()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/add_comment.php');

	pg_assert_contains("if ((\$published == '1') && (\$parent_id > 0) && (\$comment_id > 0) && function_exists('pg_config_text') && (pg_config_text('ap_settings') !== '')) {", $source);
	pg_assert_contains('pg_ap_reply_send($comment_id);', $source);
}

function test_ap_comments_thread_objects_name_the_page_or_the_record()
{
	$site = ap_comments_test_site();

	pg_assert_same(array('https://blog.example/@news/p/12'), pg_ap_thread_objects($site, 12, '', 0), 'a page\'s own thread');
	pg_assert_same(array('https://blog.example/@news/r/7'), pg_ap_thread_objects($site, 12, 'submitted_form', 7), 'a record\'s thread');
	pg_assert_same(array(), pg_ap_thread_objects($site, 12, 'product', 3), 'a product is never published');
	pg_assert_same(array(), pg_ap_thread_objects($site, 12, 'calendar_event', 3), 'nor an event');
	pg_assert_same(array(), pg_ap_thread_objects($site, 0, '', 0), 'no page');
	pg_assert_same(array(), pg_ap_thread_objects($site, 12, 'submitted_form', 0), 'no record');
}

function test_ap_comments_reaction_counts_read_grouped_rows()
{
	pg_assert_same(array('like' => 0, 'announce' => 0), pg_ap_reaction_counts_read(false), 'a failed query reads as none');
	pg_assert_same(array('like' => 3, 'announce' => 1), pg_ap_reaction_counts_read(array(
		array('kind' => 'like', 'n' => '3'),
		array('kind' => 'announce', 'n' => '1'),
		array('kind' => 'other', 'n' => '9'),
		array('kind' => 'like', 'n' => 'x'),
		'junk',
	)));
	pg_assert_same(array('like' => 0, 'announce' => 0), pg_ap_reaction_counts(array()), 'no object asks nothing');
}

function test_ap_comments_reactions_show_in_the_comments_widget()
{
	if (!function_exists('pg_comments_reaction_values')) {
		return;
	}

	pg_assert_same(array('__comments_likes_count' => '', '__comments_boosts_count' => '', '__comments_reactions_text' => ''),
		pg_comments_reaction_values(array('like' => 0, 'announce' => 0)), 'none: every token empty');

	$one = pg_comments_reaction_values(array('like' => 1, 'announce' => 0));
	pg_assert_same('1', $one['__comments_likes_count']);
	pg_assert_same('', $one['__comments_boosts_count']);
	pg_assert_same('1 like', $one['__comments_reactions_text'], 'singular, no empty boost part');

	$many = pg_comments_reaction_values(array('like' => 3, 'announce' => 2));
	pg_assert_same('3 likes · 2 boosts', $many['__comments_reactions_text']);
	pg_assert_same('2', $many['__comments_boosts_count']);
	pg_assert_same('1 boost', pg_comments_reaction_values(array('announce' => 1))['__comments_reactions_text']);
	pg_assert_same('', pg_comments_reaction_values('junk')['__comments_reactions_text']);

	// The widget reads them for its thread; an e-mail draws none.
	$widget = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_comments.php');
	pg_assert_contains('+ pg_comments_reaction_values(pg_comments_widget_reactions($page_id, $post_type, $post_id));', $widget);
	pg_assert_contains("if (pg_config_text('ap_settings') === '') {", $widget, 'a site that never saved the settings loads nothing');
	pg_assert_contains(") + pg_comments_reaction_values(array());", $widget);

	// A like or its undo makes the cached pages stale.
	$inbox = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_inbox.php');
	pg_assert_contains("// The comments widget shows the counts (pg_comments_widget_reactions()).", $inbox);
	pg_assert_same(2, substr_count($inbox, 'pg_page_cache_mark_dirty();'), 'react and undo');
}
