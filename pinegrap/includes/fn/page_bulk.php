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
 * The counts of pg_page_bulk_tally() before the first page: all 0.
 *
 * @return array
 */
function pg_page_bulk_tally_start()
{
    return array_fill_keys(array(
        'pages', 'folder', 'search_on', 'search_off', 'sitemap_on', 'sitemap_off',
        'style_desktop', 'style_mobile', 'style_skipped', 'sitemap_noindex', 'sitemap_type',
    ), 0);
}

/**
 * Adds one page's decision to the counts a bulk edit reports, so the notice,
 * the site log and the audit name only what was written. A page counts as
 * changed when a column or a style is written; the sitemap switch asked on
 * for a page that stays out of the site map (noindex, or a type the site map
 * does not list) is counted as left alone, with its reason.
 *
 * @param array $tally     the counts so far (array() to start)
 * @param array $page      the page as pg_page_bulk_decide() read it
 * @param array $set       the values asked for, as given to the decision
 * @param array $decision  pg_page_bulk_decide()
 * @return array  pages, folder, search_on, search_off, sitemap_on,
 *                sitemap_off, style_desktop, style_mobile, style_skipped,
 *                sitemap_noindex, sitemap_type
 */
function pg_page_bulk_tally($tally, $page, $set, $decision)
{
    $tally = array_merge(pg_page_bulk_tally_start(), (array) $tally);

    $columns = $decision['columns'];

    if ((count($columns) > 0) || (count($decision['styles']) > 0)) {
        $tally['pages']++;
    }

    if (isset($columns['page_folder'])) {
        $tally['folder']++;
    }

    if (isset($columns['page_search'])) {
        $tally[$columns['page_search'] ? 'search_on' : 'search_off']++;
    }

    if (isset($columns['sitemap'])) {
        $tally[$columns['sitemap'] ? 'sitemap_on' : 'sitemap_off']++;
    }

    foreach (array('desktop', 'mobile') as $device) {
        if (isset($decision['styles'][$device])) {
            $tally['style_' . $device]++;
        }
    }

    if ($decision['style_skipped']) {
        $tally['style_skipped']++;
    }

    // Asked on, not on before and not written: say why.
    if (isset($set['sitemap']) && ((string) $set['sitemap'] === '1')
        && ((int) $page['sitemap'] !== 1) && empty($columns['sitemap'])) {
        $noindex = array_key_exists('noindex', $columns) ? $columns['noindex'] : (int) ($page['noindex'] ?? 0);

        $tally[$noindex ? 'sitemap_noindex' : 'sitemap_type']++;
    }

    return $tally;
}

/**
 * Whether pages may be moved into a folder: it exists, and it is neither in
 * the Recycle Bin (a page moved there would have no restore record) nor the
 * Workspace's folder or below it, which the page folder picker leaves out
 * (pg_page_folder_left_out()). Pure over the rows.
 *
 * @param int   $folder_id
 * @param array $rows      folder_id => array('folder_parent' => …)
 * @param int   $workspace the Workspace's folder, 0 when there is none
 * @param int   $bin       the Recycle Bin folder, 0 when there is none
 * @return bool
 */
function pg_page_bulk_folder_ok($folder_id, $rows, $workspace, $bin)
{
    $folder_id = (int) $folder_id;

    return ($folder_id > 0)
        && isset($rows[$folder_id])
        && !pg_folder_in_bin_chain($rows, $folder_id, (int) $bin)
        && !pg_page_folder_left_out($folder_id, $rows, (int) $workspace);
}

/**
 * Marks a page changed now by a user: the Last Modified column of the Pages
 * list and its default sort read page_timestamp and page_user. For the
 * screens that write a page's columns one by one (mass_edit.php), called
 * once per changed page.
 *
 * @param int $page_id
 * @param int $user_id
 * @return bool false when the write failed
 */
function pg_page_bulk_touch($page_id, $user_id)
{
    return db_q(
        "UPDATE page SET page_timestamp = UNIX_TIMESTAMP(), page_user = ? WHERE page_id = ?",
        array((int) $user_id, (int) $page_id)) !== false;
}

/**
 * The Modify Selected dialog of the Pages list: the folder, the two styles
 * (staff only, as on the page screen), site search and sitemap.xml pick
 * lists, owned by the list's form ($form_id) and posted to edit_pages.php
 * with the list's selection. An empty pick list leaves its property alone.
 *
 * @param array  $user     validate_user()
 * @param string $form_id  the id of the list's form
 * @return string
 */
