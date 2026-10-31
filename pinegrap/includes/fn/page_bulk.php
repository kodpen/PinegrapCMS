<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * One page of a bulk page edit (the Pages list's Modify Selected,
 * edit_pages.php, and the file manager's bulk page panel,
 * pg_explorer_handle_pages_bulk_edit()): which columns are written, which
 * style is set, whether the SEO score goes stale and how the site-search
 * keyword cloud changes, by the rules the single page save
 * (edit_page.php) applies.
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
 * The page types whose sitemap switch may be turned on, the list the page
 * screen stores it for: the types sitemap.xml lists bare
 * (pg_sitemap_page_types()) and the three detail types whose switch lists
 * their records.
 *
 * @return string[]
 */
function pg_page_bulk_sitemap_types()
{
    return array_merge(pg_sitemap_page_types(), array(
        'form item view',
        'calendar event view',
        'catalog detail',
    ));
}

/**
 * What a bulk edit does to one page.
 *
 * @param array $page  The page as stored: page_type, page_folder,
 *                     page_search, sitemap, noindex, nofollow (0 while the
 *                     columns are missing), is_visual (1 when the visual
 *                     editor opens the page)
 * @param array $set   The values asked for; a missing key or null leaves
 *                     the field alone: folder, search, sitemap, comments,
 *                     noindex, nofollow ('0'/'1', the last two only where
 *                     the columns exist), style, mobile_style (style ids,
 *                     '0' for the default)
 * @param int   $role  The operator's role
 * @return array  'columns' (column => value to write), 'styles'
 *                ('desktop'/'mobile' => style id), 'style_skipped' (a
 *                style was asked for a visual-editor page), 'stale'
 *                (a column the SEO score reads changes; a style written
 *                to the page marks it too), 'tag_cloud'
 *                ('add', 'remove' or '')
 */
function pg_page_bulk_decide($page, $set, $role)
{
    $columns = array();
    $styles = array();
    $style_skipped = false;

    $flag = function ($key) use ($set) {
        if (!isset($set[$key])) {
            return null;
        }
        $value = (string) $set[$key];
        return in_array($value, array('0', '1'), true) ? (int) $value : null;
    };

    $folder = (isset($set['folder']) && ((int) $set['folder'] > 0)) ? (int) $set['folder'] : null;
    if (($folder !== null) && ($folder != (int) $page['page_folder'])) {
        $columns['page_folder'] = $folder;
    }

    $search = $flag('search');
    if (($search !== null) && ($search != (int) $page['page_search'])) {
        $columns['page_search'] = $search;
    }

    $comments = $flag('comments');
    if ($comments !== null) {
        $columns['comments'] = $comments;
    }

    // The noindex pair: nofollow only qualifies noindex, so it is kept only
    // while the page stays closed to search engines (pg_page_form_index_flags()).
    $current_noindex = (int) ($page['noindex'] ?? 0);
    $current_nofollow = (int) ($page['nofollow'] ?? 0);
    $noindex = $flag('noindex');
    $noindex = ($noindex === null) ? $current_noindex : $noindex;
    $nofollow = $flag('nofollow');
    $nofollow = ($nofollow === null) ? $current_nofollow : $nofollow;
    if (!$noindex) {
        $nofollow = 0;
    }
    if ($noindex != $current_noindex) {
        $columns['noindex'] = $noindex;
    }
    if ($nofollow != $current_nofollow) {
        $columns['nofollow'] = $nofollow;
    }

    // The sitemap switch: turned on only for a type the page screen offers it
    // to, and never on a noindex page.
    $current_sitemap = (int) $page['sitemap'];
    $sitemap = $flag('sitemap');
    if (($sitemap === 1) && !in_array((string) $page['page_type'], pg_page_bulk_sitemap_types(), true)) {
        $sitemap = null;
    }
    $sitemap = ($sitemap === null) ? $current_sitemap : $sitemap;
    if ($noindex) {
        $sitemap = 0;
    }
    if ($sitemap != $current_sitemap) {
        $columns['sitemap'] = $sitemap;
    }

    // The styles: staff only, as on the page screen, and never on a page the
    // visual editor opens, whose layout tree was built against its style.
    foreach (array('style' => 'desktop', 'mobile_style' => 'mobile') as $key => $device) {
        if (!isset($set[$key]) || ((string) $set[$key] === '') || ((int) $role >= 3)) {
            continue;
        }
        if (!empty($page['is_visual'])) {
            $style_skipped = true;
            continue;
        }
        $styles[$device] = max(0, (int) $set[$key]);
    }

    $tag_cloud = '';
    if (isset($columns['page_search'])) {
        $tag_cloud = $columns['page_search'] ? 'add' : 'remove';
    }

    $stale_columns = array_diff(array_keys($columns), array('comments'));

    return array(
        'columns' => $columns,
        'styles' => $styles,
        'style_skipped' => $style_skipped,
        'stale' => (count($stale_columns) > 0),
        'tag_cloud' => $tag_cloud,
    );
}

