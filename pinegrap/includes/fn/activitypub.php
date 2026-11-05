<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ActivityPub: the site as one Fediverse actor. This module holds the
 * actor's documents (the actor, WebFinger, NodeInfo, the followers and
 * outbox collections), its key pair and HTTP Signatures (draft-cavage-12,
 * the form Mastodon signs and checks): pg_ap_sign() for a request this site
 * sends, pg_ap_verify() for one it receives.
 *
 * The documents are built by pure functions from a site description
 * (pg_ap_site()), so the tests check them without a database. JSON-LD is
 * written by hand to the shapes Mastodon accepts; no library.
 *
 * Off by default: until the config_text row ap_settings switches it on with
 * a valid handle, router.php sends none of the addresses here and they
 * answer as before. The tables are 2026.11.0, 11.0.163.
 *
 * Loaded on demand (activitypub.php, the inbox, the tests), not by
 * functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// How far a signed request's Date may be from this server's clock, in
// seconds. A sender signs every delivery attempt afresh, so an hour covers
// clock drift and queue delays while an old captured request is refused.
if (!defined('PG_AP_SIGNATURE_WINDOW')) {
    define('PG_AP_SIGNATURE_WINDOW', 3600);
}

// How long a remote actor's public key is kept in data/cache/ap/.
if (!defined('PG_AP_KEY_CACHE_TTL')) {
    define('PG_AP_KEY_CACHE_TTL', 86400);
}

/**
 * The actor's settings: the config_text row ap_settings, JSON written by
 * the settings screen. Missing (before the upgrade, or never saved) reads as
 * off.
 *
 * @return array enabled (bool), handle, name, summary, icon (a file name
 *               or an address), type ('Person' or 'Organization'), publish
 *               (the chosen forms and folders)
 */
function pg_ap_settings()
{
    static $settings = null;

    if ($settings === null) {
        $settings = pg_ap_settings_read(pg_config_text('ap_settings'));
    }

    return $settings;
}

/**
 * The ap_settings JSON read into its fields, each with its default.
 *
 * @param string $json
 * @return array
 */
function pg_ap_settings_read($json)
{
    $data = json_decode((string) $json, true);

    if (!is_array($data)) {
        $data = array();
    }

    return array(
        'enabled' => !empty($data['enabled']),
        'handle'  => isset($data['handle']) ? (string) $data['handle'] : '',
        'name'    => isset($data['name']) ? (string) $data['name'] : '',
        'summary' => isset($data['summary']) ? (string) $data['summary'] : '',
        'icon'    => isset($data['icon']) ? (string) $data['icon'] : '',
        'type'    => (isset($data['type']) && ($data['type'] === 'Organization')) ? 'Organization' : 'Person',
        'publish' => (isset($data['publish']) && is_array($data['publish'])) ? $data['publish'] : array(),
    );
}

/**
 * Whether the site speaks ActivityPub: switched on with a valid handle.
 *
 * @return bool
 */
function pg_ap_enabled()
{
    $settings = pg_ap_settings();

    return $settings['enabled'] && pg_ap_handle_valid($settings['handle']);
}

/**
 * A handle as Mastodon accepts a username: letters, digits and underscores.
 *
 * @param string $handle
 * @return bool
 */
function pg_ap_handle_valid($handle)
{
    return is_string($handle) && (bool) preg_match('/^[A-Za-z0-9_]{1,64}$/', $handle);
}

/**
 * The site description the documents are built from.
 *
 * @return array base (scheme, host and path, ending in /), host, handle,
 *               name, summary, type, icon (URL or ''), public_key,
 *               software_directory, version, followers (count)
 */
