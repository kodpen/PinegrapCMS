<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ActivityPub actor (includes/fn/activitypub.php): the actor,
 * WebFinger and NodeInfo documents against the fixtures in
 * tests/fixtures/activitypub/ (a Mastodon actor and the documents this site
 * answers), content negotiation, and HTTP Signatures: a request signed as
 * Mastodon signs it, a round trip through pg_ap_sign() and pg_ap_verify(), a
 * stale date, a changed body and a rotated key.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub.php');

function activitypub_test_fixture($name)
{
	return json_decode(file_get_contents(dirname(__FILE__) . '/fixtures/activitypub/' . $name), true);
}

// The site the expected documents (blog_*.json) were written for.
function activitypub_test_site($public_key = "-----BEGIN PUBLIC KEY-----\nMIIB\n-----END PUBLIC KEY-----\n")
{
	return array(
		'base'               => 'https://blog.example/',
		'host'               => 'blog.example',
		'handle'             => 'news',
		'name'               => 'Example News',
		'summary'            => 'News & notes from Example.',
		'type'               => 'Organization',
		'icon'               => 'https://blog.example/logo.png',
		'public_key'         => $public_key,
		'software_directory' => 'pinegrap',
		'version'            => '2026.11.0',
		'followers'          => 3,
	);
}

// The Mastodon actor's key, answered as pg_ap_remote_key() would.
function activitypub_test_mastodon_key($key_id, $fresh)
{
	return pg_ap_key_from_document(activitypub_test_fixture('mastodon_actor.json'), $key_id);
}

function activitypub_test_key_pair()
{
	$made = pg_openssl_key_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
	pg_assert_true((bool) $made, 'a key could be made');
	openssl_pkey_export($made['key'], $private, null, $made['arguments']);
	$details = openssl_pkey_get_details($made['key']);

	return array('private' => $private, 'public' => $details['key']);
}

// A request from pg_ap_sign()'s header lines, as pg_ap_verify() takes it.
function activitypub_test_request($method, $target, $lines, $body)
{
	$headers = array();

	foreach ($lines as $line) {
		$colon = strpos($line, ':');
		$headers[substr($line, 0, $colon)] = ltrim(substr($line, $colon + 1));
	}

	return array('method' => $method, 'target' => $target, 'headers' => $headers, 'body' => $body);
}

function test_activitypub_actor_matches_the_fixture()
{
	pg_assert_same(activitypub_test_fixture('blog_actor.json'), pg_ap_actor(activitypub_test_site()));
}

function test_activitypub_actor_has_the_fields_a_mastodon_actor_has()
{
	$mastodon = activitypub_test_fixture('mastodon_actor.json');
	$actor = pg_ap_actor(activitypub_test_site());

	foreach (array('id', 'type', 'preferredUsername', 'name', 'summary', 'url', 'inbox', 'outbox', 'followers', 'endpoints', 'manuallyApprovesFollowers', 'discoverable', 'publicKey', 'icon') as $field) {
		pg_assert_true(array_key_exists($field, $mastodon), 'the Mastodon fixture has ' . $field);
		pg_assert_true(array_key_exists($field, $actor), 'the actor has ' . $field);
	}

	pg_assert_same(array_keys($mastodon['publicKey']), array_keys($actor['publicKey']));
	pg_assert_same($mastodon['@context'][0], $actor['@context'][0]);
	pg_assert_same($mastodon['@context'][1], $actor['@context'][1]);
	pg_assert_same($actor['id'] . '#main-key', $actor['publicKey']['id']);
}

function test_activitypub_actor_type_is_person_unless_organization()
{
	$site = activitypub_test_site();
	$site['type'] = 'Group';
	$site['icon'] = '';
	$site['summary'] = '';
	$actor = pg_ap_actor($site);

	pg_assert_same('Person', $actor['type']);
	pg_assert_false(isset($actor['icon']), 'no logo, no icon');
	pg_assert_same('', $actor['summary']);
}

function test_activitypub_webfinger_matches_the_fixture()
{
	$site = activitypub_test_site();
	$expected = activitypub_test_fixture('blog_webfinger.json');

	pg_assert_same($expected, pg_ap_webfinger('acct:news@blog.example', $site));
	pg_assert_same($expected, pg_ap_webfinger('acct:NEWS@Blog.Example', $site), 'case-insensitive');
	pg_assert_same($expected, pg_ap_webfinger('https://blog.example/@news', $site), 'by the actor address');
}

function test_activitypub_webfinger_names_nobody_else()
{
	$site = activitypub_test_site();

	pg_assert_same(null, pg_ap_webfinger('acct:other@blog.example', $site));
	pg_assert_same(null, pg_ap_webfinger('acct:news@elsewhere.example', $site));
	pg_assert_same(null, pg_ap_webfinger('', $site));
	pg_assert_same(null, pg_ap_webfinger('https://blog.example/@newsroom', $site));
}

