<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The pages list (view_pages.php), shared by the screen and the panel action
 * that pages it (page_list_rows, includes/panel/lists.php).
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
 * The style each folder hands down to a page without a style of its own:
 * the folder's style for the device type, else its nearest parent's, as
 * get_style() walks the chain, but for all folders from one read. A root
 * folder (level 0) without a style, a missing parent or a loop ends at 0.
 *
 * @param array  $folders     rows: folder_id, folder_parent, folder_level,
 *                            folder_style, mobile_style_id
 * @param string $device_type 'desktop' or 'mobile'
 * @return array folder id => style id (0 for none)
 */
function pg_page_list_folder_styles($folders, $device_type)
{
    $column = ($device_type === 'mobile') ? 'mobile_style_id' : 'folder_style';
    $by_id = array();

    foreach ((array) $folders as $folder) {
        if (is_array($folder) && isset($folder['folder_id'])) {
            $by_id[(int) $folder['folder_id']] = $folder;
        }
    }

    $styles = array();

    foreach (array_keys($by_id) as $start) {
        $path = array();
        $id = $start;
        $style = 0;

        while (true) {
            if (isset($styles[$id])) {
                $style = $styles[$id];
                break;
            }

            if (!isset($by_id[$id]) || isset($path[$id])) {
                break;
            }

            $path[$id] = true;
            $folder = $by_id[$id];

            if ((int) ($folder[$column] ?? 0) > 0) {
                $style = (int) $folder[$column];
                break;
            }

            if ((int) ($folder['folder_level'] ?? 0) === 0) {
                break;
            }

            $id = (int) ($folder['folder_parent'] ?? 0);
        }

        foreach (array_keys($path) as $seen) {
            $styles[$seen] = $style;
        }
    }

    ksort($styles);

    return $styles;
}
