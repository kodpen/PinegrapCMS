<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Internal link graph: the third component of the SEO score.
//
// Every link found while the structure pass renders a page is resolved
// against the database and stored. Two kinds of question come out of that.
// Per-page ones - does this link point at anything, does a public page link
// into a private folder - are answered while the page is being analyzed.
// Graph-wide ones - is this page reachable by clicking at all, how many
// clicks from the home page - can only be answered once every page has been
// collected, so they run in a second pass.
//
// No HTTP. A destination is resolved in the same order router.php dispatches
// one, so "broken" here means the router would not find a destination either.

// Codes this file owns, split by which pass writes them.
//
// seo_issue rows are rewritten per scope because three passes write findings
// for the same record at different times: the markup analysis, the link
// resolution that rides along with it, and the graph walk that can only run
// once every page has been collected. A blanket delete would mean whichever
// ran last erased the other two.
function pg_seo_page_link_codes()
{
    return array('link_broken_internal', 'link_to_private');
}

function pg_seo_graph_codes()
{
    return array('orphan_page', 'depth_too_deep', 'anchor_inconsistent');
}

// Everything this file owns, which is what the markup pass must leave alone.
function pg_seo_link_codes()
{
    return array_merge(pg_seo_page_link_codes(), pg_seo_graph_codes());
}

function pg_seo_link_severities()
{
    return array(
        'link_broken_internal' => 'error',
        'link_to_private'      => 'warning',
        'orphan_page'          => 'warning',
        'depth_too_deep'       => 'notice',
        'anchor_inconsistent'  => 'notice',
    );
}

// Name lookups the resolver needs, loaded once per request.
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

