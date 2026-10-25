<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Link resolution: what one href on a page leads to, answered in the order
 * router.php dispatches an address, with no HTTP.
 *
 * The resolution is pure: pg_link_parse() reads the href against the site's
 * host, path and software directory, pg_link_resolve_item() looks the item
 * name up in name maps handed in, pg_link_resolve() does both. The maps are
 * loaded separately: pg_seo_link_maps() reads every name once for the SEO
 * pass, which resolves every link of every page; pg_link_maps_for_items()
 * reads only the names a handful of links can reach, for a viewer that
 * resolves the links of one page (prototype.php). The SEO link graph
 * (seo_links.php, pg_seo_resolve_link()) keeps answering type and id exactly
 * as before; the class a result carries says what an "external" answer was
 * (an e-mail address, an anchor, another host...), for callers that treat
 * them apart.
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
 * The site values an href is read against: the host this request names, the
 * installation's path and the software directory. Defaults where a constant
 * is missing (a run from the command line).
 *
 * @return array host, path, software_directory (null when not defined)
 */
function pg_link_resolve_env()
{
    return array(
        'host' => defined('HOSTNAME') ? (string) HOSTNAME : '',
        'path' => defined('PATH') ? (string) PATH : '/',
        'software_directory' => defined('SOFTWARE_DIRECTORY') ? (string) SOFTWARE_DIRECTORY : null,
    );
}

/**
 * Reads one href as far as it can be read without any name: an answer for
 * what is not an address of this site (or not an address at all), else the
 * item name the router would look up.
 *
 * Classes of an early answer: empty, invalid (types 'unknown'); mailto, tel,
 * sms, script, data, ftp, anchor, host (another host), self (a bare query
 * string, or this host with no path), outside (another application on this
 * host), robots (robots.txt, sitemap.xml), backend (the control panel)
 * (type 'external').
 *
 * @param mixed $href
 * @param array $env pg_link_resolve_env()
 * @return array result (type, id, class) or null, item (string)
 */
function pg_link_parse($href, $env)
{
    $href = trim((string) $href);

    if ($href === '') {
        return array('result' => array('type' => 'unknown', 'id' => 0, 'class' => 'empty'), 'item' => '');
    }

    // Not addresses of anything this software serves.
    if (preg_match('/^(mailto:|tel:|sms:|javascript:|data:|ftp:|#)/i', $href, $scheme)) {
        $classes = array('mailto:' => 'mailto', 'tel:' => 'tel', 'sms:' => 'sms', 'javascript:' => 'script', 'data:' => 'data', 'ftp:' => 'ftp', '#' => 'anchor');

        return array('result' => array('type' => 'external', 'id' => 0, 'class' => $classes[strtolower($scheme[1])]), 'item' => '');
    }

    $parts = parse_url($href);

    if ($parts === false) {
        return array('result' => array('type' => 'unknown', 'id' => 0, 'class' => 'invalid'), 'item' => '');
    }

    // A host that is not ours is somebody else's problem; a link check that
    // followed it would need HTTP, which this does not do.
    if (!empty($parts['host'])) {
        $own_host = preg_replace('/:\d+$/', '', isset($env['host']) ? (string) $env['host'] : '');
        $link_host = preg_replace('/:\d+$/', '', $parts['host']);

        if (($own_host === '') || (strcasecmp($link_host, $own_host) !== 0)) {
            return array('result' => array('type' => 'external', 'id' => 0, 'class' => 'host'), 'item' => '');
        }
    }

    $path = isset($parts['path']) ? $parts['path'] : '';

    // A bare query string or fragment points at the page it sits on.
    if ($path === '') {
        return array('result' => array('type' => 'external', 'id' => 0, 'class' => 'self'), 'item' => '');
    }

    $site_path = isset($env['path']) ? (string) $env['path'] : '/';

    // A relative link is resolved against the site root rather than against
    // the containing page: Pinegrap writes absolute paths everywhere, so a
    // relative one is hand-written and its base is not knowable from here.
    if (strpos($path, '/') !== 0) {
        $path = $site_path . $path;
    }

    if (($site_path === '') || (strpos($path, $site_path) !== 0)) {
        // Outside the installation's own path - another application on the
        // same host.
        return array('result' => array('type' => 'external', 'id' => 0, 'class' => 'outside'), 'item' => '');
    }

    $item_name = ltrim(rawurldecode(substr($path, strlen($site_path))), '/');

    $lowercase = mb_strtolower($item_name);

    if (($lowercase === 'robots.txt') || ($lowercase === 'sitemap.xml')) {
        return array('result' => array('type' => 'external', 'id' => 0, 'class' => 'robots'), 'item' => '');
    }

    // Anything inside the control panel directory is a backend address, not
    // site content.
    if (isset($env['software_directory']) && (strpos($item_name, $env['software_directory'] . '/') === 0)) {
        return array('result' => array('type' => 'external', 'id' => 0, 'class' => 'backend'), 'item' => '');
    }

    return array('result' => null, 'item' => $item_name);
}

