<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Redirects: the rules that send old addresses on to their new place
 * (includes/fn/redirects.php). The list of every rule with how often it was
 * used, the deletion of the rules picked in it, and every rule as a CSV file
 * (?export=csv) in the form import_redirects.php reads back. One rule is
 * written on edit_redirect.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$user = validate_user();
validate_area_access($user, 'manager');

include_once('liveform.class.php');
$liveform = new liveform('view_redirects');

$redirects_url = PATH . SOFTWARE_DIRECTORY . '/view_redirects.php';
$redirects_ready = pg_redirects_ready();

// "Delete Selected" posts the ticked rows (backend.src.js names the action).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_token_field();

    $redirects_action = (isset($_POST['action']) && is_string($_POST['action'])) ? $_POST['action'] : '';

    if (($redirects_action === 'delete') && $redirects_ready) {

        $redirects_ids = (isset($_POST['redirects']) && is_array($_POST['redirects'])) ? $_POST['redirects'] : array();
        $redirects_deleted = pg_redirect_delete($redirects_ids);

        if ($redirects_deleted > 0) {
            log_activity(lang(array('string' => '{var:1} redirect(s) deleted', 'vars' => array($redirects_deleted))), $_SESSION['sessionusername']);
            $liveform->add_notice(h(lang(array('string' => '{var:1} redirect(s) deleted.', 'vars' => array($redirects_deleted)))));
        }
    }

    go($redirects_url);
}

// Every rule as a CSV file, oldest first.
if ($redirects_ready && isset($_GET['export']) && ($_GET['export'] === 'csv')) {

    $redirects_csv = pg_redirect_csv(pg_redirect_rules());

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="redirects-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    header('Content-Length: ' . strlen($redirects_csv));

    echo $redirects_csv;
    exit();
}

// The columns this list sorts by, in the order the table shows them.
$sort_columns = array(
    'from'     => lang('Old Address'),
    'to'       => lang('New Address'),
    'code'     => lang('Type'),
    'hits'     => lang('Times Used'),
    'last_hit' => lang('Last Used'),
    'created'  => lang('Created'),
);

$sort_default = array('sort' => 'created', 'order' => 'desc');

$list_state = pg_list_state('view_redirects', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

$order_by = pg_list_order_by($sort_levels, array(
    'from'     => 'redirects.from_path',
    'to'       => 'redirects.to_url',
    'code'     => 'redirects.code',
    'hits'     => 'redirects.hits',
    'last_hit' => 'redirects.last_hit',
    'created'  => 'redirects.created_at',
));

$output_rows = '';
$redirects_count = 0;

if ($redirects_ready) {

    $redirects = db_items(
        "SELECT
            redirects.id,
            redirects.from_path,
            redirects.to_url,
            redirects.code,
            redirects.is_pattern,
            redirects.hits,
            redirects.last_hit,
            redirects.created_at,
            user.user_username AS created_username
        FROM redirects
        LEFT JOIN user ON redirects.created_by = user.user_id
        ORDER BY " . (($order_by !== '') ? $order_by . ', ' : '') . "redirects.id DESC");

    $redirects_count = count($redirects);

    foreach ($redirects as $redirect) {

        $redirect_id = (int) $redirect['id'];
        $is_pattern = !empty($redirect['is_pattern']);

        // An exact address can be opened to see the redirect at work; a
        // pattern has no one address to open.
        $output_visit = '';

        if (!$is_pattern) {
            $output_visit = '<a class="m-1 btn-data-control btn btn-outline-secondary border-2" title="' . h(lang('Open the old address')) . '" href="' . h(PATH . ltrim(encode_url_path($redirect['from_path']), '/')) . '" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>';
        }

        $output_from = h($redirect['from_path']);

        if ($is_pattern) {
            $output_from .= ' <span class="badge text-bg-secondary fw-normal">' . h(lang('Wildcard')) . '</span>';
        }

        $output_last_hit = ((int) $redirect['last_hit'] > 0)
            ? get_relative_time(array('timestamp' => (int) $redirect['last_hit']))
            : '<span class="text-body-secondary">' . h(lang('Never')) . '</span>';

        $output_created_by = ((string) $redirect['created_username'] !== '')
            ? ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($redirect['created_username']))))
            : '';

        $output_rows .=
            '<tr>
                <td class="select-all align-middle text-start"><input class="form-check-input" type="checkbox" name="redirects[]" value="' . $redirect_id . '"></td>
                <td class="align-middle text-start action-buttons">
                    <a class="m-1 btn-data-control btn btn-outline-primary border-2" title="' . h(lang('Edit')) . '" href="edit_redirect.php?id=' . $redirect_id . '"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                    ' . $output_visit . '
                </td>
                <td class="align-middle chart_label text-break">' . $output_from . '</td>
                <td class="align-middle text-break">' . h($redirect['to_url']) . '</td>
                <td class="align-middle text-nowrap">' . h(pg_redirect_code_label($redirect['code'])) . '</td>
                <td class="align-middle text-end">' . h(pg_format_number((int) $redirect['hits'], 0)) . '</td>
                <td class="align-middle">' . $output_last_hit . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => (int) $redirect['created_at'])) . $output_created_by . '</td>
            </tr>';
    }
}

