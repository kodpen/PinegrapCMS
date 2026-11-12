<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the person's workflow tables: their own and those shared with
 * them or in a channel they read. Without ?id= the list and a new table;
 * with it one table: its rows a page at a time, the row form (?row= opens a
 * row in it), the columns, the name and description, the shares and the
 * rows in the table's bin; the list shows the person's deleted tables too
 * (includes/workspace/wtable_bin.php). Every
 * change is a form posted with the token and goes through the store
 * (includes/workspace/wtables.php, wtable_rows.php, wtable_shares.php),
 * which decides who may do what; wtable_screen.php turns fields into cells.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
include_once('liveform.class.php');

pg_upload_refuse_discarded_body();

$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$viewer = ws_screen_gate($user);

if (!ws_wtables_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('workspace_wtables');

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace_wtables.php';

// The list of tables has no parent; a table goes back to that list.
$cancel = false;
$breadcrumb = array();

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_token_field();

    pg_request_single_values(array('wt_action', 'wt_table', 'wt_column', 'wt_row', 'wt_share', 'wt_bin', 'revision', 'name', 'description', 'type', 'target', 'level', 'value', 'note', 'starter', 'samples'));

    $action = (string) ($_POST['wt_action'] ?? '');
    $table = ws_wtable((int) ($_POST['wt_table'] ?? 0));
    $table_url = $table ? $self_url . '?id=' . (int) $table['id'] : $self_url;
    $back = $table_url;
    $field = function ($name) {
        return isset($_POST[$name]) && is_string($_POST[$name]) ? $_POST[$name] : '';
    };
    $refused = function ($result) use ($liveform) {
        $liveform->add_error(h(ws_wtable_screen_refusal_text($result)));
    };

    switch ($action) {

        case 'table_create':
            // An empty table, or one from a starter with its columns made.
            $starter = $field('starter');

            if (($starter !== '') && isset(ws_wtable_starters()[$starter])) {
                $result = ws_wtable_starter_create($viewer, $starter, $field('name'), $field('description'), $field('samples') === '1');

                if ($result['ok']) {
                    $liveform->add_notice(h(lang('The table was made from the starter.')));
                    $back = $self_url . '?id=' . (int) $result['table_id'];
                } else {
                    $refused($result);
                    $back = $self_url;
                }

                break;
            }

            $result = ws_wtable_create($viewer, array('name' => $field('name'), 'description' => $field('description')));

            if ($result['ok']) {
                $liveform->add_notice(h(lang('The table was made: add its columns.')));
                $back = $self_url . '?id=' . (int) $result['table_id'] . '#ws-wt-columns';
            } else {
                $refused($result);
                $back = $self_url;
            }

            break;

        case 'table_update':
            $result = ws_wtable_update($viewer, $table, array('name' => $field('name'), 'description' => $field('description')));
            $result['ok'] ? $liveform->add_notice(h(lang('The table was saved.'))) : $refused($result);
            break;

        case 'table_delete':
            $result = ws_wtable_delete($viewer, $table);

            if ($result['ok']) {
                log_activity(lang(array('string' => 'workspace table ({var:1}) was deleted', 'vars' => $table['name'])), (string) ($_SESSION['sessionusername'] ?? ''));
                $liveform->add_notice(h(ws_wtable_bin_ready()
                    ? lang(array('string' => 'The table {var:1} was deleted. It is in Deleted tables, where you can restore it.', 'vars' => $table['name']))
                    : lang(array('string' => 'The table {var:1} was deleted.', 'vars' => $table['name']))));
                $back = $self_url . (ws_wtable_bin_ready() ? '#ws-wt-deleted' : '');
            } else {
                $refused($result);
            }

            break;

        case 'table_restore':
            // A table of the person's own back from the bin, whole.
            $binned = ws_wtable_binned((int) ($_POST['wt_table'] ?? 0));
            $result = ws_wtable_restore($viewer, $binned);

            if ($result['ok']) {
                log_activity(lang(array('string' => 'workspace table ({var:1}) was restored', 'vars' => $binned['name'])), (string) ($_SESSION['sessionusername'] ?? ''));
                $liveform->add_notice(h(lang(array('string' => 'The table {var:1} was restored.', 'vars' => $binned['name']))));
                $back = $self_url . '?id=' . (int) $binned['id'];
            } else {
                $refused($result);
                $back = $self_url . '#ws-wt-deleted';
            }

            break;

        case 'column_create':
        case 'column_update':
            $column = ($action === 'column_update') ? ws_wtable_column((int) ($table['id'] ?? 0), (int) ($_POST['wt_column'] ?? 0)) : null;
            $type = $column ? $column['type'] : $field('type');
            $input = array(
                'name'    => $field('name'),
                'options' => ws_wtable_screen_options_from_form(
                    $type,
                    (isset($_POST['options']) && is_array($_POST['options'])) ? $_POST['options'] : array(),
                    $column,
                    ($column && ($type === 'status')) ? ws_wtable_column_held_values((int) $table['id'], (int) $column['id']) : array()
                ),
            );

            if ($column) {
                $result = ws_wtable_column_update($viewer, $table, $column, $input);
            } else {
                $result = ws_wtable_column_create($viewer, $table, $input + array('type' => $type));
            }

            $result['ok'] ? $liveform->add_notice(h(ws_wtable_screen_column_saved_text($result))) : $refused($result);
            $back .= '#ws-wt-columns';
            break;

        case 'column_retype':
            $column = ws_wtable_column((int) ($table['id'] ?? 0), (int) ($_POST['wt_column'] ?? 0));
            $result = ws_wtable_column_retype($viewer, $table, $column, ws_wtable_screen_retype_input($field('to'), $field('decimals'), (array) $column));

            if ($result['ok']) {
                $liveform->add_notice(h(lang('The column\'s type was changed.')));
            } else {
                $refused($result);

                // The cells that do not convert are listed under the column.
                if (!empty($result['faults'])) {
                    $_SESSION['software']['ws_wtable_retype'] = array(
                        'table'  => (int) $table['id'],
                        'column' => (int) $column['id'],
                        'count'  => count($result['faults']),
                        'faults' => array_slice($result['faults'], 0, 100),
                    );
                }
            }

            $back .= '#ws-wt-columns';
            break;

        case 'column_delete':
            $column = ws_wtable_column((int) ($table['id'] ?? 0), (int) ($_POST['wt_column'] ?? 0));
            $result = ws_wtable_column_delete($viewer, $table, $column);
            $result['ok'] ? $liveform->add_notice(h(lang('The column was deleted.'))) : $refused($result);
            $back .= '#ws-wt-columns';
            break;

        case 'row_save':
            $columns = $table ? ws_wtable_columns((int) $table['id']) : array();
            $row = ((int) ($_POST['wt_row'] ?? 0) > 0) ? ws_wtable_row((int) ($table['id'] ?? 0), (int) $_POST['wt_row']) : null;
            $typed = (isset($_POST['cell']) && is_array($_POST['cell'])) ? $_POST['cell'] : array();
            $cells = array();
            $values = array();

            foreach ($columns as $id => $column) {
                // A row link the form could not offer (a table the person
                // does not read) is not sent, and the row keeps it.
                if (!ws_wtable_type_stores($column['type']) || (($column['type'] === 'row') && !array_key_exists($id, $typed))) {
                    continue;
                }

                $raw = $typed[$id] ?? '';
                $values[$id] = is_string($raw) ? mb_substr($raw, 0, 20000) : '';
                $cells[$id] = ws_wtable_screen_cell_from_form($column, $raw);
            }

            if ($row) {
                $result = ws_wtable_row_update($viewer, $table, $row, array('cells' => $cells, 'revision' => $field('revision')));
            } elseif ((int) ($_POST['wt_row'] ?? 0) > 0) {
                $result = ws_wtable_refusal('not_found', '', 404);
            } else {
                $result = ws_wtable_row_create($viewer, $table, array('cells' => $cells));
            }

            if ($result['ok']) {
                $liveform->add_notice(h($row ? lang('The row was saved.') : lang('The row was added.')));
                $back .= '#ws-wt-rows';
            } else {
                $refused($result);

                // What was typed comes back into the form with each refused
                // cell named, unless the row is gone.
                if ($result['error'] !== 'not_found') {
                    $_SESSION['software']['ws_wtable_row'] = array(
                        'table'  => (int) $table['id'],
                        'row'    => $row ? (int) $row['id'] : 0,
                        'values' => ($result['error'] === 'changed_meanwhile') ? null : $values,
                        'errors' => (array) ($result['errors'] ?? array()),
                    );
                    $back = $table_url . ($row ? '&row=' . (int) $row['id'] : '') . '#ws-wt-row-form';
                }
            }

            break;

        case 'row_delete':
            $row = ws_wtable_row((int) ($table['id'] ?? 0), (int) ($_POST['wt_row'] ?? 0));
            $result = ws_wtable_row_delete($viewer, $table, $row, array('revision' => $field('revision')));
            $result['ok'] ? $liveform->add_notice(h(lang('The row was deleted.'))) : $refused($result);
            $back .= '#ws-wt-rows';
            break;

        case 'row_restore':
            // A row back from the table's bin, at the end of the table.
            $bin = ws_wtable_bin_row((int) ($table['id'] ?? 0), (int) ($_POST['wt_bin'] ?? 0));
            $result = ws_wtable_row_restore($viewer, $table, $bin);

            if (!$result['ok']) {
                $refused($result);
                $back .= '#ws-wt-deleted-rows';
                break;
            }

            $liveform->add_notice(h($result['id_changed']
                ? lang('The row was restored under a new number: the links other rows had to it were not restored.')
                : lang('The row was restored at the end of the table.')));
            $back .= '#ws-wt-row-' . (int) $result['row_id'];
            break;

        case 'bin_row_delete':
            $bin = ws_wtable_bin_row((int) ($table['id'] ?? 0), (int) ($_POST['wt_bin'] ?? 0));
            $result = ws_wtable_bin_row_delete($viewer, $table, $bin);
            $result['ok'] ? $liveform->add_notice(h(lang('The row was deleted for good.'))) : $refused($result);
            $back .= '#ws-wt-deleted-rows';
            break;

        case 'share_set':
            $target = explode(':', $field('target'), 2);
            $result = ws_wtable_share_set($viewer, $table, array(
                'target_type' => $target[0],
                'target_id'   => $target[1] ?? '',
                'level'       => $field('level'),
            ));
            $result['ok'] ? $liveform->add_notice(h(lang('The table was shared.'))) : $refused($result);
            $back .= '#ws-wt-shares';
            break;

        case 'share_remove':
            $result = ws_wtable_share_remove($viewer, $table, (int) ($_POST['wt_share'] ?? 0));
            $result['ok'] ? $liveform->add_notice(h(lang('The share was taken back.'))) : $refused($result);
            $back .= '#ws-wt-shares';
            break;

        case 'import_read':
            // A file read and kept, its columns shown for confirmation.
            $file = $_FILES['file'] ?? null;
            $append = ($table !== null);
            $back = $append ? $table_url . '&import=1#ws-wt-import' : $self_url . '?import=1#ws-wt-import';

            if ($append && !ws_wtable_may(ws_wtable_access($viewer, $table), 'write')) {
                $refused(ws_wtable_refusal('not_found', '', 404));
                $back = $self_url;
            } elseif (($upload_refusal = pg_upload_file_refusal($file, pg_upload_limits()['file_max'])) !== '') {
                $liveform->add_error(h($upload_refusal));
                $back = $append ? $table_url : $self_url;
            } elseif (!is_array($file) || !is_string($file['tmp_name'] ?? null) || ((int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK) || !is_uploaded_file($file['tmp_name'])) {
                $liveform->add_error(h(lang('Choose a file to import.')));
                $back = $append ? $table_url : $self_url;
            } else {
                $read = ws_wtable_import_file_table($file['tmp_name'], pg_basename((string) $file['name']), ws_wtable_limits()['rows'] + 1);

                if (!$read['ok']) {
                    $liveform->add_error(h($read['error']));
                    $back = $append ? $table_url : $self_url;
                } elseif (count($read['table']) < 2) {
                    $liveform->add_error(h(lang('The file has a header row but no rows under it.')));
                    $back = $append ? $table_url : $self_url;
                } elseif (count($read['table'][0]) > ws_wtable_limits()['columns']) {
                    $refused(ws_wtable_refusal('too_many_columns', '', 409));
                    $back = $append ? $table_url : $self_url;
                } elseif (!ws_wtable_import_keep($read, pg_basename((string) $file['name']), $append ? (int) $table['id'] : 0)) {
                    $liveform->add_error(h(lang('The file could not be read.')));
                    $back = $append ? $table_url : $self_url;
                }
            }

            break;

        case 'import_note':
            // One table of a note the person may read, kept like a file.
            $append = ($table !== null);
            $fallback = $append ? $table_url : $self_url;
            $back = $append ? $table_url . '&import=1#ws-wt-import' : $self_url . '?import=1#ws-wt-import';
            $chosen = preg_match('/^([0-9]+):([0-9]+)$/', $field('note'), $match) ? $match : null;

            if ($append && !ws_wtable_may(ws_wtable_access($viewer, $table), 'write')) {
                $refused(ws_wtable_refusal('not_found', '', 404));
                $back = $self_url;
                break;
            }

            $read = $chosen ? ws_wtable_import_note_read($viewer, (int) $chosen[1], (int) $chosen[2], ws_wtable_limits()['rows'] + 1) : null;

            if (!$read) {
                $liveform->add_error(h(lang('Choose a note table to import.')));
                $back = $fallback;
            } elseif (!$read['ok']) {
                $liveform->add_error(h($read['error']));
                $back = $fallback;
            } elseif (count($read['table']) < 2) {
                $liveform->add_error(h(lang('The table has a header row but no rows under it.')));
                $back = $fallback;
            } elseif (!ws_wtable_import_keep($read, $read['name'], $append ? (int) $table['id'] : 0, true)) {
                $liveform->add_error(h(lang('The table could not be read.')));
                $back = $fallback;
            }

            break;

        case 'import_write':
            $kept = ws_wtable_import_kept();
            $chosen = function ($name) {
                return (isset($_POST[$name]) && is_array($_POST[$name])) ? $_POST[$name] : array();
            };

            if (!$kept || ($kept['table'] !== ($table ? (int) $table['id'] : 0))) {
                $liveform->add_error(h(lang('The file is no longer at hand: choose it again.')));
                $back = $table ? $table_url : $self_url;
                break;
            }

            @set_time_limit(300);

            if ($table) {
                // Rows added: each file column into the table column chosen.
                $columns = ws_wtable_columns((int) $table['id']);
                $map = array();

                foreach ($chosen('col_map') as $index => $column_id) {
                    $column_id = (int) (is_string($column_id) ? $column_id : 0);

                    if (isset($kept['names'][(int) $index], $columns[$column_id]) && ws_wtable_type_stores($columns[$column_id]['type']) && !in_array($column_id, $map, true)) {
                        $map[(int) $index] = $column_id;
                    }
                }

                $checked = empty($map) ? null : ws_wtable_import_check($viewer, $table, $kept, array('map' => $map));

                if (!$checked || !$checked['ok']) {
                    $liveform->add_error(h($checked ? ws_wtable_screen_error_text($checked['error']) : lang('Choose at least one column to fill.')));
                    $back = $table_url . '&import=1#ws-wt-import';
                    break;
                }

                $result = array('ok' => true, 'table_id' => (int) $table['id'], 'report' => ws_wtable_import_rows($viewer, $table, $kept['rows'], $map));
            } else {
                // A new table: each kept file column in the type chosen.
                $plan = array();
                $names = $chosen('col_name');
                $types = $chosen('col_type');

                foreach ($chosen('col_keep') as $index => $keep) {
                    if (($keep === '1') && isset($kept['names'][(int) $index])) {
                        $name = (isset($names[$index]) && is_string($names[$index]) && (trim($names[$index]) !== '')) ? trim($names[$index]) : $kept['names'][(int) $index];
                        $type = (isset($types[$index]) && is_string($types[$index])) ? $types[$index] : 'text';
                        $plan[] = array('index' => (int) $index, 'name' => $name, 'type' => $type);
                    }
                }

                $checked = empty($plan) ? null : ws_wtable_import_check($viewer, null, $kept, array('name' => $field('name'), 'plan' => $plan));

                if (!$checked || !$checked['ok']) {
                    $liveform->add_error(h($checked ? (isset($checked['column']) ? $checked['column'] . ': ' : '') . ws_wtable_screen_error_text($checked['error']) : lang('Choose at least one column to fill.')));
                    $back = $self_url . '?import=1#ws-wt-import';
                    break;
                }

                $result = ws_wtable_import_create($viewer, $field('name'), $plan, $kept['rows']);

                if (!$result['ok']) {
                    $liveform->add_error(h((isset($result['column']) ? $result['column'] . ': ' : '') . ws_wtable_screen_error_text($result['error'])));
                    $back = $self_url . '?import=1#ws-wt-import';
                    break;
                }
            }

            ws_wtable_import_drop();
            $_SESSION['software']['ws_wtable_import_report'] = array('table' => (int) $result['table_id'], 'more' => $kept['more']) + $result['report'];
            $liveform->add_notice(h(ws_wtable_import_report_text($result['report'])));
            $back = $self_url . '?id=' . (int) $result['table_id'] . '#ws-wt-import-report';
            break;

        case 'import_cancel':
            ws_wtable_import_drop();
            $back = $table ? $table_url : $self_url;
            break;

        case 'flow_from_table':
            // The rows compiled once into a new draft flow of the person's,
            // opened on the canvas; what the rows could not give is told here.
            $result = ws_wtable_flow_create($viewer, $table, (isset($_POST['flow_map']) && is_array($_POST['flow_map'])) ? $_POST['flow_map'] : array());

            if (!$result['ok']) {
                $liveform->add_error(h($result['error']));
                break;
            }

            $flow_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace_flows.php?flow=' . (int) $result['flow_id'];
            $notes = ws_wtable_flow_notes($result['compiled']);

            if (empty($notes)) {
                $back = $flow_url;
                break;
            }

            $liveform->add_notice(h(lang('A draft flow was made from the rows.')) . ' ' . h(implode(' ', $notes))
                . ' <a href="' . h($flow_url) . '">' . h(lang('Open the draft flow')) . '</a>');
            break;

        case 'row_status':
            // A card moved on the board: the status cell alone, on the
            // revision the card was drawn with, answered in JSON.
            $columns = $table ? ws_wtable_columns((int) $table['id']) : array();
            $status_id = (int) ($_POST['wt_column'] ?? 0);
            $row = ws_wtable_row((int) ($table['id'] ?? 0), (int) ($_POST['wt_row'] ?? 0));

            if (!isset(ws_wtable_board_status_columns($columns)[$status_id])) {
                $result = ws_wtable_refusal('not_found', '', 404);
            } elseif (!$row) {
                $result = ws_wtable_refusal('not_found', '', 404);
            } else {
                $result = ws_wtable_row_update($viewer, $table, $row, ws_wtable_board_move_input($status_id, $field('value'), $field('revision')));
            }

            header('Content-Type: application/json; charset=utf-8');
            echo pg_json_encode_answer($result['ok']
                ? array('ok' => true, 'message' => lang('The row was saved.'))
                : array('ok' => false, 'message' => ws_wtable_screen_error_text($result['error'])));
            exit;

        default:
            $back = $self_url;
    }

    go($back);

}

/* ---------------------------------------------------------------------------
   Reads
   --------------------------------------------------------------------------- */

$state = (isset($_SESSION['software']['ws_wtable_row']) && is_array($_SESSION['software']['ws_wtable_row'])) ? $_SESSION['software']['ws_wtable_row'] : null;

unset($_SESSION['software']['ws_wtable_row']);

$retype_state = (isset($_SESSION['software']['ws_wtable_retype']) && is_array($_SESSION['software']['ws_wtable_retype'])) ? $_SESSION['software']['ws_wtable_retype'] : null;

unset($_SESSION['software']['ws_wtable_retype']);

$table_id = (is_string($_GET['id'] ?? null) && ((int) $_GET['id'] > 0)) ? (int) $_GET['id'] : 0;
$table = ($table_id > 0) ? ws_wtable_for($viewer, $table_id) : null;

if (($table_id > 0) && !$table) {
    output_error(h(lang('That table could not be found.')) . ' <a href="' . h($self_url) . '">' . h(lang('Back')) . '</a>');
}

// The board's cards, read by the board component.
if ($table && (($_GET['board_json'] ?? '') === '1')) {
    $columns = ws_wtable_columns((int) $table['id']);
    $status_id = ws_wtable_board_column_id($columns, $_GET['by'] ?? 0);
    $rows = ($status_id > 0) ? ws_wtable_joined_fill($viewer, $columns, ws_wtable_rows((int) $table['id'], $columns)) : array();
    $lookup_targets = ws_wtable_joined_targets($viewer, $columns);
    $person_ids = ws_wtable_lookup_person_ids($lookup_targets, $rows);

    foreach ($rows as $row) {
        foreach ($columns as $column_id => $column) {
            if (($column['type'] === 'person') && !empty($row['cells'][$column_id])) {
                $person_ids[] = (int) $row['cells'][$column_id];
            }
        }
    }

    $people = ws_wtable_people_with_lookups(ws_wtable_people_with_links(empty($person_ids) ? array() : ws_people(array_unique($person_ids)), ws_wtable_link_texts($viewer, $columns, $rows)), $lookup_targets);
    $board = ws_wtable_board($columns, $rows, $status_id, $people, ws_wtable_may($table['access'], 'write'), (($_GET['all'] ?? '') === '1'));

    header('Content-Type: application/json; charset=utf-8');
    echo pg_json_encode_answer($board);
    exit;
}

// The table as an Excel workbook: one sheet, the heading row in bold, every
// row in the table's order (ws_wtable_export_sheet()). Viewing the table is
// enough, as the screen shows the same cells.
if ($table && (($_GET['download'] ?? '') === 'xlsx')) {
    $columns = ws_wtable_columns((int) $table['id']);
    $rows = ws_wtable_joined_fill($viewer, $columns, ws_wtable_rows((int) $table['id'], $columns));
    $lookup_targets = ws_wtable_joined_targets($viewer, $columns);
    $person_ids = ws_wtable_lookup_person_ids($lookup_targets, $rows);

    foreach ($rows as $row) {
        foreach ($columns as $column_id => $column) {
            if (($column['type'] === 'person') && !empty($row['cells'][$column_id])) {
                $person_ids[] = (int) $row['cells'][$column_id];
            }
        }
    }

    $sheet = ws_wtable_export_sheet($columns, $rows, ws_wtable_people_with_lookups(ws_wtable_people_with_links(empty($person_ids) ? array() : ws_people(array_unique($person_ids)), ws_wtable_link_texts($viewer, $columns, $rows)), $lookup_targets));
    $directory = PG_FUNCTIONS_DIR . '/data/temp';
    $path = (is_dir($directory) || @mkdir($directory, 0755, true)) ? @tempnam($directory, 'wtable_') : false;

    if (($path === false) || !pg_xlsx_write_plain($sheet['headings'], $sheet['rows'], $path, (string) $table['name'])) {
        if ($path !== false) {
            @unlink($path);
        }

        output_error(h(lang('The file could not be written. Check that data/temp is writable.')) . ' <a href="' . h($self_url . '?id=' . (int) $table['id']) . '">' . h(lang('Back')) . '</a>');
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . ws_wtable_export_file_name((string) $table['name'], (int) $table['id']) . '"');
    header('Content-Length: ' . (int) filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($path);
    @unlink($path);
    exit;
}

$types = ws_wtable_column_types();
$board_script = '';
$levels = ws_wtable_share_levels();
$access_labels = array('owner' => lang('Yours'), 'edit' => lang('Can edit'), 'view' => lang('Can view'));

// A file brought in: the form that uploads it, the confirmation of what
// it becomes, and the report of the rows refused.
$kept = ((($_GET['import'] ?? '') === '1') && ($table === null || ws_wtable_may($table['access'], 'write'))) ? ws_wtable_import_kept() : null;
$kept = ($kept && ($kept['table'] === ($table ? (int) $table['id'] : 0))) ? $kept : null;
$report = $_SESSION['software']['ws_wtable_import_report'] ?? null;
$report = (is_array($report) && $table && ((int) $report['table'] === (int) $table['id'])) ? $report : null;

if ($report) {
    unset($_SESSION['software']['ws_wtable_import_report']);
}

$import_upload = function ($table_id, $intro) use ($self_url) {
    return '
                    <form method="post" action="' . h($self_url) . '" class="disable_shortcut" enctype="multipart/form-data">
                        ' . get_token_field() . '
                        <input type="hidden" name="wt_action" value="import_read">
                        <input type="hidden" name="wt_table" value="' . (int) $table_id . '">
                        <p class="small fg-secondary">' . h($intro) . '</p>
                        <div class="mb-3">
                            <label class="form-label small mb-1" for="ws_wt_import_file">' . h(lang('File')) . '</label>
                            <input type="file" class="form-control form-control-sm" name="file" id="ws_wt_import_file" accept=".csv,.xlsx,.xlsm,.xls,.ods" required>
                            ' . pg_upload_limit_hint() . '
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline theme-primary rounded-pill px-5">' . h(lang('Read the file')) . '</button>
                    </form>';
};

// A note's table brought in the same way: one choice per table.
$note_choices = null;
$import_note = function ($table_id, $intro) use ($self_url, $viewer, &$note_choices) {
    if ($note_choices === null) {
        $note_choices = ws_wtable_import_note_choices($viewer);
    }

    if (empty($note_choices)) {
        return '<p class="small fg-secondary mb-0">' . h(lang('None of your notes holds a table yet.')) . '</p>';
    }

    $options = '';

    foreach ($note_choices as $choice) {
        $options .= '<option value="' . (int) $choice['note'] . ':' . (int) $choice['block'] . '">' . h($choice['label']) . '</option>';
    }

    return '
                    <form method="post" action="' . h($self_url) . '" class="disable_shortcut">
                        ' . get_token_field() . '
                        <input type="hidden" name="wt_action" value="import_note">
                        <input type="hidden" name="wt_table" value="' . (int) $table_id . '">
                        <p class="small fg-secondary">' . h($intro) . '</p>
                        <div class="mb-3">
                            <label class="form-label small mb-1" for="ws_wt_import_note">' . h(lang('Note table')) . '</label>
                            <select class="form-control form-control-sm" name="note" id="ws_wt_import_note" required>' . $options . '</select>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline theme-primary rounded-pill px-5">' . h(lang('Read the table')) . '</button>
                    </form>';
};

$import_card = '';

if ($kept) {
    $columns_now = $table ? ws_wtable_columns((int) $table['id']) : array();
    $match = ws_wtable_import_match($kept['names'], $columns_now);
    $type_labels = ws_wtable_column_types();
    $lines = '';

    foreach ($kept['names'] as $index => $name) {
        $values = array();

        foreach ($kept['rows'] as $row) {
            $values[] = (string) ($row[$index] ?? '');
        }

        $sample = array_slice(array_values(array_filter($values, 'strlen')), 0, 3);
        $sample_text = implode(' · ', array_map(function ($value) {
            return mb_strimwidth($value, 0, 40, '…');
        }, $sample));

        if ($table) {
            $choices = '<option value="0">' . h(lang('(not imported)')) . '</option>';

            foreach ($columns_now as $column_id => $column) {
                if (ws_wtable_type_stores($column['type'])) {
                    $choices .= '<option value="' . (int) $column_id . '"' . ((($match['map'][$index] ?? 0) === (int) $column_id) ? ' selected' : '') . '>' . h($column['name']) . '</option>';
                }
            }

            $lines .= '<tr><td class="fw-semibold">' . h($name) . '</td>'
                . '<td><select class="form-control form-control-sm" name="col_map[' . (int) $index . ']" aria-label="' . h(lang(array('string' => 'Column for {var:1}', 'vars' => $name))) . '">' . $choices . '</select></td>'
                . '<td class="small fg-secondary">' . h($sample_text) . '</td></tr>';
        } else {
            $guess = ws_wtable_import_guess($values);
            $types_list = '';

            foreach (ws_wtable_import_types() as $type) {
                $types_list .= '<option value="' . h($type) . '"' . (($type === $guess['type']) ? ' selected' : '') . '>' . h($type_labels[$type] ?? $type) . '</option>';
            }

            $lines .= '<tr><td><input class="check" type="checkbox" name="col_keep[' . (int) $index . ']" value="1" checked aria-label="' . h(lang(array('string' => 'Import {var:1}', 'vars' => $name))) . '"></td>'
                . '<td><input type="text" class="form-control form-control-sm" name="col_name[' . (int) $index . ']" value="' . h($name) . '" maxlength="' . (int) ws_wtable_limits()['name'] . '" aria-label="' . h(lang('Name')) . '"></td>'
                . '<td><select class="form-control form-control-sm" name="col_type[' . (int) $index . ']" aria-label="' . h(lang('Type')) . '">' . $types_list . '</select></td>'
                . '<td class="small fg-secondary">' . h($sample_text) . '</td></tr>';
        }
    }

    $head = $table
        ? '<th scope="col">' . h($kept['note'] ? lang('In the note') : lang('In the file')) . '</th><th scope="col">' . h(lang('Into the column')) . '</th><th scope="col">' . h(lang('First values')) . '</th>'
        : '<th scope="col" aria-label="' . h(lang('Import')) . '"></th><th scope="col">' . h(lang('Column')) . '</th><th scope="col">' . h(lang('Type')) . '</th><th scope="col">' . h(lang('First values')) . '</th>';

    $import_card = '
    <div class="card mb-5 border-primary" id="ws-wt-import">
        <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-file-earmark-arrow-up me-1" aria-hidden="true"></i>'
            . h($kept['note']
                ? lang(array('string' => '{var:1} rows from the note “{var:2}”', 'vars' => array(count($kept['rows']), $kept['file'])))
                : lang(array('string' => '{var:1} rows from “{var:2}”', 'vars' => array(count($kept['rows']), $kept['file'])))) . '</h2></div>
        <div class="card-body">
            ' . ($kept['more'] ? '<div class="alert theme-warning small py-3">' . h(lang(array('string' => 'The file has more rows than a table holds: only the first {var:1} are read.', 'vars' => ws_wtable_limits()['rows']))) . '</div>' : '') . '
            <p class="small fg-secondary">' . h($kept['note'] ? ($table
                ? lang('Each column of the note\'s table fills the table column of its name; choose another, or leave it out. Rows the table refuses are listed afterwards.')
                : lang('Each column of the note\'s table becomes a column of the new table, in the type its values suggest. Change a name or type, or leave a column out.')) : ($table
                ? lang('Each file column fills the table column of its name; choose another, or leave it out. Rows the table refuses are listed afterwards.')
                : lang('Each column of the file becomes a column of the new table, in the type its values suggest. Change a name or type, or leave a column out.'))) . '</p>
            <form method="post" action="' . h($self_url) . '" class="disable_shortcut">
                ' . get_token_field() . '
                <input type="hidden" name="wt_action" value="import_write">
                <input type="hidden" name="wt_table" value="' . ($table ? (int) $table['id'] : 0) . '">
                ' . ($table ? '' : '<div class="mb-3" style="max-width: 32rem;"><label class="form-label small mb-1" for="ws_wt_import_name">' . h(lang('Table name')) . '</label>
                    <input type="text" class="form-control form-control-sm" name="name" id="ws_wt_import_name" value="' . h(mb_substr($kept['note'] ? $kept['file'] : pg_file_name_stem($kept['file']), 0, ws_wtable_limits()['name'])) . '" maxlength="' . (int) ws_wtable_limits()['name'] . '" required></div>') . '
                <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr>' . $head . '</tr></thead><tbody>' . $lines . '</tbody></table></div>
                <div class="d-flex gap-3 mt-5">
                    <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5">' . h(lang('Import')) . '</button>
                    <button type="button" class="btn btn-sm btn-text theme-secondary ws-wt-action" data-action="import_cancel">' . h(lang('Cancel')) . '</button>
                </div>
            </form>
        </div>
    </div>';
}