function pg_page_bulk_edit_dialog($user, $form_id)
{
    $id = 'page_bulk_edit_dialog';
    $owner = ' form="' . h($form_id) . '"';
    $default_style_label = lang('Default') . ' (' . lang('inherit') . ')';
    $output_preview_note = '';

    // While a theme other than the activated one is previewed, the styles
    // picked here go to the preview (edit_pages.php).
    $preview_theme_id = (int) ($_SESSION['software']['preview_theme_id'] ?? 0);

    if (($preview_theme_id > 0)
        && ($preview_theme_id != (int) db_value("SELECT id FROM files WHERE activated_desktop_theme = '1'"))
        && ($preview_theme_id != (int) db_value("SELECT id FROM files WHERE activated_mobile_theme = '1'"))) {
        $default_style_label = '[' . lang('Activated') . ']';
        $output_preview_note = '
                    <div class="col-12">
                        <div class="alert theme-success small mb-0" role="alert">
                            <strong class="d-block">' . lang('Theme Preview Mode') . '</strong>
                            ' . lang('You are currently previewing a Theme that is not activated, so your Page Style and Mobile Page Style selections below will update the preview Page Style, and not the activated Page Style.  The preview Page Styles that you set here will be activated when you activate the Theme.') . '
                        </div>
                    </div>';
    }

    $select = function ($name, $label, $options, $width = 'sm:col-6') use ($id, $owner) {
        return '
                    <div class="col-12 ' . $width . '">
                        <label class="form-label" for="' . $id . '_' . $name . '">' . $label . '</label>
                        <select class="form-control" id="' . $id . '_' . $name . '" name="' . $name . '"' . $owner . '><option value=""></option>' . $options . '</select>
                    </div>';
    };

    $include_options = '<option value="1">' . h(lang('Include')) . '</option><option value="0">' . h(lang('Exclude')) . '</option>';

    $output_style_fields = '';

    if ((int) $user['role'] < 3) {
        $output_style_fields = $output_preview_note
            . $select('edit_page_style', lang('Page Style'), select_style('', $default_style_label))
            . $select('edit_mobile_style_id', lang('Mobile Page Style'), get_mobile_style_options('', $default_style_label));
    }

    return '<dialog id="' . $id . '" class="dialog" aria-labelledby="' . $id . '_title">
            <div class="dialog-header">
                <h5 class="dialog-title" id="' . $id . '_title">' . lang('Modify Pages') . '</h5>
                <button type="button" class="btn-close no-submit" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="dialog-body">
                <p class="small fg-secondary">' . lang('You may update various properties below for the selected pages. You may leave a pick list unselected if you do not want to modify a property.') . '</p>
                <div class="row g-5">'
                    . $select('move_to_folder', lang('Folder'), pg_page_select_folder(), '')
                    . $output_style_fields
                    . $select('edit_site_search', lang('Site Search'), $include_options)
                    . $select('edit_sitemap', 'sitemap.xml', $include_options) . '
                </div>
            </div>
            <div class="dialog-footer">
                <button type="button" class="no-submit btn btn-solid theme-secondary" data-bs-dismiss="dialog">' . lang('Cancel') . '</button>
                <button type="button" class="no-submit btn btn-solid theme-primary" data-pg-page-bulk-save><i class="bi bi-check-lg me-3" aria-hidden="true"></i>' . lang('Modify Pages') . '</button>
            </div>
        </dialog>';
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
 * @return bool  false as soon as a write fails; the tag cloud is then left
 *               alone, since the search flag it counts did not change
 */
function pg_page_bulk_apply($page, $decision, $user_id, $preview_theme_id = 0)
{
    $page_id = (int) $page['page_id'];
    $columns = $decision['columns'];
    $stale = $decision['stale'];

    if ($preview_theme_id > 0) {
        foreach ($decision['styles'] as $device => $style_id) {
            $deleted = db_q(
                "DELETE FROM preview_styles
                WHERE (page_id = ?) AND (theme_id = ?) AND (device_type = ?)",
                array($page_id, (int) $preview_theme_id, (string) $device));

            if ($deleted === false) {
                return false;
            }

            // The default here is the activated style: no row.
            if (($style_id > 0) && (db_q(
                    "INSERT INTO preview_styles (page_id, theme_id, style_id, device_type)
                    VALUES (?, ?, ?, ?)",
                    array($page_id, (int) $preview_theme_id, (int) $style_id, (string) $device)) === false)) {
                return false;
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

        $updated = db_q(
            "UPDATE page
            SET
                " . implode(",\n                ", $sql) . ",
                page_timestamp = UNIX_TIMESTAMP(),
                page_user = ?
            WHERE page_id = ?", $params);

        if ($updated === false) {
            return false;
        }
    }

    if ($decision['tag_cloud'] == 'add') {
        update_tag_cloud_keywords_for_page($page_id, 1, $page['page_search_keywords'], 0, '');
    } elseif ($decision['tag_cloud'] == 'remove') {
        update_tag_cloud_keywords_for_page($page_id, 0, '', 1, $page['page_search_keywords']);
    }

    return true;
}