// Resolve one href to what the router would find.
//
// Returns array('type' => ..., 'id' => int). Types mirror the seo_link
// column: page, product, product_group, file, short_link, external, unknown.
// 'unknown' is the only one that counts as broken, and the resolver is
// deliberately reluctant to return it - a broken-link report full of false
// positives is worse than no report, because the operator stops reading it.
function pg_seo_resolve_link($href)
{
    $href = trim((string) $href);

    if ($href === '') {
        return array('type' => 'unknown', 'id' => 0);
    }

    // Not addresses of anything this software serves.
    if (preg_match('/^(mailto:|tel:|sms:|javascript:|data:|ftp:|#)/i', $href)) {
        return array('type' => 'external', 'id' => 0);
    }

    $parts = parse_url($href);

    if ($parts === false) {
        return array('type' => 'unknown', 'id' => 0);
    }

    // A host that is not ours is somebody else's problem; a link check that
    // followed it would need HTTP, which this feature does not do.
    if (!empty($parts['host'])) {
        $own_host = defined('HOSTNAME') ? preg_replace('/:\d+$/', '', HOSTNAME) : '';
        $link_host = preg_replace('/:\d+$/', '', $parts['host']);

        if (($own_host === '') || (strcasecmp($link_host, $own_host) !== 0)) {
            return array('type' => 'external', 'id' => 0);
        }
    }

    $path = isset($parts['path']) ? $parts['path'] : '';

    // A bare query string or fragment points at the page it sits on.
    if ($path === '') {
        return array('type' => 'external', 'id' => 0);
    }

    $site_path = defined('PATH') ? PATH : '/';

    // A relative link is resolved against the site root rather than against
    // the containing page: Pinegrap writes absolute paths everywhere, so a
    // relative one is hand-written and its base is not knowable from here.
    if (strpos($path, '/') !== 0) {
        $path = $site_path . $path;
    }

    if (($site_path !== '') && (strpos($path, $site_path) === 0)) {
        $item_name = substr($path, strlen($site_path));
    } else {
        // Outside the installation's own path - another application on the
        // same host.
        return array('type' => 'external', 'id' => 0);
    }

    $item_name = rawurldecode($item_name);
    $item_name = ltrim($item_name, '/');

    // The router's own order, from here down.
    $maps = pg_seo_link_maps();

    if (($item_name === '') || ($item_name === 'index.php')) {
        return array('type' => 'page', 'id' => $maps['home_page_id']);
    }

    $lowercase = mb_strtolower($item_name);

    if (($lowercase === 'robots.txt') || ($lowercase === 'sitemap.xml')) {
        return array('type' => 'external', 'id' => 0);
    }

    // Anything inside the control panel directory is a backend address, not
    // site content.
    if (defined('SOFTWARE_DIRECTORY') && (strpos($item_name, SOFTWARE_DIRECTORY . '/') === 0)) {
        return array('type' => 'external', 'id' => 0);
    }

    if (isset($maps['files'][$item_name])) {
        return array('type' => 'file', 'id' => $maps['files'][$item_name]);
    }

    if (isset($maps['short_links'][$item_name])) {
        return array('type' => 'short_link', 'id' => 0);
    }

    if (isset($maps['pages'][$item_name])) {
        return array('type' => 'page', 'id' => $maps['pages'][$item_name]['id']);
    }

    // A slash means a pretty URL: the part before it names a page, the rest
    // names an item that page displays.
    if (mb_strpos($item_name, '/') !== false) {
        $prefix = mb_substr($item_name, 0, mb_strpos($item_name, '/'));
        $slug = trim(mb_substr($item_name, mb_strpos($item_name, '/') + 1), '/');

        if (!isset($maps['pages'][$prefix])) {
            return array('type' => 'unknown', 'id' => 0);
        }

        $page = $maps['pages'][$prefix];

        // A trailing slash with nothing after it is the page itself.
        if ($slug === '') {
            return array('type' => 'page', 'id' => $page['id']);
        }

        if (($page['type'] === 'catalog') || ($page['type'] === 'catalog detail') || ($page['layout_type'] === 'system')) {

            if (isset($maps['products'][$slug])) {
                return array('type' => 'product', 'id' => $maps['products'][$slug]);
            }

            if (isset($maps['product_groups'][$slug])) {
                return array('type' => 'product_group', 'id' => $maps['product_groups'][$slug]);
            }

            // A system-layout page can host widgets this resolver does not
            // model, so an unmatched slug there is not proof of a dead link.
            if ($page['layout_type'] === 'system') {
                return array('type' => 'page', 'id' => $page['id']);
            }

            return array('type' => 'unknown', 'id' => 0);
        }

        if ($page['type'] === 'form list view') {
            $form_names = pg_seo_form_address_names();

            if (isset($form_names[$slug])) {
                return array('type' => 'page', 'id' => $page['id']);
            }

            return array('type' => 'unknown', 'id' => 0);
        }

        // Some other page type carrying a path segment - a calendar view, a
        // custom layout reading its own parameter. The prefix resolves, and
        // what the page does with the rest is beyond what can be checked
        // from the database, so it is reported as reaching that page rather
        // than as broken.
        return array('type' => 'page', 'id' => $page['id']);
    }

    return array('type' => 'unknown', 'id' => 0);
}