if ($report) {
    $lines = '';

    foreach ((array) $report['refused'] as $refusal) {
        $why = array();

        foreach ((array) $refusal['errors'] as $column_name => $code) {
            $why[] = $column_name . ': ' . ws_wtable_screen_error_text($code);
        }

        $lines .= '<li>' . h(lang(array('string' => 'Row {var:1}', 'vars' => (int) $refusal['line']))) . ' — '
            . h(empty($why) ? ws_wtable_screen_error_text($refusal['error']) : implode('; ', $why)) . '</li>';
    }

    $import_card = '
    <div class="card mb-5" id="ws-wt-import-report">
        <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-file-earmark-check me-1" aria-hidden="true"></i>'
            . h(ws_wtable_import_report_text($report)) . '</h2></div>
        <div class="card-body small">
            ' . ((int) $report['refused_count'] > 0 ? '<p class="fg-danger mb-3">' . h(lang(array('string' => '{var:1} rows were refused (the header is row 1):', 'vars' => (int) $report['refused_count']))) . '</p><ul class="mb-0">' . $lines . '</ul>'
                . ((int) $report['refused_count'] > count((array) $report['refused']) ? '<p class="fg-secondary mt-3 mb-0">' . h(lang('Only the first 100 are listed.')) . '</p>' : '')
                : '<p class="mb-0">' . h(lang('No row was refused.')) . '</p>') . '
            ' . (!empty($report['more']) ? '<p class="fg-warning mt-3 mb-0">' . h(lang(array('string' => 'The file has more rows than a table holds: only the first {var:1} are read.', 'vars' => ws_wtable_limits()['rows']))) . '</p>' : '') . '
        </div>
    </div>';
}

