<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for ActivityPub moderation (includes/fn/activitypub_moderation.php):
 * the value a block is stored with, typed as an address, a host or a
 * handle, and which known actors a block covers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_moderation.php');

function test_ap_block_normalize_actor()
{
	pg_assert_same('https://mastodon.example/users/alice', pg_ap_block_normalize('actor', ' https://mastodon.example/users/alice#main-key '));
	pg_assert_same('', pg_ap_block_normalize('actor', 'mastodon.example/users/alice'), 'no scheme');
	pg_assert_same('', pg_ap_block_normalize('actor', 'ftp://mastodon.example/a'), 'other scheme');
	pg_assert_same('', pg_ap_block_normalize('actor', 'https://a.example/x y'), 'space');
	pg_assert_same('', pg_ap_block_normalize('actor', array('x')), 'not a string');
	pg_assert_same('', pg_ap_block_normalize('actor', 'https://' . str_repeat('a', 260) . '.example/'), 'too long');
}

function test_ap_block_normalize_domain()
{
	pg_assert_same('mastodon.example', pg_ap_block_normalize('domain', 'Mastodon.Example'));
	pg_assert_same('mastodon.example', pg_ap_block_normalize('domain', '.mastodon.example.'));
	pg_assert_same('mastodon.example', pg_ap_block_normalize('domain', 'https://mastodon.example/users/alice'));
	pg_assert_same('mastodon.example', pg_ap_block_normalize('domain', '@alice@mastodon.example'));
	pg_assert_same('mastodon.example', pg_ap_block_normalize('domain', 'mastodon.example:8443/path'));
	pg_assert_same('', pg_ap_block_normalize('domain', 'localhost'), 'one label');
	pg_assert_same('', pg_ap_block_normalize('domain', 'bad_host.example'), 'underscore');
	pg_assert_same('', pg_ap_block_normalize('domain', '%.example'), 'wildcard');
	pg_assert_same('', pg_ap_block_normalize('server', 'mastodon.example'), 'unknown kind');
}

function test_ap_block_actors_by_domain()
{
	$block = array('kind' => 'domain', 'value' => 'spam.example');
	$known = array(
		'https://spam.example/users/a',
		'https://eu.spam.example/users/b',
		'https://notspam.example/users/c',
		'https://spam.example.org/users/d',
		'https://spam.example/users/a',
		'',
	);

	pg_assert_same(array('https://spam.example/users/a', 'https://eu.spam.example/users/b'), pg_ap_block_actors($block, $known));
}

function test_ap_block_actors_by_actor()
{
	$block = array('kind' => 'actor', 'value' => 'https://mastodon.example/users/alice');
	$known = array('https://mastodon.example/users/alice', 'https://mastodon.example/users/alicia', 'https://mastodon.example/users/bob');

	pg_assert_same(array('https://mastodon.example/users/alice'), pg_ap_block_actors($block, $known));
}

function test_ap_like_escape()
{
	pg_assert_same('a\\_b\\%c\\\\', pg_ap_like_escape('a_b%c\\'));
}
