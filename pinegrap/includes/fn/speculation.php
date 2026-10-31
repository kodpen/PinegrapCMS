<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Speculation Rules prefetch of menu links: a published page tells Chromium
 * browsers the same-site page addresses of its menus and navbars in one
 * <script type="speculationrules"> block (a "list" source, eagerness
 * "moderate"), so the page a visitor points at or presses is fetched a moment
 * before it is opened.
 *
 * The menus note their addresses while they are drawn (get_menu_content(),
 * the Menu widget); the navbar links of the visual designer are read from
 * the finished HTML (class "nav-link"). get_page_content() splices the block
 * before </head>. Left out: addresses with a query string, off-site and
 * relative links, files and the software directory (the panel, downloads,
 * sign-out), and every page that is not a public content page (sign-in, cart,
 * checkout, account pages: pg_speculation_page_type_kept()), a page of the
 * visual designer included when a system widget in its tree does such a
 * page's work (pg_speculation_widget_kept()). On a page drawn
 * in another language the addresses get its prefix by the translation
 * module's own rule (pg_tr_prefix_path()), as its links get it on the way
 * out. No block while the Settings > Features switch is off
 * (SPECULATION_PREFETCH), for a visitor who is signed in, in edit mode or in
 * an e-mail. A followed prefetch makes no second request, so the prefetch is
 * counted once as the page view, as any request is.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** At most this many addresses go into one block. */
define('PG_SPECULATION_MAX_URLS', 20);

/**
 * The addresses noted by the menus drawn since the last take.
 *
 * @return array
 */
function &_pg_speculation_noted()
{
    static $noted = array();

    return $noted;
}

/**
 * Note link addresses a menu drew (raw, not HTML-escaped).
 *
 * @param array $hrefs
 */
function pg_speculation_note(array $hrefs)
{
    $noted = &_pg_speculation_noted();

    foreach ($hrefs as $href) {
        if (is_string($href) && $href !== '') {
            $noted[] = $href;
        }
    }
}

/**
 * The noted addresses, forgotten as they are handed over.
 *
 * @return array
 */
function pg_speculation_take()
{
    $noted = &_pg_speculation_noted();
    $out = $noted;
    $noted = array();

    return $out;
}

/**
 * The href values of the navigation links in an HTML document: <a> tags with
 * the class "nav-link" that toggle nothing but a dropdown.
 *
 * @param string $html
 * @return array
 */
