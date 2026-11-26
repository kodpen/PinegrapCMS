<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The stored-content rules of the Security Scan: a script, a hidden frame or
 * a redirect from a host the site does not use, found in pages, regions,
 * styles and the settings that hold code. The rules are pure; the pass reads
 * the records in batches inside a time budget, keeps its place in a cursor
 * the caller stores, and writes what it finds through the finding model of
 * security_scan.php.
 *
 * A host is known when it is the site itself, a source of the Content
 * Security Policy (the software's own CDNs, the payment and CAPTCHA services
 * and whatever the operator listed) or a widely used embed, analytics or CDN
 * service. A link is never a finding: following it is a visitor's click.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Records read by one query of the pass.
define('PG_SECURITY_CONTENT_BATCH', 50);

/**
 * The stored-content rules by kind, in the order they are reported, with
 * their severity.
 *
 * @return array<string, int>
 */
function pg_security_content_kinds()
{
    return array(
        'content_script_host' => PG_SECURITY_SEVERITY_HIGH,
        'content_iframe_hidden' => PG_SECURITY_SEVERITY_HIGH,
        'content_redirect' => PG_SECURITY_SEVERITY_CRITICAL,
    );
}

/**
 * The reason a stored-content finding is shown with, and what to do about
 * it, in the reader's language. Null for a kind that is not one of these
 * rules.
 *
 * @param string $kind
 * @return array|null array('reason' => ..., 'what_to_do' => ...)
 */
function pg_security_content_rule_text($kind)
{
    switch ((string) $kind) {
        case 'content_script_host':
            return array(
                'reason' => lang('A script is loaded from a host the site does not otherwise use.'),
                'what_to_do' => lang('Open the record and check the script. If nobody on your team added it, remove it and change the passwords of the accounts that can edit content.'),
            );

        case 'content_iframe_hidden':
            return array(
                'reason' => lang('A hidden frame loads a page from a host the site does not otherwise use.'),
                'what_to_do' => lang('Open the record and remove the frame unless your team added it on purpose. A frame nobody can see is a common way to load harmful pages.'),
            );

        case 'content_redirect':
            return array(
                'reason' => lang('Visitors are sent to a host the site does not otherwise use.'),
                'what_to_do' => lang('Open the record and remove the redirect at once unless your team added it. Then change the passwords of the accounts that can edit content.'),
            );
    }

    return null;
}

/**
 * Hosts widely used for embeds, analytics, fonts and libraries. A host
 * covers its subdomains.
 *
 * @return string[]
 */
function pg_security_content_builtin_hosts()
{
    return array(
        'google.com', 'googleapis.com', 'gstatic.com', 'googletagmanager.com', 'google-analytics.com',
        'googleadservices.com', 'googlesyndication.com', 'doubleclick.net', 'recaptcha.net',
        'youtube.com', 'youtube-nocookie.com', 'ytimg.com', 'vimeo.com',
        'facebook.com', 'facebook.net', 'instagram.com', 'twitter.com', 'x.com', 'twimg.com',
        'linkedin.com', 'licdn.com', 'tiktok.com', 'pinterest.com', 'spotify.com', 'soundcloud.com',
        'cdn.jsdelivr.net', 'code.jquery.com', 'cdnjs.cloudflare.com', 'unpkg.com',
        'cloudflareinsights.com', 'challenges.cloudflare.com', 'hcaptcha.com',
        'clarity.ms', 'hotjar.com', 'mc.yandex.ru', 'mc.yandex.com', 'openstreetmap.org',
    );
}

/**
 * The host a URL points to, lower case, without port or trailing dot (a
 * backslash read as a slash, as browsers do); '' for
 * a URL on the site itself (a relative path), a data: or other non-web URL,
 * or one that is not a URL at all.
 *
 * @param mixed $url
 * @return string
 */
function pg_security_content_url_host($url)
{
    if (!is_string($url)) {
        return '';
    }

    // A JSON-escaped slash first; then a browser reads a backslash as a
    // slash in an http or https address, and any run of two or more slashes
    // as the start of the host.
    $url = str_replace('\\', '/', str_replace('\\/', '/', trim($url)));

    if (preg_match('#^(?:https?:)?/{2,}(?:[^/?\#@]*@)?([^/?\#:]+)#i', $url, $match) !== 1) {
        return '';
    }

    $host = rtrim(strtolower($match[1]), '.');

    return (preg_match('/^[a-z0-9-]+(?:\.[a-z0-9-]+)+$/', $host) === 1) ? $host : '';
}