/**
 * Looks one item name up in the router's own order: the home page, files,
 * short links, pages, then a pretty address (a page name, a slash, an item
 * that page shows). 'unknown' is the only answer that counts as broken, and
 * it is given reluctantly: a page type whose path segment cannot be checked
 * from the database is reported as reaching that page.
 *
 * @param string         $item_name  pg_link_parse() item
 * @param array          $maps       pages (name => id, type, layout_type,
 *                                   folder), files (name => id),
 *                                   short_links (name => true), products and
 *                                   product_groups (address name => id),
 *                                   home_page_id
 * @param array|callable $form_names address names of submitted forms (name
 *                                   => true), or a function returning them,
 *                                   called only when a form list address is
 *                                   met
 * @return array type, id, class
 */
function pg_link_resolve_item($item_name, $maps, $form_names = array())
{
    $item_name = (string) $item_name;
    $pages = isset($maps['pages']) ? $maps['pages'] : array();

    if (($item_name === '') || ($item_name === 'index.php')) {
        return array('type' => 'page', 'id' => isset($maps['home_page_id']) ? (int) $maps['home_page_id'] : 0, 'class' => 'page');
    }

    if (isset($maps['files'][$item_name])) {
        return array('type' => 'file', 'id' => (int) $maps['files'][$item_name], 'class' => 'file');
    }

    if (isset($maps['short_links'][$item_name])) {
        return array('type' => 'short_link', 'id' => 0, 'class' => 'short_link');
    }

    if (isset($pages[$item_name])) {
        return array('type' => 'page', 'id' => (int) $pages[$item_name]['id'], 'class' => 'page');
    }

    $unknown = array('type' => 'unknown', 'id' => 0, 'class' => 'unknown');

    if (mb_strpos($item_name, '/') === false) {
        return $unknown;
    }

    $prefix = mb_substr($item_name, 0, mb_strpos($item_name, '/'));
    $slug = trim(mb_substr($item_name, mb_strpos($item_name, '/') + 1), '/');

    if (!isset($pages[$prefix])) {
        return $unknown;
    }

    $page = $pages[$prefix];
    $reaches_page = array('type' => 'page', 'id' => (int) $page['id'], 'class' => 'page');

    // A trailing slash with nothing after it is the page itself.
    if ($slug === '') {
        return $reaches_page;
    }

    if (($page['type'] === 'catalog') || ($page['type'] === 'catalog detail') || ($page['layout_type'] === 'system')) {

        if (isset($maps['products'][$slug])) {
            return array('type' => 'product', 'id' => (int) $maps['products'][$slug], 'class' => 'product');
        }

        if (isset($maps['product_groups'][$slug])) {
            return array('type' => 'product_group', 'id' => (int) $maps['product_groups'][$slug], 'class' => 'product_group');
        }

        // A system-layout page can host widgets this resolver does not
        // model, so an unmatched slug there is not proof of a dead link.
        if ($page['layout_type'] === 'system') {
            return $reaches_page;
        }

        return $unknown;
    }

    if ($page['type'] === 'form list view') {
        // A list of names is never taken for a callable: an array of two
        // keys could otherwise read as [class, method].
        if (!is_array($form_names) && is_callable($form_names)) {
            $form_names = call_user_func($form_names);
        }

        return isset($form_names[$slug]) ? $reaches_page : $unknown;
    }

    // Some other page type carrying a path segment - a calendar view, a
    // custom layout reading its own parameter: what the page does with the
    // rest cannot be checked from the database.
    return $reaches_page;
}

/**
 * One href resolved: pg_link_parse(), then pg_link_resolve_item().
 *
 * @param mixed          $href
 * @param array          $maps       see pg_link_resolve_item()
 * @param array          $env        pg_link_resolve_env()
 * @param array|callable $form_names see pg_link_resolve_item()
 * @return array type (page, product, product_group, file, short_link,
 *               external, unknown), id, class
 */
function pg_link_resolve($href, $maps, $env, $form_names = array())
{
    $parsed = pg_link_parse($href, $env);

    if ($parsed['result'] !== null) {
        return $parsed['result'];
    }

    return pg_link_resolve_item($parsed['item'], $maps, $form_names);
}

/**
 * The names a set of item names can be looked up by: each item itself and,
 * for a pretty address, the page name before its slash; and the slugs after
 * it (products, product groups, form records). Kept in first-seen order,
 * each once.
 *
 * @param string[] $items pg_link_parse() items
 * @return array items, slugs
 */
function pg_link_lookup_names($items)
{
    $names = array();
    $slugs = array();

    foreach ((array) $items as $item) {
        $item = (string) $item;

        if ($item === '') {
            continue;
        }

        $names[$item] = true;

        if (mb_strpos($item, '/') !== false) {
            $names[mb_substr($item, 0, mb_strpos($item, '/'))] = true;
            $slug = trim(mb_substr($item, mb_strpos($item, '/') + 1), '/');

            if ($slug !== '') {
                $slugs[$slug] = true;
            }
        }
    }

    return array('items' => array_map('strval', array_keys($names)), 'slugs' => array_map('strval', array_keys($slugs)));
}

