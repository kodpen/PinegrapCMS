<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 25 - Comment Moderation: comments waiting for approval.
 *
 * Loaded and called by includes/dashboard/widgets.php; see that file for the
 * contract every widget follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_WIDGETS')) {
    exit;
}

function pg_dashboard_widget_25($request, $user)
{
    // ── Comment moderation ──────────────────────────────────
    //
    // An unapproved comment is invisible to the person who wrote
    // it and to everyone else, and nothing on the dashboard has
    // ever said one was waiting. view_comments.php exists, but a
    // queue nobody is reminded of is a queue that grows.
    //
    // Scope follows view_comments.php exactly: the comment's page
    // decides, through its folder. check_edit_access() returns
    // true for every folder below role 3, so only contributors
    // need the folder list — and resolving it in SQL keeps this
    // to two queries instead of one recursive access check per
    // row.
    $cm_where = "(comments.published = '0')";
    $cm_no_access = false;

    if ($user['role'] == 3) {

        $cm_folders = get_folders_that_user_has_access_to($user['id']);

        if ($cm_folders) {

            $cm_folder_ids = array();

            foreach ($cm_folders as $cm_folder_id) {
                $cm_folder_ids[] = "'" . e($cm_folder_id) . "'";
            }

            $cm_where .= " AND (page.page_folder IN (" . implode(',', $cm_folder_ids) . "))";

        } else {
            $cm_no_access = true;
        }
    }

    $cm_items = array();
    $cm_total = 0;

    if (!$cm_no_access) {

        $cm_items = db_items(
            "SELECT
                comments.id,
                comments.name,
                comments.message,
                comments.rating,
                comments.created_timestamp,
                page.page_name
             FROM comments
             LEFT JOIN page ON comments.page_id = page.page_id
             WHERE " . $cm_where . "
             ORDER BY comments.created_timestamp DESC
             LIMIT 5");

        $cm_total = (int) db_value(
            "SELECT COUNT(*)
             FROM comments
             LEFT JOIN page ON comments.page_id = page.page_id
             WHERE " . $cm_where);
    }

    $cm_rows = '';

    foreach ($cm_items as $cm_item) {

        // The stored body is rich text from the comment form.
        // Tags are stripped rather than rendered: this is a
        // three-line preview inside a card, and a stray <div> or
        // an unclosed tag would take the widget's layout with it.
        $cm_preview = trim(preg_replace('/\s+/', ' ', strip_tags($cm_item['message'])));

        if (mb_strlen($cm_preview) > 90) {
            $cm_preview = mb_substr($cm_preview, 0, 89) . '…';
        }

        $cm_stars = '';

        if ((int) $cm_item['rating'] > 0) {
            $cm_stars = '<span class="text-warning ms-1 flex-shrink-0" style="font-size:10px">'
                . str_repeat('★', min(5, (int) $cm_item['rating'])) . '</span>';
        }

        $cm_where_text = ($cm_item['page_name'] != '')
            ? $cm_item['page_name']
            : lang('Unknown');

        $cm_rows .= '
        <a class="d-block px-3 py-2 border-top text-decoration-none text-body" href="edit_comment.php?id=' . (int) $cm_item['id'] . '&amp;send_to=' . h(PATH . SOFTWARE_DIRECTORY . '/view_comments.php') . '">
            <div class="d-flex align-items-center" style="gap:6px">
                <span class="text-truncate fw-semibold" style="font-size:12px">' . h($cm_item['name'] != '' ? $cm_item['name'] : lang('Unknown')) . '</span>
                ' . $cm_stars . '
                <span class="text-muted ms-auto flex-shrink-0" style="font-size:10px">' . get_relative_time(array('timestamp' => (int) $cm_item['created_timestamp'])) . '</span>
            </div>
            <div class="text-muted" style="font-size:11px;line-height:1.35">' . h($cm_preview) . '</div>
            <div class="text-muted text-truncate" style="font-size:10px;opacity:.75">' . h($cm_where_text) . '</div>
        </a>';
    }

    if ($cm_rows === '') {
        $cm_rows = '
        <div class="text-center py-4">
            <i class="bi bi-check2-circle d-block mb-2 text-success" style="font-size:22px;opacity:.8"></i>
            <p class="text-muted mb-0" style="font-size:12px">' . lang('No comments are waiting for approval.') . '</p>
        </div>';
    }

    // The list stops at five; the number says whether that is the
    // whole queue or the top of it.
    $cm_more = '';

    if ($cm_total > count($cm_items)) {
        $cm_more = '
        <div class="px-3 py-1 text-center border-top">
            <span class="text-muted" style="font-size:10px">' . lang(array(
                'string' => '{var:1} more waiting',
                'vars'   => pg_format_number($cm_total - count($cm_items), 0),
            )) . '</span>
        </div>';
    }

    $output_data = '
    <div class="card-body p-0 d-flex flex-column" style="overflow-x:hidden;overflow-y:auto">
        <div class="d-flex align-items-center justify-content-between px-3 pt-2 pb-1">
            <span class="text-muted" style="font-size:11px">' . lang('Waiting for approval') . '</span>
            <span class="fw-semibold text-' . ($cm_total > 0 ? 'warning' : 'success') . '" style="font-size:15px">' . pg_format_number($cm_total, 0) . '</span>
        </div>
        ' . $cm_rows . '
        ' . $cm_more . '
    </div>
    <div class="card-footer border-0 bg-reset py-1 text-center">
        <a href="view_comments.php" class="text-decoration-none" style="font-size:11px">'
        . lang('Comments') . ' <i class="bi bi-arrow-right-short"></i></a>
    </div>';

    $response = array(
        'status' => 'success',
        'message' => 'Action Success',
        'data' => $output_data,
    );
    return $response;
}