// Replace the stored links of one record.
function pg_seo_store_links($type, $id, $links)
{
    db("DELETE FROM seo_link WHERE (from_type = '" . e($type) . "') AND (from_id = '" . (int) $id . "')");

    foreach ($links as $link) {
        db(
            "INSERT INTO seo_link (from_type, from_id, to_type, to_id, href, anchor, rel)
            VALUES (
                '" . e($type) . "',
                '" . (int) $id . "',
                '" . e($link['to_type']) . "',
                '" . (int) $link['to_id'] . "',
                '" . e(mb_substr($link['href'], 0, 512)) . "',
                '" . e(mb_substr($link['anchor'], 0, 160)) . "',
                '" . e(mb_substr($link['rel'], 0, 64)) . "')");
    }
}

// Resolve the raw links collected from a rendered record, store them, and
// return the findings that can be judged without the rest of the graph.
function pg_seo_process_links($type, $id, $raw_links, $context = array())
{
    $severities = pg_seo_link_severities();
    $maps = pg_seo_link_maps();
    $resolved = array();
    $findings = array();
    $broken = 0;
    $broken_example = '';
    $private = 0;
    $private_example = '';

    // Which folders are public, resolved once. A link from a public page
    // into a private folder is a dead end for a visitor who is not logged
    // in and for every search engine.
    $source_is_public = !empty($context['source_public']);

    foreach ($raw_links as $raw_link) {
        $destination = pg_seo_resolve_link($raw_link['href']);

        $resolved[] = array(
            'to_type' => $destination['type'],
            'to_id' => $destination['id'],
            'href' => $raw_link['href'],
            'anchor' => $raw_link['anchor'],
            'rel' => $raw_link['rel'],
        );

        if ($destination['type'] === 'unknown') {
            $broken++;

            if ($broken_example === '') {
                $broken_example = $raw_link['href'];
            }

            continue;
        }

        if ($source_is_public && ($destination['type'] === 'page') && $destination['id']) {
            foreach ($maps['pages'] as $page_name => $page) {
                if ($page['id'] !== $destination['id']) {
                    continue;
                }

                if (get_access_control_type($page['folder']) !== 'public') {
                    $private++;

                    if ($private_example === '') {
                        $private_example = $page_name;
                    }
                }

                break;
            }
        }
    }

    pg_seo_store_links($type, $id, $resolved);

    if ($broken) {
        $findings['link_broken_internal'] = array(
            'code' => 'link_broken_internal',
            'severity' => $severities['link_broken_internal'],
            'occurrences' => $broken,
            'source' => 'generated',
            'detail' => mb_substr($broken_example, 0, 250),
        );
    }

    if ($private) {
        $findings['link_to_private'] = array(
            'code' => 'link_to_private',
            'severity' => $severities['link_to_private'],
            'occurrences' => $private,
            'source' => 'generated',
            'detail' => mb_substr($private_example, 0, 250),
        );
    }

    return $findings;
}

// Graph-wide findings, computed once every page has been collected.
//
// Two questions no single page can answer: whether anything links to it, and
// how far it is from the home page. Both are read from seo_link, which the
// per-page pass has already filled in.
//
// Returns the number of pages whose graph findings were rewritten.
function pg_seo_build_link_graph()
{
    // pg_seo_link_schema_ready() as well: upgrade 2026.4.13 creates seo_link
    // before it adds seo_link_score, so a run interrupted between the two
    // leaves the table present and the column missing - and this function both
    // reads and writes that column.
    if (!pg_seo_structure_schema_ready() || !pg_seo_link_schema_ready() || !db_item("SHOW TABLES LIKE 'seo_link'")) {
        return 0;
    }

    $maps = pg_seo_link_maps();
    $severities = pg_seo_link_severities();

    // Only pages that are supposed to be found are judged on being findable.
    // A login screen nobody links to is not an orphan, it is a login screen.
    $candidates = array();

    foreach (db_items(
        "SELECT page.page_id, page.page_folder, folder.folder_archived
        FROM page
        LEFT JOIN folder ON page.page_folder = folder.folder_id
        WHERE (page.sitemap = '1') AND (page.page_type IN ('" . implode("','", pg_seo_sitemap_eligible_types()) . "'))") as $row
    ) {
        if ((((string) $row['folder_archived']) === '1') || (get_access_control_type($row['page_folder']) !== 'public')) {
            continue;
        }

        $candidates[(int) $row['page_id']] = true;
    }

    // Adjacency, page to page only. A link from a product description counts
    // as an incoming link but not as a step in the click path, because a
    // visitor reaches the product through a page first.
    $outgoing = array();
    $incoming = array();

    foreach (db_items("SELECT from_type, from_id, to_id FROM seo_link WHERE to_type = 'page' AND to_id > 0") as $row) {
        $to_id = (int) $row['to_id'];
        $from_id = (int) $row['from_id'];

        $incoming[$to_id] = isset($incoming[$to_id]) ? $incoming[$to_id] + 1 : 1;

        if (((string) $row['from_type']) === 'page') {
            // Self links say nothing about reachability.
            if ($from_id !== $to_id) {
                $outgoing[$from_id][$to_id] = true;
            }
        }
    }

    // Breadth-first from the home page. Anything the walk never reaches has
    // no click path at all, which is a stronger statement than being deep.
    $depth = array();

    if ($maps['home_page_id']) {
        $depth[$maps['home_page_id']] = 0;
        $queue = array($maps['home_page_id']);
        $cursor = 0;

        while ($cursor < count($queue)) {
            $current = $queue[$cursor];
            $cursor++;

            foreach (($outgoing[$current] ?? array()) as $next => $ignored) {
                if (isset($depth[$next])) {
                    continue;
                }

                $depth[$next] = $depth[$current] + 1;
                $queue[] = $next;
            }
        }
    }

    $processed = 0;

    foreach (array_keys($candidates) as $page_id) {
        $findings = array();

        if (empty($incoming[$page_id])) {
            $findings['orphan_page'] = array(
                'code' => 'orphan_page',
                'severity' => $severities['orphan_page'],
                'occurrences' => 1,
                'source' => 'generated',
                'detail' => '',
            );
        }

        $page_depth = isset($depth[$page_id]) ? $depth[$page_id] : null;

        if (($page_depth !== null) && ($page_depth > 3)) {
            $findings['depth_too_deep'] = array(
                'code' => 'depth_too_deep',
                'severity' => $severities['depth_too_deep'],
                'occurrences' => 1,
                'source' => 'generated',
                'detail' => (string) $page_depth,
            );
        }

        pg_seo_store_issues('page', $page_id, $findings, 'graph');

        $link_score = (int) pg_seo_link_score('page', $page_id);
        $new_depth = ($page_depth === null) ? null : (int) min(255, $page_depth);

        $current = db_item(
            "SELECT seo_depth, seo_link_score
            FROM page
            WHERE page_id = '" . (int) $page_id . "'");

        $depth_changed = !$current
            || (($current['seo_depth'] === null) !== ($new_depth === null))
            || (($new_depth !== null) && ((int) $current['seo_depth'] !== $new_depth));

        $score_changed = !$current
            || ($current['seo_link_score'] === null)
            || ((int) $current['seo_link_score'] !== $link_score);

        // Marking the record stale is what makes the meta pass recompose the
        // number the screens show, and that pass clears a bounded number of
        // rows per run. Marking every page in the sitemap on every graph run
        // would queue more work than the pass can drain, so the backlog would
        // never empty and the list screens would recalculate synchronously on
        // every load, forever.
        if (!$depth_changed && !$score_changed) {
            continue;
        }

        db(
            "UPDATE page
            SET
                seo_depth = " . (($new_depth === null) ? 'NULL' : "'" . $new_depth . "'") . ",
                seo_link_score = '" . $link_score . "',
                seo_analysis_current = '0'
            WHERE page_id = '" . (int) $page_id . "'");

        $processed++;
    }

    // Graph findings are written per candidate, so a page that leaves the
    // candidate set - unticked from the sitemap, moved into a private or
    // archived folder - is simply never visited again and keeps whatever it
    // was last told. An orphan flag would stay on it forever, the "Orphan
    // Pages" filter would keep listing it, and its link score would keep
    // deducting for a finding that no longer applies.
    $sql_candidates = $candidates ? implode(',', array_map('intval', array_keys($candidates))) : '0';
    $sql_graph_codes = implode("','", pg_seo_graph_codes());

    $departed = db_items(
        "SELECT DISTINCT entity_id
        FROM seo_issue
        WHERE
            (entity_type = 'page')
            AND (code IN ('" . $sql_graph_codes . "'))
            AND (entity_id NOT IN (" . $sql_candidates . "))");

    if ($departed) {

        db(
            "DELETE FROM seo_issue
            WHERE
                (entity_type = 'page')
                AND (code IN ('" . $sql_graph_codes . "'))
                AND (entity_id NOT IN (" . $sql_candidates . "))");

        // Removing the finding is only half of it: the score it fed and the
        // depth that goes with it are both stored, so they have to be
        // recomputed and the record marked for recomposition.
        foreach ($departed as $departed_row) {

            $departed_id = (int) $departed_row['entity_id'];

            db(
                "UPDATE page
                SET
                    seo_depth = NULL,
                    seo_link_score = '" . (int) pg_seo_link_score('page', $departed_id) . "',
                    seo_analysis_current = '0'
                WHERE page_id = '" . $departed_id . "'");

            $processed++;
        }
    }

    return $processed;
}

// Link score for one record, from its stored findings. Same deduction model
// as the structure score, so the two components read on the same scale.
function pg_seo_link_score($type, $id)
{
    $codes = pg_seo_link_codes();

    $findings = db_items(
        "SELECT code, severity, occurrences
        FROM seo_issue
        WHERE
            (entity_type = '" . e($type) . "')
            AND (entity_id = '" . (int) $id . "')
            AND (code IN ('" . implode("','", $codes) . "'))");

    return pg_seo_structure_score($findings);
}