function pg_ap_site()
{
    $settings = pg_ap_settings();

    $name = trim($settings['name']);

    if ($name === '') {
        $name = trim((string) ORGANIZATION_NAME);
    }

    if ($name === '') {
        $name = trim((string) TITLE);
    }

    if ($name === '') {
        $name = HOSTNAME_SETTING;
    }

    $summary = trim($settings['summary']);

    if (($summary === '') && defined('META_DESCRIPTION')) {
        $summary = trim((string) META_DESCRIPTION);
    }

    $icon = '';

    // The screen's own avatar, else the organization logo.
    $picture = (trim($settings['icon']) !== '') ? $settings['icon'] : (defined('ORGANIZATION_LOGO') ? (string) ORGANIZATION_LOGO : '');

    if (($picture !== '') && function_exists('pg_resolve_config_image')) {
        $logo = pg_resolve_config_image($picture);
        $icon = !empty($logo['url']) ? (string) $logo['url'] : '';
    }

    $keys = pg_ap_keys();

    return array(
        'base'               => URL_SCHEME . HOSTNAME_SETTING . PATH,
        'host'               => HOSTNAME_SETTING,
        'handle'             => $settings['handle'],
        'name'               => $name,
        'summary'            => $summary,
        'type'               => $settings['type'],
        'icon'               => $icon,
        'public_key'         => $keys ? $keys['public'] : '',
        'software_directory' => SOFTWARE_DIRECTORY,
        'version'            => defined('VERSION') ? (string) VERSION : '',
        'followers'          => (int) db_value("SELECT COUNT(*) FROM ap_followers"),
    );
}

/**
 * The link a page's head carries while the feature is on, so a Fediverse
 * server given any page address finds the site's account.
 *
 * @return string '' while off
 */
function pg_ap_alternate_link()
{
    if (!pg_ap_enabled()) {
        return '';
    }

    $settings = pg_ap_settings();
    $id = URL_SCHEME . HOSTNAME_SETTING . PATH . '@' . $settings['handle'];

    return '        <link rel="alternate" type="application/activity+json" href="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" />' . "\n";
}

/**
 * The actor's addresses.
 *
 * @param array $site pg_ap_site()
 * @return array id, key_id, inbox, shared_inbox, outbox, followers, url
 */
function pg_ap_urls($site)
{
    $id = $site['base'] . '@' . $site['handle'];
    $inbox = $site['base'] . $site['software_directory'] . '/ap_inbox.php';

    return array(
        'id'           => $id,
        'key_id'       => $id . '#main-key',
        'inbox'        => $inbox,
        'shared_inbox' => $inbox,
        'outbox'       => $id . '/outbox',
        'followers'    => $id . '/followers',
        'url'          => $site['base'],
    );
}

/**
 * The actor document.
 *
 * @param array $site pg_ap_site()
 * @return array
 */
function pg_ap_actor($site)
{
    $urls = pg_ap_urls($site);

    $actor = array(
        '@context' => array(
            'https://www.w3.org/ns/activitystreams',
            'https://w3id.org/security/v1',
            array(
                'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
                'toot'                      => 'http://joinmastodon.org/ns#',
                'discoverable'              => 'toot:discoverable',
            ),
        ),
        'id'                        => $urls['id'],
        'type'                      => ($site['type'] === 'Organization') ? 'Organization' : 'Person',
        'preferredUsername'         => $site['handle'],
        'name'                      => $site['name'],
        'summary'                   => ($site['summary'] !== '') ? '<p>' . htmlspecialchars($site['summary'], ENT_QUOTES, 'UTF-8') . '</p>' : '',
        'url'                       => $urls['url'],
        'inbox'                     => $urls['inbox'],
        'outbox'                    => $urls['outbox'],
        'followers'                 => $urls['followers'],
        'endpoints'                 => array('sharedInbox' => $urls['shared_inbox']),
        'manuallyApprovesFollowers' => false,
        'discoverable'              => true,
        'publicKey'                 => array(
            'id'           => $urls['key_id'],
            'owner'        => $urls['id'],
            'publicKeyPem' => (string) $site['public_key'],
        ),
    );

    if ($site['icon'] !== '') {
        $actor['icon'] = array('type' => 'Image', 'mediaType' => pg_ap_media_type($site['icon']), 'url' => $site['icon']);
    }

    return $actor;
}

/**
 * The media type of an image address, by its extension.
 *
 * @param string $url
 * @return string
 */
function pg_ap_media_type($url)
{
    $path = (string) parse_url($url, PHP_URL_PATH);
    $extension = strtolower((string) substr(strrchr($path, '.'), 1));
    $types = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'avif' => 'image/avif');

    return isset($types[$extension]) ? $types[$extension] : 'image/png';
}

/**
 * The WebFinger answer (RFC 7033) for $resource, or null when it names
 * nobody here. Accepted: acct:<handle>@<host> (the handle in any case) and
 * the actor's own address.
 *
 * @param string $resource
 * @param array  $site pg_ap_site()
 * @return array|null
 */