$output_not_ready = '';

if (!$redirects_ready) {
    $output_not_ready =
        '<div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>' . h(lang('The redirect tables are added by the software update. Run the update to use redirects.')) . '
        </div>';
}

$output_toolbar_tools = '';

if ($redirects_ready) {
    $output_toolbar_tools =
        '<a class="btn btn-sm btn-primary rounded-pill px-3" href="edit_redirect.php"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('Create Redirect')) . '</a>
        <a class="btn btn-sm btn-ghost" href="import_redirects.php"><i class="bi bi-upload me-1" aria-hidden="true"></i>' . h(lang('Import')) . '</a>
        ' . (($redirects_count > 0) ? '<a class="btn btn-sm btn-ghost" href="view_redirects.php?export=csv"><i class="bi bi-download me-1" aria-hidden="true"></i>' . h(lang('Export')) . '</a>' : '') . '
        <div class="pg-toolbar-grow"></div>
        ' . pg_list_sort_menu('view_redirects.php', $sort_columns, $sort_levels, $sort_default);
}

$output_table = '';

if ($redirects_ready) {
    $output_table =
        '<div class="card my-4">
            <div class="card-body p-0 position-relative">
                <form name="form" action="view_redirects.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="action">
                    <table class="chart table-hover table" style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" title="' . h(lang('Select/Deselect All')) . '" type="checkbox" id="select_all">
                                    </div>
                                </th>
                                <th class="noVis">' . lang('Action') . '</th>
                                <th>' . get_column_heading(lang('Old Address'), $sort_levels, '', '', 'from') . '</th>
                                <th>' . get_column_heading(lang('New Address'), $sort_levels, '', '', 'to') . '</th>
                                <th>' . get_column_heading(lang('Type'), $sort_levels, '', '', 'code') . '</th>
                                <th class="text-end">' . get_column_heading(lang('Times Used'), $sort_levels, '', '', 'hits') . '</th>
                                <th>' . get_column_heading(lang('Last Used'), $sort_levels, '', '', 'last_hit') . '</th>
                                <th>' . get_column_heading(lang('Created'), $sort_levels, '', '', 'created') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                    <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="' . h(lang('Selected redirects')) . '">
                        <div class="container">
                            <div class="btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                <button type="button" value="Delete Selected" class="btn mb-1 mt-1 btn-danger disabled" data-confirm-content="' . h(lang(array('string' => 'WARNING: Selected {var:1} will be permanently deleted.', 'vars' => array(lang('redirects'))))) . '"><i class="bi bi-trash me-2" aria-hidden="true"></i>' . h(lang('Delete Selected')) . '</button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>';
}

print
pg_page_shell(
    array(
        'title' => lang('Redirects'),
        'extra classes' => 'page',
        'icon' => 'page',
        'heading' => lang('Redirects'),
        'heading_description' => lang('Old addresses sent on to their new place. A redirect answers only an address where no page, file or short link is found, so a live page always wins.'),
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . $output_not_ready . '
            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . h(lang('Button Bar')) . '">
                ' . $output_toolbar_tools . '
            </nav>
            ' . $output_table . '
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
