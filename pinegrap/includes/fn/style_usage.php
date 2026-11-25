<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * How many folders and pages use each style (view_styles.php). A folder or
 * page with no style of its own takes its parent folder's, desktop and
 * mobile apart, as get_style() does; a mobile style that works out to the
 * same style as the desktop one is counted once.
 *
 * Loaded where it is needed (require_once), not by functions.php.
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
 * Pure: no query.
 *
 * @param array $folders  rows: id, parent_folder_id, style_id, mobile_style_id
 * @param array $pages    rows: folder_id, style_id, mobile_style_id
 * @return array  style id => array('number_of_folders' => n, 'number_of_pages' => n);
 *                styles nothing uses are left out
 */
function pg_style_usage($folders, $pages)
{
    $children = array();
    foreach ((array)$folders as $folder) {
        if (is_array($folder) && isset($folder['id'])) {
            $children[(int)($folder['parent_folder_id'] ?? 0)][] = $folder;
        }
    }

    $pages_in = array();
    foreach ((array)$pages as $page) {
        if (is_array($page)) {
            $pages_in[(int)($page['folder_id'] ?? 0)][] = $page;
        }
    }

    $usage = array();
    $count = function ($desktop, $mobile, $key) use (&$usage) {
        foreach (array_unique(array($desktop, $mobile)) as $style_id) {
            if ($style_id > 0) {
                if (!isset($usage[$style_id])) {
                    $usage[$style_id] = array('number_of_folders' => 0, 'number_of_pages' => 0);
                }
                ++$usage[$style_id][$key];
            }
        }
    };

    // Folders walked from the top, each with the styles it passes down. A
    // folder seen twice (a loop in the rows) is not walked again.
    $queue = array(array(0, 0, 0));
    $seen = array();
    while ($queue) {
        list($parent_id, $desktop, $mobile) = array_shift($queue);

        foreach ($pages_in[$parent_id] ?? array() as $page) {
            $count(
                ((int)($page['style_id'] ?? 0)) ?: $desktop,
                ((int)($page['mobile_style_id'] ?? 0)) ?: $mobile,
                'number_of_pages'
            );
        }

        foreach ($children[$parent_id] ?? array() as $folder) {
            $id = (int)$folder['id'];
            if (isset($seen[$id]) || $id === 0) {
                continue;
            }
            $seen[$id] = true;
            $folder_desktop = ((int)($folder['style_id'] ?? 0)) ?: $desktop;
            $folder_mobile = ((int)($folder['mobile_style_id'] ?? 0)) ?: $mobile;
            $count($folder_desktop, $folder_mobile, 'number_of_folders');
            $queue[] = array($id, $folder_desktop, $folder_mobile);
        }
    }

    return $usage;
}

/**
 * The pages that keep a style from being deleted (delete_styles.php, Edit
 * Page Style's Delete), as a WHERE condition on `page`. Pure.
 *
 * $bin_folder_ids are passed only once the Recycle Bin keeps styles
 * (pg_recycle_style_ready()): a page in the bin that was built on the style
 * is written in the style's restore record, and restoring the page brings
 * the style back with it, as a design deleted with its pages does. Before
 * that the style is deleted for good, so every page holds it. A page's
 * mobile style always holds: nothing brings that back with the page.
 *
 * @param int   $style_id
 * @param array $bin_folder_ids the bin folder and the folders under it
 * @return string
 */
function pg_style_pages_holding_sql($style_id, $bin_folder_ids)
{
    $id = "'" . (int)$style_id . "'";
    $bins = array_values(array_unique(array_filter(array_map('intval', (array)$bin_folder_ids))));

    $built_on = $bins
        ? "((page_style = $id) AND (page_folder NOT IN (" . implode(',', $bins) . ")))"
        : "(page_style = $id)";

    return $built_on . " OR (mobile_style_id = $id)";
}

/**
 * pg_style_pages_holding_sql() for this site: the bin folders once the
 * Recycle Bin keeps styles, none before.
 *
 * @param int $style_id
 * @return string
 */
function pg_style_pages_holding_where($style_id)
{
    require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

    return pg_style_pages_holding_sql($style_id, pg_recycle_style_ready() ? pg_recycle_bin_folder_ids() : array());
}
