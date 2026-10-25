<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The prototype viewer (prototype.php): one design's draft pages played as a
 * clickable prototype.
 *
 * The viewer is two requests. The signed-in one draws a small bar (the
 * design's name, its draft pages, a hotspots switch, close) around a
 * sandboxed frame. The frame is a second request with no session and no
 * cookie at all, admitted by a short-lived signed address the first one
 * made and bound to the browser it made it for (the session cookie is
 * compared, no session is started): there is no signed-in person in it
 * whose rights could decide what a list or a widget shows, so the draft is
 * drawn exactly as a signed-out visitor would get it once it is published.
 * Every link of the drawn page is resolved (includes/fn/link_resolve.php)
 * and rewritten here: to another draft of the design it stays in the
 * prototype, to a page on the site it opens that page in a new tab, to
 * nothing it is marked and goes nowhere.
 *
 * Loaded by prototype.php only (and by the tests).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * How long a frame address is accepted, in seconds: fifteen minutes. A click
 * in the prototype goes back through the viewer, which makes a new one.
 *
 * @return int
 */
function pg_proto_frame_ttl()
{
    return 900;
}

/**
 * Whether a role may open the viewer: administrators, designers and managers
 * (0-2), the people who can already open a saved draft at its address.
 *
 * @param mixed $role USER_ROLE or a user row's role
 * @return bool
 */
function pg_proto_viewer_role_allowed($role)
{
    if (is_int($role)) {
        $role = (string) $role;
    }

    return is_string($role) && in_array($role, array('0', '1', '2'), true);
}

/**
 * A short piece of address text (a record code, the part of a pretty address
 * after the page name) as the viewer passes it on: plain text without control
 * characters, at most $max characters, '' otherwise.
 *
 * @param mixed $value
 * @param int   $max
 * @return string
 */
function pg_proto_clean_text($value, $max)
{
    if (!is_string($value) || ($value === '') || (mb_strlen($value) > $max) || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        return '';
    }

    return $value;
}

/**
 * A section name (the fragment of a link) as the viewer passes it on to the
 * frame's address: at most 200 characters without control characters, every
 * character a fragment may not hold written as a %-escape (an escape already
 * written stays as it is); '' otherwise.
 *
 * @param mixed $value
 * @return string
 */
function pg_proto_clean_fragment($value)
{
    // The limit counts the name, not its escapes: the viewer cleans the
    // escaped form it is handed again, and must keep what the link kept.
    if (!is_string($value) || ($value === '') || (strlen(rawurldecode($value)) > 200) || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        return '';
    }

    $clean = preg_replace_callback('~[^A-Za-z0-9\-._\~!$&\'()*+,;=:@/?%]~', function ($match) {
        return rawurlencode($match[0]);
    }, $value);

    return is_string($clean) ? $clean : '';
}

/**
 * The signature of a frame address: design, page, person, expiry, and the
 * item and record the page shows, so an address opens only the view it was
 * made for. Its own prefix keeps it apart from any other HMAC made with the
 * same key; the item and the record are %-escaped, so no ':' of theirs can
 * shift a part into the next.
 *
 * @param mixed  $style_id
 * @param mixed  $page_id
 * @param mixed  $user_id  the person the viewer was opened by
 * @param mixed  $expires  Unix time
 * @param string $key      ENCRYPTION_KEY
 * @param string $sub      the part of a pretty address after the page name
 * @param string $record   a record code (?r=)
 * @return string 64 hex characters, '' when any part is missing
 */
function pg_proto_frame_sign($style_id, $page_id, $user_id, $expires, $key, $sub = '', $record = '')
{
    $style_id = (int) $style_id;
    $page_id = (int) $page_id;
    $user_id = (int) $user_id;
    $expires = (int) $expires;
    $key = (string) $key;

    if (($style_id <= 0) || ($page_id <= 0) || ($user_id <= 0) || ($expires <= 0) || ($key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'prototype-frame:' . $style_id . ':' . $page_id . ':' . $user_id . ':' . $expires
        . ':' . rawurlencode((string) $sub) . ':' . rawurlencode((string) $record), $key);
}

/**
 * What binds a frame address to the browser the viewer made it for: a MAC of
 * the address's signature and that browser's session id, sent as b=. The
 * page's own scripts run in the frame and can read its address (a tag pasted
 * into the design reports it to its vendor), so the address must open
 * nothing without the session cookie it was made for, which those scripts
 * cannot read. A MAC of its own rather than a part of the signature: an
 * address asked for by another browser, or by the same one after its
 * session changed, is refused without being counted as a forgery
 * (pg_proto_frame_state() answers 'unbound', not 'invalid').
 *
 * @param string $signature pg_proto_frame_sign()
 * @param string $client    the viewer's session id
 * @param string $key       ENCRYPTION_KEY
 * @return string 64 hex characters, '' when any part is missing
 */
function pg_proto_frame_bind($signature, $client, $key)
{
    $signature = (string) $signature;
    $client = (string) $client;
    $key = (string) $key;

    if (($signature === '') || ($client === '') || ($key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'prototype-bind:' . $signature . ':' . $client, $key);
}

/**
 * The frame's address: the viewer, the signed query and the section the
 * frame scrolls to. A fragment never reaches the server, so it is no part of
 * the signature.
 *
 * @param string $viewer PATH . SOFTWARE_DIRECTORY . '/prototype.php'
 * @param string $query  pg_proto_frame_query()
 * @param string $anchor pg_proto_clean_fragment()
 * @return string
 */
function pg_proto_frame_url($viewer, $query, $anchor = '')
{
    return (string) $viewer . '?' . (string) $query . (((string) $anchor !== '') ? '#' . (string) $anchor : '');
}

/**
 * The address a visitor would ask the drawn page by: what the frame's
 * widgets read as the request (get_request_uri()) for the links they build
 * to the page itself (language switcher, calendar, sign-in's way back), in
 * place of the frame's own signed address.
 *
 * @param string $path   PATH
 * @param string $name   page name
 * @param string $sub    the part of a pretty address after the page name
 * @param string $record a record code (?r=)
 * @return string
 */
function pg_proto_public_uri($path, $name, $sub = '', $record = '')
{
    $uri = (string) $path . encode_url_path((string) $name);

    if ((string) $sub !== '') {
        $uri .= '/' . encode_url_path((string) $sub);
    }

    if ((string) $record !== '') {
        $uri .= '?r=' . rawurlencode((string) $record);
    }

    return $uri;
}

/**
 * The pg_consent cookie value of a visitor who refused every optional
 * category (pg_consent_parse() reads it back): the frame's answer to the
 * cookie notice, so no tag held for consent starts in a prototype.
 *
 * @param array $letters pg_consent_category_letters()
 * @return string
 */
function pg_proto_consent_refused($letters)
{
    $value = '1';

    foreach ((array) $letters as $letter) {
        $value .= '.' . $letter . '0';
    }

    return $value;
}

/**
 * Cuts the language directory off an item name (/de/about is the page
 * "about" in German): only a prefix the site has, compared as written, the
 * way router.php compares it.
 *
 * @param string $item      pg_link_parse() item
 * @param array  $languages prefix => language code (pg_tr_prefix_map())
 * @return array language (the prefix, '' for none), item (the rest)
 */
function pg_proto_split_language($item, $languages)
{
    $item = (string) $item;
    $slash = strpos($item, '/');
    $first = ($slash === false) ? $item : substr($item, 0, $slash);

    if (($first === '') || !is_array($languages) || !array_key_exists($first, $languages)) {
        return array('language' => '', 'item' => $item);
    }

    return array('language' => $first, 'item' => ($slash === false) ? '' : ltrim((string) substr($item, $slash + 1), '/'));
}

/**
 * The query string of a frame address, without the leading "?".
 *
 * @param string $bind pg_proto_frame_bind(), '' for none
 * @return string
 */
function pg_proto_frame_query($style_id, $page_id, $user_id, $expires, $signature, $sub = '', $record = '', $bind = '')
{
    $query = 'frame=1'
        . '&s=' . (int) $style_id
        . '&p=' . (int) $page_id
        . '&u=' . (int) $user_id
        . '&e=' . (int) $expires
        . '&sig=' . rawurlencode((string) $signature);

    if ((string) $bind !== '') {
        $query .= '&b=' . rawurlencode((string) $bind);
    }

    if ((string) $sub !== '') {
        $query .= '&sub=' . rawurlencode((string) $sub);
    }

    if ((string) $record !== '') {
        $query .= '&r=' . rawurlencode((string) $record);
    }

    return $query;
}

/**
 * Reads a frame address from the request. Design, page, person and expiry
 * are plain positive whole numbers written as such; the signature is 64
 * lower-case hex characters. Anything else is no address. The record code
 * and the sub-address are optional and dropped when they are not plain text;
 * a binding that is not 64 hex characters is read as none (an address that
 * is not bound, not a forged one).
 *
 * @param mixed $source $_GET
 * @return array|null s, p, u, e (int), sig, sub, r, b (string)
 */
function pg_proto_frame_parse($source)
{
    if (!is_array($source)) {
        return null;
    }

    $parsed = array();

    foreach (array('s', 'p', 'u', 'e') as $name) {
        $value = isset($source[$name]) ? $source[$name] : null;

        if (!is_string($value) || (preg_match('/^[1-9][0-9]{0,9}$/', $value) !== 1) || ((string) (int) $value !== $value)) {
            return null;
        }

        $parsed[$name] = (int) $value;
    }

    $signature = isset($source['sig']) ? $source['sig'] : null;

    if (!is_string($signature) || (preg_match('/^[0-9a-f]{64}$/', $signature) !== 1)) {
        return null;
    }

    $parsed['sig'] = $signature;
    $parsed['sub'] = pg_proto_clean_text(isset($source['sub']) ? $source['sub'] : '', 255);
    $parsed['r'] = pg_proto_clean_text(isset($source['r']) ? $source['r'] : '', 100);

    $bind = isset($source['b']) ? $source['b'] : '';
    $parsed['b'] = (is_string($bind) && (preg_match('/^[0-9a-f]{64}$/', $bind) === 1)) ? $bind : '';

    return $parsed;
}

/**
 * What a frame address can do now: 'ok'; 'invalid' (not parsed, no key, a
 * signature that does not match, or an expiry further ahead than the viewer
 * ever sets); 'expired'; 'unbound' (a valid address that is not bound to the
 * browser asking for it, pg_proto_frame_bind()).
 *
 * @param array|null  $parsed pg_proto_frame_parse()
 * @param string      $key
 * @param int         $now
 * @param string|null $client the asking browser's session cookie ('' for
 *                            none); null checks the signature alone
 * @return string
 */
function pg_proto_frame_state($parsed, $key, $now, $client = null)
{
    if (!is_array($parsed) || !isset($parsed['s'], $parsed['p'], $parsed['u'], $parsed['e'], $parsed['sig'])) {
        return 'invalid';
    }

    $expected = pg_proto_frame_sign($parsed['s'], $parsed['p'], $parsed['u'], $parsed['e'], $key,
        isset($parsed['sub']) ? $parsed['sub'] : '', isset($parsed['r']) ? $parsed['r'] : '');

    if (($expected === '') || !hash_equals($expected, (string) $parsed['sig'])) {
        return 'invalid';
    }

    if ((int) $parsed['e'] > ((int) $now + pg_proto_frame_ttl() + 60)) {
        return 'invalid';
    }

    if ((int) $parsed['e'] < (int) $now) {
        return 'expired';
    }

    // Checked last: only an address that is genuine and in time can be
    // refused as asked for in the wrong browser.
    if ($client !== null) {
        $bind = pg_proto_frame_bind($parsed['sig'], $client, $key);

        if (($bind === '') || !isset($parsed['b']) || !hash_equals($bind, (string) $parsed['b'])) {
            return 'unbound';
        }
    }

    return 'ok';
}

/**
 * The pages of a design's prototype: its pages in the Drafts folder (a
 * scheduled page is one until it goes live), none in the Recycle Bin, in the
 * order of the editor's tab strip (pg_designer_tab_layout_normalize(): the
 * listed tabs, then the rest by id).
 *
 * @param array             $rows            page rows of the design: page_id,
 *                                           page_name, page_title,
 *                                           page_folder, publish_at
 *                                           (optional)
 * @param int               $draft_folder_id pg_page_draft_folder_id()
 * @param int[]             $bin_folder_ids  pg_recycle_bin_folder_ids()
 * @param array|string|null $tab_layout      style.style_tab_layout
 * @return array[] id, name, title, publish_at, scheduled
 */
function pg_proto_page_set($rows, $draft_folder_id, $bin_folder_ids, $tab_layout)
{
    $draft_folder_id = (int) $draft_folder_id;

    if ($draft_folder_id <= 0) {
        return array();
    }

    $bins = array();

    foreach ((array) $bin_folder_ids as $folder_id) {
        $bins[(int) $folder_id] = true;
    }

    if (isset($bins[$draft_folder_id])) {
        return array();
    }

    $pages = array();

    foreach ((array) $rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $id = isset($row['page_id']) ? (int) $row['page_id'] : 0;
        $folder = isset($row['page_folder']) ? (int) $row['page_folder'] : 0;

        if (($id <= 0) || ($folder !== $draft_folder_id)) {
            continue;
        }

        $publish_at = isset($row['publish_at']) ? (int) $row['publish_at'] : 0;

        $pages[$id] = array(
            'id' => $id,
            'name' => isset($row['page_name']) ? (string) $row['page_name'] : '',
            'title' => isset($row['page_title']) ? (string) $row['page_title'] : '',
            'publish_at' => $publish_at,
            'scheduled' => ($publish_at > 0),
        );
    }

    if (!$pages) {
        return array();
    }

    $layout = pg_designer_tab_layout_normalize($tab_layout, array_keys($pages));
    $ordered = array();

    foreach ($layout['tabs'] as $tab) {
        $ordered[] = $pages[(int) $tab['id']];
    }

    return $ordered;
}

/**
 * The page a prototype opens on: the asked one when it is in the set, else
 * the first (fallback says the asked one was passed over).
 *
 * @param array[] $pages        pg_proto_page_set()
 * @param int     $requested_id
 * @return array id (0 for an empty set), fallback
 */
function pg_proto_start_page($pages, $requested_id)
{
    $requested_id = (int) $requested_id;

    if (!$pages) {
        return array('id' => 0, 'fallback' => false);
    }

    foreach ($pages as $page) {
        if ($page['id'] === $requested_id) {
            return array('id' => $requested_id, 'fallback' => false);
        }
    }

    return array('id' => (int) $pages[0]['id'], 'fallback' => ($requested_id > 0));
}

/**
 * The viewer's address for one page of the prototype.
 *
 * @param string $base   PATH . SOFTWARE_DIRECTORY . '/prototype.php', or a
 *                       share link's viewer with its token already in the
 *                       query (pg_proto_link_viewer_base())
 * @param int    $page_id
 * @param string $sub    the part of a pretty address after the page name
 * @param string $record a record code (?r=)
 * @param string $anchor the section the page opens at (pg_proto_clean_fragment())
 * @return string
 */
function pg_proto_viewer_url($base, $page_id, $sub = '', $record = '', $anchor = '')
{
    $url = (string) $base . ((strpos((string) $base, '?') === false) ? '?' : '&') . 'page=' . (int) $page_id;

    if ((string) $sub !== '') {
        $url .= '&sub=' . rawurlencode((string) $sub);
    }

    if ((string) $record !== '') {
        $url .= '&r=' . rawurlencode((string) $record);
    }

    if ((string) $anchor !== '') {
        $url .= '&anchor=' . rawurlencode((string) $anchor);
    }

    return $url;
}

/**
 * The record code (?r=) a link names, as the viewer passes it on; '' for
 * none.
 *
 * @param string $href
 * @return string
 */
function pg_proto_href_record($href)
{
    $query = parse_url((string) $href, PHP_URL_QUERY);

    if (!is_string($query) || ($query === '')) {
        return '';
    }

    parse_str($query, $query_values);

    return pg_proto_clean_text(isset($query_values['r']) ? $query_values['r'] : '', 100);
}

/**
 * Where a link to one view of a page of the prototype leads: a section of
 * the view in the frame scrolls there ('#name'; drawing the view again would
 * put the visitor back at the top), anything else goes through the viewer.
 *
 * @param array  $ctx      pg_proto_link_target()
 * @param int    $page_id  a page of the prototype
 * @param string $sub      the part of a pretty address after the page name
 * @param string $record   a record code (?r=)
 * @param string $fragment pg_proto_clean_fragment()
 * @return array kind (anchor, prototype), page_id, href
 */
function pg_proto_view_target($ctx, $page_id, $sub, $record, $fragment)
{
    $page_id = (int) $page_id;
    $same_view = ($page_id === (isset($ctx['current_page']) ? (int) $ctx['current_page'] : 0))
        && ((string) $sub === (isset($ctx['current_sub']) ? (string) $ctx['current_sub'] : ''))
        && ((string) $record === (isset($ctx['current_record']) ? (string) $ctx['current_record'] : ''));

    if ($same_view && ((string) $fragment !== '')) {
        return array('kind' => 'anchor', 'page_id' => $page_id, 'href' => '#' . $fragment);
    }

    return array('kind' => 'prototype', 'page_id' => $page_id,
        'href' => pg_proto_viewer_url(isset($ctx['viewer']) ? (string) $ctx['viewer'] : '', $page_id, $sub, $record, $fragment));
}

/**
 * Where one link of a drawn draft leads in the prototype.
 *
 * Kinds: prototype (another page of the prototype, or the page on view
 * showing another item: the viewer's address for it, with the section the
 * link names), anchor (a section of the view in the frame: '#name', scrolled
 * there), site (a page, file or item on the site, or a page in another
 * language: opens there in a new tab), draft (a draft of another design or
 * an item under one: marked, goes nowhere), broken (nothing by that name, a
 * binned page or an item under one, an empty or unreadable address: marked,
 * goes nowhere), external (another host), contact (mailto, tel, sms), and
 * '' for what is left as it is (a bare anchor, a script address, a toggle).
 *
 * @param string $href decoded attribute value
 * @param array  $ctx  env, maps, form_names (pg_link_resolve_item()),
 *                     proto (page id => true), draft_folder, bin_folders
 *                     (folder id => true), current_page, current_sub,
 *                     current_record (the view in the frame), languages
 *                     (pg_tr_prefix_map()), viewer
 * @return array kind, page_id, href ('' for draft and broken)
 */
function pg_proto_link_target($href, $ctx)
{
    $href = trim((string) $href);
    $env = isset($ctx['env']) ? $ctx['env'] : array();
    $maps = isset($ctx['maps']) ? $ctx['maps'] : array();
    $proto = isset($ctx['proto']) ? $ctx['proto'] : array();
    $out = array('kind' => '', 'page_id' => 0, 'href' => $href);

    $parsed = pg_link_parse($href, $env);

    if ($parsed['result'] !== null) {
        switch ($parsed['result']['class']) {
            case 'anchor':
            case 'script':
                return $out;

            case 'mailto':
            case 'tel':
            case 'sms':
                $out['kind'] = 'contact';
                return $out;

            case 'host':
            case 'ftp':
            case 'data':
                $out['kind'] = 'external';
                return $out;

            case 'self':
                // A bare query string is read against the view on screen, as
                // a browser reads it: the page and the item stay, the query
                // (so the record) and the fragment are the link's own.
                if (strpos($href, '?') === 0) {
                    return pg_proto_view_target($ctx,
                        isset($ctx['current_page']) ? (int) $ctx['current_page'] : 0,
                        isset($ctx['current_sub']) ? (string) $ctx['current_sub'] : '',
                        pg_proto_href_record($href),
                        pg_proto_clean_fragment(parse_url($href, PHP_URL_FRAGMENT)));
                }

                $out['kind'] = 'site';
                return $out;

            case 'outside':
            case 'robots':
            case 'backend':
                $out['kind'] = 'site';
                return $out;

            default:
                return array('kind' => 'broken', 'page_id' => 0, 'href' => '');
        }
    }

    // A page in another language is the same page under a language
    // directory; its name decides as it does without one.
    $split = pg_proto_split_language($parsed['item'], isset($ctx['languages']) ? $ctx['languages'] : array());
    $item = $split['item'];
    $resolved = pg_link_resolve_item($item, $maps, isset($ctx['form_names']) ? $ctx['form_names'] : array());

    if ($resolved['type'] === 'unknown') {
        return array('kind' => 'broken', 'page_id' => 0, 'href' => '');
    }

    $record = pg_proto_href_record($href);
    $fragment = pg_proto_clean_fragment(parse_url($href, PHP_URL_FRAGMENT));

    // The page the address is drawn on: the page itself, or for a pretty
    // address (a product, a record) the page before the slash, whose folder
    // decides as it does for a link to that page. Under a page of the
    // prototype it is that page showing the item.
    $page_id = ($resolved['type'] === 'page') ? (int) $resolved['id'] : 0;
    $sub = '';

    if (in_array($resolved['type'], array('page', 'product', 'product_group'), true) && (mb_strpos($item, '/') !== false)) {
        $prefix = mb_substr($item, 0, mb_strpos($item, '/'));

        if (isset($maps['pages'][$prefix])) {
            $page_id = (int) $maps['pages'][$prefix]['id'];
            $sub = trim(mb_substr($item, mb_strpos($item, '/') + 1), '/');
        }
    }

    $in_proto = ($page_id > 0) && isset($proto[$page_id]);

    // The frame draws the source language only, so another language's
    // version of a draft opens on the site like any other page.
    if ($in_proto && ($split['language'] === '')) {
        return pg_proto_view_target($ctx, $page_id, $sub, $record, $fragment);
    }

    if (($resolved['type'] === 'page') && ($page_id <= 0)) {
        return array('kind' => 'broken', 'page_id' => 0, 'href' => '');
    }

    if (($page_id > 0) && !$in_proto) {
        $folder = null;

        foreach ((isset($maps['pages']) ? $maps['pages'] : array()) as $page) {
            if ((int) $page['id'] === $page_id) {
                $folder = (int) $page['folder'];
                break;
            }
        }

        if (($folder !== null) && isset($ctx['bin_folders'][$folder])) {
            return array('kind' => 'broken', 'page_id' => $page_id, 'href' => '');
        }

        if (($folder !== null) && isset($ctx['draft_folder']) && ($folder === (int) $ctx['draft_folder'])) {
            return array('kind' => 'draft', 'page_id' => $page_id, 'href' => '');
        }
    }

    // A relative address is read against the site root (pg_link_parse());
    // the frame is not at the site root, so it is written out in full.
    $site_href = $href;

    if (!preg_match('~^(/|[a-z][a-z0-9+.-]*:)~i', $href)) {
        $site_href = (isset($env['path']) ? (string) $env['path'] : '/') . $href;
    }

    return array('kind' => 'site', 'page_id' => $page_id, 'href' => $site_href);
}

/**
 * Splits markup into the parts whose links are page links and the parts
 * that only look like markup: script and style bodies, text areas and
 * comments. Odd indexes are the second kind.
 *
 * @param string $html
 * @return string[]|null null when the markup could not be split
 */
function pg_proto_html_parts($html)
{
    $parts = preg_split('~(<script\b[^>]*>.*?</script\s*>|<style\b[^>]*>.*?</style\s*>|<textarea\b[^>]*>.*?</textarea\s*>|<!--.*?-->)~is', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE);

    return is_array($parts) ? $parts : null;
}

/**
 * Every tag of a piece of markup, read the way a browser tokenizes it:
 * group 1 is the name as written ("/name" for an end tag), group 2 what
 * follows up to the ">" that ends the tag. A quote opens a quoted value only
 * after "=", and a quoted value runs to its own closing quote, so a "<a"
 * written inside another tag's attribute value (a popover's content, a
 * custom element's setting) is part of that tag and never read as a link.
 * Every repeat is possessive: markup that does not close cannot make the
 * match backtrack.
 *
 * @return string a preg pattern
 */
function pg_proto_tag_pattern()
{
    return '~<(/?[a-z][^\s/>]*+)((?:[\s/]++|(?:=[^\s/>=]*+|[^\s/>=]++)(?:\s*+=\s*+(?:"[^"]*+"|\'[^\']*+\'|[^\s"\'>][^\s>]*+)?)?)*+)>~i';
}

/**
 * The attributes of one start tag, in order: name (lower case), value
 * (decoded; null for an attribute written without one) and the text it was
 * written as.
 *
 * @param string $inner the tag without "<", its name and ">" (group 2 of
 *                      pg_proto_tag_pattern())
 * @return array[]
 */
function pg_proto_tag_attributes($inner)
{
    preg_match_all('~([^\s"\'<>/=`]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'<>=`]+))?~', (string) $inner, $matches, PREG_SET_ORDER);

    $attributes = array();

    foreach ($matches as $match) {
        $value = null;

        if (isset($match[2]) && ($match[2] !== '')) {
            $raw = $match[2];

            if (($raw[0] === '"') || ($raw[0] === "'")) {
                $raw = substr($raw, 1, -1);
            }

            $value = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } elseif (strpos($match[0], '=') !== false) {
            $value = '';
        }

        $attributes[] = array('name' => strtolower($match[1]), 'value' => $value, 'text' => $match[0]);
    }

    return $attributes;
}

/**
 * The href of every link in the markup, decoded, in order, outside scripts,
 * styles, text areas and comments.
 *
 * @param string $html
 * @return string[]
 */
function pg_proto_html_hrefs($html)
{
    $parts = pg_proto_html_parts($html);
    $hrefs = array();

    if ($parts === null) {
        return $hrefs;
    }

    foreach ($parts as $index => $part) {
        if (($index % 2) === 1) {
            continue;
        }

        if (!preg_match_all(pg_proto_tag_pattern(), $part, $tags, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($tags as $tag) {
            if (strtolower($tag[1]) !== 'a') {
                continue;
            }

            foreach (pg_proto_tag_attributes(isset($tag[2]) ? $tag[2] : '') as $attribute) {
                if (($attribute['name'] === 'href') && ($attribute['value'] !== null)) {
                    $hrefs[] = $attribute['value'];
                    break;
                }
            }
        }
    }

    return $hrefs;
}

/**
 * The links of a drawn page rewritten for the prototype. Each link's href is
 * handed to $classify (pg_proto_link_target()); a link it leaves alone (kind
 * '') keeps its tag exactly as written. Other attributes keep their place
 * and text; href, target, rel and the viewer's own data attributes are
 * written anew. Scripts, styles, text areas and comments are not touched.
 *
 * @param string   $html
 * @param callable $classify href => pg_proto_link_target() result
 * @param array    $strings  broken, draft: the title an inert link gets
 * @return string the markup unchanged when it could not be read
 */
function pg_proto_rewrite_links($html, $classify, $strings = array())
{
    $parts = pg_proto_html_parts($html);

    if ($parts === null) {
        return (string) $html;
    }

    $rewrite_tag = function ($match) use ($classify, $strings) {
        // Only a link is rewritten; every other tag is matched so that what
        // its attribute values hold is never taken for a link.
        if ((strtolower($match[1]) !== 'a') || !isset($match[2]) || (trim($match[2]) === '')) {
            return $match[0];
        }

        $attributes = pg_proto_tag_attributes($match[2]);
        $href = null;

        foreach ($attributes as $attribute) {
            if (($attribute['name'] === 'href') && ($attribute['value'] !== null)) {
                $href = $attribute['value'];
                break;
            }
        }

        if ($href === null) {
            return $match[0];
        }

        $target = call_user_func($classify, $href);
        $kind = (is_array($target) && isset($target['kind'])) ? (string) $target['kind'] : '';

        if ($kind === '') {
            return $match[0];
        }

        $inert = (($kind === 'broken') || ($kind === 'draft'));
        $set = array();

        if ($inert) {
            $set['data-pg-proto'] = $kind;
            $set['data-pg-proto-href'] = $href;
            $set['aria-disabled'] = 'true';
        } else {
            $set['href'] = (string) $target['href'];
            // A section of the view scrolls inside the frame, another page
            // of the prototype moves the viewer, the rest opens a new tab.
            $set['target'] = ($kind === 'prototype') ? '_top' : (($kind === 'anchor') ? '_self' : '_blank');

            if (($kind !== 'prototype') && ($kind !== 'anchor')) {
                $rel = '';

                foreach ($attributes as $attribute) {
                    if ($attribute['name'] === 'rel') {
                        $rel = (string) $attribute['value'];
                    }
                }

                $tokens = preg_split('/\s+/', trim($rel), -1, PREG_SPLIT_NO_EMPTY);
                $wanted = ($kind === 'site') ? array('noopener') : array('noopener', 'noreferrer');

                foreach ($wanted as $token) {
                    if (!in_array($token, array_map('strtolower', $tokens), true)) {
                        $tokens[] = $token;
                    }
                }

                $set['rel'] = implode(' ', $tokens);
            }

            $set['data-pg-proto'] = $kind;
        }

        $written = array();
        $out = '<' . $match[1];
        $present = array();

        foreach ($attributes as $attribute) {
            $name = $attribute['name'];
            $present[$name] = true;

            // An inert link has no address, so nothing opens it.
            if ($inert && ($name === 'href')) {
                continue;
            }

            if (array_key_exists($name, $set)) {
                if (!isset($written[$name])) {
                    $out .= ' ' . $name . '="' . h($set[$name]) . '"';
                    $written[$name] = true;
                }
                continue;
            }

            $out .= ' ' . $attribute['text'];
        }

        foreach ($set as $name => $value) {
            if (!isset($written[$name])) {
                $out .= ' ' . $name . '="' . h($value) . '"';
            }
        }

        if ($inert) {
            if (!isset($present['role'])) {
                $out .= ' role="link"';
            }

            if (!isset($present['title']) && isset($strings[$kind]) && ((string) $strings[$kind] !== '')) {
                $out .= ' title="' . h($strings[$kind]) . '"';
            }
        }

        return $out . '>';
    };

    foreach ($parts as $index => $part) {
        if (($index % 2) === 1) {
            continue;
        }

        $rewritten = preg_replace_callback(pg_proto_tag_pattern(), $rewrite_tag, $part);

        if ($rewritten !== null) {
            $parts[$index] = $rewritten;
        }
    }

    return implode('', $parts);
}

/**
 * The frame's own additions to a drawn page: robots and referrer meta tags,
 * the cookie and storage shim and the marks' styles at the start of the
 * head, the helper script at the end of the body. The script outlines the
 * links while the viewer's hotspots switch is on (a message from the
 * viewer), keeps inert links inert, opens a link a script drew later in a
 * new tab, and says why a form does not send (the frame's sandbox stops the
 * submission itself).
 *
 * @param string $html
 * @param array  $strings forms, broken, draft
 * @param array  $cookies name => value the shim's jar starts with (the
 *                        cookie notice's answer)
 * @return string
 */
function pg_proto_frame_inject($html, $strings, $cookies = array())
{
    $html = (string) $html;
    $config = array(
        'forms' => isset($strings['forms']) ? (string) $strings['forms'] : '',
        'broken' => isset($strings['broken']) ? (string) $strings['broken'] : '',
        'draft' => isset($strings['draft']) ? (string) $strings['draft'] : '',
    );

    $jar = array();

    foreach ((array) $cookies as $name => $value) {
        $jar[(string) $name] = (string) $value;
    }

    // First in the head, before any script of the page: in the frame's
    // opaque origin reading document.cookie or the storage objects throws,
    // which stopped the site's own scripts. They get a cookie jar and
    // storage that live in memory for this view only, a first visit that
    // keeps nothing; the jar holds the answer the server drew the page with,
    // so a script reading the consent cookie sees the same refusal.
    $shim = '<script id="pg-proto-shim">(function () {'
        . 'var jar = ' . json_encode((object) $jar, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ';'
        . 'try { void document.cookie; } catch (e) {'
        . 'try { Object.defineProperty(document, "cookie", { configurable: true,'
        . 'get: function () { var out = []; for (var k in jar) { if (Object.prototype.hasOwnProperty.call(jar, k)) out.push(k + "=" + jar[k]); } return out.join("; "); },'
        . 'set: function (v) {'
        . 'v = String(v); var pair = v.split(";")[0], at = pair.indexOf("=");'
        . 'if (at < 1) return;'
        . 'var name = pair.slice(0, at).trim(), age = /;\s*max-age=(-?\d+)/i.exec(v), exp = /;\s*expires=([^;]+)/i.exec(v);'
        . 'if ((age && +age[1] <= 0) || (exp && Date.parse(exp[1]) < Date.now())) { delete jar[name]; } else { jar[name] = pair.slice(at + 1).trim(); }'
        . '} }); } catch (e2) {}'
        . '}'
        . 'function memory() { var d = {}; return {'
        . 'getItem: function (k) { k = String(k); return Object.prototype.hasOwnProperty.call(d, k) ? d[k] : null; },'
        . 'setItem: function (k, v) { d[String(k)] = String(v); },'
        . 'removeItem: function (k) { delete d[String(k)]; },'
        . 'clear: function () { d = {}; },'
        . 'key: function (i) { var keys = Object.keys(d); return (i >= 0 && i < keys.length) ? keys[i] : null; },'
        . 'get length() { return Object.keys(d).length; }'
        . '}; }'
        . '["localStorage", "sessionStorage"].forEach(function (name) {'
        . 'try { void window[name]; } catch (e) { try { Object.defineProperty(window, name, { configurable: true, value: memory() }); } catch (e2) {} }'
        . '});'
        . '})();</script>';

    $head = '<meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">'
        . $shim
        . '<style id="pg-proto-style">'
        // The cookie notice is answered with no (prototype.php) and not
        // shown: nothing is kept between pages, so it would open on every
        // one, and an answer given there would start the tags it holds.
        . '#pg-cc{display:none!important}'
        . 'a[data-pg-proto="broken"],a[data-pg-proto="draft"]{cursor:not-allowed;text-decoration-line:underline;text-decoration-style:wavy;text-decoration-color:#dc3545}'
        . '.pg-proto-hotspots a[data-pg-proto="prototype"],.pg-proto-hotspots a[data-pg-proto="anchor"]{outline:2px solid rgba(13,110,253,.9)!important;outline-offset:2px;background-color:rgba(13,110,253,.12)!important}'
        . '.pg-proto-hotspots a[data-pg-proto="site"],.pg-proto-hotspots a[data-pg-proto="external"],.pg-proto-hotspots a[data-pg-proto="contact"]{outline:2px dashed rgba(25,135,84,.9)!important;outline-offset:2px}'
        . '.pg-proto-hotspots a[data-pg-proto="broken"],.pg-proto-hotspots a[data-pg-proto="draft"]{outline:2px dashed rgba(220,53,69,.95)!important;outline-offset:2px}'
        . '.pg-proto-toast{position:fixed;left:50%;bottom:16px;z-index:2147483647;max-width:calc(100% - 32px);padding:8px 14px;border-radius:8px;background:rgba(33,37,41,.95);color:#fff;font:14px/1.4 system-ui,-apple-system,"Segoe UI",sans-serif;opacity:0;pointer-events:none;transform:translate(-50%,12px);transition:opacity .2s,transform .2s}'
        . '.pg-proto-toast-on{opacity:1;transform:translate(-50%,0)}'
        . '@media (prefers-reduced-motion:reduce){.pg-proto-toast{transition:none}}'
        . '</style>';

    $script = '<script id="pg-proto-frame">(function () {'
        . 'var cfg = ' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ';'
        . 'var root = document.documentElement, toastEl = null, toastTimer = 0;'
        . 'function toast(text) {'
        . 'if (!text || !document.body) return;'
        . 'if (!toastEl) { toastEl = document.createElement("div"); toastEl.className = "pg-proto-toast"; toastEl.setAttribute("role", "status"); document.body.appendChild(toastEl); }'
        . 'toastEl.textContent = text; toastEl.classList.add("pg-proto-toast-on");'
        . 'clearTimeout(toastTimer); toastTimer = setTimeout(function () { toastEl.classList.remove("pg-proto-toast-on"); }, 2600);'
        . '}'
        . 'window.addEventListener("message", function (e) {'
        . 'if (e.source !== window.parent || !e.data || e.data.pgPrototype !== "hotspots") return;'
        . 'root.classList.toggle("pg-proto-hotspots", !!e.data.on);'
        . '});'
        // The sandbox refuses a submission before any submit event fires, so
        // the note goes with the click or the Enter key that would send it.
        . 'function sends(el) {'
        . 'var b = (el && el.closest) ? el.closest("button, input") : null;'
        . 'if (!b || !b.form) return false;'
        . 'var type = (b.getAttribute("type") || (b.tagName === "BUTTON" ? "submit" : "text")).toLowerCase();'
        . 'return type === "submit" || type === "image";'
        . '}'
        . 'document.addEventListener("keydown", function (e) {'
        . 'var t = e.target;'
        . 'if (e.key !== "Enter" || !t || t.tagName !== "INPUT" || !t.form) return;'
        . 'if (/^(button|checkbox|radio|file|reset|submit|image)$/i.test(t.type || "")) return;'
        . 'e.preventDefault(); toast(cfg.forms);'
        . '}, true);'
        // A plain click on a link to another view of the prototype is handed
        // to the viewer, which opens it on its own address: a share link's
        // viewer holds the token the frame's links never carry (they name the
        // link by its id), so the way on does not end with the session that
        // opened the link. Only the page number and the view's item go. A
        // click asking for a new tab, or a page outside a viewer, keeps the
        // link's own address.
        . 'function go(e, a) {'
        . 'if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || window.parent === window) return false;'
        . 'var q;'
        . 'try { q = new URL(a.getAttribute("href") || "", document.baseURI).searchParams; } catch (err) { return false; }'
        . 'var page = q.get("page") || "";'
        . 'if (!/^[0-9]+$/.test(page)) return false;'
        . 'window.parent.postMessage({ pgPrototype: "go", page: page, sub: q.get("sub") || "", r: q.get("r") || "", anchor: q.get("anchor") || "" }, "*");'
        . 'return true;'
        . '}'
        . 'document.addEventListener("click", function (e) {'
        . 'if (sends(e.target)) { e.preventDefault(); e.stopImmediatePropagation(); toast(cfg.forms); return; }'
        . 'var a = (e.target && e.target.closest) ? e.target.closest("a") : null;'
        . 'if (!a) return;'
        . 'var kind = a.getAttribute("data-pg-proto");'
        . 'if (kind === "prototype" && go(e, a)) { e.preventDefault(); return; }'
        . 'if (kind === "broken" || kind === "draft") { e.preventDefault(); e.stopImmediatePropagation(); toast(cfg[kind]); return; }'
        . 'if (kind || !a.hasAttribute("href")) return;'
        . 'var href = a.getAttribute("href") || "";'
        . 'if (href.charAt(0) === "#" || /^javascript:/i.test(href)) return;'
        . 'a.setAttribute("target", "_blank"); a.setAttribute("rel", "noopener noreferrer");'
        . '}, true);'
        . 'document.addEventListener("submit", function (e) { e.preventDefault(); e.stopImmediatePropagation(); toast(cfg.forms); }, true);'
        . 'try { window.parent.postMessage({ pgPrototype: "ready" }, "*"); } catch (err) {}'
        . '})();</script>';

    if (preg_match('~<head\b[^>]*>~i', $html, $match, PREG_OFFSET_CAPTURE)) {
        $at = $match[0][1] + strlen($match[0][0]);
        $html = substr($html, 0, $at) . $head . substr($html, $at);
    } else {
        $html = $head . $html;
    }

    $body_end = strripos($html, '</body>');

    if ($body_end !== false) {
        return substr($html, 0, $body_end) . $script . substr($html, $body_end);
    }

    return $html . $script;
}

/**
 * The viewer's page: the bar and the frame the draft is drawn in.
 *
 * The frame's sandbox gives the page its scripts, new tabs and a click that
 * moves the viewer to another page, and nothing else: no form is sent, and
 * without allow-same-origin the page has its own opaque origin, so neither
 * its scripts nor its requests carry the viewer's session cookie or reach
 * the site's storage.
 *
 * @param array $view design, pages (pg_proto_page_set()), current, fallback,
 *                    fallback_text (optional), frame_url, viewer, close_url
 *                    ('' for none), software, lang, error
 * @return string
 */
function pg_proto_shell_html($view)
{
    $pages = isset($view['pages']) ? (array) $view['pages'] : array();
    $current = isset($view['current']) ? (int) $view['current'] : 0;
    $viewer = isset($view['viewer']) ? (string) $view['viewer'] : '';
    $software = isset($view['software']) ? (string) $view['software'] : '';
    $design = isset($view['design']) ? (string) $view['design'] : '';
    $frame_url = isset($view['frame_url']) ? (string) $view['frame_url'] : '';
    $error = isset($view['error']) ? (string) $view['error'] : '';
    // A share link's viewer has no editor to go back to: no close button.
    $close_url = isset($view['close_url']) ? (string) $view['close_url'] : '';
    $version = function ($path) {
        return (string) @filemtime(PG_FUNCTIONS_DIR . $path);
    };

    $index = -1;
    $options = '';

    foreach ($pages as $position => $page) {
        if ($page['id'] === $current) {
            $index = $position;
        }

        $label = ($page['title'] !== '') ? $page['title'] : $page['name'];

        if (!empty($page['scheduled'])) {
            $label .= ' (' . lang('scheduled to go live') . ')';
        }

        $options .= '<option value="' . h(pg_proto_viewer_url($viewer, $page['id'])) . '"' . (($page['id'] === $current) ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    $step = function ($position, $icon, $label) use ($pages, $viewer) {
        if (($position < 0) || !isset($pages[$position])) {
            return '<span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true" title="' . h($label) . '"><span class="bi ' . $icon . '" aria-hidden="true"></span><span class="visually-hidden">' . h($label) . '</span></span>';
        }

        return '<a class="btn btn-sm btn-outline-secondary" href="' . h(pg_proto_viewer_url($viewer, $pages[$position]['id'])) . '" title="' . h($label) . '"><span class="bi ' . $icon . '" aria-hidden="true"></span><span class="visually-hidden">' . h($label) . '</span></a>';
    };

    $nav = '';

    if ($pages) {
        $nav = '<div class="pg-proto-nav">'
            . $step(($index >= 0) ? $index - 1 : -1, 'bi-chevron-left', lang('Previous page'))
            . '<select class="form-select form-select-sm" id="pg-proto-pages" aria-label="' . h(lang('Pages')) . '">' . $options . '</select>'
            . $step(($index >= 0) ? $index + 1 : -1, 'bi-chevron-right', lang('Next page'))
            . '<span class="pg-proto-count text-body-secondary small">' . (($index >= 0) ? ($index + 1) : 0) . ' / ' . count($pages) . '</span>'
            . '</div>';
    }

    $notice = '';

    if (!empty($view['fallback'])) {
        $notice = '<div class="pg-proto-notice alert alert-warning rounded-0 border-0 border-bottom small py-1 px-3 mb-0" role="status">'
            . h(isset($view['fallback_text']) ? (string) $view['fallback_text'] : lang('The page you opened is not a saved draft of this design, so the prototype starts at its first draft.')) . '</div>';
    }

    if ($error !== '') {
        $main = '<div class="pg-proto-empty"><p class="mb-0">' . h($error) . '</p></div>';
    } elseif (($frame_url === '') || !$pages) {
        $main = '<div class="pg-proto-empty"><p class="fw-semibold mb-1">' . h(lang('This design has no draft pages.')) . '</p>'
            . '<p class="text-body-secondary small mb-0">' . h(lang('Save a page as a draft in the Visual Page Editor to play it here as a prototype.')) . '</p></div>';
    } else {
        $main = '<iframe id="pg-proto-frame" class="pg-proto-frame" src="' . h($frame_url) . '"'
            . ' title="' . h(lang('Prototype')) . '"'
            . ' sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation"'
            . ' referrerpolicy="no-referrer"></iframe>';
    }

    $title = lang('Prototype') . (($design !== '') ? ' · ' . $design : '');

    return '<!DOCTYPE html>
<html lang="' . h(isset($view['lang']) ? (string) $view['lang'] : 'en') . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>' . h($title) . '</title>
<link rel="stylesheet" href="' . h($software) . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css?v=' . $version('/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css') . '">
<link rel="stylesheet" href="' . h($software) . '/assets/fonts/bootstrap-icons/bootstrap-icons.min.css?v=' . $version('/assets/fonts/bootstrap-icons/bootstrap-icons.min.css') . '">
<style>
html, body { height: 100%; }
body.pg-proto { display: flex; flex-direction: column; margin: 0; overflow: hidden; background: var(--bs-tertiary-bg); }
.pg-proto-bar { display: flex; align-items: center; gap: .5rem 1rem; flex-wrap: wrap; padding: .375rem 1rem; background: var(--bs-body-bg); border-bottom: 1px solid var(--bs-border-color); }
.pg-proto-title { display: flex; align-items: center; gap: .5rem; min-width: 0; flex: 1 1 12rem; }
.pg-proto-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pg-proto-nav { display: flex; align-items: center; gap: .375rem; flex: 0 1 auto; min-width: 0; }
.pg-proto-nav .form-select { width: auto; max-width: 16rem; min-width: 8rem; }
.pg-proto-count { white-space: nowrap; }
.pg-proto-tools { display: flex; align-items: center; gap: .375rem; margin-left: auto; }
.pg-proto-frame { flex: 1 1 auto; width: 100%; border: 0; background: #fff; }
.pg-proto-empty { flex: 1 1 auto; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem 1rem; text-align: center; }
@media (max-width: 575.98px) {
  .pg-proto-bar { padding: .375rem .75rem; }
  .pg-proto-tools .pg-proto-label { display: none; }
  .pg-proto-nav { order: 3; flex: 1 1 100%; }
  .pg-proto-nav .form-select { flex: 1 1 auto; max-width: none; }
}
</style>
</head>
<body class="pg-proto">
<nav class="pg-proto-bar" aria-label="' . h(lang('Prototype')) . '">
<div class="pg-proto-title"><span class="badge text-bg-primary">' . h(lang('Prototype')) . '</span><span class="pg-proto-name" title="' . h($design) . '">' . h($design) . '</span></div>
' . $nav . '
<div class="pg-proto-tools">
<button type="button" class="btn btn-sm btn-outline-primary" id="pg-proto-hotspots" aria-pressed="false" title="' . h(lang('Outline the links you can click')) . '"' . (($frame_url === '' || $error !== '') ? ' disabled' : '') . '><span class="bi bi-hand-index" aria-hidden="true"></span> <span class="pg-proto-label">' . h(lang('Hotspots')) . '</span></button>
' . (($close_url !== '') ? '<a class="btn btn-sm btn-outline-secondary" id="pg-proto-close" href="' . h($close_url) . '" title="' . h(lang('Close')) . '"><span class="bi bi-x-lg" aria-hidden="true"></span><span class="visually-hidden">' . h(lang('Close')) . '</span></a>' : '') . '
</div>
</nav>
' . $notice . '
' . $main . '
<script>
(function () {
    var frame = document.getElementById("pg-proto-frame");
    var button = document.getElementById("pg-proto-hotspots");
    var pages = document.getElementById("pg-proto-pages");
    var close = document.getElementById("pg-proto-close");
    var viewer = ' . json_encode($viewer, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) . ';
    var KEY = "pg-proto-hotspots";
    var on = false;
    try { on = window.sessionStorage.getItem(KEY) === "1"; } catch (e) {}
    function send() {
        // The frame has an opaque origin, so no narrower target fits; the
        // message carries nothing but the switch.
        if (frame && frame.contentWindow) frame.contentWindow.postMessage({ pgPrototype: "hotspots", on: on }, "*");
    }
    function paint() {
        if (!button) return;
        button.setAttribute("aria-pressed", on ? "true" : "false");
        button.classList.toggle("active", on);
    }
    if (button) button.addEventListener("click", function () {
        on = !on;
        try { window.sessionStorage.setItem(KEY, on ? "1" : "0"); } catch (e) {}
        paint();
        send();
    });
    // A click on a link to another view, handed over by the frame
    // (pg_proto_frame_inject()): a page number and the item of the view,
    // never an address. The way on is built on the address of this viewer,
    // which for a share link holds its token.
    function go(d) {
        var page = String(d.page || "");
        if (!/^[0-9]+$/.test(page)) return;
        var url = viewer + (viewer.indexOf("?") === -1 ? "?" : "&") + "page=" + page;
        ["sub", "r", "anchor"].forEach(function (k) {
            if (typeof d[k] === "string" && d[k] !== "") url += "&" + k + "=" + encodeURIComponent(d[k]);
        });
        window.location.href = url;
    }
    window.addEventListener("message", function (e) {
        if (!frame || e.source !== frame.contentWindow || !e.data) return;
        if (e.data.pgPrototype === "ready") send();
        else if (e.data.pgPrototype === "go") go(e.data);
    });
    if (frame) frame.addEventListener("load", send);
    if (pages) pages.addEventListener("change", function () { if (pages.value) window.location.href = pages.value; });
    if (close) close.addEventListener("click", function (e) {
        // A window the editor opened closes; one opened any other way goes
        // back to the editor.
        e.preventDefault();
        var back = close.getAttribute("href");
        try { window.close(); } catch (err) {}
        setTimeout(function () { if (!window.closed && back) window.location.href = back; }, 150);
    });
    paint();
})();
</script>
</body>
</html>';
}

/**
 * The prototype of a design: its draft pages (pg_proto_page_set()) read from
 * the database, with the editor's tab order.
 *
 * @param int $style_id
 * @return array[]
 */
function pg_proto_design_pages($style_id)
{
    $style_id = (int) $style_id;
    $draft_folder = pg_page_draft_folder_id(false);

    if (($style_id <= 0) || ($draft_folder <= 0)) {
        return array();
    }

    $schedule = function_exists('pg_page_schedule_ready') && pg_page_schedule_ready();

    $rows = db_items(
        "SELECT page_id, page_name, page_title, page_folder" . ($schedule ? ", publish_at" : "") . "
        FROM page
        WHERE page_style = '" . $style_id . "'
            AND layout_type = 'system'
            AND page_folder = '" . (int) $draft_folder . "'" . pg_designer_not_binned_sql('page_folder') . "
        ORDER BY page_id");

    $layout = pg_style_tab_layout_ready() ? (string) db_value("SELECT style_tab_layout FROM style WHERE style_id = '" . $style_id . "'") : '';

    return pg_proto_page_set($rows, $draft_folder, pg_recycle_bin_folder_ids(), $layout);
}

/**
 * A frame address that does not carry this site's signature was not made by
 * the viewer: the refusal is written to the firewall log (the event and the
 * fail2ban line) and counted toward a ban like a wrong guest token, so a
 * source guessing addresses ends up banned by the normal path. Monitor mode
 * counts it without blocking.
 *
 * @return void
 */
function pg_proto_frame_offence()
{
    if (!function_exists('waf_client_ip') || !isset(db::$con) || !db::$con) {
        return;
    }

    $blocking = (!function_exists('waf_mode') || (waf_mode() === 'block'));

    if (function_exists('waf_log_event')) {
        waf_log_event($blocking ? 'block' : 'would-block', 'prototype-frame', 'protocol', 5, 'prototype.php', 'frame address not signed by this site');
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', waf_client_ip(), array('status' => 403, 'rule' => 'prototype-frame'));
    }

    if (function_exists('waf_register_offence')) {
        waf_register_offence(waf_client_ip(), 5, $blocking);
    }
}

/**
 * The page of a short note: inside the frame, and on its own for a share
 * link that opens nothing, which is mostly read on a phone (a link sent on
 * in a messenger): it says its language and fits the screen, as the viewer
 * and the password page do.
 *
 * @param string $message
 * @param string $lang the page's language ('' for en)
 * @return string
 */
function pg_proto_note_html($message, $lang)
{
    $lang = ((string) $lang !== '') ? (string) $lang : 'en';

    return '<!DOCTYPE html><html lang="' . h($lang) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer"><title>' . h(lang('Prototype')) . '</title></head>'
        . '<body style="margin:0;box-sizing:border-box;display:flex;min-height:100vh;align-items:center;justify-content:center;font:15px/1.5 system-ui,-apple-system,\'Segoe UI\',sans-serif;color:#495057;background:#f8f9fa;text-align:center;padding:1rem">'
        . '<p>' . h($message) . '</p></body></html>';
}

/**
 * Whether a frame whose page could not be drawn names the reason. A
 * person's frame does: only a signed-in administrator, designer or manager
 * opens it. A share link's frame does not: anyone holding the link opens it
 * signed out, and a PHP error's message can name a server file and line
 * ("... called in /home/site/... on line 812"); the reason then goes to the
 * error log only. A page that came out empty has no reason to hide.
 *
 * @param bool   $link_mode whether the frame was made for a share link
 * @param string $error     the error's message, '' for an empty page
 * @return bool
 */
function pg_proto_frame_names_failure($link_mode, $error)
{
    return !$link_mode || ((string) $error === '');
}

/**
 * The answer when no page is drawn: a short note (pg_proto_note_html()),
 * with the status it carries.
 *
 * @param string $message
 * @param int    $status
 * @return void
 */
function pg_proto_frame_refuse($message, $status)
{
    http_response_code((int) $status);
    header('Content-Type: text/html; charset=utf-8');
    header_remove('Set-Cookie');

    echo pg_proto_note_html($message, lang(array('info' => '')));
    exit;
}
