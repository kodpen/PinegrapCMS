<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 18 - File Management: images that can be made lighter and the heaviest files.
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

function pg_dashboard_widget_18($request, $user)
{
    // ── File Management ─────────────────────────────────────
    //
    // Two lists, in the order an operator acts on them: images
    // that can still be made lighter without changing how they
    // look, then the heaviest files on the site whatever their
    // type.
    //
    // Keeps the id of the Admin Notes widget it replaced, so a
    // dashboard whose stored order already contains "18" picks
    // this up in the same slot with no reset and no migration.
    //
    // Scoped to what the user may actually edit, so the widget
    // never advertises a file that answers with access denied on
    // click:
    //
    //   0 administrator  everything
    //   1 designer       everything
    //   2 manager        design files excluded — view_files.php
    //                    and optimize.php both refuse them above
    //                    role 1, so listing them would only
    //                    produce a dead end
    //   3 contributor    design files excluded, and narrowed to
    //                    the folders granted with edit rights
    //
    // Archived folders are left out for everyone, matching the
    // default view_files.php listing.

    // Above this an image is too heavy for a web page however
    // well it is compressed, so it is reported even when the row
    // is already flagged optimized. Optimizing strips metadata
    // and recompresses; it does not resize, and a 6000 px photo
    // stays a 6000 px photo. Those rows get a resize suggestion
    // instead of an optimize button, because that is the only
    // thing left that would help.
    $fm_large_image_bytes = 3 * 1024 * 1024;

    // The optimize list is the one that gets worked through, so
    // it carries the longer allowance. The size list only has to
    // answer "what is taking up the room", which the top few do.
    $fm_optimize_limit = 10;
    $fm_largest_limit  = 5;

    // Exactly what optimize_image() accepts. 'tif' is absent on
    // purpose: optimize.php answers that spelling with "we don't
    // support optimizing that type of file", so offering the
    // button for one would hand the operator a guaranteed error.
    $fm_optimizable_types = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp');

    // Everything counted as an image, which is the wider set: the
    // column stores whatever extension was uploaded, and a .tif
    // too heavy for a page is still too heavy for a page even
    // though nothing here can recompress it.
    $fm_image_types = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp');

    // Without one of these there is nothing to run the image
    // through, so the button is withheld rather than offered and
    // then failing.
    $fm_optimizer_available = (extension_loaded('imagick') || extension_loaded('gd'));

    // Printed in the shrink button's tooltip.
    $fm_image_settings = pg_image_settings();
    $fm_resize_target  = $fm_image_settings['file_max_dimension'];

    $fm_where = "(folder.folder_archived = '0')";

    // Design files belong to designers and administrators.
    if ($user['role'] > 1) {
        $fm_where .= " AND (files.design = '0')";
    }

    $fm_no_access = false;

    // Contributors see only the folders they were granted edit
    // rights on, plus those folders' children. Resolved into the
    // WHERE clause rather than by filtering rows afterwards: on a
    // site with thousands of files, reading them all to discard
    // most would make this the most expensive card on a dashboard
    // that reloads on every visit.
    if ($user['role'] == 3) {

        $fm_folders = get_folders_that_user_has_access_to($user['id']);

        if ($fm_folders) {

            $fm_folder_ids = array();

            foreach ($fm_folders as $fm_folder_id) {
                $fm_folder_ids[] = "'" . e($fm_folder_id) . "'";
            }

            $fm_where .= " AND (files.folder IN (" . implode(',', $fm_folder_ids) . "))";

        } else {
            // Granted nothing: no query can return a row this user
            // is allowed to touch.
            $fm_no_access = true;
        }
    }

    $fm_image_list = array();

    foreach ($fm_image_types as $fm_image_type) {
        $fm_image_list[] = "'" . e($fm_image_type) . "'";
    }

    $fm_image_list = implode(',', $fm_image_list);

    $fm_optimizable_list = array();

    foreach ($fm_optimizable_types as $fm_optimizable_type) {
        $fm_optimizable_list[] = "'" . e($fm_optimizable_type) . "'";
    }

    $fm_optimizable_list = implode(',', $fm_optimizable_list);

    $fm_optimize_items = array();
    $fm_largest_items  = array();
    $fm_optimize_total = 0;

    if (!$fm_no_access) {

        // One pass for both reasons a picture lands on this list.
        //
        // Never optimized, and of a type optimize.php will accept
        // — an unoptimized .tif is left out because there is no
        // action to offer for it and a row with no action is just
        // a row the operator cannot clear.
        //
        // Or over the size ceiling, whatever the flag says and
        // whatever the type: too heavy is too heavy.
        //
        // Ordered by size so the rows worth the operator's time
        // are the ones that fit.
        $fm_optimize_condition =
            "(((LOWER(files.type) IN (" . $fm_optimizable_list . ")) AND (files.optimized = '0'))
              OR ((LOWER(files.type) IN (" . $fm_image_list . ")) AND (files.size > " . (int) $fm_large_image_bytes . ")))";

        $fm_result = mysqli_query(
            db::$con,
            "SELECT
                files.id,
                files.name,
                files.type,
                files.size,
                files.optimized,
                files.optimization_percent,
                files.image_width,
                files.image_height,
                files.design
             FROM files
             LEFT JOIN folder ON files.folder = folder.folder_id
             WHERE " . $fm_where . "
               AND " . $fm_optimize_condition . "
             ORDER BY files.size DESC
             LIMIT " . (int) $fm_optimize_limit
        );

        $fm_optimize_items = $fm_result ? mysqli_fetch_items($fm_result) : array();

        // The list is capped at ten, so the count is what tells an
        // operator whether they are looking at the whole backlog
        // or the top of it.
        $fm_optimize_total = (int) db_value(
            "SELECT COUNT(*)
             FROM files
             LEFT JOIN folder ON files.folder = folder.folder_id
             WHERE " . $fm_where . "
               AND " . $fm_optimize_condition
        );

        // Every type, not just images: a 40 MB video or an
        // uncompressed PDF costs the same disk and the same
        // backup as a photo does.
        $fm_result = mysqli_query(
            db::$con,
            "SELECT
                files.id,
                files.name,
                files.type,
                files.size
             FROM files
             LEFT JOIN folder ON files.folder = folder.folder_id
             WHERE " . $fm_where . "
             ORDER BY files.size DESC
             LIMIT " . (int) $fm_largest_limit
        );

        $fm_largest_items = $fm_result ? mysqli_fetch_items($fm_result) : array();
    }

    // File names carry meaning at both ends — what it is at the
    // front, what it is at the back — so long ones lose the
    // middle rather than either edge.
    $fm_shorten = function ($fm_text, $fm_max = 30) {

        if (mb_strlen($fm_text) <= $fm_max) {
            return $fm_text;
        }

        $fm_head = (int) floor(($fm_max - 1) / 2);
        $fm_tail = $fm_max - 1 - $fm_head;

        return mb_substr($fm_text, 0, $fm_head) . '…' . mb_substr($fm_text, -$fm_tail);
    };

    $fm_optimize_rows = '';

    foreach ($fm_optimize_items as $fm_item) {

        $fm_size      = (int) $fm_item['size'];
        $fm_too_large = ($fm_size > $fm_large_image_bytes);
        $fm_can_run   = ((!$fm_item['optimized'])
            && $fm_optimizer_available
            && in_array(mb_strtolower($fm_item['type']), $fm_optimizable_types));

        // Read from the cached column only. Filling it in means
        // decoding and recompressing the image, which view_files.php
        // does deliberately and under a threshold; a dashboard that
        // every logged-in user loads is the wrong place to start
        // that work.
        $fm_percent = '';

        if ($fm_can_run
            && ($fm_item['optimization_percent'] !== null)
            && ($fm_item['optimization_percent'] !== '')
            && ((int) $fm_item['optimization_percent'] > 0)
        ) {
            $fm_percent = '<span class="ps-1" style="font-size:10px">' . (int) $fm_item['optimization_percent'] . '%</span>';
        }

        // Whether the row can be made narrower rather than only
        // lighter. Read from the cached dimension columns, never
        // measured here: this widget loads on every visit to the
        // dashboard, and opening ten image headers to draw a
        // button is exactly the kind of work that does not belong
        // on that path. An unmeasured row simply does not get the
        // button until some file screen has measured it.
        $fm_can_resize = ($fm_optimizer_available
            && in_array(mb_strtolower($fm_item['type']), $fm_optimizable_types)
            && ($fm_item['image_width'] !== null) && ($fm_item['image_width'] !== '')
            && pg_image_can_be_resized($fm_item['image_width'], $fm_item['image_height']));

        $fm_action = '';

        // send_to is a fixed keyword rather than a URL: optimize.php
        // turns it into a hard-coded address, so nothing a visitor
        // puts in the query string can become a redirect target.
        if ($fm_can_run) {

            $fm_action .= '
            <a class="btn btn-sm btn-outline-success border-0 py-0 px-1 flex-shrink-0 d-flex align-items-center"
               title="' . lang('Optimize this image') . '"
               href="optimize.php?id=' . h($fm_item['id']) . get_token_query_string_field() . '&amp;send_to=welcome"><i class="bi bi-fast-forward-circle"></i>' . $fm_percent . '</a>';
        }

        // Both buttons can appear on the same row, and that is the
        // point: compressing and shrinking are different jobs, and
        // an image can want one, the other or both. The shrink is
        // offered even when the row is already flagged optimized,
        // because compression never made anything narrower.
        if ($fm_can_resize) {

            $fm_action .= '
            <a class="btn btn-sm btn-outline-warning border-0 py-0 px-1 flex-shrink-0 d-flex align-items-center"
               title="' . lang(array('string' => 'Resize to {var:1} pixels and optimize', 'vars' => array($fm_resize_target))) . '"
               href="optimize.php?id=' . h($fm_item['id']) . get_token_query_string_field() . '&amp;mode=resize&amp;send_to=welcome"><i class="bi bi-arrows-angle-contract"></i></a>';

        } elseif (!$fm_can_run && $fm_too_large && (!$fm_item['design'])) {

            // Heavy, already compressed, and not wide enough for
            // the shrink to help — so the only thing left is a
            // person deciding what to do with it.
            //
            // Design files are excluded even for administrators:
            // image_editor_edit.php refuses to save over one at
            // any role, so the link would open an editor whose
            // save button always fails. The row still carries the
            // size warning, it just has nothing to offer.
            $fm_action .= '
            <a class="btn btn-sm btn-outline-secondary border-0 py-0 px-1 flex-shrink-0 d-flex align-items-center"
               title="' . lang(array('string' => 'Edit this image with {var:1}', 'vars' => array(lang('Image Editor')))) . '"
               href="image_editor_edit.php?file_name=' . rawurlencode($fm_item['name']) . '&amp;send_to=' . h(PATH . SOFTWARE_DIRECTORY . '/welcome.php') . '"><i class="bi bi-brush"></i></a>';
        }

        $fm_note = '';

        if ($fm_too_large) {
            $fm_note = '
            <div class="text-warning" style="font-size:10px;line-height:1.3">' . lang('The file is very large, please make it smaller if possible.') . '</div>';
        }

        $fm_optimize_rows .= '
        <div class="mb-2">
            <div class="d-flex align-items-center" style="gap:6px">
                <span class="text-truncate flex-fill" style="font-size:12px" title="' . h($fm_item['name']) . '">' . h($fm_shorten($fm_item['name'])) . '</span>
                <span class="text-muted flex-shrink-0" style="font-size:11px">' . h(convert_bytes_to_string($fm_size, 1)) . '</span>
                ' . $fm_action . '
            </div>
            ' . $fm_note . '
        </div>';
    }

    if ($fm_optimize_rows === '') {
        $fm_optimize_rows = '
        <div class="text-center py-3">
            <i class="bi bi-check2-circle d-block mb-1 text-success" style="font-size:22px;opacity:.8"></i>
            <p class="text-muted mb-0" style="font-size:12px">' . lang('Congratulations, there are no files that need optimizing.') . '</p>
        </div>';
    }

    // Bars are scaled against the biggest file on the list rather
    // than an absolute ceiling: with one 200 MB archive present,
    // every other bar would round away to nothing.
    $fm_peak = 0;

    foreach ($fm_largest_items as $fm_item) {
        if ((int) $fm_item['size'] > $fm_peak) {
            $fm_peak = (int) $fm_item['size'];
        }
    }

    $fm_largest_rows = '';

    foreach ($fm_largest_items as $fm_item) {

        $fm_size  = (int) $fm_item['size'];
        $fm_width = ($fm_peak > 0) ? (int) round(100 * $fm_size / $fm_peak) : 0;

        if ($fm_width < 3) {
            $fm_width = 3;
        }

        // One neutral colour for every bar. A large video or
        // archive is not a fault the way an unoptimized photo is,
        // and colouring it as one would train the operator to
        // ignore the warning that does mean something.
        $fm_largest_rows .= '
        <div class="mb-2">
            <div class="d-flex align-items-center justify-content-between" style="gap:6px">
                <a class="text-truncate text-decoration-none" style="font-size:12px"
                   title="' . h($fm_item['name']) . '"
                   href="edit_file.php?id=' . h($fm_item['id']) . '&amp;send_to=' . h(PATH . SOFTWARE_DIRECTORY . '/welcome.php') . '">' . h($fm_shorten($fm_item['name'])) . '</a>
                <span class="text-muted flex-shrink-0" style="font-size:11px">' . h(convert_bytes_to_string($fm_size, 1)) . '</span>
            </div>
            <div class="progress mt-1" style="height:4px;background:rgba(0,0,0,.06)">
                <div class="progress-bar bg-secondary" style="width:' . $fm_width . '%"></div>
            </div>
        </div>';
    }

    if ($fm_largest_rows === '') {
        $fm_largest_rows = '
        <div class="text-center py-3">
            <i class="bi bi-folder2-open d-block mb-1" style="font-size:22px;opacity:.35"></i>
            <p class="text-muted mb-0" style="font-size:12px">' . lang('No files found.') . '</p>
        </div>';
    }

    // Said out loud only when it applies, so the absent buttons
    // read as a server limitation rather than a broken widget.
    $fm_optimizer_note = '';

    if ((!$fm_optimizer_available) && $fm_optimize_items) {
        $fm_optimizer_note = '
        <div class="px-3 pb-2">
            <div class="text-muted" style="font-size:10px">' . lang('Image optimization is not available on this server.') . '</div>
        </div>';
    }

    $output_data = '
    <div class="card-body p-0 d-flex flex-column" style="overflow-x:hidden;overflow-y:auto">
        <div class="d-flex align-items-center justify-content-between px-3 pt-2 pb-1">
            <span class="text-muted" style="font-size:11px">' . lang('Needs optimizing') . '</span>
            <span class="text-muted" style="font-size:10px">' . pg_format_number($fm_optimize_total, 0) . '</span>
        </div>
        <div class="px-3 pb-1">' . $fm_optimize_rows . '</div>
        ' . $fm_optimizer_note . '
        <div class="d-flex align-items-center justify-content-between px-3 pt-2 pb-1 border-top">
            <span class="text-muted" style="font-size:11px">' . lang('Largest files') . '</span>
            <span class="text-muted" style="font-size:10px">' . lang(array('string' => 'Top {var:1}', 'vars' => pg_format_number($fm_largest_limit, 0))) . '</span>
        </div>
        <div class="px-3 pb-2">' . $fm_largest_rows . '</div>
    </div>
    <div class="card-footer border-0 bg-reset py-1 text-center">
        <a href="view_files.php" class="text-decoration-none" style="font-size:11px">'
        . lang('Files') . ' <i class="bi bi-arrow-right-short"></i></a>
    </div>';

    $response = array(
        'status' => 'success',
        'message' => 'Action Success',
        'data' => $output_data,
    );
    return $response;
}