function test_activitypub_nodeinfo_documents()
{
	$site = activitypub_test_site();
	$links = pg_ap_nodeinfo_links($site);
	$info = pg_ap_nodeinfo($site);

	pg_assert_same('https://blog.example/.well-known/nodeinfo/2.1', $links['links'][0]['href']);
	pg_assert_same('http://nodeinfo.diaspora.software/ns/schema/2.1', $links['links'][0]['rel']);
	pg_assert_same('2.1', $info['version']);
	pg_assert_same(array('activitypub'), $info['protocols']);
	pg_assert_same('pinegrap', $info['software']['name']);
	pg_assert_same(false, $info['openRegistrations']);
}

function test_activitypub_collections()
{
	$site = activitypub_test_site();
	$followers = pg_ap_followers_collection($site);
	$outbox = pg_ap_outbox_collection($site);

	pg_assert_same('https://blog.example/@news/followers', $followers['id']);
	pg_assert_same(3, $followers['totalItems']);
	pg_assert_false(isset($followers['orderedItems']), 'members are not listed');
	pg_assert_same('https://blog.example/@news/outbox', $outbox['id']);
	pg_assert_same(0, $outbox['totalItems']);
	pg_assert_same(array(), $outbox['orderedItems']);
}

function test_activitypub_content_negotiation()
{
	pg_assert_true(pg_ap_accepts_activity('application/activity+json'));
	pg_assert_true(pg_ap_accepts_activity('application/activity+json, application/ld+json'));
	pg_assert_true(pg_ap_accepts_activity('application/ld+json; profile="https://www.w3.org/ns/activitystreams"'));
	pg_assert_false(pg_ap_accepts_activity('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'));
	pg_assert_false(pg_ap_accepts_activity('application/ld+json'));
	pg_assert_false(pg_ap_accepts_activity(''));
}

function test_activitypub_handle_rule()
{
	pg_assert_true(pg_ap_handle_valid('news'));
	pg_assert_true(pg_ap_handle_valid('Blog_2'));
	pg_assert_false(pg_ap_handle_valid(''));
	pg_assert_false(pg_ap_handle_valid('news.blog'));
	pg_assert_false(pg_ap_handle_valid('ha ber'));
	pg_assert_false(pg_ap_handle_valid(str_repeat('a', 65)));
}

function test_activitypub_key_from_a_mastodon_actor()
{
	$key = pg_ap_key_from_document(activitypub_test_fixture('mastodon_actor.json'), 'https://mastodon.example/users/alice#main-key');

	pg_assert_contains('BEGIN PUBLIC KEY', $key['pem']);
	pg_assert_same('https://mastodon.example/users/alice', $key['owner']);
	pg_assert_false(pg_ap_key_from_document(array('id' => 'x'), 'x'), 'no key in the document');
}

function test_activitypub_key_owner_must_share_the_key_host()
{
	$pem = "-----BEGIN PUBLIC KEY-----\nAAAA\n-----END PUBLIC KEY-----";

	// A key served by one server cannot speak for an actor on another.
	pg_assert_false(pg_ap_key_from_document(
		array('id' => 'https://evil.example/key', 'publicKeyPem' => $pem, 'owner' => 'https://mastodon.example/users/alice'),
		'https://evil.example/key'
	), 'an owner on another host');
	pg_assert_false(pg_ap_key_from_document(
		array('id' => 'https://mastodon.example/users/alice', 'publicKey' => array('id' => 'https://evil.example/key', 'publicKeyPem' => $pem)),
		'https://evil.example/key'
	), 'a document id on another host');

	$key = pg_ap_key_from_document(
		array('id' => 'https://Social.Example/users/bob/key', 'publicKeyPem' => $pem, 'owner' => 'https://social.example/users/bob'),
		'https://social.example/users/bob/key'
	);

	pg_assert_same('https://social.example/users/bob', $key['owner'], 'same host, case aside');
}

function test_activitypub_verifies_a_request_signed_by_mastodon()
{
	$request = activitypub_test_fixture('signed_request.json');
	$result = pg_ap_verify($request, array('now' => strtotime('2026-11-03 10:05:00 UTC'), 'key' => 'activitypub_test_mastodon_key'));

	pg_assert_same('', $result['error']);
	pg_assert_true($result['ok']);
	pg_assert_same('https://mastodon.example/users/alice', $result['owner']);
}

function test_activitypub_refuses_a_stale_date()
{
	$request = activitypub_test_fixture('signed_request.json');
	$options = array('key' => 'activitypub_test_mastodon_key');

	$options['now'] = strtotime('2026-11-03 11:00:01 UTC');
	$late = pg_ap_verify($request, $options);
	pg_assert_false($late['ok'], 'an hour and a second later');
	pg_assert_same('The request date is outside the accepted window.', $late['error']);

	$options['now'] = strtotime('2026-11-03 08:59:59 UTC');
	pg_assert_false(pg_ap_verify($request, $options)['ok'], 'dated in the future');
}

