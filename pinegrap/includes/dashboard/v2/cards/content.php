<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Content and design card: the four pages edited last, each with its
 * state (draft, scheduled with its time, or on the site) and when it was
 * saved, opening where the page is edited (pg_page_edit_url()); and a link
 * to the Visual Editor for those who may build designs. A contributor sees
 * only pages in folders they may edit; pages in the Recycle Bin are left
 * out for everyone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * A page's state on the card.
 *
 * @param bool $draft      the page is in the drafts folder
 * @param int  $publish_at its scheduled publish time, 0 for none
 * @param int  $now
 * @return string draft, scheduled or live
 */
function pg_dashboard_v2_page_state($draft, $publish_at, $now)
{
    if (!$draft) {
        return 'live';
    }

    return ((int) $publish_at > (int) $now) ? 'scheduled' : 'draft';
}

function pg_dashboard_v2_card_content($user, $period, $compare)
{
    $role = (int) $user['role'];

    if (($role >= 3) && !no_acl_check($user['id'])) {
        return array('status' => 'denied');
    }

    $now = time();
    $scheduled = pg_schema_has('page', 'publish_at');
    $tree = pg_multi_page_design_ready() ? "(page.page_tree_json <> '') AS has_tree" : "0 AS has_tree";

    // A contributor's pages are filtered by folder after the read, so the
    // read goes deeper, to a fixed ceiling, before the filter.
    $scan = ($role >= 3) ? 60 : 4;

    $rows = db_items(
        "SELECT page.page_id, page.page_name, page.page_title, page.page_folder, page.page_style, page.page_timestamp,
                " . ($scheduled ? 'page.publish_at' : '0 AS publish_at') . ", " . $tree . "
         FROM page
         WHERE 1 = 1" . pg_designer_not_binned_sql() . "
         ORDER BY page.page_timestamp DESC, page.page_id DESC
         LIMIT " . (int) $scan);

    $states = array(
        'draft'     => lang('Draft'),
        'live'      => lang('Live'),
        'scheduled' => lang('Scheduled'),
    );

    $pages = array();

    foreach ((array) $rows as $row) {
        if (($role >= 3) && !pg_folder_edit_access((int) $row['page_folder'], (int) $user['id'], $role)) {
            continue;
        }

        $state = pg_dashboard_v2_page_state(pg_page_is_draft((int) $row['page_folder']), (int) $row['publish_at'], $now);
        $title = trim((string) $row['page_title']);

        $pages[] = array(
            'text'  => ($title !== '') ? $title : (string) $row['page_name'],
            'state' => $state,
            'chip'  => ($state === 'scheduled')
                ? get_absolute_time(array('timestamp' => (int) $row['publish_at'], 'format' => 'plain_text', 'size' => 'short'))
                : $states[$state],
            'note'  => ($state === 'scheduled')
                ? $states['scheduled']
                : get_relative_time(array('timestamp' => (int) $row['page_timestamp'], 'format' => 'plain_text')),
            'url'   => pg_page_edit_url($row),
        );

        if (count($pages) >= 4) {
            break;
        }
    }

    $designer = ($role < 2);

    return array('status' => 'success', 'data' => array(
        'pages' => $pages,
        'empty' => empty($pages) ? lang('No pages edited yet.') : '',
        'url'   => $designer ? 'view_system_styles.php' : 'view_pages.php',
        'link'  => $designer ? lang('Open the Visual Editor') : lang('All pages'),
    ));
}