function pg_ap_webfinger($resource, $site)
{
    $resource = trim((string) $resource);
    $urls = pg_ap_urls($site);
    $found = false;

    if (preg_match('/^acct:@?([^@]+)@(.+)$/i', $resource, $match)) {
        $found = (strcasecmp($match[1], $site['handle']) === 0) && (strcasecmp($match[2], $site['host']) === 0);
    } elseif ($resource !== '') {
        $found = (strcasecmp(rtrim($resource, '/'), $urls['id']) === 0);
    }

    if (!$found) {
        return null;
    }

    return array(
        'subject' => 'acct:' . $site['handle'] . '@' . $site['host'],
        'aliases' => array($urls['id']),
        'links'   => array(
            array('rel' => 'self', 'type' => 'application/activity+json', 'href' => $urls['id']),
            array('rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => $urls['url']),
        ),
    );
}

/**
 * The NodeInfo discovery document (/.well-known/nodeinfo).
 *
 * @param array $site pg_ap_site()
 * @return array
 */
function pg_ap_nodeinfo_links($site)
{
    return array(
        'links' => array(
            array('rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.1', 'href' => $site['base'] . '.well-known/nodeinfo/2.1'),
        ),
    );
}

/**
 * The NodeInfo 2.1 document: the software, the protocol, one user.
 *
 * @param array $site pg_ap_site()
 * @return array
 */
function pg_ap_nodeinfo($site)
{
    return array(
        'version'           => '2.1',
        'software'          => array('name' => 'pinegrap', 'version' => (string) $site['version'], 'homepage' => 'https://kodpen.com'),
        'protocols'         => array('activitypub'),
        'services'          => array('inbound' => array(), 'outbound' => array()),
        'openRegistrations' => false,
        'usage'             => array('users' => array('total' => 1, 'activeMonth' => 1, 'activeHalfyear' => 1)),
        'metadata'          => array('nodeName' => $site['name']),
    );
}

/**
 * The followers collection: the count only, as Mastodon shows it; the
 * members are not listed to others.
 *
 * @param array $site pg_ap_site()
 * @return array
 */
function pg_ap_followers_collection($site)
{
    $urls = pg_ap_urls($site);

    return array(
        '@context'   => 'https://www.w3.org/ns/activitystreams',
        'id'         => $urls['followers'],
        'type'       => 'OrderedCollection',
        'totalItems' => (int) $site['followers'],
    );
}

/**
 * The outbox collection.
 *
 * @param array $site  pg_ap_site()
 * @param array $items activities, newest first
 * @return array
 */
function pg_ap_outbox_collection($site, $items = array())
{
    $urls = pg_ap_urls($site);

    return array(
        '@context'     => 'https://www.w3.org/ns/activitystreams',
        'id'           => $urls['outbox'],
        'type'         => 'OrderedCollection',
        'totalItems'   => count($items),
        'orderedItems' => array_values($items),
    );
}

/**
 * Whether an Accept header asks for ActivityPub JSON rather than a page.
 *
 * @param string $accept
 * @return bool
 */
function pg_ap_accepts_activity($accept)
{
    $accept = strtolower((string) $accept);

    return (strpos($accept, 'application/activity+json') !== false)
        || ((strpos($accept, 'application/ld+json') !== false) && (strpos($accept, 'activitystreams') !== false));
}

/**
 * The actor's key pair, made once with the DKIM generator and kept in the
 * config_text row ap_keys: the public PEM as it is, the private one sealed
 * with the site's key. False when OpenSSL cannot make a key or the site has
 * no key to seal it with.
 *
 * @return array|false array('public' => PEM, 'private' => PEM)
 */
function pg_ap_keys()
{
    static $keys = null;

    if ($keys !== null) {
        return $keys;
    }

    $keys = pg_ap_keys_open(pg_config_text('ap_keys'));

    if ($keys || !pg_schema_has('config_text') || !pg_connect_can_seal()) {
        return $keys;
    }

    $pair = pg_dkim_key_pair('ap', HOSTNAME_SETTING);

    if (!$pair) {
        return $keys;
    }

    $stored = json_encode(array('public' => $pair['public'], 'private' => pg_connect_seal(array('pem' => $pair['private']))));

    // Only the first request that finds no pair writes one; a second made at
    // the same time is dropped and the stored pair read back.
    db_q("INSERT IGNORE INTO config_text (name, value) VALUES ('ap_keys', ?)", array($stored));

    $keys = pg_ap_keys_open((string) db_value("SELECT value FROM config_text WHERE name = 'ap_keys'"));

    return $keys;
}

/**
 * The stored pair opened, or false when either half is missing.
 *
 * @param string $stored JSON: public (PEM), private (pg_connect_seal() of
 *                       array('pem' => private PEM))
 * @return array|false
 */
function pg_ap_keys_open($stored)
{
    $data = json_decode((string) $stored, true);

    if (!is_array($data) || empty($data['public']) || empty($data['private'])) {
        return false;
    }

    $private = pg_connect_unseal((string) $data['private']);

    return !empty($private['pem']) ? array('public' => (string) $data['public'], 'private' => (string) $private['pem']) : false;
}

/**
 * The headers that sign a request: Host, Date, Digest (with a body) and
 * Signature over (request-target), host, date and digest, rsa-sha256.
 *
 * @param string     $method
 * @param string     $url
 * @param string     $body '' for a GET
 * @param array|null $key  array('id' => key address, 'private' => PEM);
 *                         null signs with the site's own key
 * @param int|null   $time the signing time, now by default
 * @return array|false header lines, or false when there is no key
 */
function pg_ap_sign($method, $url, $body = '', $key = null, $time = null)
{
    if ($key === null) {
        $own = pg_ap_keys();

        if (!$own) {
            return false;
        }

        $urls = pg_ap_urls(array('base' => URL_SCHEME . HOSTNAME_SETTING . PATH, 'handle' => pg_ap_settings()['handle'], 'software_directory' => SOFTWARE_DIRECTORY));
        $key = array('id' => $urls['key_id'], 'private' => $own['private']);
    }

    $parts = parse_url($url);

    if (empty($parts['host'])) {
        return false;
    }

    $host = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    $target = (isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

    $headers = array(
        'host' => $host,
        'date' => gmdate('D, d M Y H:i:s', ($time === null) ? time() : (int) $time) . ' GMT',
    );

    $names = array('(request-target)', 'host', 'date');

    if ((string) $body !== '') {
        $headers['digest'] = 'SHA-256=' . base64_encode(hash('sha256', (string) $body, true));
        $names[] = 'digest';
    }

    $string = pg_ap_signing_string($names, $method, $target, $headers);
    $signature = '';

    if (($string === false) || !function_exists('openssl_sign') || !@openssl_sign($string, $signature, (string) $key['private'], OPENSSL_ALGO_SHA256)) {
        return false;
    }

    $lines = array('Host: ' . $headers['host'], 'Date: ' . $headers['date']);

    if (isset($headers['digest'])) {
        $lines[] = 'Digest: ' . $headers['digest'];
    }

    $lines[] = 'Signature: keyId="' . $key['id'] . '",algorithm="rsa-sha256",headers="' . implode(' ', $names) . '",signature="' . base64_encode($signature) . '"';

    return $lines;
}

/**
 * The string a signature covers: one "name: value" line per signed header,
 * (request-target) as "<method> <path>".
 *
 * @param array  $names   signed header names, lower case, in order
 * @param string $method
 * @param string $target  path and query
 * @param array  $headers lower-case name => value
 * @return string|false false when a signed header is missing
 */
function pg_ap_signing_string($names, $method, $target, $headers)
{
    $lines = array();

    foreach ($names as $name) {
        $name = strtolower(trim($name));

        if ($name === '(request-target)') {
            $lines[] = '(request-target): ' . strtolower($method) . ' ' . $target;
        } elseif (isset($headers[$name])) {
            $lines[] = $name . ': ' . trim((string) $headers[$name]);
        } else {
            return false;
        }
    }

    return implode("\n", $lines);
}

/**
 * The parts of a Signature header (keyId, algorithm, headers, signature).
 *
 * @param string $header
 * @return array lower-case part name => value
 */
function pg_ap_signature_parse($header)
{
    $parts = array();

    if (preg_match_all('/([A-Za-z]+)="([^"]*)"/', (string) $header, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $parts[strtolower($match[1])] = $match[2];
        }
    }

    return $parts;
}

/**
 * Checks a received request's HTTP Signature.
 *
 * Refused: no or a malformed signature, an algorithm other than rsa-sha256
 * (hs2019 is taken as it), (request-target), host or date left unsigned, a
 * body without a signed Digest that matches it, a Date outside
 * PG_AP_SIGNATURE_WINDOW, a key that cannot be had, a signature that does
 * not verify. A failure against a cached key fetches the key again once: the
 * sender may have rotated it.
 *
 * @param array $request method, target (path and query), headers (lower-case
 *                       name => value), body
 * @param array $options now (time), key (callable($key_id, $fresh) answering
 *                       array('pem' => ..., 'owner' => ...) or false;
 *                       pg_ap_remote_key() by default)
 * @return array ok (bool), error (string), key_id, owner (the key's actor)
 */
function pg_ap_verify($request, $options = array())
{
    $refuse = function ($error, $key_id = '') {
        return array('ok' => false, 'error' => $error, 'key_id' => $key_id, 'owner' => '');
    };

    $headers = array();

    foreach ((array) $request['headers'] as $name => $value) {
        $headers[strtolower((string) $name)] = (string) $value;
    }

    if (!isset($headers['signature'])) {
        return $refuse('The request is not signed.');
    }

    $signature = pg_ap_signature_parse($headers['signature']);

    if (empty($signature['keyid']) || empty($signature['signature'])) {
        return $refuse('The signature is malformed.');
    }

    $key_id = $signature['keyid'];
    $algorithm = isset($signature['algorithm']) ? strtolower($signature['algorithm']) : 'rsa-sha256';

    if (!in_array($algorithm, array('rsa-sha256', 'hs2019'), true)) {
        return $refuse('The signature algorithm is not supported.', $key_id);
    }

    $names = preg_split('/\s+/', strtolower(trim(isset($signature['headers']) ? $signature['headers'] : 'date')));

    foreach (array('(request-target)', 'host', 'date') as $required) {
        if (!in_array($required, $names, true)) {
            return $refuse('The signature does not cover ' . $required . '.', $key_id);
        }
    }

    $body = isset($request['body']) ? (string) $request['body'] : '';

    if ($body !== '') {
        if (!in_array('digest', $names, true) || !isset($headers['digest'])) {
            return $refuse('The body has no signed digest.', $key_id);
        }

        if (!pg_ap_digest_matches($headers['digest'], $body)) {
            return $refuse('The digest does not match the body.', $key_id);
        }
    }

    $date = isset($headers['date']) ? strtotime($headers['date']) : false;
    $now = isset($options['now']) ? (int) $options['now'] : time();

    if (($date === false) || (abs($now - $date) > PG_AP_SIGNATURE_WINDOW)) {
        return $refuse('The request date is outside the accepted window.', $key_id);
    }

    $string = pg_ap_signing_string($names, (string) $request['method'], (string) $request['target'], $headers);

    if ($string === false) {
        return $refuse('A signed header is missing.', $key_id);
    }

    $raw = base64_decode($signature['signature'], true);

    if ($raw === false) {
        return $refuse('The signature is malformed.', $key_id);
    }

    $fetch = isset($options['key']) ? $options['key'] : 'pg_ap_remote_key';

    foreach (array(false, true) as $fresh) {
        $key = call_user_func($fetch, $key_id, $fresh);

        if (!$key || empty($key['pem'])) {
            return $refuse('The sender\'s key could not be read.', $key_id);
        }

        if (function_exists('openssl_verify') && (@openssl_verify($string, $raw, $key['pem'], OPENSSL_ALGO_SHA256) === 1)) {
            return array('ok' => true, 'error' => '', 'key_id' => $key_id, 'owner' => isset($key['owner']) ? (string) $key['owner'] : '');
        }

        if (empty($key['cached'])) {
            break;
        }
    }

    return $refuse('The signature does not verify.', $key_id);
}

/**
 * Whether a Digest header holds the body's SHA-256.
 *
 * @param string $header "SHA-256=<base64>", possibly among other digests
 * @param string $body
 * @return bool
 */
function pg_ap_digest_matches($header, $body)
{
    $expected = base64_encode(hash('sha256', $body, true));

    foreach (explode(',', $header) as $part) {
        $pair = explode('=', trim($part), 2);

        if ((count($pair) === 2) && (strtolower($pair[0]) === 'sha-256') && hash_equals($expected, trim($pair[1]))) {
            return true;
        }
    }

    return false;
}

/**
 * The public key in a fetched actor (or key) document: the one whose id is
 * $key_id, or the only one. Refused when its owner is on another host than
 * $key_id, the address the document was fetched from.
 *
 * @param array  $document
 * @param string $key_id
 * @return array|false array('pem' => ..., 'owner' => ...)
 */
function pg_ap_key_from_document($document, $key_id)
{
    if (!is_array($document)) {
        return false;
    }

    $candidates = array();

    if (isset($document['publicKeyPem'])) {
        $candidates[] = $document;
    } elseif (isset($document['publicKey'])) {
        $candidates = isset($document['publicKey']['publicKeyPem']) ? array($document['publicKey']) : (array) $document['publicKey'];
    }

    foreach ($candidates as $candidate) {
        if (!is_array($candidate) || empty($candidate['publicKeyPem'])) {
            continue;
        }

        if ((count($candidates) === 1) || (isset($candidate['id']) && ($candidate['id'] === $key_id))) {
            $owner = isset($candidate['owner']) ? (string) $candidate['owner'] : (isset($document['id']) ? (string) $document['id'] : '');

            // The document came from the key id's host; it may only speak
            // for an actor on that host, or any server could sign as anyone.
            $key_host = strtolower((string) parse_url((string) $key_id, PHP_URL_HOST));

            if (($owner !== '') && (($key_host === '') || (strtolower((string) parse_url($owner, PHP_URL_HOST)) !== $key_host))) {
                return false;
            }

            return array('pem' => (string) $candidate['publicKeyPem'], 'owner' => $owner);
        }
    }

    return false;
}

/**
 * A remote key, from data/cache/ap/ while it is fresh, else fetched (by a
 * signed GET, as servers in authorized-fetch mode ask) and cached.
 *
 * @param string $key_id
 * @param bool   $fresh  skip the cache
 * @return array|false array('pem', 'owner', 'cached' => bool)
 */
function pg_ap_remote_key($key_id, $fresh = false)
{
    $url = preg_replace('/#.*$/', '', (string) $key_id);

    if (!preg_match('#^https://#i', $url)) {
        return false;
    }

    $file = pg_ap_cache_directory() . '/' . sha1($key_id) . '.json';

    if (!$fresh && is_file($file) && ((time() - (int) @filemtime($file)) < PG_AP_KEY_CACHE_TTL)) {
        $cached = json_decode((string) @file_get_contents($file), true);

        // Read back through the same rule, so an entry cached before the
        // owner check is not trusted.
        $cached = is_array($cached) ? pg_ap_key_from_document(array('publicKeyPem' => isset($cached['pem']) ? $cached['pem'] : '', 'owner' => isset($cached['owner']) ? (string) $cached['owner'] : ''), $key_id) : false;

        if ($cached) {
            return array('pem' => (string) $cached['pem'], 'owner' => (string) $cached['owner'], 'cached' => true);
        }
    }

    if (!function_exists('api_http_check_url')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
    }

    $headers = array('Accept: application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"');
    $signed = pg_ap_sign('GET', $url);

    if ($signed) {
        $headers = array_merge($headers, $signed);
    }

    $answer = pg_curl_run('GET', $url, array(
        'headers'       => $headers,
        'timeout'       => 10,
        'max_size'      => 1048576,
        'check_address' => true,
    ));

    if (!$answer['ok']) {
        return false;
    }

    $key = pg_ap_key_from_document(json_decode($answer['body'], true), $key_id);

    if (!$key) {
        return false;
    }

    $directory = pg_ap_cache_directory();

    if (is_dir($directory) || @mkdir($directory, 0755, true)) {
        @file_put_contents($file, json_encode($key), LOCK_EX);
    }

    $key['cached'] = false;

    return $key;
}

/**
 * The folder remote keys are cached in.
 *
 * @return string
 */
function pg_ap_cache_directory()
{
    return PG_FUNCTIONS_DIR . '/data/cache/ap';
}

/**
 * Sends a document as JSON and ends the request.
 *
 * @param array  $data
 * @param string $type the media type
 * @param int    $status
 */
function pg_ap_json($data, $type = 'application/activity+json', $status = 200)
{
    http_response_code($status);
    header('Content-Type: ' . $type . '; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Vary: Accept');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}