function test_activitypub_refuses_a_changed_request()
{
	$options = array('now' => strtotime('2026-11-03 10:00:00 UTC'), 'key' => 'activitypub_test_mastodon_key');

	$request = activitypub_test_fixture('signed_request.json');
	$request['body'] = str_replace('Follow', 'Undo', $request['body']);
	pg_assert_same('The digest does not match the body.', pg_ap_verify($request, $options)['error']);

	$request = activitypub_test_fixture('signed_request.json');
	$request['target'] = '/pinegrap/other.php';
	pg_assert_same('The signature does not verify.', pg_ap_verify($request, $options)['error']);

	$request = activitypub_test_fixture('signed_request.json');
	unset($request['headers']['Signature']);
	pg_assert_same('The request is not signed.', pg_ap_verify($request, $options)['error']);

	$request = activitypub_test_fixture('signed_request.json');
	$request['headers']['Signature'] = str_replace('(request-target) host date', '(request-target) date', $request['headers']['Signature']);
	pg_assert_same('The signature does not cover host.', pg_ap_verify($request, $options)['error']);
}

function test_activitypub_sign_and_verify_round_trip()
{
	$pair = activitypub_test_key_pair();
	$key = array('id' => 'https://blog.example/@news#main-key', 'private' => $pair['private']);
	$body = json_encode(activitypub_test_fixture('create_note.json'));
	$time = 1793700000;

	$lines = pg_ap_sign('POST', 'https://mastodon.example/inbox?x=1', $body, $key, $time);
	pg_assert_true(is_array($lines), 'signed');
	pg_assert_same('Host: mastodon.example', $lines[0]);
	pg_assert_same('Date: ' . gmdate('D, d M Y H:i:s', $time) . ' GMT', $lines[1]);
	pg_assert_contains('headers="(request-target) host date digest"', $lines[3]);

	$request = activitypub_test_request('POST', '/inbox?x=1', $lines, $body);
	$fetch = function ($key_id, $fresh) use ($pair) {
		return array('pem' => $pair['public'], 'owner' => 'https://blog.example/@news');
	};
	$result = pg_ap_verify($request, array('now' => $time + 30, 'key' => $fetch));
	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same('https://blog.example/@news#main-key', $result['key_id']);

	// A GET has no body and no Digest.
	$get = pg_ap_sign('GET', 'https://mastodon.example/users/alice', '', $key, $time);
	pg_assert_same(3, count($get));
	pg_assert_true(pg_ap_verify(activitypub_test_request('GET', '/users/alice', $get, ''), array('now' => $time, 'key' => $fetch))['ok']);
}

function test_activitypub_verify_fetches_a_rotated_key_again()
{
	$old = activitypub_test_key_pair();
	$new = activitypub_test_key_pair();
	$time = 1793700000;
	$lines = pg_ap_sign('GET', 'https://blog.example/@news', '', array('id' => 'https://mastodon.example/users/alice#main-key', 'private' => $new['private']), $time);
	$request = activitypub_test_request('GET', '/@news', $lines, '');
	$asked = array();

	$fetch = function ($key_id, $fresh) use ($old, $new, &$asked) {
		$asked[] = $fresh;

		return $fresh ? array('pem' => $new['public'], 'owner' => 'o') : array('pem' => $old['public'], 'owner' => 'o', 'cached' => true);
	};

	pg_assert_true(pg_ap_verify($request, array('now' => $time, 'key' => $fetch))['ok']);
	pg_assert_same(array(false, true), $asked);
}

function test_activitypub_digest_rule()
{
	$digest = 'SHA-256=' . base64_encode(hash('sha256', 'abc', true));

	pg_assert_true(pg_ap_digest_matches($digest, 'abc'));
	pg_assert_true(pg_ap_digest_matches('SHA-512=xyz, ' . $digest, 'abc'));
	pg_assert_false(pg_ap_digest_matches($digest, 'abd'));
	pg_assert_false(pg_ap_digest_matches('MD5=x', 'abc'));
}

function test_activitypub_settings_defaults()
{
	$off = pg_ap_settings_read('');
	pg_assert_false($off['enabled']);
	pg_assert_same('', $off['handle']);
	pg_assert_same('Person', $off['type']);
	pg_assert_same(array(), $off['publish']);

	$on = pg_ap_settings_read('{"enabled":1,"handle":"news","summary":"S","type":"Organization","publish":{"forms":[4]}}');
	pg_assert_true($on['enabled']);
	pg_assert_same('news', $on['handle']);
	pg_assert_same('Organization', $on['type']);
	pg_assert_same(array('forms' => array(4)), $on['publish']);
	pg_assert_same('Person', pg_ap_settings_read('{"type":"Service"}')['type']);
}