if (!$table) {

    /* -----------------------------------------------------------------------
       The list
       ----------------------------------------------------------------------- */

    $scope = in_array($_GET['scope'] ?? '', array('mine', 'shared'), true) ? (string) $_GET['scope'] : 'all';
    $tables = ws_wtables_reached($viewer, $scope);

    $tabs = '';

    foreach (array('all' => lang('All'), 'mine' => lang('Mine'), 'shared' => lang('Shared with me')) as $key => $label) {
        $tabs .= '<a class="btn btn-sm ' . (($key === $scope) ? 'btn-solid theme-secondary' : 'btn-text theme-secondary') . '" href="' . h($self_url . (($key === 'all') ? '' : '?scope=' . $key)) . '"'
            . (($key === $scope) ? ' aria-current="page"' : '') . '>' . h($label) . '</a>';
    }

    $lines = '';

    foreach ($tables as $item) {
        $lines .= '
            <tr>
                <td><a class="fw-semibold" href="' . h($self_url . '?id=' . (int) $item['id']) . '"><i class="bi bi-table me-1" aria-hidden="true"></i>' . h($item['name']) . '</a>'
                    . (((string) $item['description'] !== '') ? '<div class="small fg-secondary text-truncate" style="max-width: 32rem;">' . h($item['description']) . '</div>' : '') . '</td>
                <td class="text-end">' . (int) $item['row_count'] . '</td>
                <td><span class="badge ' . (($item['access'] === 'owner') ? 'theme-primary' : 'theme-secondary') . ' fw-normal">' . h($access_labels[$item['access']] ?? '') . '</span></td>
                <td class="small fg-secondary text-nowrap">' . h(ws_time_label((int) $item['updated_at'])) . '</td>
            </tr>';
    }

    $list = ($lines === '')
        ? pg_empty_state('bi-table', ($scope === 'shared') ? lang('Nothing is shared with you yet') : lang('No table yet'), lang('A table keeps rows of your own columns: text, numbers, money, dates, people, statuses and formulas. Share it with a colleague or a channel.'))
        : '<div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th scope="col">' . h(lang('Name')) . '</th><th scope="col" class="text-end">' . h(lang('Rows')) . '</th><th scope="col">' . h(lang('Access')) . '</th><th scope="col">' . h(lang('Changed')) . '</th></tr></thead>
            <tbody>' . $lines . '</tbody>
        </table></div>';

    // The person's own deleted tables, each restored whole until the bin
    // lets it go.
    $deleted_card = '';
    $binned = ($scope === 'shared') ? array() : ws_wtables_binned($viewer);

    if (!empty($binned)) {
        $deleted_lines = '';

        foreach ($binned as $item) {
            $deleted_lines .= '
            <tr>
                <td><i class="bi bi-table me-1 fg-secondary" aria-hidden="true"></i>' . h($item['name']) . '</td>
                <td class="text-end">' . (int) $item['row_count'] . '</td>
                <td class="small fg-secondary text-nowrap">' . h(ws_time_label((int) $item['deleted_at'])) . '</td>
                <td class="small fg-secondary text-nowrap">' . h(date('d.m.Y', (int) $item['expires_at'])) . '</td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-text theme-primary ws-wt-action" data-action="table_restore" data-table="' . (int) $item['id'] . '">'
                    . '<i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>' . h(lang('Restore')) . '</button></td>
            </tr>';
        }

        $deleted_card = '
            <div class="card mb-5" id="ws-wt-deleted">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . h(lang('Deleted tables')) . '</h2></div>
                <div class="card-body">
                    <p class="small fg-secondary">' . h(lang(array('string' => 'A deleted table stays here for {var:1} days, out of everybody\'s reach; then it is deleted for good.', 'vars' => ws_wtable_bin_days()))) . '</p>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                        <thead><tr><th scope="col">' . h(lang('Name')) . '</th><th scope="col" class="text-end">' . h(lang('Rows')) . '</th><th scope="col">' . h(lang('Deleted')) . '</th><th scope="col">' . h(lang('Deleted for good on')) . '</th><th scope="col" aria-label="' . h(lang('Actions')) . '"></th></tr></thead>
                        <tbody>' . $deleted_lines . '</tbody>
                    </table></div>
                </div>
            </div>';
    }

    $starter_options = '<option value="">' . h(lang('An empty table')) . '</option>';

    foreach (ws_wtable_starters() as $key => $starter) {
        $starter_options .= '<option value="' . h($key) . '">' . h($starter['name']) . '</option>';
    }

    $body = $import_card . '
    <div class="row g-5">
        <div class="col-12 xl:col-8">
            <div class="card mb-5">
                <div class="card-header d-flex flex-wrap gap-3 align-items-center">' . $tabs . '</div>
                <div class="card-body">' . $list . '</div>
            </div>' . $deleted_card . '
        </div>
        <div class="col-12 xl:col-4">
            <div class="card mb-5" id="ws-wt-new">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('New table')) . '</h2></div>
                <div class="card-body">
                    <form method="post" action="' . h($self_url) . '" class="disable_shortcut">
                        ' . get_token_field() . '
                        <input type="hidden" name="wt_action" value="table_create">
                        <div class="mb-3">
                            <label class="form-label small mb-1" for="ws_wt_new_name">' . h(lang('Name')) . '</label>
                            <input type="text" class="form-control form-control-sm" name="name" id="ws_wt_new_name" maxlength="' . (int) ws_wtable_limits()['name'] . '">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small mb-1" for="ws_wt_new_description">' . h(lang('Description')) . '</label>
                            <textarea class="form-control form-control-sm" name="description" id="ws_wt_new_description" rows="2" maxlength="' . (int) ws_wtable_limits()['description'] . '"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small mb-1" for="ws_wt_new_starter">' . h(lang('Start from')) . '</label>
                            <select class="form-control form-control-sm" name="starter" id="ws_wt_new_starter">' . $starter_options . '</select>
                            <div class="form-text">' . h(lang('A starter makes its columns; the name and description are its own when left empty.')) . '</div>
                        </div>
                        <div class="form-field mb-5">
                            <input class="check" type="checkbox" name="samples" value="1" id="ws_wt_new_samples" checked>
                            <label class="small" for="ws_wt_new_samples">' . h(lang('Add sample rows to a starter')) . '</label>
                        </div>
                        <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5">' . h(lang('Create')) . '</button>
                    </form>
                </div>
            </div>
            <div class="card mb-5" id="ws-wt-import-new">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-file-earmark-arrow-up me-1" aria-hidden="true"></i>' . h(lang('Table from a file')) . '</h2></div>
                <div class="card-body">' . $import_upload(0, lang('A CSV, Excel or OpenDocument file: its first row names the columns.')) . '</div>
            </div>
            <div class="card mb-5" id="ws-wt-import-note">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-journal-text me-1" aria-hidden="true"></i>' . h(lang('Table from a note')) . '</h2></div>
                <div class="card-body">' . $import_note(0, lang('A table in one of your notes: its first row names the columns. The note stays as it is.')) . '</div>
            </div>
        </div>
    </div>';

    $toolbar = '
    <nav id="button_bar" class="pg-toolbar navigation mb-5" aria-label="' . lang('Button Bar') . '">
        <a class="btn btn-sm btn-text theme-primary" href="#ws-wt-new" onclick="document.getElementById(\'ws_wt_new_name\').focus();"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('New table')) . '</a>
    </nav>';

    $title = lang('Tables');
    $description = lang('Your own tables and those shared with you.');

} else {

    /* -----------------------------------------------------------------------
       One table
       ----------------------------------------------------------------------- */

    $id = (int) $table['id'];
    $table_url = $self_url . '?id=' . $id;
    $can_write = ws_wtable_may($table['access'], 'write');
    $can_manage = ws_wtable_may($table['access'], 'manage');
    $columns = ws_wtable_columns($id);
    $after = (is_string($_GET['after'] ?? null) && ((int) $_GET['after'] > 0)) ? (int) $_GET['after'] : 0;
    $team = ws_team_members();
    $team_names = array();

    foreach ($team as $person) {
        $team_names[(int) $person['id']] = array('name' => (string) $person['name']);
    }

    // The query bar: conditions as typed, read the screen's way into the
    // values the conditions compare (money to minor units, a status by its
    // label), and a sort. A query that does not fit the table is named and
    // the rows show unfiltered.
    $screen_query = ws_wtable_query_from_get($_GET);
    $query = array('where' => array()) + $screen_query;
    unset($query['typed']);

    foreach ($screen_query['typed'] as $line) {
        $column = $columns[(string) $line['column']] ?? null;
        $query['where'][] = array('column' => $line['column'], 'op' => $line['op'],
            'value' => is_array($column) ? ws_wtable_query_screen_value($column, $line['value'], $team_names, defined('DATE_FORMAT') ? (string) DATE_FORMAT : '') : $line['value']);
    }

    $query_refusal = ws_wtable_query_active($query) ? ws_wtable_query_refusal($columns, $query) : '';
    $query_on = ws_wtable_query_active($query) && ($query_refusal === '');
    $query_params = ws_wtable_query_params($screen_query);
    $total_columns = array_filter($columns, function ($column) {
        return ws_wtable_total_type($column['type']);
    });
    $totals_all = !empty($total_columns) && (($_GET['totals'] ?? '') === 'all');

    if ($totals_all) {
        $query_params['totals'] = 'all';
    }

    $query_qs = empty($query_params) ? '' : '&' . http_build_query($query_params);
    $from = 0;
    $query_rows = null;

    if ($query_on) {
        // Filtered or sorted: worked out over every row, paged by an offset.
        $from = (is_string($_GET['from'] ?? null) && ((int) $_GET['from'] > 0)) ? (int) $_GET['from'] : 0;
        $after = 0;
        $query_rows = ws_wtable_query_rows($columns, ws_wtable_rows($id, $columns), $query);
        $page = ws_wtable_query_page($query_rows, $from, 100);
    } else {
        $page = ws_wtable_rows_page($id, $columns, $after, 100);

        if ($totals_all) {
            $query_rows = ws_wtable_rows($id, $columns);
        }
    }

    // What a link to this page of rows carries: the query and the page.
    $page_qs = $query_qs . ($query_on ? ($from ? '&from=' . $from : '') : ($after ? '&after=' . $after : ''));

    // The lookups of the rows shown, worked out for the reader.
    $page['rows'] = ws_wtable_joined_fill($viewer, $columns, $page['rows']);
    $lookup_targets = ws_wtable_joined_targets($viewer, $columns);
    $person_ids = ws_wtable_lookup_person_ids($lookup_targets, $page['rows']);

    foreach ($page['rows'] as $row) {
        foreach ($columns as $column_id => $column) {
            if (($column['type'] === 'person') && !empty($row['cells'][$column_id])) {
                $person_ids[] = (int) $row['cells'][$column_id];
            }
        }
    }

    // The rows in the table's bin, for whoever may change the table.
    $bin_days = ws_wtable_bin_ready() ? ws_wtable_bin_days() : 0;
    $bin_rows = $can_write ? (array) ws_wtable_bin_rows($viewer, $table) : array();

    foreach ($bin_rows as $row) {
        foreach ($columns as $column_id => $column) {
            if (($column['type'] === 'person') && !empty($row['cells'][$column_id])) {
                $person_ids[] = (int) $row['cells'][$column_id];
            }
        }

        $person_ids[] = (int) $row['deleted_by'];
    }

    // The texts of the rows that row link cells point at, and how many rows
    // link to each row shown, for its delete confirmation.
    $people = ws_wtable_people_with_lookups(ws_wtable_people_with_links(empty($person_ids) ? array() : ws_people(array_unique($person_ids)),
        ws_wtable_link_texts($viewer, $columns, array_merge($page['rows'], $bin_rows))), $lookup_targets);
    $link_counts = $can_write ? ws_wtable_link_counts($id, array_column($page['rows'], 'id')) : array();

    // The rows.
    $head = '';

    foreach ($columns as $column_id => $column) {
        // A heading sorts by its column: up, then down, then the table's order.
        $sorted = ((int) $screen_query['sort'] === (int) $column_id) ? (string) $screen_query['dir'] : '';
        $next = array('' => array('sort' => (string) (int) $column_id), 'asc' => array('sort' => (string) (int) $column_id, 'dir' => 'desc'), 'desc' => array())[$sorted];
        $sort_params = array_diff_key($query_params, array('sort' => 1, 'dir' => 1)) + $next;
        $icon = array('' => '', 'asc' => ' <i class="bi bi-sort-up" aria-hidden="true"></i>', 'desc' => ' <i class="bi bi-sort-down" aria-hidden="true"></i>')[$sorted];

        $shown_type = (string) (ws_wtable_joined_type($column['type']) ? ($lookup_targets[(string) $column_id]['type'] ?? '') : $column['type']);
        $head .= '<th scope="col"' . (in_array($shown_type, array('number', 'money', 'formula'), true) ? ' class="text-end"' : '')
            . (($sorted !== '') ? ' aria-sort="' . (($sorted === 'asc') ? 'ascending' : 'descending') . '"' : '') . '>'
            // A lookup or rollup is worked out after the rows are sorted: it sorts nothing.
            . (ws_wtable_joined_type($column['type']) ? h($column['name']) : '<a class="fg-reset text-decoration-none" href="' . h($table_url . (empty($sort_params) ? '' : '&' . http_build_query($sort_params))) . '#ws-wt-rows" title="' . h(lang('Sort by this column')) . '">' . h($column['name']) . $icon . '</a>') . '</th>';
    }

    // The totals line: sum, average, smallest and largest of each number,
    // money and formula column, over the rows shown or every row the query
    // keeps.
    $totals_foot = '';
    $totals_switch = '';

    if (!empty($total_columns) && !empty($page['rows'])) {
        $totals = ws_wtable_rows_totals($columns, $totals_all ? (array) $query_rows : $page['rows']);
        $names = array('sum' => lang('Sum'), 'avg' => lang('Average'), 'min' => lang('Smallest'), 'max' => lang('Largest'));

        foreach ($names as $key => $label) {
            $cells = '';

            foreach ($columns as $column_id => $column) {
                $cells .= isset($totals[(string) $column_id])
                    ? '<td class="text-end text-nowrap">' . h(ws_wtable_query_total_text($column, $totals[(string) $column_id][$key])) . '</td>'
                    : '<td></td>';
            }

            $totals_foot .= '<tr class="small"><th scope="row" class="fw-normal fg-secondary text-end text-nowrap">' . h($label) . '</th>' . $cells . ($can_write ? '<td></td>' : '') . '</tr>';
        }

        $totals_foot = '<tfoot class="ws-wt-totals">' . $totals_foot . '</tfoot>';
        $totals_switch = '<div class="btn-group btn-group-sm" role="group" aria-label="' . h(lang('Totals over')) . '">';

        foreach (array('page' => lang('Rows shown'), 'all' => $query_on ? lang('All matching rows') : lang('All rows')) as $key => $label) {
            $here = (($key === 'all') === $totals_all);
            $switch_params = array_diff_key($query_params, array('totals' => 1)) + (($key === 'all') ? array('totals' => 'all') : array());
            $totals_switch .= '<a class="btn btn-text theme-secondary' . ($here ? ' active' : '') . '" href="' . h($table_url . (empty($switch_params) ? '' : '&' . http_build_query($switch_params)) . ($query_on ? ($from ? '&from=' . $from : '') : ($after ? '&after=' . $after : ''))) . '#ws-wt-rows"'
                . ($here ? ' aria-current="true"' : '') . '>' . h($label) . '</a>';
        }

        $totals_switch .= '</div>';
    }

    $lines = '';

    foreach ($page['rows'] as $row) {
        $line = '';

        foreach ($columns as $column_id => $column) {
            $value = $row['cells'][$column_id] ?? null;
            $text = ws_wtable_screen_cell_text($column, $value, $people);
            $shown_type = (string) (ws_wtable_joined_type($column['type']) ? ($lookup_targets[(string) $column_id]['type'] ?? '') : $column['type']);
            $class = in_array($shown_type, array('number', 'money', 'formula'), true) ? ' class="text-end text-nowrap"' : '';

            if (($column['type'] === 'formula') && isset($row['formula_errors'][$column_id])) {
                $cell = '<span class="fg-danger" title="' . h((string) $row['formula_errors'][$column_id]) . '">#' . h(strtoupper((string) $row['formula_errors'][$column_id])) . '</span>';
            } elseif (($column['type'] === 'status') && ($text !== '')) {
                $color = '';

                foreach ((array) ($column['options']['choices'] ?? array()) as $choice) {
                    if (((string) $choice['key'] === (string) $value) && ((string) ($choice['color'] ?? '') !== '')) {
                        $color = ' style="background: ' . h($choice['color']) . '; color: #fff;"';
                    }
                }

                $cell = '<span class="badge theme-secondary fw-normal"' . $color . '>' . h($text) . '</span>';
            } else {
                $cell = nl2br(h($text));
            }

            $line .= '<td' . $class . '>' . $cell . '</td>';
        }

        $tools = '';

        if ($can_write) {
            $tools = '<a class="btn btn-sm btn-text theme-secondary" href="' . h($table_url . '&row=' . (int) $row['id'] . $page_qs) . '#ws-wt-row-form" title="' . h(lang('Edit')) . '" aria-label="' . h(lang('Edit')) . '"><i class="bi bi-pencil" aria-hidden="true"></i></a>'
                . ws_wtable_screen_action_button(array('action' => 'row_delete', 'row' => (int) $row['id'], 'revision' => (int) $row['revision']), lang('Delete'), 'bi-trash',
                    ws_wtable_screen_delete_confirm('row', (string) $table['name'], $bin_days, (int) ($link_counts[(int) $row['id']] ?? 0)));
        }

        $lines .= '<tr id="ws-wt-row-' . (int) $row['id'] . '"><td class="small fg-secondary text-end">' . ws_wtable_row_label($row) . '</td>' . $line . ($can_write ? '<td class="text-end text-nowrap">' . $tools . '</td>' : '') . '</tr>';
    }

    $pager = '';

    if (($after > 0) || ($from > 0)) {
        $pager .= '<a class="btn btn-sm btn-text theme-secondary" href="' . h($table_url . $query_qs) . '#ws-wt-rows"><i class="bi bi-chevron-double-left me-1" aria-hidden="true"></i>' . h(lang('First page')) . '</a>';
    }

    if ($page['next'] !== null) {
        $pager .= '<a class="btn btn-sm btn-text theme-secondary ms-auto" href="' . h($table_url . $query_qs . ($query_on ? '&from=' : '&after=') . (int) $page['next']) . '#ws-wt-rows">' . h(lang('Next page')) . '<i class="bi bi-chevron-right ms-1" aria-hidden="true"></i></a>';
    }

    if (empty($columns)) {
        $rows_body = pg_empty_state('bi-layout-three-columns', lang('No column yet'), $can_write ? lang('Add the table\'s columns first: their names and what each holds.') : '');
    } elseif (($lines === '') && $query_on) {
        $rows_body = pg_empty_state('bi-funnel', lang('No row meets the conditions'));
    } elseif ($lines === '') {
        $rows_body = pg_empty_state('bi-table', lang('No row yet'));
    } else {
        $rows_body = '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th scope="col" class="text-end">#</th>' . $head . ($can_write ? '<th scope="col" aria-label="' . h(lang('Actions')) . '"></th>' : '') . '</tr></thead>
            <tbody>' . $lines . '</tbody>' . $totals_foot . '
        </table></div>' . ((($pager !== '') || ($totals_switch !== '')) ? '<div class="d-flex flex-wrap align-items-center gap-3 mt-3">' . $totals_switch . $pager . '</div>' : '');
    }

    if (!empty($columns)) {
        $row_choices = array();

        foreach ($columns as $column_id => $column) {
            if ($column['type'] === 'row') {
                $row_choices[(string) $column_id] = ws_wtable_link_choices($viewer, $column);
            }
        }

        $rows_body = ws_wtable_screen_query_bar($self_url, $id, $columns, $screen_query, $team, $query_refusal, $totals_all, $row_choices) . $rows_body;
    }

    // The row form: a new row, or the one ?row= names.
    $row_form = '';

    if ($can_write && !empty($columns)) {
        $edit_id = (is_string($_GET['row'] ?? null) && ((int) $_GET['row'] > 0)) ? (int) $_GET['row'] : 0;
        $edit = null;

        if ($edit_id > 0) {
            $stored = ws_wtable_row($id, $edit_id);
            $edit = $stored ? (ws_wtable_joined_fill($viewer, $columns, array(ws_wtable_row_out($stored, $columns)))[0] ?? null) : null;
        }

        $mine = ($state && ((int) $state['table'] === $id) && ((int) $state['row'] === ($edit ? (int) $edit['id'] : 0))) ? $state : null;
        $fields = '';

        foreach ($columns as $column_id => $column) {
            // A lookup or rollup is read from other rows: shown, never written here.
            if (ws_wtable_joined_type($column['type']) && $edit) {
                $fields .= '<div class="mb-3"><label class="form-label small mb-1" for="ws_wt_cell_' . (int) $column_id . '">' . h($column['name']) . '</label>'
                    . '<input type="text" class="form-control form-control-sm" id="ws_wt_cell_' . (int) $column_id . '" value="' . h(ws_wtable_screen_cell_text($column, $edit['cells'][$column_id] ?? null, $people)) . '" readonly>'
                    . '<div class="form-text">' . h(($column['type'] === 'rollup') ? lang('Worked out from the rows that link to this row.') : lang('Shown from the linked row; change it there.')) . '</div></div>';
            }

            if (!ws_wtable_type_stores($column['type'])) {
                continue;
            }

            $name = 'cell[' . $column_id . ']';
            $field_id = 'ws_wt_cell_' . $column_id;
            $value = ($mine && is_array($mine['values']) && array_key_exists($column_id, $mine['values']))
                ? (string) $mine['values'][$column_id]
                : ($edit ? ws_wtable_screen_cell_to_form($column, $edit['cells'][$column_id] ?? null) : '');
            $error = ($mine && isset($mine['errors'][$column_id])) ? (string) $mine['errors'][$column_id] : '';
            $invalid = ($error !== '') ? ' is-invalid' : '';
            $options = (array) $column['options'];
            $hint = '';

            switch ($column['type']) {
                case 'checkbox':
                    $input = '<div class="form-field"><input type="hidden" name="' . h($name) . '" value="0">'
                        . '<input class="check' . $invalid . '" type="checkbox" name="' . h($name) . '" id="' . $field_id . '" value="1"' . (($value === '1') ? ' checked' : '') . '>'
                        . '<label class="small" for="' . $field_id . '">' . h($column['name']) . '</label></div>';
                    break;

                case 'person':
                case 'status':
                    $choices = '<option value="">' . h(lang('(none)')) . '</option>';
                    $list = array();

                    if ($column['type'] === 'person') {
                        foreach ($team as $person) {
                            $list[(string) $person['id']] = (string) $person['name'];
                        }

                        // A colleague who left keeps showing in a row written before.
                        if (($value !== '') && !isset($list[$value])) {
                            $list[$value] = ws_wtable_screen_cell_text($column, (int) $value, ws_people(array((int) $value)));
                        }
                    } else {
                        foreach ((array) ($options['choices'] ?? array()) as $choice) {
                            $list[(string) $choice['key']] = (string) $choice['label'];
                        }
                    }

                    foreach ($list as $key => $label) {
                        $choices .= '<option value="' . h($key) . '"' . (((string) $key === $value) ? ' selected' : '') . '>' . h($label) . '</option>';
                    }

                    // A person column lists the whole team: searched in the
                    // browser once it is long (data-pg-combobox).
                    $input = '<select class="form-control form-control-sm' . $invalid . '" name="' . h($name) . '" id="' . $field_id . '"' . (($column['type'] === 'person') ? ' data-pg-combobox' : '') . '>' . $choices . '</select>';
                    break;

                case 'row':
                    // The linked table's rows by their key or first text; a
                    // table the person does not read is not listed, and its
                    // link is not sent, so the row keeps it.
                    $linked = ws_wtable_link_choices($viewer, $column);

                    if ($linked === null) {
                        $input = '<input type="text" class="form-control form-control-sm" id="' . $field_id . '" value="' . h(($value !== '') ? ws_wtable_link_unread_text() : '') . '" disabled>';
                        break;
                    }

                    $choices = '<option value="">' . h(lang('(none)')) . '</option>';

                    foreach ($linked as $key => $label) {
                        $choices .= '<option value="' . (int) $key . '"' . (((string) $key === $value) ? ' selected' : '') . '>' . h($label) . '</option>';
                    }

                    $input = '<select class="form-control form-control-sm' . $invalid . '" name="' . h($name) . '" id="' . $field_id . '" data-pg-combobox>' . $choices . '</select>';
                    break;

                case 'date':
                    // A native input keeps the ISO store the cell is saved in.
                    $input = '<input type="' . (!empty($options['time']) ? 'datetime-local' : 'date') . '" class="form-control form-control-sm' . $invalid . '" name="' . h($name) . '" id="' . $field_id . '" value="' . h($value) . '" ' . (!empty($options['time']) ? 'data-pg-datetime' : 'data-pg-date') . ' autocomplete="off">';
                    break;

                case 'text':
                case 'list':
                    // Tall enough for the lines the value has, two at least;
                    // a list takes one item a line.
                    $lines = min(8, max(2, count(preg_split('/\r\n|\r|\n/', $value))));
                    $input = '<textarea class="form-control form-control-sm' . $invalid . '" name="' . h($name) . '" id="' . $field_id . '" rows="' . $lines . '">' . h($value) . '</textarea>';

                    if ($column['type'] === 'list') {
                        $hint = lang('One item a line.');
                    }

                    break;

                default:
                    $input = '<input type="text" class="form-control form-control-sm' . $invalid . '" name="' . h($name) . '" id="' . $field_id . '" value="' . h($value) . '"'
                        . (in_array($column['type'], array('number', 'money'), true) ? ' inputmode="decimal"' : '') . '>';

                    if ($column['type'] === 'money') {
                        $hint = (string) ($options['currency'] ?? '');
                    } elseif ($column['type'] === 'link') {
                        $hint = lang('kind:id, such as contact:12');
                    }
            }

            $fields .= '<div class="mb-3">'
                . (($column['type'] !== 'checkbox') ? '<label class="form-label small mb-1" for="' . $field_id . '">' . h($column['name']) . '</label>' : '')
                . $input
                . (($error !== '') ? '<div class="invalid-feedback d-block">' . h(ws_wtable_screen_error_text($error)) . '</div>' : '')
                . (($hint !== '') ? '<div class="form-text">' . h($hint) . '</div>' : '')
                . '</div>';
        }

        $row_form = '
            <div class="card mb-5' . ($edit ? ' border-primary' : '') . '" id="ws-wt-row-form">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi ' . ($edit ? 'bi-pencil' : 'bi-plus-lg') . ' me-1" aria-hidden="true"></i>'
                    . h($edit ? lang(array('string' => 'Row {var:1}', 'vars' => ws_wtable_row_label($edit))) : lang('New row')) . '</h2></div>
                <div class="card-body">
                    ' . (($edit_id > 0) && !$edit ? '<div class="alert theme-warning small py-3">' . h(lang('That row could not be found: it may have been deleted.')) . '</div>' : '') . '
                    <form method="post" action="' . h($self_url) . '" class="disable_shortcut">
                        ' . get_token_field() . '
                        <input type="hidden" name="wt_action" value="row_save">
                        <input type="hidden" name="wt_table" value="' . $id . '">
                        <input type="hidden" name="wt_row" value="' . ($edit ? (int) $edit['id'] : 0) . '">
                        <input type="hidden" name="revision" value="' . ($edit ? (int) $edit['revision'] : 0) . '">
                        ' . (($fields !== '') ? $fields : '<p class="small fg-secondary">' . h(lang('Every column of this table is worked out by a formula.')) . '</p>') . '
                        <div class="d-flex gap-3 mt-5">
                            <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5">' . h($edit ? lang('Save') : lang('Add')) . '</button>
                            ' . ($edit || ($edit_id > 0) ? '<a class="btn btn-sm btn-text theme-secondary" href="' . h($table_url . $page_qs) . '#ws-wt-rows">' . h(lang('Cancel')) . '</a>' : '') . '
                        </div>
                    </form>
                </div>
            </div>';
    }

    // The column form fields of one type: the options it takes.
    $option_fields = function ($type, $column, $prefix) use ($viewer, $id, $columns) {
        $options = $column ? (array) $column['options'] : array();
        $out = '';

        switch ($type) {
            case 'number':
            case 'money':
                $out .= '<div class="mb-3"><label class="form-label small mb-1" for="' . $prefix . '_decimals">' . h(lang('Decimals')) . '</label>'
                    . '<input type="number" class="form-control form-control-sm" name="options[decimals]" id="' . $prefix . '_decimals" min="0" max="' . (($type === 'money') ? 3 : 6) . '" value="' . h((string) ($options['decimals'] ?? (($type === 'money') ? 2 : 0))) . '"></div>';

                if ($type === 'money') {
                    $out .= '<div class="mb-3"><label class="form-label small mb-1" for="' . $prefix . '_currency">' . h(lang('Currency')) . '</label>'
                        . '<input type="text" class="form-control form-control-sm" name="options[currency]" id="' . $prefix . '_currency" maxlength="3" placeholder="EUR" value="' . h((string) ($options['currency'] ?? '')) . '"></div>';
                }

                break;

            case 'date':
                $out .= '<div class="form-field mb-3"><input type="hidden" name="options[time]" value="0"><input class="check" type="checkbox" name="options[time]" id="' . $prefix . '_time" value="1"' . (!empty($options['time']) ? ' checked' : '') . '>'
                    . '<label class="small" for="' . $prefix . '_time">' . h(lang('With the time of day')) . '</label></div>';
                break;

            case 'status':
                $labels = array();

                foreach ((array) ($options['choices'] ?? array()) as $choice) {
                    $labels[] = (string) $choice['label'];
                }

                $out .= '<div class="mb-3"><label class="form-label small mb-1" for="' . $prefix . '_choices">' . h(lang('Choices, one a line')) . '</label>'
                    . '<textarea class="form-control form-control-sm" name="options[choices]" id="' . $prefix . '_choices" rows="3">' . h(implode("\n", $labels)) . '</textarea></div>';
                break;

            case 'link':
                $out .= '<div class="mb-3"><label class="form-label small mb-1" for="' . $prefix . '_kinds">' . h(lang('Record kinds')) . '</label>'
                    . '<input type="text" class="form-control form-control-sm" name="options[kinds]" id="' . $prefix . '_kinds" placeholder="contact, product" value="' . h(implode(', ', (array) ($options['kinds'] ?? array()))) . '">'
                    . '<div class="form-text">' . h(lang('Empty for any kind.')) . '</div></div>';
                break;

            case 'list':
                // Fixed once made, as the type is: a made column shows it.
                $of = $column ? ws_wtable_list_of($column) : 'text';
                $kinds = '';

                foreach (ws_wtable_list_kinds() as $kind => $label) {
                    $kinds .= '<option value="' . h($kind) . '"' . (($kind === $of) ? ' selected' : '') . '>' . h($label) . '</option>';
                }

                $out .= '<div class="mb-3"><label class="form-label small mb-1" for="' . $prefix . '_of">' . h(lang('Items')) . '</label>'
                    . '<select class="form-control form-control-sm" name="options[of]" id="' . $prefix . '_of"' . ($column ? ' disabled' : '') . '>' . $kinds . '</select></div>';
                break;

            case 'row':
                // The table its cells link to, among those the person reads;
                // fixed once made, as the type is.
                $linked = $column ? ws_wtable_link_table($column) : 0;
                $tables = '';

                foreach (ws_wtables_reached($viewer, 'all') as $item) {
                    $tables .= '<option value="' . (int) $item['id'] . '"' . (((int) $item['id'] === $linked) ? ' selected' : '') . '>'
                        . h(((int) $item['id'] === (int) $id) ? lang(array('string' => '{var:1} (this table)', 'vars' => $item['name'])) : $item['name']) . '</option>';
                }

                if (($linked > 0) && (strpos($tables, 'value="' . $linked . '" selected') === false)) {
                    $tables = '<option value="' . $linked . '" selected>' . h(lang('A table you do not read')) . '</option>' . $tables;
                }

                $out .= '<div class="mb-3"><label class="form-label small mb-1" for="' . $prefix . '_table">' . h(lang('Linked table')) . '</label>'
                    . '<select class="form-control form-control-sm" name="options[table]" id="' . $prefix . '_table"' . ($column ? ' disabled' : ' required') . '>' . $tables . '</select>'
                    . '<div class="form-text">' . h(lang('A cell picks one of its rows, shown by its key or first text.')) . '</div></div>';
                break;

            case 'lookup':
                // The row link column and the linked column it shows, in one choice.
                $picked = $column ? implode(':', ws_wtable_lookup_parts($column)) : '';
                $picks = '';

                foreach (ws_wtable_lookup_choices($viewer, $columns) as $value => $label) {
                    $picks .= '<option value="' . h($value) . '"' . (($value === $picked) ? ' selected' : '') . '>' . h($label) . '</option>';
                }

                if (($picked !== '') && (strpos($picks, 'value="' . $picked . '" selected') === false)) {
                    $picks = '<option value="' . h($picked) . '" selected>' . h(ws_wtable_lookup_label($column, $columns, null)) . '</option>' . $picks;
                }

                $out .= '<div class="mb-3"><label class="form-label small mb-1" for="' . $prefix . '_pick">' . h(lang('Column of the linked row')) . '</label>'
                    . '<select class="form-control form-control-sm" name="options[pick]" id="' . $prefix . '_pick" required>'
                    . (($picks === '') ? '<option value="">' . h(lang('Add a row link column first.')) . '</option>' : $picks) . '</select>'
                    . '<div class="form-text">' . h(lang('Shows that column of the row each cell links to; it is read, never written.')) . '</div></div>';
                break;

            case 'rollup':
                // The other table, its link to this table and the column, in
                // one choice; then the operation and the conditions.
                $rolled = $column ? ws_wtable_rollups(array('0' => $column)) : array();
                $rolled = $rolled['0'] ?? null;
                $picked = $rolled ? $rolled['table'] . ':' . $rolled['link'] . ':' . $rolled['column'] : '';
                $picks = '';

                foreach (ws_wtable_rollup_choices($viewer, $id) as $value => $label) {
                    $picks .= '<option value="' . h($value) . '"' . (($value === $picked) ? ' selected' : '') . '>' . h($label) . '</option>';
                }

                if (($picked !== '') && (strpos($picks, 'value="' . $picked . '" selected') === false)) {
                    $picks = '<option value="' . h($picked) . '" selected>' . h(ws_wtable_rollup_column_label($viewer, $column)) . '</option>' . $picks;
                }

                $ops = '';

                foreach (ws_wtable_rollup_ops() as $op => $label) {
                    $ops .= '<option value="' . h($op) . '"' . (($op === ($rolled['op'] ?? 'sum')) ? ' selected' : '') . '>' . h($label) . '</option>';
                }

                $where = ($rolled && !empty($rolled['where'])) ? (string) json_encode($rolled['where'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
                $matches = '';

                foreach (array('all' => lang('Every condition holds'), 'any' => lang('Any condition holds')) as $match => $label) {
                    $matches .= '<option value="' . h($match) . '"' . (($match === ($rolled['match'] ?? 'all')) ? ' selected' : '') . '>' . h($label) . '</option>';
                }

                $out .= '<div class="mb-3"><label class="form-label small mb-1" for="' . $prefix . '_pick">' . h(lang('Rows that link here')) . '</label>'
                    . '<select class="form-control form-control-sm" name="options[pick]" id="' . $prefix . '_pick" required>'
                    . (($picks === '') ? '<option value="">' . h(lang('No table you read links to this table yet.')) . '</option>' : $picks) . '</select>'
                    . '<div class="form-text">' . h(lang('The rows of another table whose row link points at this row, and the column to total.')) . '</div></div>'
                    . '<div class="mb-3"><label class="form-label small mb-1" for="' . $prefix . '_op">' . h(lang('Work out')) . '</label>'
                    . '<select class="form-control form-control-sm" name="options[op]" id="' . $prefix . '_op">' . $ops . '</select></div>'
                    . '<div class="mb-3"><label class="form-label small mb-1" for="' . $prefix . '_where">' . h(lang('Only the rows that meet')) . '</label>'
                    . '<input type="text" class="form-control form-control-sm font-monospace" name="options[where]" id="' . $prefix . '_where" placeholder="' . h('[{"column":12,"op":"eq","value":"paid"}]') . '" value="' . h($where) . '">'
                    . '<div class="form-text">' . h(lang('Empty for every linked row; else a JSON list of conditions on the other table\'s columns, as the tables API takes them.')) . '</div></div>'
                    . '<div class="mb-3"><label class="form-label small mb-1" for="' . $prefix . '_match">' . h(lang('Conditions join')) . '</label>'
                    . '<select class="form-control form-control-sm" name="options[match]" id="' . $prefix . '_match">' . $matches . '</select></div>';
                break;

            case 'formula':
                $out .= '<div class="mb-3"><label class="form-label small mb-1" for="' . $prefix . '_expression">' . h(lang('Formula')) . '</label>'
                    . '<input type="text" class="form-control form-control-sm font-monospace" name="options[expression]" id="' . $prefix . '_expression" placeholder="Price * Quantity" value="' . h((string) ($options['expression'] ?? '')) . '">'
                    . '<div class="form-text">' . h(lang('Name other columns by their names.')) . '</div></div>';
                break;
        }

        // A text or number column may be the table's key.
        if (in_array($type, ws_wtable_key_types(), true)) {
            $out .= '<div class="form-field mb-3"><input type="hidden" name="options[key]" value="0"><input class="check" type="checkbox" name="options[key]" id="' . $prefix . '_key" value="1"' . (!empty($options['key']) ? ' checked' : '') . '>'
                . '<label class="small" for="' . $prefix . '_key">' . h(lang('The table\'s key: filled in and different on every row')) . '</label></div>';
        }

        return $out;
    };

    // A column's Change type form, and the cells that did not convert when
    // the last try was refused.
    $retype_form = function ($column_id, $column, $prefix) use ($self_url, $id, $retype_state) {
        $choices = ws_wtable_screen_retype_choices($column);

        if (empty($choices)) {
            return '';
        }

        $options = '';

        foreach ($choices as $choice => $label) {
            $options .= '<option value="' . h($choice) . '">' . h($label) . '</option>';
        }

        $faults = '';

        if ($retype_state && ((int) $retype_state['table'] === $id) && ((int) $retype_state['column'] === (int) $column_id)) {
            $items = '';

            foreach ((array) $retype_state['faults'] as $fault) {
                $items .= '<li>' . h(ws_wtable_screen_retype_fault_text($fault)) . '</li>';
            }

            $more = (int) $retype_state['count'] - count((array) $retype_state['faults']);
            $faults = '<div class="alert theme-danger small mt-3" role="alert" id="ws-wt-retype-' . (int) $column_id . '">'
                . '<p class="fw-semibold mb-2">' . h(lang(array('string' => '{var:1} cells do not fit the new type:', 'vars' => (int) $retype_state['count']))) . '</p>'
                . '<ul class="mb-0">' . $items . '</ul>'
                . (($more > 0) ? '<p class="mb-0 mt-2">' . h(lang(array('string' => 'And {var:1} more.', 'vars' => $more))) . '</p>' : '')
                . '</div>';
        }

        return '<form method="post" action="' . h($self_url) . '" class="disable_shortcut mt-4 pt-3 border-top" data-ws-confirm="' . h(lang(array('string' => 'Change the type of {var:1}? Every cell is converted; if one does not fit, nothing changes and those cells are listed.', 'vars' => $column['name']))) . '">
                ' . get_token_field() . '
                <input type="hidden" name="wt_action" value="column_retype">
                <input type="hidden" name="wt_table" value="' . $id . '">
                <input type="hidden" name="wt_column" value="' . (int) $column_id . '">
                <p class="small fw-semibold mb-2">' . h(lang('Change type')) . '</p>
                <div class="d-flex flex-wrap gap-3 align-items-end">
                    <div><label class="form-label small mb-1" for="' . $prefix . '_to">' . h(lang('New type')) . '</label>
                        <select class="form-control form-control-sm" name="to" id="' . $prefix . '_to">' . $options . '</select></div>
                    <div><label class="form-label small mb-1" for="' . $prefix . '_retype_decimals">' . h(lang('Decimals')) . '</label>
                        <input type="number" class="form-control form-control-sm" name="decimals" id="' . $prefix . '_retype_decimals" min="0" max="6" placeholder="' . h(lang('Default')) . '"></div>
                    <button type="submit" class="btn btn-sm btn-outline theme-secondary rounded-pill px-5">' . h(lang('Change type')) . '</button>
                </div>
                <div class="form-text">' . h(lang('Decimals count for a number or money only.')) . '</div>
            </form>' . $faults;
    };

    // The columns.
    $column_list = '';

    foreach ($columns as $column_id => $column) {
        $line = '<div class="d-flex align-items-center gap-3">'
            . '<div class="flex-grow-1 min-w-0"><span class="fw-semibold">' . h($column['name']) . '</span> <span class="small fg-secondary">· ' . h($types[$column['type']] ?? $column['type'])
            . (($column['type'] === 'row') ? ' · ' . h(ws_wtable_link_readable($viewer, ws_wtable_link_table($column)) ? (string) (ws_wtable(ws_wtable_link_table($column))['name'] ?? '') : lang('A table you do not read')) : '')
            . (($column['type'] === 'lookup') ? ' · ' . h(ws_wtable_lookup_column_label($viewer, $column, $columns)) : '')
            . (($column['type'] === 'rollup') ? ' · ' . h(ws_wtable_rollup_column_label($viewer, $column)) : '') . '</span>'
            . (ws_wtable_is_key($column) ? ' <span class="badge theme-secondary fw-normal"><i class="bi bi-key me-1" aria-hidden="true"></i>' . h(lang('Key')) . '</span>' : '') . '</div>';

        if ($can_write) {
            $line .= ws_wtable_screen_action_button(array('action' => 'column_delete', 'column' => (int) $column_id), lang('Delete'), 'bi-trash',
                lang(array('string' => 'Delete the column {var:1}? Its cells cannot be brought back.', 'vars' => $column['name'])));
        }

        $line .= '</div>';

        if ($can_write) {
            $prefix = 'ws_wt_col_' . (int) $column_id;
            $refused_here = $retype_state && ((int) $retype_state['table'] === $id) && ((int) $retype_state['column'] === (int) $column_id);
            $line = '<details class="py-3 border-bottom"' . ($refused_here ? ' open' : '') . '><summary class="list-unstyled">' . $line . '</summary>
                <form method="post" action="' . h($self_url) . '" class="disable_shortcut mt-3">
                    ' . get_token_field() . '
                    <input type="hidden" name="wt_action" value="column_update">
                    <input type="hidden" name="wt_table" value="' . $id . '">
                    <input type="hidden" name="wt_column" value="' . (int) $column_id . '">
                    <div class="mb-3"><label class="form-label small mb-1" for="' . $prefix . '_name">' . h(lang('Name')) . '</label>
                        <input type="text" class="form-control form-control-sm" name="name" id="' . $prefix . '_name" value="' . h($column['name']) . '" maxlength="' . (int) ws_wtable_limits()['name'] . '" required></div>
                    ' . $option_fields($column['type'], $column, $prefix) . '
                    <button type="submit" class="btn btn-sm btn-outline theme-primary rounded-pill px-5">' . h(lang('Save')) . '</button>
                </form>' . $retype_form($column_id, $column, $prefix) . '
            </details>';
        } else {
            $line = '<div class="py-3 border-bottom">' . $line . '</div>';
        }

        $column_list .= $line;
    }

    $new_column = '';

    if ($can_write) {
        $type_options = '';
        $type_fields = '';

        foreach ($types as $type => $label) {
            $type_options .= '<option value="' . h($type) . '">' . h($label) . '</option>';
            $fields = $option_fields($type, null, 'ws_wt_new_' . $type);

            if ($fields !== '') {
                $type_fields .= '<fieldset class="ws-wt-type-options d-none" data-type="' . h($type) . '" disabled>' . $fields . '</fieldset>';
            }
        }

        $new_column = '
                    <form method="post" action="' . h($self_url) . '" class="disable_shortcut mt-5" id="ws-wt-new-column">
                        ' . get_token_field() . '
                        <input type="hidden" name="wt_action" value="column_create">
                        <input type="hidden" name="wt_table" value="' . $id . '">
                        <h3 class="h6">' . h(lang('New column')) . '</h3>
                        <div class="mb-3"><label class="form-label small mb-1" for="ws_wt_new_column_name">' . h(lang('Name')) . '</label>
                            <input type="text" class="form-control form-control-sm" name="name" id="ws_wt_new_column_name" maxlength="' . (int) ws_wtable_limits()['name'] . '" required></div>
                        <div class="mb-3"><label class="form-label small mb-1" for="ws_wt_new_column_type">' . h(lang('Type')) . '</label>
                            <select class="form-control form-control-sm" name="type" id="ws_wt_new_column_type">' . $type_options . '</select>
                            <div class="form-text">' . h(lang('The type cannot change once the column is made.')) . '</div></div>
                        ' . $type_fields . '
                        <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5">' . h(lang('Add column')) . '</button>
                    </form>';
    }

    $columns_card = '
            <div class="card mb-5" id="ws-wt-columns">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-layout-three-columns me-1" aria-hidden="true"></i>' . h(lang('Columns')) . '</h2></div>
                <div class="card-body">
                    ' . (($column_list !== '') ? $column_list : '<p class="small fg-secondary mb-0">' . h(lang('No column yet')) . '</p>') . '
                    ' . $new_column . '
                </div>
            </div>';

    // Rows from a file.
    $import_rows_card = ($can_write && !empty($columns) && !$kept) ? '
            <div class="card mb-5" id="ws-wt-import-rows">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-file-earmark-arrow-up me-1" aria-hidden="true"></i>' . h(lang('Rows from a file')) . '</h2></div>
                <div class="card-body">' . $import_upload($id, lang('A CSV, Excel or OpenDocument file whose first row names the columns: each fills the column of its name.')) . '</div>
            </div>
            <div class="card mb-5" id="ws-wt-import-note-rows">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-journal-text me-1" aria-hidden="true"></i>' . h(lang('Rows from a note')) . '</h2></div>
                <div class="card-body">' . $import_note($id, lang('A table in one of your notes whose first row names the columns: each fills the column of its name. The note stays as it is.')) . '</div>
            </div>' : '';

    // The name and description.
    $details_card = $can_write ? '
            <div class="card mb-5" id="ws-wt-details">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-card-text me-1" aria-hidden="true"></i>' . h(lang('Name and description')) . '</h2></div>
                <div class="card-body">
                    <form method="post" action="' . h($self_url) . '" class="disable_shortcut">
                        ' . get_token_field() . '
                        <input type="hidden" name="wt_action" value="table_update">
                        <input type="hidden" name="wt_table" value="' . $id . '">
                        <div class="mb-3"><label class="form-label small mb-1" for="ws_wt_name">' . h(lang('Name')) . '</label>
                            <input type="text" class="form-control form-control-sm" name="name" id="ws_wt_name" value="' . h($table['name']) . '" maxlength="' . (int) ws_wtable_limits()['name'] . '" required></div>
                        <div class="mb-3"><label class="form-label small mb-1" for="ws_wt_description">' . h(lang('Description')) . '</label>
                            <textarea class="form-control form-control-sm" name="description" id="ws_wt_description" rows="2" maxlength="' . (int) ws_wtable_limits()['description'] . '">' . h($table['description']) . '</textarea></div>
                        <button type="submit" class="btn btn-sm btn-outline theme-primary rounded-pill px-5">' . h(lang('Save')) . '</button>
                    </form>
                </div>
            </div>' : '';

    // The shares.
    $share_lines = '';

    foreach ((array) ws_wtable_shares_list($viewer, $table) as $share) {
        $is_channel = ((string) $share['target_type'] === 'channel');
        $name = ((string) $share['name'] !== '') ? ($is_channel ? '#' : '') . $share['name'] : ($is_channel ? lang('A channel you do not read') : lang('A former colleague'));

        $share_lines .= '<div class="d-flex align-items-center gap-3 py-2 border-bottom">'
            . '<i class="bi ' . ($is_channel ? 'bi-hash' : 'bi-person') . ' opacity-75" aria-hidden="true"></i>'
            . '<div class="flex-grow-1 min-w-0 text-truncate">' . h($name) . '</div>'
            . '<span class="badge ' . (((string) $share['gives'] === '') ? 'theme-warning' : 'theme-secondary') . ' fw-normal">'
            . h(((string) $share['gives'] === '') ? lang('Gives nothing now') : ($levels[(string) $share['level']] ?? '')) . '</span>'
            . ($can_manage ? ws_wtable_screen_action_button(array('action' => 'share_remove', 'share' => (int) $share['id']), lang('Take back'), 'bi-x-lg', lang(array('string' => 'Take the share with {var:1} back?', 'vars' => $name))) : '')
            . '</div>';
    }

    $share_form = '';

    if ($can_manage) {
        $targets = '<option value="">' . h(lang('Choose…')) . '</option><optgroup label="' . h(lang('Colleagues')) . '">';

        foreach ($team as $person) {
            if ((int) $person['id'] !== (int) $table['user_id']) {
                $targets .= '<option value="user:' . (int) $person['id'] . '">' . h($person['name']) . '</option>';
            }
        }

        $targets .= '</optgroup><optgroup label="' . h(lang('Channels')) . '">';

        foreach (ws_channels_for($viewer) as $channel) {
            if (in_array((string) $channel['kind'], array('public', 'private'), true) && empty($channel['archived'])) {
                $targets .= '<option value="channel:' . (int) $channel['id'] . '">#' . h($channel['name']) . '</option>';
            }
        }

        $targets .= '</optgroup>';
        $level_options = '';

        foreach ($levels as $level => $label) {
            $level_options .= '<option value="' . h($level) . '">' . h($label) . '</option>';
        }

        $share_form = '
                    <form method="post" action="' . h($self_url) . '" class="disable_shortcut mt-5">
                        ' . get_token_field() . '
                        <input type="hidden" name="wt_action" value="share_set">
                        <input type="hidden" name="wt_table" value="' . $id . '">
                        <div class="mb-3"><label class="form-label small mb-1" for="ws_wt_share_target">' . h(lang('Share with')) . '</label>
                            <select class="form-control form-control-sm" name="target" id="ws_wt_share_target" required data-pg-combobox>' . $targets . '</select></div>
                        <div class="mb-3"><label class="form-label small mb-1" for="ws_wt_share_level">' . h(lang('They may')) . '</label>
                            <select class="form-control form-control-sm" name="level" id="ws_wt_share_level">' . $level_options . '</select>
                            <div class="form-text">' . h(lang('A channel\'s readers can only view the table. Sharing again with the same one changes what they may do.')) . '</div></div>
                        <button type="submit" class="btn btn-sm btn-outline theme-primary rounded-pill px-5">' . h(lang('Share')) . '</button>
                    </form>';
    }

    $shares_card = '
            <div class="card mb-5" id="ws-wt-shares">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-share me-1" aria-hidden="true"></i>' . h(lang('Shared with')) . '</h2></div>
                <div class="card-body">
                    ' . (($share_lines !== '') ? $share_lines : '<p class="small fg-secondary mb-0">' . h(lang('Nobody else reaches this table.')) . '</p>') . '
                    ' . $share_form . '
                </div>
            </div>';

    // The table's deleted rows: restored at the end of the table, or deleted
    // for good, until the bin lets them go.
    $bin_card = '';

    if (!empty($bin_rows)) {
        $bin_lines = '';

        foreach ($bin_rows as $row) {
            $summary = ws_wtable_screen_bin_summary($columns, (array) $row['cells'], $people);
            $bin_lines .= '
                    <li class="d-flex align-items-start gap-2 py-2 border-bottom">
                        <div class="flex-grow-1 min-w-0">
                            <div class="small text-truncate">' . h(($summary !== '') ? $summary : lang('An empty row')) . '</div>
                            <div class="small fg-secondary">' . h(lang(array('string' => 'Deleted by {var:1}, {var:2} · deleted for good on {var:3}', 'vars' => array(
                                $people[(int) $row['deleted_by']]['name'] ?? lang('somebody'), ws_time_label((int) $row['deleted_at']), date('d.m.Y', (int) $row['expires_at']))))) . '</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-text theme-primary ws-wt-action" data-action="row_restore" data-bin="' . (int) $row['bin_id'] . '" title="' . h(lang('Restore')) . '" aria-label="' . h(lang('Restore')) . '"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
                        ' . ws_wtable_screen_action_button(array('action' => 'bin_row_delete', 'bin' => (int) $row['bin_id']), lang('Delete for good'), 'bi-x-lg', lang('Delete this row for good? It cannot be brought back.')) . '
                    </li>';
        }

        $bin_card = '
            <div class="card mb-5" id="ws-wt-deleted-rows">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Deleted rows ({var:1})', 'vars' => count($bin_rows)))) . '</h2></div>
                <div class="card-body">
                    <p class="small fg-secondary">' . h(lang(array('string' => 'A deleted row stays here for {var:1} days. Restored, it comes back at the end of the table.', 'vars' => $bin_days))) . '</p>
                    <ul class="list-unstyled mb-0">' . $bin_lines . '</ul>
                </div>
            </div>';
    }

    $owner_note = '';

    if (!$can_manage) {
        $owner = ws_people(array((int) $table['user_id']));
        $owner_note = '<p class="small fg-secondary mb-5"><i class="bi bi-person me-1" aria-hidden="true"></i>'
            . h(lang(array('string' => '{var:1}\'s table · {var:2}', 'vars' => array($owner[(int) $table['user_id']]['name'] ?? '', $access_labels[$table['access']] ?? '')))) . '</p>';
    }

    // The board view: the rows as cards by a status column, drawn by the
    // channel Board tab's component (assets/js/workspace_board_channel.js).
    $status_columns = ws_wtable_board_status_columns($columns);
    $board_id = ws_wtable_board_column_id($columns, $_GET['by'] ?? 0);
    $on_board = ($board_id > 0) && (($_GET['view'] ?? '') === 'board');
    $views = '';

    if ($board_id > 0) {
        $views = '<div class="btn-group btn-group-sm" role="group" aria-label="' . h(lang('View')) . '">';

        foreach (array('table' => array(lang('Table'), 'bi-table'), 'board' => array(lang('Board'), 'bi-kanban')) as $key => $view) {
            $here = (($key === 'board') === $on_board);
            $views .= '<a class="btn btn-text theme-secondary' . ($here ? ' active' : '') . '" href="' . h($table_url . (($key === 'board') ? '&view=board&by=' . $board_id : '')) . '"'
                . ($here ? ' aria-current="page"' : '') . '><i class="bi ' . $view[1] . ' me-1" aria-hidden="true"></i>' . h($view[0]) . '</a>';
        }

        $views .= '</div>';
    }

    if ($on_board) {
        $by = '';

        // More than one status column: which one the board groups by.
        if (count($status_columns) > 1) {
            $by = '<div class="btn-group btn-group-sm" role="group" aria-label="' . h(lang('Group by')) . '">';

            foreach ($status_columns as $column_id => $name) {
                $by .= '<a class="btn btn-text theme-secondary' . (($column_id === $board_id) ? ' active" aria-current="page' : '') . '" href="' . h($table_url . '&view=board&by=' . $column_id) . '">' . h($name) . '</a>';
            }

            $by .= '</div>';
        }

        $texts = ws_channel_board_js_strings();
        $texts['cb_more_done'] = ws_js_template('{var:1} more', 1);
        $texts['loading'] = lang('Loading…');
        $texts['error_generic'] = lang('Something went wrong. Please try again.');
        $config = array(
            'read'     => $table_url . '&board_json=1&by=' . $board_id,
            'write'    => $self_url,
            'open'     => $table_url . '&row=',
            'token'    => (string) ($_SESSION['software']['token'] ?? ''),
            'table'    => $id,
            'column'   => $board_id,
            'texts'    => $texts,
        );

        $body = '
    ' . $owner_note . '
    <div class="card mb-5" id="ws-wt-board">
        <div class="card-header d-flex flex-wrap align-items-center gap-3"><h2 class="h6 mb-0 flex-grow-1"><i class="bi bi-kanban me-1" aria-hidden="true"></i>'
            . h(lang(array('string' => '{var:1} rows', 'vars' => (int) $table['row_count']))) . '</h2>' . $by . $views . '</div>
        <div class="card-body ws-wt-board"><div class="small fg-secondary">' . h(lang('Loading…')) . '</div></div>
    </div>';

        $board_script = pg_js_bundle_tags('assets/js/workspace_board_channel.min.js') . '
<script type="application/json" id="ws-wt-board-config">' . str_replace('</', '<\\/', (string) pg_json_encode_answer($config)) . '</script>
<script>
(function () {
    // The board reads the table\'s cards and writes a moved card\'s status
    // through this screen; a card opens its row in the table view.
    var config = JSON.parse(document.getElementById("ws-wt-board-config").textContent);
    function answer(response) {
        return response.json().catch(function () { return { ok: false, message: config.texts.error_generic }; });
    }
    function start() {
        var pane = document.querySelector("#ws-wt-board .ws-wt-board");
        if (!window.PGWsChannelBoard || !window.PGWsChannelBoard.mountSource) {
            pane.textContent = config.texts.error_generic;
            return;
        }
        window.PGWsChannelBoard.mountSource(pane, {
            texts: config.texts,
            load: function (options) {
                return fetch(config.read + (options.all ? "&all=1" : ""), { credentials: "same-origin" }).then(function (response) {
                    if (!response.ok) { throw new Error(config.texts.error_generic); }
                    return response.json();
                });
            },
            move: function (item, drop) {
                var body = new FormData();
                body.append("token", config.token);
                body.append("wt_action", "row_status");
                body.append("wt_table", config.table);
                body.append("wt_column", config.column);
                body.append("wt_row", item.id);
                body.append("revision", item.revision);
                body.append("value", drop);
                return fetch(config.write, { method: "POST", body: body, credentials: "same-origin" }).then(answer).then(function (data) {
                    if (!data.ok) { throw new Error(data.message); }
                    return data.message;
                });
            },
            open: function (item) {
                window.location.href = config.open + item.id + "#ws-wt-row-form";
            }
        });
    }
    if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", start); } else { start(); }
})();
</script>';
    } else {

        $body = $import_card . '
        ' . $owner_note . (((string) $table['description'] !== '') ? '<p class="fg-secondary mb-5" style="white-space: pre-line;">' . h($table['description']) . '</p>' : '') . '
        <div class="row g-5">
            <div class="col-12 xl:col-8">
                <div class="card mb-5" id="ws-wt-rows">
                    <div class="card-header d-flex align-items-center gap-3"><h2 class="h6 mb-0 flex-grow-1"><i class="bi bi-table me-1" aria-hidden="true"></i>'
                        . h($query_on && !empty($query['where'])
                            ? lang(array('string' => '{var:1} of {var:2} rows', 'vars' => array(count((array) $query_rows), (int) $table['row_count'])))
                            : lang(array('string' => '{var:1} rows', 'vars' => (int) $table['row_count']))) . '</h2>' . $views . '</div>
                    <div class="card-body">' . $rows_body . '</div>
                </div>
                ' . $row_form . '
            </div>
            <div class="col-12 xl:col-4">
                ' . $columns_card . $import_rows_card . $details_card . $shares_card . $bin_card . '
            </div>
        </div>';

    }

    // Run from table: the window that names the columns a draft flow is
    // made of, pre-filled by type; nothing of it is kept on the table.
    $flow_window = '';
    $flow_button = '';

    if (ws_flows_ready() && ws_can_schedule($viewer) && !empty($columns)) {
        $prefill = ws_wtable_flow_prefill($columns);
        $flow_fields = '';
        $has_text = in_array('text', array_column($columns, 'type'), true);
        $hint = array(
            'step'   => lang('A text column: each row with a step becomes a task.'),
            'person' => lang('The person the task is given to.'),
            'days'   => lang('Days the task is due after it becomes ready.'),
            'after'  => lang('The row number the step comes after; the row before when empty.'),
        );

        foreach (ws_wtable_flow_roles() as $role => $part) {
            $options = $part['required'] ? '' : '<option value="0">' . h(lang('None')) . '</option>';

            foreach ($columns as $column_id => $column) {
                if ($column['type'] === $part['type']) {
                    $options .= '<option value="' . (int) $column_id . '"' . (((int) $column_id === $prefill[$role]) ? ' selected' : '') . '>' . h($column['name']) . '</option>';
                }
            }

            $flow_fields .= '
                        <div class="mb-3">
                            <label class="form-label small mb-1" for="ws_wt_flow_' . $role . '">' . h($part['label']) . '</label>
                            <select class="form-control form-control-sm" name="flow_map[' . $role . ']" id="ws_wt_flow_' . $role . '" aria-describedby="ws_wt_flow_' . $role . '_hint"' . ($part['required'] ? ' required' : '') . '>' . $options . '</select>
                            <div class="form-text" id="ws_wt_flow_' . $role . '_hint">' . h($hint[$role]) . '</div>
                        </div>';
        }

        $flow_button = '<button type="button" class="btn btn-sm btn-text theme-primary" data-bs-toggle="dialog" data-bs-target="#ws_wt_flow"><i class="bi bi-diagram-3 me-1" aria-hidden="true"></i>' . h(lang('Run from table')) . '</button>';
        $flow_window = '
    <dialog class="dialog pg-dialog-centered" id="ws_wt_flow" aria-labelledby="ws_wt_flow_title">
        <form method="post" action="' . h($self_url) . '" class="d-contents disable_shortcut">
            ' . get_token_field() . '
            <input type="hidden" name="wt_action" value="flow_from_table">
            <input type="hidden" name="wt_table" value="' . $id . '">
            <div class="dialog-header">
                <h5 class="dialog-title" id="ws_wt_flow_title">' . h(lang('Run from table')) . '</h5>
                <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="dialog-body">
                <p class="small fg-secondary">' . h(lang('The rows become a draft flow of yours: a task for each step, in row order. Review it on the canvas and publish it to run it.')) . '</p>
                ' . ($has_text ? $flow_fields : '<p class="small mb-0">' . h(lang('Add a text column that names each step first.')) . '</p>') . '
            </div>
            <div class="dialog-footer">
                <button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-dismiss="dialog">' . h(lang('Cancel')) . '</button>
                ' . ($has_text ? '<button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5">' . h(lang('Make the draft flow')) . '</button>' : '') . '
            </div>
        </form>
    </dialog>';
    }

    $toolbar = '
    <nav id="button_bar" class="pg-toolbar navigation mb-5" aria-label="' . lang('Button Bar') . '">
        ' . $flow_button . '
        ' . (($row_form !== '') ? '<a class="btn btn-sm btn-text theme-primary" href="' . h($table_url) . '#ws-wt-row-form"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('New row')) . '</a>' : '') . '
        <a class="btn btn-sm btn-text theme-secondary" href="' . h($table_url . '&download=xlsx') . '" download><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i>' . h(lang('Download as Excel')) . '</a>
        ' . ($can_manage ? '<button type="button" class="btn btn-sm btn-text theme-danger ws-wt-action ms-auto" data-action="table_delete" data-confirm="'
            . h(ws_wtable_screen_delete_confirm('table', (string) $table['name'], $bin_days)) . '"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . h(lang('Delete table')) . '</button>' : '') . '
    </nav>' . $flow_window;

    $title = (string) $table['name'];
    $cancel = array('enable' => 'true', 'url' => $self_url, 'title' => lang('All tables'));
    $breadcrumb = array(
        array('label' => lang('Tables'), 'url' => $self_url),
        array('label' => $title),
    );
    $description = lang('A workflow table of the Workspace.');
}

