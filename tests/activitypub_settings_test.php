<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Fediverse settings screen's helpers
 * (includes/fn/activitypub_settings.php): the handle check, the row a save
 * stores, the delivery log's rows and the "Look me up" verdict.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_settings.php');

function activitypub_settings_test_site()
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

function test_activitypub_settings_handle_error()
{
	$users = array('Erdal', 'editor ');

	pg_assert_same('', pg_ap_settings_handle_error('news', $users));
	pg_assert_same('empty', pg_ap_settings_handle_error('', $users));
	pg_assert_same('format', pg_ap_settings_handle_error('news desk', $users));
	pg_assert_same('format', pg_ap_settings_handle_error('haber-merkezi', $users));
	pg_assert_same('format', pg_ap_settings_handle_error(str_repeat('a', 65), $users));
	pg_assert_same('taken', pg_ap_settings_handle_error('erdal', $users), 'a user name, case aside');
	pg_assert_same('taken', pg_ap_settings_handle_error('EDITOR', $users), 'a user name, spaces aside');
}

function test_activitypub_settings_ids()
{
	pg_assert_same(array(4, 2), pg_ap_settings_ids(array('4', '2', '4', '9', 'x', '0', array()), array(2, 4, 7)));
	pg_assert_same(array(), pg_ap_settings_ids('4', array(4)));
}

function test_activitypub_settings_build()
{
	$stored = array('enabled' => false, 'handle' => 'old', 'later' => 'kept', 'publish' => array('forms' => array(3), 'other' => 1));

	$row = pg_ap_settings_build(array(
		'enabled' => '1',
		'handle'  => ' news ',
		'type'    => 'Organization',
		'name'    => " <b>Example</b>\n News ",
		'summary' => str_repeat('ş', 600),
		'icon'    => 'logo.png',
		'forms'   => array('5', '6'),
		'folders' => array('8'),
	), $stored, array('forms' => array(5), 'folders' => array(8, 9)));

	pg_assert_true($row['enabled']);
	pg_assert_same('news', $row['handle']);
	pg_assert_same('Organization', $row['type']);
	pg_assert_same('Example News', $row['name'], 'tags dropped, white space folded');
	pg_assert_same(500, mb_strlen($row['summary'], 'UTF-8'));
	pg_assert_same('logo.png', $row['icon']);
	pg_assert_same(array(5), $row['publish']['forms'], 'only the forms offered');
	pg_assert_same(array(8), $row['publish']['folders']);
	pg_assert_same(1, $row['publish']['other'], 'a publish key the screen does not draw is kept');
	pg_assert_same('kept', $row['later'], 'a key the screen does not draw is kept');

	$off = pg_ap_settings_build(array('type' => 'Service'), array(), array());

	pg_assert_true(!$off['enabled']);
	pg_assert_same('Person', $off['type']);
	pg_assert_same(array(), $off['publish']['forms']);

	$read = pg_ap_settings_read(json_encode($row));

	pg_assert_same('Example News', $read['name'], 'the actor reads the name back');
	pg_assert_same('logo.png', $read['icon']);
}

function test_activitypub_settings_delivery_view()
{
	$row = pg_ap_delivery_view(array(
		'id' => '7', 'activity_id' => 'https://blog.example/@news/r/12#update-1700000000',
		'inbox' => 'https://mastodon.example/inbox', 'attempts' => '6', 'next_at' => '0',
		'state' => 'failed', 'created_at' => '1700000100',
	));

	pg_assert_same(7, $row['id']);
	pg_assert_same('Update', $row['type']);
	pg_assert_same('https://blog.example/@news/r/12', $row['object']);
	pg_assert_same('mastodon.example', $row['host']);
	pg_assert_same(6, $row['attempts']);
	pg_assert_true($row['retry'], 'a failed row can be tried again');

	$accept = pg_ap_delivery_view(array('activity_id' => 'https://blog.example/@news#accepts/abc', 'inbox' => 'https://x.example/users/a/inbox', 'state' => 'sent'));

	pg_assert_same('Accept', $accept['type']);
	pg_assert_true(!$accept['retry'], 'a sent row is not');
}

function test_activitypub_settings_lookup()
{
	$site = activitypub_settings_test_site();

	pg_assert_same('https://blog.example/.well-known/webfinger?resource=acct%3Anews%40blog.example', pg_ap_lookup_url($site));

	$good = json_encode(pg_ap_webfinger('acct:news@blog.example', $site));

	pg_assert_same('', pg_ap_lookup_verdict(200, $good, $site), 'our own WebFinger answer names our actor');
	pg_assert_same('unreachable', pg_ap_lookup_verdict(0, '', $site));
	pg_assert_same('status', pg_ap_lookup_verdict(404, '', $site));
	pg_assert_same('json', pg_ap_lookup_verdict(200, '<html></html>', $site));
	pg_assert_same('actor', pg_ap_lookup_verdict(200, '{"links":[{"rel":"self","href":"https://other.example/@news"}]}', $site));
}

// The category is wired where every category is, and pages carry the link.
function test_activitypub_settings_category_files()
{
	$root = PG_FUNCTIONS_DIR;

	pg_assert_true(is_file($root . '/settings_fediverse.php'));
	pg_assert_true(is_file($root . '/includes/settings/fediverse.php'));
	pg_assert_true(is_file($root . '/includes/settings/fediverse.save.php'));
	pg_assert_contains("'settings_fediverse'", (string) file_get_contents($root . '/get_help_url.php'));

	$registry = (string) file_get_contents($root . '/includes/settings/registry.php');

	pg_assert_contains("'fediverse' => array(", $registry);
	pg_assert_contains("'ap_forms'   => 1,", $registry);
	pg_assert_contains("'ap_folders' => 1,", $registry);

	pg_assert_contains('pg_ap_alternate_link()', (string) file_get_contents($root . '/get_page_content.php'));
}