/**
 * Is this host one of the known hosts or a subdomain of one?
 *
 * @param string $host
 * @param string[] $known
 * @return bool
 */
function pg_security_content_host_known($host, array $known)
{
    $host = (string) $host;

    if ($host === '') {
        return false;
    }

    foreach ($known as $entry) {
        $entry = (string) $entry;

        if (($entry !== '') && (($host === $entry) || (substr($host, -(strlen($entry) + 1)) === '.' . $entry))) {
            return true;
        }
    }

    return false;
}

/**
 * The known hosts: the site's own host (with and without "www."), the hosts
 * named as sources in the Content Security Policy and the built-in list.
 *
 * @param string $site_host
 * @param string $csp_policy
 * @return string[]
 */
function pg_security_content_known_hosts($site_host, $csp_policy)
{
    $hosts = array();
    $site = pg_security_content_url_host('//' . (string) $site_host);

    if ($site !== '') {
        $hosts[] = $site;

        if (strpos($site, 'www.') === 0) {
            $hosts[] = substr($site, 4);
        }
    }

    foreach (preg_split('/[\s;]+/', (string) $csp_policy) as $token) {
        if (($token === '') || ($token[0] === "'") || (strpos($token, '.') === false)) {
            continue;
        }

        $token = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $token);
        $token = preg_replace('/^\*\./', '', $token);
        $host = pg_security_content_url_host('//' . $token);

        if ($host !== '') {
            $hosts[] = $host;
        }
    }

    return array_values(array_unique(array_merge($hosts, pg_security_content_builtin_hosts())));
}

/**
 * The known hosts of this site: its host setting and its Content Security
 * Policy as the firewall sends it.
 *
 * @return string[]
 */
function pg_security_content_site_known_hosts()
{
    $site = defined('HOSTNAME_SETTING') ? (string) HOSTNAME_SETTING : '';
    $policy = function_exists('waf_csp_policy') ? (string) waf_csp_policy() : '';

    return pg_security_content_known_hosts($site, $policy);
}

/**
 * The stored text as the rules read it: the escapes a JSON or JavaScript
 * string uses for slashes, quotes and angle brackets undone, so code kept
 * inside a design's JSON is read like code kept as it is. HTML entities are
 * left alone: "&lt;script" is shown as text, it does not run.
 *
 * @param string $text
 * @return string
 */
function pg_security_content_normalise($text)
{
    return str_ireplace(
        array('\\/', '\\u003c', '\\u003e', '\\u0022', '\\u0027', '\\"', "\\'"),
        array('/', '<', '>', '"', "'", '"', "'"),
        $text);
}

/**
 * The JavaScript of a text: the bodies of its script elements when it has
 * any, nothing when it is other HTML (code in an attribute runs only on a
 * visitor's click), and the whole text when it holds no markup (a custom
 * JavaScript field).
 *
 * @param string $text
 * @return string
 */
function pg_security_content_script_text($text)
{
    if (preg_match_all('#<script\b[^>]*>(.*?)(?:</script\s*>|$)#is', $text, $matches) > 0) {
        return implode("\n", $matches[1]);
    }

    return (preg_match('#<[a-z!/]#i', $text) === 1) ? '' : $text;
}

/**
 * Is a frame with these attributes hidden: the hidden attribute, a width or
 * height of 0 or 1, display none, visibility hidden, opacity 0 or pushed far
 * off the page?
 *
 * @param string $attributes The frame tag's attributes
 * @return bool
 */
function pg_security_content_frame_hidden($attributes)
{
    // The bare attribute, with quoted values blanked so overflow: hidden
    // inside a style does not read as one.
    $bare = preg_replace('/(["\']).*?\1/s', '""', $attributes);

    if (preg_match('/(?:^|\s)hidden(?:[\s=\/]|$)/i', $bare) === 1) {
        return true;
    }

    $patterns = array(
        '/(?<![\w-])(?:width|height)\s*[=:]\s*["\']?\s*[01](?:px)?(?![\w.%])/i',
        '/display\s*:\s*none/i',
        '/visibility\s*:\s*hidden/i',
        '/opacity\s*:\s*(?:0+(?:\.0*)?|\.0+)(?![\d.])/i',
        '/(?<![\w-])(?:left|top)\s*:\s*-\d{3,}/i',
    );

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $attributes) === 1) {
            return true;
        }
    }

    return false;
}