/* ---------------------------------------------------------------------------
   The screen
   --------------------------------------------------------------------------- */

$shell = pg_page_shell(array(
    // The shell prints the title and heading as markup: a table's name is text.
    'title'               => h($title),
    'extra classes'       => 'workspace workspace_wtables',
    'icon'                => 'workspace',
    'heading'             => h($title),
    'heading_description' => $description,
    'cancel'              => $cancel,
    'breadcrumb'          => $breadcrumb,
));

// The operation's result shows as corner toasts, not the panel's alert boxes.
$liveform_messages = ws_screen_messages($liveform);

$liveform->remove_form();

echo
$shell . '
<main id="content" class="container-fluid p-0">
<div class="ws-shell">
<div class="ws-shell-main">
    ' . $liveform_messages . '
    ' . $toolbar . '
    ' . $body . '

    <form method="post" action="' . h($self_url) . '" id="ws_wt_action_form" class="disable_shortcut d-none">
        ' . get_token_field() . '
        <input type="hidden" name="wt_action" value="">
        <input type="hidden" name="wt_table" value="' . ($table ? (int) $table['id'] : 0) . '">
        <input type="hidden" name="wt_column" value="">
        <input type="hidden" name="wt_row" value="">
        <input type="hidden" name="wt_share" value="">
        <input type="hidden" name="wt_bin" value="">
        <input type="hidden" name="revision" value="">
    </form>