function pg_speculation_nav_hrefs($html)
{
    $out = array();

    if (stripos((string) $html, 'nav-link') === false) {
        return $out;
    }

    if (!preg_match_all('~<a\s([^>]*)>~i', (string) $html, $tags)) {
        return $out;
    }

    foreach ($tags[1] as $attrs) {
        if (!preg_match('~\sclass\s*=\s*(["\'])([^"\']*)\1~i', ' ' . $attrs, $class)
            || !preg_match('~(^|\s)nav-link(\s|$)~', $class[2])) {
            continue;
        }

        if (preg_match('~\sdata-bs-toggle\s*=~i', ' ' . $attrs)) {
            continue;
        }

        if (preg_match('~\shref\s*=\s*(?|"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))~i', ' ' . $attrs, $href)) {
            $out[] = html_entity_decode($href[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    return $out;
}

/**
 * A link address as a same-site page path the block may list, or '' when it
 * may not: an anchor, another scheme, another host, a relative path, a query
 * string, an address outside the site's path, the software directory (panel,
 * file downloads, sign-out) or a file with an extension. On a page in another
 * language a link that already carries that language's prefix is taken
 * without it, so the page is found by its name and prefixed once later.
 *
 * @param string $href
 * @param array  $hosts         host names of this site, lower case
 * @param string $base          the site's path (PATH), ending in /
 * @param string $software_dir  the software directory's name
 * @param string $prefix        the language prefix of the page shown, '' in
 *                              the site's own language
 * @return string  the path, without its fragment
 */
function pg_speculation_path($href, array $hosts, $base, $software_dir, $prefix = '')
{
    $href = trim((string) $href);

    if ($href === '' || $href[0] === '#' || strpos($href, '?') !== false || preg_match('~[\s\\\\<>"\']~', $href)) {
        return '';
    }

    if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $href) && !preg_match('~^https?://~i', $href)) {
        return '';
    }

    $parts = parse_url($href);

    if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
        return '';
    }

    if (isset($parts['host'])) {
        if (!in_array(strtolower($parts['host']), $hosts, true)) {
            return '';
        }
    } elseif (isset($parts['scheme'])) {
        return '';
    }

    $path = isset($parts['path']) ? (string) $parts['path'] : '';

    if ($path === '' && isset($parts['host'])) {
        $path = '/';
    }

    $base = (string) $base;

    if ($path === '' || $path[0] !== '/' || strpos($path, $base) !== 0) {
        return '';
    }

    $rest = (string) substr($path, strlen($base));
    $segments = explode('/', $rest);

    if ($prefix !== '' && strtolower($segments[0]) === strtolower((string) $prefix)) {
        array_shift($segments);
        $rest = implode('/', $segments);
        $path = $base . $rest;

        if (!$segments) {
            $segments = array('');
        }
    }

    if ($software_dir !== '' && strtolower($segments[0]) === strtolower((string) $software_dir)) {
        return '';
    }

    foreach ($segments as $segment) {
        if ($segment === '.' || $segment === '..') {
            return '';
        }
    }

    if (preg_match('~\.[a-z0-9]{1,5}$~i', $rest) && !preg_match('~\.html?$~i', $rest)) {
        return '';
    }

    return $path;
}

/**
 * Whether a page of this type may be prefetched: public content pages only,
 * never one that signs in or out, holds a cart or an order, or shows an
 * account. A type added later is left out until it is named here.
 *
 * @param string $page_type
 * @return bool
 */
function pg_speculation_page_type_kept($page_type)
{
    return in_array((string) $page_type, array(
        'standard',
        'folder view',
        'photo gallery',
        'catalog',
        'catalog detail',
        'calendar view',
        'calendar event view',
        'form list view',
        'form item view',
        'form view directory',
        'custom form',
    ), true);
}

/**
 * Whether a page whose tree holds a system widget of this kind may be
 * prefetched. A page of the visual designer is page type 'standard' and a
 * system widget does the work, so the widget standing in for a page type
 * pg_speculation_page_type_kept() leaves out leaves the page out too, as do
 * every account widget and the favourites list. The login region and the
 * cart link sit in the header of every page and keep it.
 *
 * @param string $region_type        the widget's regionType
 * @param array  $page_type_widgets  page type => widget kind (pg_sw_page_type_widgets())
 * @return bool
 */
function pg_speculation_widget_kept($region_type, array $page_type_widgets)
{
    $region_type = (string) $region_type;

    if (strpos($region_type, 'account_') === 0 || $region_type === 'wishlist') {
        return false;
    }

    foreach ($page_type_widgets as $page_type => $widget_type) {
        if ((string) $widget_type === $region_type && !pg_speculation_page_type_kept($page_type)) {
            return false;
        }
    }

    return true;
}

/**
 * The widget kinds pg_speculation_widget_kept() leaves out, for the query
 * that finds them; the account_ kinds are found by their prefix.
 *
 * @param array $page_type_widgets  page type => widget kind
 * @return array
 */
function pg_speculation_acting_widget_types(array $page_type_widgets)
{
    $out = array('wishlist');

    foreach ($page_type_widgets as $widget_type) {
        if (!pg_speculation_widget_kept($widget_type, $page_type_widgets)) {
            $out[] = (string) $widget_type;
        }
    }

    return array_values(array_unique($out));
}

/**
 * An SQL condition true when a tree column holds a shared_ref to one of the
 * ids: the exact "sharedId" value, closed by a comma or a brace, as the tree
 * JSON is written. '0' for no ids.
 *
 * @param string $tree_expr  the SQL expression of the tree JSON
 * @param array  $ids        shared component ids
 * @return string
 */
function pg_speculation_tree_sql($tree_expr, array $ids)
{
    $likes = array();

    foreach (array_unique(array_map('intval', $ids)) as $id) {
        if ($id > 0) {
            $likes[] = $tree_expr . " LIKE '%\"sharedId\":" . $id . ",%'";
            $likes[] = $tree_expr . " LIKE '%\"sharedId\":" . $id . "}%'";
        }
    }

    return $likes ? '(' . implode(' OR ', $likes) . ')' : '0';
}

/**
 * Ids of the shared component rows that are system widgets
 * pg_speculation_widget_kept() leaves out. One query per request.
 *
 * @return array
 */
function pg_speculation_acting_widget_ids()
{
    $map = pg_sw_page_type_widgets();
    $likes = array("system_region_config LIKE '%\"regionType\":\"account\\_%'");

    foreach (pg_speculation_acting_widget_types($map) as $type) {
        $likes[] = "system_region_config LIKE '%\"regionType\":\"" . e($type) . "\"%'";
    }

    $rows = db_items(
        "SELECT id, system_region_config FROM shared_components
        WHERE " . implode(' OR ', $likes));
    $ids = array();

    foreach ((array) $rows as $row) {
        $cfg = json_decode((string) $row['system_region_config'], true);

        if (is_array($cfg) && isset($cfg['regionType']) && !pg_speculation_widget_kept($cfg['regionType'], $map)) {
            $ids[] = (int) $row['id'];
        }
    }

    return $ids;
}

/**
 * The block for a list of addresses: valid JSON whose <, >, & and quotes are
 * written as \u escapes, so no address can close the script element.
 *
 * @param array $urls
 * @return string  '' for an empty list
 */
function pg_speculation_rules_html(array $urls)
{
    $urls = array_values(array_unique(array_map('strval', $urls)));

    if (!$urls) {
        return '';
    }

    $json = json_encode(array(
        'prefetch' => array(array(
            'source'    => 'list',
            'urls'      => $urls,
            'eagerness' => 'moderate',
        )),
    ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    if (!is_string($json)) {
        return '';
    }

    return '<script type="speculationrules">' . $json . '</script>';
}

/**
 * Which candidate paths go into the block: those of an existing page whose
 * type is kept, whose tree holds no widget doing a left-out page's work and
 * whose folder is public, without the page shown, in the order noted, at
 * most PG_SPECULATION_MAX_URLS.
 *
 * @param array  $paths    same-site paths (pg_speculation_path())
 * @param string $base     the site's path
 * @param array  $pages    lower-case page name => array('type', 'public', 'home',
 *                         'acting'), '' for the home page at the site's path
 * @param string $current       the page shown, lower case
 * @param bool   $current_home  whether the page shown is the home page
 * @return array
 */
function pg_speculation_pick(array $paths, $base, array $pages, $current, $current_home)
{
    $out = array();

    foreach ($paths as $path) {
        $name = mb_strtolower(rawurldecode((string) substr($path, strlen((string) $base))));

        if (!isset($pages[$name]) || isset($out[$path])) {
            continue;
        }

        $page = $pages[$name];

        if (empty($page['public']) || !empty($page['acting']) || !pg_speculation_page_type_kept($page['type'])) {
            continue;
        }

        if ($name === (string) $current || (!empty($page['home']) && $current_home)) {
            continue;
        }

        $out[$path] = true;

        if (count($out) >= PG_SPECULATION_MAX_URLS) {
            break;
        }
    }

    return array_keys($out);
}

/**
 * The picked paths as the page in another language links them: each passed
 * through the language prefix rule (pg_tr_prefix_path()) decoded and written
 * back encoded. Unchanged in the site's own language.
 *
 * @param array    $paths        same-site page paths, encoded
 * @param string   $prefix       the language prefix, '' for the own language
 * @param callable $prefix_path  decoded path, prefix => decoded path
 * @return array
 */
function pg_speculation_localize(array $paths, $prefix, $prefix_path)
{
    if ((string) $prefix === '') {
        return $paths;
    }

    $out = array();

    foreach ($paths as $path) {
        $decoded = rawurldecode((string) $path);
        $new = call_user_func($prefix_path, $decoded, (string) $prefix);
        $out[] = ($new === $decoded) ? $path : pg_tr_encode_path($new);
    }

    return array_values(array_unique($out));
}

/**
 * Whether a page gets the block at all: the Features switch on and nobody
 * signed in (a member's or staff's pages are personal and their menus may
 * hold acting links).
 *
 * @param bool $enabled    the Settings > Features switch
 * @param bool $signed_in  a member or staff is signed in (or unknown)
 * @return bool
 */
function pg_speculation_rules_wanted($enabled, $signed_in)
{
    return $enabled && !$signed_in;
}

/**
 * Splice the block into a finished page before </head>.
 *
 * @param string $content    the page's HTML
 * @param string $page_name  the page shown
 * @param bool   $home       whether the page shown is the home page
 * @return string
 */
function pg_speculation_rules_inject($content, $page_name, $home)
{
    $noted = pg_speculation_take();

    if (!pg_speculation_rules_wanted(
            defined('SPECULATION_PREFETCH') && SPECULATION_PREFETCH,
            !defined('USER_LOGGED_IN') || USER_LOGGED_IN)
        || stripos($content, '</head>') === false
        || stripos($content, 'speculationrules') !== false) {
        return $content;
    }

    $hosts = array();

    foreach (array(defined('HOSTNAME') ? HOSTNAME : '', defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '') as $host) {
        $host = strtolower(preg_replace('~:\d+$~', '', trim((string) $host)));

        if ($host !== '') {
            $hosts[] = $host;
        }
    }

    $base = defined('PATH') ? (string) PATH : '/';
    $software_dir = defined('SOFTWARE_DIRECTORY') ? (string) SOFTWARE_DIRECTORY : '';

    // The language whose prefix pg_tr_finalize() adds to the page's links.
    $prefix = '';

    if (function_exists('pg_tr_active') && pg_tr_active()) {
        pg_tr_load();
        $prefix = pg_tr_prefix(pg_tr_language());
    }

    $paths = array();
    $names = array();

    foreach (array_merge($noted, pg_speculation_nav_hrefs($content)) as $href) {
        $path = pg_speculation_path($href, $hosts, $base, $software_dir, $prefix);

        if ($path !== '' && !isset($paths[$path])) {
            $paths[$path] = true;
            $names[mb_strtolower(rawurldecode((string) substr($path, strlen($base))))] = true;
        }

        if (count($paths) >= 100) {
            break;
        }
    }

    if (!$paths) {
        return $content;
    }

    $pages = array();
    $sql_names = array();

    foreach (array_keys($names) as $name) {
        if ($name !== '') {
            $sql_names[] = "'" . e($name) . "'";
        }
    }

    // A page of the visual designer whose tree places a system widget that
    // signs in or out, registers, sets a password, runs the cart or checkout,
    // shows an account or search results. Before the multi-page editor no
    // page can carry a widget.
    $acting_sql = '0';
    $style_join = '';

    if (function_exists('pg_sw_page_type_widgets') && function_exists('pg_multi_page_design_ready') && pg_multi_page_design_ready()) {
        $acting_sql = pg_speculation_tree_sql(pg_page_tree_sql_expr(), pg_speculation_acting_widget_ids());

        if ($acting_sql !== '0') {
            $style_join = 'LEFT JOIN style ON page.page_style = style.style_id';
        }
    }

    $rows = db_items(
        "SELECT page.page_name, page.page_type, page.page_folder, page.page_home,
            COALESCE(" . $acting_sql . ", 0) AS acting
        FROM page
        " . $style_join . "
        WHERE " . ($sql_names ? "page.page_name IN (" . implode(',', $sql_names) . ") OR " : '') . "page.page_home = 'yes'");

    foreach ((array) $rows as $row) {
        $page = array(
            'type'   => (string) $row['page_type'],
            'public' => (get_access_control_type($row['page_folder']) === 'public'),
            'home'   => ($row['page_home'] === 'yes'),
            'acting' => !empty($row['acting']),
        );

        $pages[mb_strtolower((string) $row['page_name'])] = $page;

        if ($page['home']) {
            $pages[''] = $page;
        }
    }

    $block = pg_speculation_rules_html(pg_speculation_localize(
        pg_speculation_pick(array_keys($paths), $base, $pages, mb_strtolower((string) $page_name), (bool) $home),
        $prefix,
        'pg_tr_prefix_path'));

    if ($block === '') {
        return $content;
    }

    return preg_replace('~</head>~i', pg_regex_replacement('        ' . $block . "\n") . '</head>', $content, 1);
}