/**
 * The value of an attribute inside a tag's attributes, quoted or not, with
 * its character references decoded.
 *
 * @param string $attributes
 * @param string $name
 * @return string
 */
function pg_security_content_attribute($attributes, $name)
{
    $pattern = '/(?<![\w-])' . preg_quote($name, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\']+))/i';

    if (preg_match($pattern, $attributes, $match) !== 1) {
        return '';
    }

    // The browser decodes character references in a value before it reads
    // the address: src="https&#58;//host/" loads from that host.
    return html_entity_decode(implode('', array_slice($match, 1)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * The stored-content rules over one text: by kind, the unknown hosts each
 * rule found, sorted, each once. Kinds that found nothing are left out.
 *
 * @param mixed $text
 * @param string[] $known From pg_security_content_known_hosts()
 * @return array<string, string[]>
 */
function pg_security_content_rules($text, array $known)
{
    if (!is_string($text) || ($text === '')) {
        return array();
    }

    $text = pg_security_content_normalise($text);
    $script = pg_security_content_script_text($text);
    $urls = array_fill_keys(array_keys(pg_security_content_kinds()), array());

    if (preg_match_all('#<script\b([^>]*)>#i', $text, $tags) > 0) {
        foreach ($tags[1] as $attributes) {
            $urls['content_script_host'][] = pg_security_content_attribute($attributes, 'src');
        }
    }

    // A script element made by code: an address ending in .js given to a
    // src property.
    if (($script !== '') && (preg_match_all('#\.src\s*=\s*(["\'])([^"\']+)\1#i', $script, $assigned) > 0)) {
        foreach ($assigned[2] as $url) {
            if (preg_match('#\.js(?:[?\#]|$)#i', $url) === 1) {
                $urls['content_script_host'][] = $url;
            }
        }
    }

    if (preg_match_all('#<iframe\b([^>]*)>#i', $text, $tags) > 0) {
        foreach ($tags[1] as $attributes) {
            if (pg_security_content_frame_hidden($attributes)) {
                $urls['content_iframe_hidden'][] = pg_security_content_attribute($attributes, 'src');
            }
        }
    }

    if (preg_match_all('#<meta\b([^>]*)>#i', $text, $tags) > 0) {
        foreach ($tags[1] as $attributes) {
            if ((strcasecmp(trim(pg_security_content_attribute($attributes, 'http-equiv')), 'refresh') === 0)
                && (preg_match('/url\s*=\s*["\']?([^"\'\s>]+)/i', pg_security_content_attribute($attributes, 'content'), $match) === 1)) {
                $urls['content_redirect'][] = $match[1];
            }
        }
    }

    if ($script !== '') {
        $redirects = array(
            '#(?<![\w$])location(?:\.href)?\s*=\s*(["\'])([^"\']+)\1#i',
            '#(?<![\w$])location\.(?:replace|assign)\s*\(\s*(["\'])([^"\']+)\1#i',
        );

        foreach ($redirects as $pattern) {
            if (preg_match_all($pattern, $script, $matches) > 0) {
                $urls['content_redirect'] = array_merge($urls['content_redirect'], $matches[2]);
            }
        }
    }

    $found = array();

    foreach ($urls as $kind => $list) {
        $hosts = array();

        foreach ($list as $url) {
            $host = pg_security_content_url_host($url);

            if (($host !== '') && !pg_security_content_host_known($host, $known)) {
                $hosts[$host] = true;
            }
        }

        if ($hosts) {
            $hosts = array_keys($hosts);
            sort($hosts, SORT_STRING);
            $found[$kind] = $hosts;
        }
    }

    return $found;
}

/**
 * The sightings of one record: one per rule that found an unknown host in
 * any of its fields, the detail naming each host and the fields it was in
 * ("evil.example (page_custom_js, page_meta_tags)").
 *
 * @param string $type The record type of the place (page, pregion, style ...)
 * @param mixed $id
 * @param array<string, mixed> $fields Field name => stored text
 * @param string[] $known
 * @return array[] From pg_security_sighting()
 */
function pg_security_content_sightings($type, $id, array $fields, array $known)
{
    $place = pg_security_place_record($type, $id);

    if ($place === '') {
        return array();
    }

    $where = array();

    foreach ($fields as $field => $text) {
        foreach (pg_security_content_rules($text, $known) as $kind => $hosts) {
            foreach ($hosts as $host) {
                $where[$kind][$host][] = (string) $field;
            }
        }
    }

    $sightings = array();

    foreach (pg_security_content_kinds() as $kind => $severity) {
        if (empty($where[$kind])) {
            continue;
        }

        ksort($where[$kind], SORT_STRING);
        $parts = array();

        foreach ($where[$kind] as $host => $in) {
            $parts[] = $host . ' (' . implode(', ', array_unique($in)) . ')';
        }

        $sighting = pg_security_sighting($kind, $place, $severity, implode(', ', $parts));

        if ($sighting !== null) {
            $sightings[] = $sighting;
        }
    }

    return $sightings;
}

/**
 * The record types the pass reads, in order. The settings come last.
 *
 * @return string[]
 */
function pg_security_content_source_names()
{
    return array_keys(pg_security_content_sources());
}

/**
 * The records the pass reads: table, id column and the fields that may hold
 * code. A field a migration added is read only once the database has it.
 *
 * @return array<string, array>
 */
function pg_security_content_sources()
{
    return array(
        'page' => array('table' => 'page', 'id' => 'page_id', 'fields' => array('page_custom_js', 'page_meta_tags')),
        'pregion' => array('table' => 'pregion', 'id' => 'pregion_id', 'fields' => array('pregion_content')),
        'cregion' => array('table' => 'cregion', 'id' => 'cregion_id', 'fields' => array('cregion_content')),
        'dregion' => array('table' => 'dregion', 'id' => 'dregion_id', 'fields' => array('dregion_code')),
        'style' => array('table' => 'style', 'id' => 'style_id', 'fields' => array('style_head', 'style_code', 'style_custom_js')),
        'config' => array('table' => 'config', 'id' => '', 'fields' => array('social_networking_code')),
    );
}

/**
 * The pass's cursor: the one given while it is whole (source index, last id
 * read, when the pass started, the record types that could not be read),
 * otherwise a new pass from the first source.
 *
 * @param mixed $cursor
 * @param int $now
 * @return array
 */
function pg_security_content_cursor($cursor, $now)
{
    $fresh = array('source' => 0, 'after' => 0, 'started' => (int) $now, 'unread' => array());

    if (!is_array($cursor)) {
        return $fresh;
    }

    foreach (array('source', 'after', 'started') as $key) {
        if (!isset($cursor[$key]) || !is_numeric($cursor[$key]) || ((int) $cursor[$key] < 0)) {
            return $fresh;
        }
    }

    if (((int) $cursor['source'] >= count(pg_security_content_sources())) || ((int) $cursor['started'] < 1)) {
        return $fresh;
    }

    $unread = (isset($cursor['unread']) && is_array($cursor['unread']))
        ? array_values(array_intersect(pg_security_content_source_names(), array_map('strval', array_filter($cursor['unread'], 'is_string'))))
        : array();

    return array('source' => (int) $cursor['source'], 'after' => (int) $cursor['after'], 'started' => (int) $cursor['started'], 'unread' => $unread);
}

/**
 * One batch of a source: id => fields, read after the given id. The settings
 * row is read whole as record 1.
 *
 * @param array $source From pg_security_content_sources()
 * @param int $after
 * @return array<int, array>|false false when the source cannot be read
 */
function pg_security_content_read_batch(array $source, $after)
{
    $fields = array();

    foreach ($source['fields'] as $field) {
        if (pg_schema_has($source['table'], $field)) {
            $fields[] = $field;
        }
    }

    if (!$fields) {
        return array();
    }

    if ($source['id'] === '') {
        if ((int) $after > 0) {
            return array();
        }

        $row = db_item("SELECT " . implode(', ', $fields) . " FROM " . $source['table'] . " LIMIT 1");

        return is_array($row) ? array(1 => $row) : array();
    }

    $rows = db_items(
        "SELECT " . $source['id'] . ", " . implode(', ', $fields)
        . " FROM " . $source['table']
        . " WHERE " . $source['id'] . " > " . (int) $after
        . " ORDER BY " . $source['id']
        . " LIMIT " . PG_SECURITY_CONTENT_BATCH);

    if (!is_array($rows)) {
        return false;
    }

    $batch = array();

    foreach ($rows as $row) {
        $id = (int) $row[$source['id']];
        unset($row[$source['id']]);
        $batch[$id] = $row;
    }

    return $batch;
}

/**
 * Write one sighting: merged into the row stored under its fingerprint, or a
 * new open finding.
 *
 * @param array $sighting
 * @param int $now
 * @return bool
 */
function pg_security_content_store(array $sighting, $now)
{
    $stored = db_item((string) pg_db_bind(
        "SELECT * FROM security_finding WHERE fingerprint = ? LIMIT 1", array($sighting['fingerprint']), 'escape'));
    $row = pg_security_finding_merge(is_array($stored) ? $stored : null, $sighting, $now);

    if (is_array($stored)) {
        return db_q(
            "UPDATE security_finding SET severity = ?, state = ?, detail = ?, last_seen = ? WHERE id = ?",
            array((int) $row['severity'], (int) $row['state'], (string) $row['detail'], (int) $row['last_seen'], (int) $stored['id'])) !== false;
    }

    return db_q(
        "INSERT INTO security_finding (kind, place, fingerprint, severity, state, detail, note, acted_by, first_seen, last_seen, acted_at)
         VALUES (?, ?, ?, ?, ?, ?, '', 0, ?, ?, 0)",
        array($row['kind'], $row['place'], $row['fingerprint'], (int) $row['severity'], (int) $row['state'], (string) $row['detail'], (int) $row['first_seen'], (int) $row['last_seen'])) !== false;
}

/**
 * Close a whole pass: a stored-content finding it did not see again (last
 * seen before the pass started) moves as pg_security_state_unseen() says:
 * open and ignored become gone, quarantined and gone stay. A record of a
 * type the pass could not read was not looked at and stays as it is.
 *
 * @param int $started
 * @param string[] $unread Record types that could not be read
 * @return void
 */
function pg_security_content_close($started, array $unread = array())
{
    pg_security_scan_close_kinds(array_keys(pg_security_content_kinds()), (int) $started, $unread);
}

/**
 * Run the stored-content pass until it ends or the time runs out. The caller
 * keeps the returned cursor and gives it back on the next tick; a pass that
 * ended returns done and closes the findings it did not see again.
 *
 * @param mixed $cursor The cursor the last tick returned, or null
 * @param float $deadline microtime(true) after which no new batch is read
 * @param int $now
 * @return array array('cursor' => array, 'done' => bool, 'found' => int, 'read' => int)
 */
function pg_security_content_pass($cursor, $deadline, $now)
{
    $cursor = pg_security_content_cursor($cursor, $now);
    $result = array('cursor' => $cursor, 'done' => false, 'found' => 0, 'read' => 0);

    if (!pg_security_finding_ready()) {
        return $result;
    }

    $sources = array_values(pg_security_content_sources());
    $types = pg_security_content_source_names();
    $known = pg_security_content_site_known_hosts();

    while (microtime(true) < (float) $deadline) {
        if ($cursor['source'] >= count($sources)) {
            pg_security_content_close($cursor['started'], $cursor['unread']);
            $result['done'] = true;
            break;
        }

        $batch = pg_security_content_read_batch($sources[$cursor['source']], $cursor['after']);

        // A source that cannot be read, or has nothing more, hands over to
        // the next one.
        if (($batch === false) || !$batch) {
            if (($batch === false) && !in_array($types[$cursor['source']], $cursor['unread'], true)) {
                $cursor['unread'][] = $types[$cursor['source']];
            }

            $cursor['source']++;
            $cursor['after'] = 0;
            continue;
        }

        foreach ($batch as $id => $fields) {
            foreach (pg_security_content_sightings($types[$cursor['source']], $id, $fields, $known) as $sighting) {
                if (pg_security_content_store($sighting, $now)) {
                    $result['found']++;
                }
            }

            $cursor['after'] = (int) $id;
            $result['read']++;
        }
    }

    $result['cursor'] = $cursor;

    return $result;
}
