<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Encryption Key card's list of sealed values a key reset
 * leaves unreadable: each area counts only non-empty sealed values, empty
 * areas drop out and the order is fixed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_encryption_key_reset_counts_every_area_in_order()
{
	$counts = pg_encryption_key_reset_counts(array(
		'connect_accounts'     => 2,
		'connect_apps'         => 3,
		'marketplace_accounts' => 1,
		'edoc_providers'       => 1,
		'mfa_keys'             => 4,
		'config'               => array(
			'parasut_credentials_enc'    => 'abc:def',
			'backup_remote_settings'     => 'abc:def',
			'oauth_google_client_secret' => 'abc',
			'product_feeds'              => json_encode(array('formats' => array(
				'google' => array('token_secret' => 'a:b'),
				'meta'   => array('token_secret' => 'c:d'),
				'tiktok' => array('token_secret' => ''),
			))),
		),
	));

	pg_assert_same(array(
		'connect'       => 5,
		'marketplace'   => 1,
		'edoc'          => 1,
		'parasut'       => 1,
		'backup'        => 1,
		'product_feeds' => 2,
		'google'        => 1,
		'mfa'           => 4,
	), $counts);
}

function test_encryption_key_reset_counts_drop_empty_areas()
{
	pg_assert_same(array(), pg_encryption_key_reset_counts(array()));

	pg_assert_same(array('mfa' => 1), pg_encryption_key_reset_counts(array(
		'connect_accounts' => 0,
		'mfa_keys'         => '1',
		'config'           => array(
			'parasut_credentials_enc'    => '',
			'backup_remote_settings'     => '  ',
			'oauth_google_client_secret' => null,
			'product_feeds'              => 'not json',
		),
	)));
}

function test_encryption_key_reset_product_feed_tokens_ignore_bad_shapes()
{
	pg_assert_same(0, pg_encryption_key_reset_feed_tokens(''));
	pg_assert_same(0, pg_encryption_key_reset_feed_tokens('{"formats":"x"}'));
	pg_assert_same(1, pg_encryption_key_reset_feed_tokens('{"formats":{"a":{"token_secret":"x:y"},"b":"bad","c":{"token_secret":["x"]}}}'));
}

function test_encryption_key_reset_counts_signed_in_devices_last()
{
	pg_assert_same(array('mfa' => 1, 'devices' => 3), pg_encryption_key_reset_counts(array(
		'devices'  => 3,
		'mfa_keys' => 1,
	)));
}

function test_encryption_key_reset_carried_areas_from_registry()
{
	pg_assert_same(array('cards', 'gateway', 'mfa'), pg_encryption_key_reset_carried_areas(array(
		array('area' => 'cards'),
		array('area' => 'gateway'),
		array('area' => 'gateway'),
		array('area' => 'mfa'),
		array('table' => 'no_area'),
		'not an entry',
	)));
}

function test_encryption_key_reset_split_by_registry()
{
	$counts = array('connect' => 2, 'parasut' => 1, 'mfa' => 4, 'devices' => 3);

	// Before the module entries: everything but the cards is lost.
	pg_assert_same(array(
		'resealed' => array(),
		'lost'     => $counts,
	), pg_encryption_key_reset_split($counts, array('cards', 'gateway')));

	// With the entries: only the devices stay in the confirmed list, even
	// when an entry were to name their area.
	pg_assert_same(array(
		'resealed' => array('connect' => 2, 'parasut' => 1, 'mfa' => 4),
		'lost'     => array('devices' => 3),
	), pg_encryption_key_reset_split($counts, array('cards', 'gateway', 'connect', 'parasut', 'mfa', 'devices')));

	pg_assert_same(array('resealed' => array(), 'lost' => array()), pg_encryption_key_reset_split(array(), array('cards')));
}

function test_encryption_key_reset_summary_lines()
{
	$summary = pg_encryption_key_reset_summary(array(
		'cards'   => array('done' => 12, 'skipped' => 1, 'failed' => 0),
		'gateway' => array('done' => 0, 'skipped' => 0, 'failed' => 0),
		'mfa'     => array('done' => 3, 'skipped' => 0, 'failed' => 2),
		'other'   => array('done' => 1, 'skipped' => 0, 'failed' => 0),
		'bad'     => 'x',
	), array('cards' => 'Cards', 'gateway' => 'Gateway', 'mfa' => 'Keys'));

	pg_assert_same(array(
		'done'   => array('Cards: 12', 'Keys: 3', 'other: 1'),
		'failed' => array('Keys: 2'),
	), $summary);
}