/**
 * The page row pg_page_bulk_decide() reads, with the search keywords the
 * tag cloud update needs. False when there is no such page.
 *
 * @param int $page_id
 * @return array|false
 */
function pg_page_bulk_fetch($page_id)
{
    $noindex = pg_page_noindex_ready() ? 'noindex, nofollow' : "'0' AS noindex, '0' AS nofollow";

    $result = db_q(
        "SELECT
            page_id,
            page_name,
            page_folder,
            page_type,
            page_search,
            page_search_keywords,
            sitemap,
            " . $noindex . ",
            (" . pg_page_visual_design_sql('page.') . ") AS is_visual
        FROM page
        WHERE page_id = ?", array((int) $page_id));
    $row = $result ? mysqli_fetch_assoc($result) : null;

    return $row ? $row : false;
}

/**
 * Writes what pg_page_bulk_decide() decided for one page. A style goes to
 * preview_styles while a theme other than the activated one is previewed
 * ($preview_theme_id), to the page otherwise.
 *
 * @param array $page              pg_page_bulk_fetch()
 * @param array $decision          pg_page_bulk_decide()
 * @param int   $user_id
 * @param int   $preview_theme_id  0 when no style goes to a preview
 */
function pg_page_bulk_apply($page, $decision, $user_id, $preview_theme_id = 0)
{
    $page_id = (int) $page['page_id'];
    $columns = $decision['columns'];
    $stale = $decision['stale'];

    if ($preview_theme_id > 0) {
        foreach ($decision['styles'] as $device => $style_id) {
            db_q(
                "DELETE FROM preview_styles
                WHERE (page_id = ?) AND (theme_id = ?) AND (device_type = ?)",
                array($page_id, (int) $preview_theme_id, (string) $device));

            // The default here is the activated style: no row.
            if ($style_id > 0) {
                db_q(
                    "INSERT INTO preview_styles (page_id, theme_id, style_id, device_type)
                    VALUES (?, ?, ?, ?)",
                    array($page_id, (int) $preview_theme_id, (int) $style_id, (string) $device));
            }
        }
    } else {
        foreach (array('desktop' => 'page_style', 'mobile' => 'mobile_style_id') as $device => $column) {
            if (isset($decision['styles'][$device])) {
                $columns[$column] = $decision['styles'][$device];
                $stale = true;
            }
        }
    }

    if ((count($columns) > 0) || $stale) {
        // The column names come from pg_page_bulk_decide() and the list
        // above, never from the request.
        $sql = array();
        $params = array();

        foreach ($columns as $column => $value) {
            $sql[] = $column . ' = ?';
            $params[] = (int) $value;
        }

        if ($stale) {
            $sql[] = "seo_analysis_current = '0'";
        }

        $params[] = (int) $user_id;
        $params[] = $page_id;

        db_q(
            "UPDATE page
            SET
                " . implode(",\n                ", $sql) . ",
                page_timestamp = UNIX_TIMESTAMP(),
                page_user = ?
            WHERE page_id = ?", $params);
    }

    if ($decision['tag_cloud'] == 'add') {
        update_tag_cloud_keywords_for_page($page_id, 1, $page['page_search_keywords'], 0, '');
    } elseif ($decision['tag_cloud'] == 'remove') {
        update_tag_cloud_keywords_for_page($page_id, 0, '', 1, $page['page_search_keywords']);
    }
}