</div>
</div>
</main>
' . get_date_picker_format() . pg_fields_script() . '
<script>
(function () {

    // A type change of a column asks first.
    document.addEventListener("submit", function (e) {
        var form = e.target.closest("form[data-ws-confirm]");
        if (form && !window.confirm(form.dataset.wsConfirm)) { e.preventDefault(); }
    });

    // Deletes and restores post through one form outside the cards and the
    // table rows; a delete asks first.
    document.addEventListener("click", function (e) {
        var button = e.target.closest(".ws-wt-action");
        if (!button) { return; }
        e.preventDefault();
        if (button.dataset.confirm && !window.confirm(button.dataset.confirm)) { return; }
        var form = document.getElementById("ws_wt_action_form");
        form.elements.wt_action.value = button.dataset.action;
        form.elements.wt_column.value = button.dataset.column || "";
        form.elements.wt_row.value = button.dataset.row || "";
        form.elements.wt_share.value = button.dataset.share || "";
        form.elements.wt_bin.value = button.dataset.bin || "";
        if (button.dataset.table) { form.elements.wt_table.value = button.dataset.table; }
        form.elements.revision.value = button.dataset.revision || "";
        form.submit();
    });

    // The new column form shows the options of the chosen type only; the
    // others stay disabled, so they are not posted.
    var type = document.getElementById("ws_wt_new_column_type");
    function showOptions() {
        document.querySelectorAll(".ws-wt-type-options").forEach(function (set) {
            var on = (set.dataset.type === type.value);
            set.classList.toggle("d-none", !on);
            set.disabled = !on;
        });
    }
    if (type) { type.addEventListener("change", showOptions); showOptions(); }

})();
</script>' . $board_script . output_footer();
