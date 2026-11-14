<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pages offered as copies of a new e-mail design on the Templates tab: the
 * e-mail pages the installed design templates brought (the newsletter's
 * welcome, say) and the pages earlier campaigns were sent from. A copy is
 * the page's tree with props.pgEmail on its root, opened as a new e-mail
 * design (add_system_style.php?start=email&copy=<page_id>); the page
 * itself is never changed. A page that places a system widget (an order
 * receipt, a form's or a comment's e-mail) is not offered: the record the
 * widget draws is given by the mail that sends it, and a campaign has none.
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
 * A page tree as the start of a new e-mail design: the same content, the
 * root marked as an e-mail design. Null for anything that is no page tree.
 *
 * @param mixed $tree a decoded tree
 * @return array|null
 */
function pg_email_design_copy_tree($tree)
{
    if (!is_array($tree) || !isset($tree['type']) || $tree['type'] !== 'root') {
        return null;
    }

    $props = (isset($tree['props']) && is_array($tree['props'])) ? $tree['props'] : array();
    $props['pgEmail'] = 1;
    $tree['props'] = $props;

    if (!isset($tree['children']) || !is_array($tree['children'])) {
        $tree['children'] = array();
    }

    return $tree;
}

/**
 * The shared components and system widgets a tree places, by row id.
 *
 * @param mixed $tree
 * @return array of int
 */
function pg_email_design_copy_shared_ids($tree)
{
    $ids = array();
    $walk = function ($node, $depth) use (&$walk, &$ids) {
        if (!is_array($node) || $depth > 80) {
            return;
        }

        if (isset($node['type']) && $node['type'] === 'shared_ref') {
            $id = isset($node['props']['sharedId']) ? (int) $node['props']['sharedId'] : 0;

            if ($id > 0) {
                $ids[$id] = $id;
            }

            return;
        }

        foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
            $walk($child, $depth + 1);
        }
    };
    $walk($tree, 0);

    return array_values($ids);
}

/**
 * A template's e-mail pages among a design's pages, by their name: the name
 * the template gives, or that name numbered when it was taken at install
 * ("email-welcome-2"). Pure.
 *
 * @param array $names key => page name and title: name => title
 * @param array $pages page_id, page_name
 * @return array page_id => title
 */
function pg_email_design_template_matches(array $names, array $pages)
{
    $out = array();

    foreach ($pages as $page) {
        $page_name = (string) $page['page_name'];

        foreach ($names as $name => $title) {
            $name = (string) $name;

            if ($name !== '' && ($page_name === $name || preg_match('/^' . preg_quote($name, '/') . '-[0-9]+$/', $page_name))) {
                $out[(int) $page['page_id']] = (string) $title;
                break;
            }
        }
    }

    return $out;
}

/**
 * Whether a tree places a system widget.
 *
 * @param mixed $tree
 * @return bool
 */
function pg_email_design_copy_has_widget($tree)
{
    $ids = pg_email_design_copy_shared_ids($tree);

    if (!$ids) {
        return false;
    }

    return (int) db_value(
        "SELECT COUNT(*) FROM shared_components
        WHERE id IN ('" . implode("','", array_map('intval', $ids)) . "')
        AND system_region_config IS NOT NULL AND system_region_config != ''"
    ) > 0;
}

/**
 * The tree a page is copied from, or null when it is not offered: no such
 * page or tree, a page in the e-mail designs folder (that is one already),
 * or one that places a system widget.
 *
 * @param int $page_id
 * @return array|null pg_email_design_copy_tree()
 */
function pg_email_design_copy_source($page_id)
{
    $page_id = (int) $page_id;

    if ($page_id <= 0) {
        return null;
    }

    $folder = (int) db_value("SELECT page_folder FROM page WHERE page_id = '$page_id' LIMIT 1");
    $designs_folder = pg_email_designs_folder_id(false);

    if ($designs_folder > 0 && $folder === $designs_folder) {
        return null;
    }

    $tree = json_decode(pg_page_tree_json($page_id), true);

    if (pg_email_design_tree_is($tree) || pg_email_design_copy_has_widget($tree)) {
        return null;
    }

    return pg_email_design_copy_tree($tree);
}

/**
 * The pages offered as copies: the installed templates' e-mail pages, then
 * the pages of the latest manual campaigns (under the latest one's
 * subject), each page once, the ones that
 * are no copy source left out (pg_email_design_copy_source()).
 *
 * @param int $limit at most this many of each kind
 * @return array of page_id, title, kind (template, campaign), stamp
 */
function pg_email_design_copy_list($limit = 20)
{
    $out = array();
    $seen = array();
    $limit = max(1, (int) $limit);

    if (pg_style_framework_ready()) {
        $count = 0;

        foreach (db_items("SELECT style_id, style_template FROM style WHERE style_template != '' ORDER BY style_id DESC LIMIT 10") as $style) {
            $tpl = pg_design_template((string) $style['style_template']);

            if (!$tpl) {
                continue;
            }

            $keys = pg_design_template_email_pages($tpl);
            $names = array();

            foreach ($tpl['pages'] as $p) {
                if (isset($p['key'], $p['name']) && isset($keys[$p['key']])) {
                    $names[(string) $p['name']] = $keys[$p['key']] !== '' ? $keys[$p['key']] : (string) $p['name'];
                }
            }

            if (!$names) {
                continue;
            }

            $pages = db_items("SELECT page_id, page_name FROM page WHERE page_style = '" . (int) $style['style_id'] . "'");

            foreach (pg_email_design_template_matches($names, is_array($pages) ? $pages : array()) as $page_id => $title) {
                if ($count >= $limit || isset($seen[$page_id]) || pg_email_design_copy_source($page_id) === null) {
                    continue;
                }

                $seen[$page_id] = true;
                $count++;
                $out[] = array('page_id' => $page_id, 'title' => $title, 'kind' => 'template', 'stamp' => 0);
            }
        }
    }

    // The latest campaigns first; a page used again is listed once, under
    // its latest campaign's subject.
    $campaigns = db_items(
        "SELECT email_campaigns.page_id, email_campaigns.subject, email_campaigns.created_timestamp
        FROM email_campaigns
        INNER JOIN page ON page.page_id = email_campaigns.page_id
        WHERE email_campaigns.type = 'manual' AND email_campaigns.page_id > 0
        ORDER BY email_campaigns.created_timestamp DESC LIMIT 100"
    );
    $count = 0;

    foreach (is_array($campaigns) ? $campaigns : array() as $row) {
        $page_id = (int) $row['page_id'];

        if ($count >= $limit || isset($seen[$page_id])) {
            continue;
        }

        $seen[$page_id] = true;

        if (pg_email_design_copy_source($page_id) === null) {
            continue;
        }

        $count++;
        $out[] = array('page_id' => $page_id, 'title' => (string) $row['subject'], 'kind' => 'campaign', 'stamp' => (int) $row['created_timestamp']);
    }

    return $out;
}