/**
 * Name maps holding only what the given item names can reach, in the shape
 * pg_seo_link_maps() returns, plus form_names (address names of submitted
 * forms among the slugs). For a caller that resolves the links of one page:
 * a few small IN() reads instead of every name of the site.
 *
 * @param string[] $items pg_link_parse() items
 * @return array
 */
function pg_link_maps_for_items($items)
{
    $maps = array(
        'pages' => array(),
        'files' => array(),
        'short_links' => array(),
        'products' => array(),
        'product_groups' => array(),
        'home_page_id' => (int) db_value("SELECT page_id FROM page WHERE page_home = 'yes' ORDER BY page_id LIMIT 1"),
        'form_names' => array(),
    );

    $names = pg_link_lookup_names($items);

    $in = function ($values) {
        return "'" . implode("','", array_map('e', $values)) . "'";
    };

    if ($names['items']) {
        foreach (db_items("SELECT page_id, page_name, page_type, layout_type, page_folder FROM page WHERE page_name IN (" . $in($names['items']) . ")") as $row) {
            $maps['pages'][(string) $row['page_name']] = array(
                'id' => (int) $row['page_id'],
                'type' => (string) $row['page_type'],
                'layout_type' => (string) $row['layout_type'],
                'folder' => (int) $row['page_folder'],
            );
        }

        foreach (db_items("SELECT id, name FROM files WHERE name IN (" . $in($names['items']) . ")") as $row) {
            $maps['files'][(string) $row['name']] = (int) $row['id'];
        }

        foreach (db_items("SELECT name FROM short_links WHERE name IN (" . $in($names['items']) . ")") as $row) {
            $maps['short_links'][(string) $row['name']] = true;
        }
    }

    if ($names['slugs']) {
        if (defined('ECOMMERCE') && (ECOMMERCE == true)) {
            foreach (db_items("SELECT id, address_name FROM products WHERE address_name IN (" . $in($names['slugs']) . ")") as $row) {
                $maps['products'][(string) $row['address_name']] = (int) $row['id'];
            }

            foreach (db_items("SELECT id, address_name FROM product_groups WHERE address_name IN (" . $in($names['slugs']) . ")") as $row) {
                $maps['product_groups'][(string) $row['address_name']] = (int) $row['id'];
            }
        }

        foreach (db_items("SELECT address_name FROM forms WHERE address_name IN (" . $in($names['slugs']) . ")") as $row) {
            $maps['form_names'][(string) $row['address_name']] = true;
        }
    }

    return $maps;
}

// Name lookups the SEO resolver needs, loaded once per request.
//
// Three maps and two small sets, all keyed by the exact string the router
// compares against. Building them costs three scans of narrow columns; the
// alternative is a query per link, and a page with a navigation menu has
// dozens.
function pg_seo_link_maps()
{
    static $maps = null;

    if ($maps !== null) {
        return $maps;
    }

    $maps = array(
        'pages' => array(),
        'files' => array(),
        'short_links' => array(),
        'products' => array(),
        'product_groups' => array(),
        'home_page_id' => 0,
    );

    foreach (db_items("SELECT page_id, page_name, page_type, layout_type, page_home, page_folder FROM page") as $row) {
        $maps['pages'][(string) $row['page_name']] = array(
            'id' => (int) $row['page_id'],
            'type' => (string) $row['page_type'],
            'layout_type' => (string) $row['layout_type'],
            'folder' => (int) $row['page_folder'],
        );

        if (((string) $row['page_home']) === 'yes') {
            $maps['home_page_id'] = (int) $row['page_id'];
        }
    }

    foreach (db_items("SELECT id, name FROM files") as $row) {
        $maps['files'][(string) $row['name']] = (int) $row['id'];
    }

    foreach (db_items("SELECT name FROM short_links") as $row) {
        $maps['short_links'][(string) $row['name']] = true;
    }

    // Item namespaces reachable through a slash in a pretty URL. Only these
    // three exist, which is what makes an unresolved slug a confident
    // finding rather than a guess.
    if (defined('ECOMMERCE') && (ECOMMERCE == true)) {
        foreach (db_items("SELECT id, address_name FROM products WHERE address_name <> ''") as $row) {
            $maps['products'][(string) $row['address_name']] = (int) $row['id'];
        }

        foreach (db_items("SELECT id, address_name FROM product_groups WHERE address_name <> ''") as $row) {
            $maps['product_groups'][(string) $row['address_name']] = (int) $row['id'];
        }
    }

    return $maps;
}

// Address names of submitted forms, loaded only if a form list view link is
// actually seen. Sites with a large blog have many, and most pages never
// link to one.
function pg_seo_form_address_names()
{
    static $names = null;

    if ($names === null) {
        $names = array();

        foreach (db_items("SELECT address_name FROM forms WHERE address_name <> ''") as $row) {
            $names[(string) $row['address_name']] = true;
        }
    }

    return $names;
}
