<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the person's workflow tables: their own and those shared with
 * them or in a channel they read. Without ?id= the list and a new table;
 * with it one table: its rows a page at a time, the row form (?row= opens a
 * row in it), the columns, the name and description, and the shares. Every
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

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$viewer = ws_screen_gate($user);

if (!ws_wtables_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('workspace_wtables');

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace_wtables.php';

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_token_field();

    pg_request_single_values(array('wt_action', 'wt_table', 'wt_column', 'wt_row', 'wt_share', 'revision', 'name', 'description', 'type', 'target', 'level'));

    $action = (string) ($_POST['wt_action'] ?? '');
    $table = ws_wtable((int) ($_POST['wt_table'] ?? 0));
    $table_url = $table ? $self_url . '?id=' . (int) $table['id'] : $self_url;
    $back = $table_url;
    $field = function ($name) {
        return isset($_POST[$name]) && is_string($_POST[$name]) ? $_POST[$name] : '';
    };
    $refused = function ($result) use ($liveform) {
        $liveform->add_error(h(ws_wtable_screen_error_text($result['error'])));
    };

    switch ($action) {

        case 'table_create':
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
                $liveform->add_notice(h(lang(array('string' => 'The table {var:1} was deleted.', 'vars' => $table['name']))));
                $back = $self_url;
            } else {
                $refused($result);
            }

            break;

        case 'column_create':
        case 'column_update':
            $column = ($action === 'column_update') ? ws_wtable_column((int) ($table['id'] ?? 0), (int) ($_POST['wt_column'] ?? 0)) : null;
            $type = $column ? $column['type'] : $field('type');
            $input = array(
                'name'    => $field('name'),
                'options' => ws_wtable_screen_options_from_form($type, (isset($_POST['options']) && is_array($_POST['options'])) ? $_POST['options'] : array(), $column),
            );

            if ($column) {
                $result = ws_wtable_column_update($viewer, $table, $column, $input);
            } else {
                $result = ws_wtable_column_create($viewer, $table, $input + array('type' => $type));
            }

            $result['ok'] ? $liveform->add_notice(h(lang('The column was saved.'))) : $refused($result);
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
                if (!ws_wtable_type_stores($column['type'])) {
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

$table_id = (is_string($_GET['id'] ?? null) && ((int) $_GET['id'] > 0)) ? (int) $_GET['id'] : 0;
$table = ($table_id > 0) ? ws_wtable_for($viewer, $table_id) : null;

if (($table_id > 0) && !$table) {
    output_error(h(lang('That table could not be found.')) . ' <a href="' . h($self_url) . '">' . h(lang('Back')) . '</a>');
}

$types = ws_wtable_column_types();
$levels = ws_wtable_share_levels();
$access_labels = array('owner' => lang('Yours'), 'edit' => lang('Can edit'), 'view' => lang('Can view'));

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

    $body = '
    <div class="row g-5">
        <div class="col-12 xl:col-8">
            <div class="card mb-5">
                <div class="card-header d-flex flex-wrap gap-3 align-items-center">' . $tabs . '</div>
                <div class="card-body">' . $list . '</div>
            </div>
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
                            <input type="text" class="form-control form-control-sm" name="name" id="ws_wt_new_name" maxlength="' . (int) ws_wtable_limits()['name'] . '" required>
                        </div>
                        <div class="mb-5">
                            <label class="form-label small mb-1" for="ws_wt_new_description">' . h(lang('Description')) . '</label>
                            <textarea class="form-control form-control-sm" name="description" id="ws_wt_new_description" rows="2" maxlength="' . (int) ws_wtable_limits()['description'] . '"></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5">' . h(lang('Create')) . '</button>
                    </form>
                </div>
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
    $page = ws_wtable_rows_page($id, $columns, $after, 100);

    $team = ws_team_members();
    $person_ids = array();

    foreach ($page['rows'] as $row) {
        foreach ($columns as $column_id => $column) {
            if (($column['type'] === 'person') && !empty($row['cells'][$column_id])) {
                $person_ids[] = (int) $row['cells'][$column_id];
            }
        }
    }

    $people = empty($person_ids) ? array() : ws_people(array_unique($person_ids));

    // The rows.
    $head = '';

    foreach ($columns as $column) {
        $head .= '<th scope="col"' . (in_array($column['type'], array('number', 'money', 'formula'), true) ? ' class="text-end"' : '') . '>' . h($column['name']) . '</th>';
    }

    $lines = '';

    foreach ($page['rows'] as $row) {
        $line = '';

        foreach ($columns as $column_id => $column) {
            $value = $row['cells'][$column_id] ?? null;
            $text = ws_wtable_screen_cell_text($column, $value, $people);
            $class = in_array($column['type'], array('number', 'money', 'formula'), true) ? ' class="text-end text-nowrap"' : '';

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
            $tools = '<a class="btn btn-sm btn-text theme-secondary" href="' . h($table_url . '&row=' . (int) $row['id'] . ($after ? '&after=' . $after : '')) . '#ws-wt-row-form" title="' . h(lang('Edit')) . '" aria-label="' . h(lang('Edit')) . '"><i class="bi bi-pencil" aria-hidden="true"></i></a>'
                . ws_wtable_screen_action_button(array('action' => 'row_delete', 'row' => (int) $row['id'], 'revision' => (int) $row['revision']), lang('Delete'), 'bi-trash', lang('Delete this row?'));
        }

        $lines .= '<tr id="ws-wt-row-' . (int) $row['id'] . '"><td class="small fg-secondary text-end">' . (int) $row['position'] . '</td>' . $line . ($can_write ? '<td class="text-end text-nowrap">' . $tools . '</td>' : '') . '</tr>';
    }

    $pager = '';

    if ($after > 0) {
        $pager .= '<a class="btn btn-sm btn-text theme-secondary" href="' . h($table_url) . '#ws-wt-rows"><i class="bi bi-chevron-double-left me-1" aria-hidden="true"></i>' . h(lang('First page')) . '</a>';
    }

    if ($page['next'] !== null) {
        $pager .= '<a class="btn btn-sm btn-text theme-secondary ms-auto" href="' . h($table_url . '&after=' . (int) $page['next']) . '#ws-wt-rows">' . h(lang('Next page')) . '<i class="bi bi-chevron-right ms-1" aria-hidden="true"></i></a>';
    }

    if (empty($columns)) {
        $rows_body = pg_empty_state('bi-layout-three-columns', lang('No column yet'), $can_write ? lang('Add the table\'s columns first: their names and what each holds.') : '');
    } elseif ($lines === '') {
        $rows_body = pg_empty_state('bi-table', lang('No row yet'));
    } else {
        $rows_body = '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th scope="col" class="text-end">#</th>' . $head . ($can_write ? '<th scope="col"><span class="visually-hidden">' . h(lang('Actions')) . '</span></th>' : '') . '</tr></thead>
            <tbody>' . $lines . '</tbody>
        </table></div>' . (($pager !== '') ? '<div class="d-flex gap-3 mt-3">' . $pager . '</div>' : '');
    }

    // The row form: a new row, or the one ?row= names.
    $row_form = '';

    if ($can_write && !empty($columns)) {
        $edit_id = (is_string($_GET['row'] ?? null) && ((int) $_GET['row'] > 0)) ? (int) $_GET['row'] : 0;
        $edit = null;

        if ($edit_id > 0) {
            $stored = ws_wtable_row($id, $edit_id);
            $edit = $stored ? ws_wtable_row_out($stored, $columns) : null;
        }

        $mine = ($state && ((int) $state['table'] === $id) && ((int) $state['row'] === ($edit ? (int) $edit['id'] : 0))) ? $state : null;
        $fields = '';

        foreach ($columns as $column_id => $column) {
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

                    $input = '<select class="form-control form-control-sm' . $invalid . '" name="' . h($name) . '" id="' . $field_id . '">' . $choices . '</select>';
                    break;

                case 'date':
                    $input = '<input type="text" class="form-control form-control-sm' . $invalid . '" name="' . h($name) . '" id="' . $field_id . '" value="' . h($value) . '" ' . (!empty($options['time']) ? 'data-pg-datetime' : 'data-pg-date') . ' autocomplete="off">';
                    break;

                case 'text':
                    $input = '<textarea class="form-control form-control-sm' . $invalid . '" name="' . h($name) . '" id="' . $field_id . '" rows="1">' . h($value) . '</textarea>';
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
                    . h($edit ? lang(array('string' => 'Row {var:1}', 'vars' => (int) $edit['position'])) : lang('New row')) . '</h2></div>
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
                            ' . ($edit || ($edit_id > 0) ? '<a class="btn btn-sm btn-text theme-secondary" href="' . h($table_url . ($after ? '&after=' . $after : '')) . '#ws-wt-rows">' . h(lang('Cancel')) . '</a>' : '') . '
                        </div>
                    </form>
                </div>
            </div>';
    }

    // The column form fields of one type: the options it takes.
    $option_fields = function ($type, $column, $prefix) {
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

            case 'formula':
                $out .= '<div class="mb-3"><label class="form-label small mb-1" for="' . $prefix . '_expression">' . h(lang('Formula')) . '</label>'
                    . '<input type="text" class="form-control form-control-sm font-monospace" name="options[expression]" id="' . $prefix . '_expression" placeholder="Price * Quantity" value="' . h((string) ($options['expression'] ?? '')) . '">'
                    . '<div class="form-text">' . h(lang('Name other columns by their names.')) . '</div></div>';
                break;
        }

        return $out;
    };

    // The columns.
    $column_list = '';

    foreach ($columns as $column_id => $column) {
        $line = '<div class="d-flex align-items-center gap-3">'
            . '<div class="flex-grow-1 min-w-0"><span class="fw-semibold">' . h($column['name']) . '</span> <span class="small fg-secondary">· ' . h($types[$column['type']] ?? $column['type']) . '</span></div>';

        if ($can_write) {
            $line .= ws_wtable_screen_action_button(array('action' => 'column_delete', 'column' => (int) $column_id), lang('Delete'), 'bi-trash',
                lang(array('string' => 'Delete the column {var:1}? Its cells cannot be brought back.', 'vars' => $column['name'])));
        }

        $line .= '</div>';

        if ($can_write) {
            $prefix = 'ws_wt_col_' . (int) $column_id;
            $line = '<details class="py-3 border-bottom"><summary class="list-unstyled">' . $line . '</summary>
                <form method="post" action="' . h($self_url) . '" class="disable_shortcut mt-3">
                    ' . get_token_field() . '
                    <input type="hidden" name="wt_action" value="column_update">
                    <input type="hidden" name="wt_table" value="' . $id . '">
                    <input type="hidden" name="wt_column" value="' . (int) $column_id . '">
                    <div class="mb-3"><label class="form-label small mb-1" for="' . $prefix . '_name">' . h(lang('Name')) . '</label>
                        <input type="text" class="form-control form-control-sm" name="name" id="' . $prefix . '_name" value="' . h($column['name']) . '" required></div>
                    ' . $option_fields($column['type'], $column, $prefix) . '
                    <button type="submit" class="btn btn-sm btn-outline theme-primary rounded-pill px-5">' . h(lang('Save')) . '</button>
                </form>
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
                            <select class="form-control form-control-sm" name="target" id="ws_wt_share_target" required>' . $targets . '</select></div>
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

    $owner_note = '';

    if (!$can_manage) {
        $owner = ws_people(array((int) $table['user_id']));
        $owner_note = '<p class="small fg-secondary mb-5"><i class="bi bi-person me-1" aria-hidden="true"></i>'
            . h(lang(array('string' => '{var:1}\'s table · {var:2}', 'vars' => array($owner[(int) $table['user_id']]['name'] ?? '', $access_labels[$table['access']] ?? '')))) . '</p>';
    }

    $body = '
    ' . $owner_note . (((string) $table['description'] !== '') ? '<p class="fg-secondary mb-5" style="white-space: pre-line;">' . h($table['description']) . '</p>' : '') . '
    <div class="row g-5">
        <div class="col-12 xl:col-8">
            <div class="card mb-5" id="ws-wt-rows">
                <div class="card-header d-flex align-items-center gap-3"><h2 class="h6 mb-0 flex-grow-1"><i class="bi bi-table me-1" aria-hidden="true"></i>'
                    . h(lang(array('string' => '{var:1} rows', 'vars' => (int) $table['row_count']))) . '</h2></div>
                <div class="card-body">' . $rows_body . '</div>
            </div>
            ' . $row_form . '
        </div>
        <div class="col-12 xl:col-4">
            ' . $columns_card . $details_card . $shares_card . '
        </div>
    </div>';

    $toolbar = '
    <nav id="button_bar" class="pg-toolbar navigation mb-5" aria-label="' . lang('Button Bar') . '">
        <a class="btn btn-sm btn-text theme-secondary" href="' . h($self_url) . '"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>' . h(lang('All tables')) . '</a>
        ' . (($row_form !== '') ? '<a class="btn btn-sm btn-text theme-primary" href="' . h($table_url) . '#ws-wt-row-form"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('New row')) . '</a>' : '') . '
        ' . ($can_manage ? '<button type="button" class="btn btn-sm btn-text theme-danger ws-wt-action ms-auto" data-action="table_delete" data-confirm="'
            . h(lang(array('string' => 'Delete the table {var:1} with its rows and shares? It cannot be brought back.', 'vars' => $table['name']))) . '"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . h(lang('Delete table')) . '</button>' : '') . '
    </nav>';

    $title = (string) $table['name'];
    $description = lang('A workflow table of the Workspace.');
}

/* ---------------------------------------------------------------------------
   The screen
   --------------------------------------------------------------------------- */

$liveform_messages = $liveform->output_errors() . $liveform->output_notices();

$liveform->remove_form();

echo
pg_page_shell(array(
    // The shell prints the title and heading as markup: a table's name is text.
    'title'               => h($title),
    'extra classes'       => 'workspace workspace_wtables',
    'icon'                => 'workspace',
    'heading'             => h($title),
    'heading_description' => $description,
    'cancel'              => false,
)) . '
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
        <input type="hidden" name="revision" value="">
    </form>
</div>
</div>
</main>
' . get_date_picker_format() . pg_fields_script() . '
<script>
(function () {

    // Deletes post through one form outside the cards and the table rows,
    // each asking first.
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
</script>' . output_footer();
