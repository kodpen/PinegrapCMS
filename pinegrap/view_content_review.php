<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Content review: the pages and collection records with a person and a
 * date by which they are read again, and the ones nobody set. Paged on the
 * server by the list table driver (includes/fn/content_review_list.php);
 * Set person, Set review date, Mark reviewed and Clear post the selection
 * or one row to content_review_action.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$liveform = new liveform('view_content_review');

$user = validate_user();
validate_area_access($user, 'user');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_review_list.php');

if (!pg_content_review_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$review_role = (int) $user['role'];
$review_filter = pg_content_review_list_filter($_GET['filter'] ?? null, $review_role);
$review_managers = ($review_role < 3);

$list_source = pg_content_review_list_source($user, array('filter' => $review_filter));

if ($list_source === null) {
    output_error(lang('Access denied'));
}

$list_page = pg_list_table_page($list_source, array(), null);

if ($list_page === false) {
    output_error(lang('Query failed.'));
}

$review_sort_columns = pg_list_table_sort_columns($list_source['columns']);
$review_sort_levels = pg_list_state('view_content_review', $review_sort_columns, array('sort' => 'review', 'order' => 'asc'))['sorts'];

$review_toolbar = pg_screen_toolbar(array(
    'search' => array(
        pg_list_table_search_box('view_content_review'),
        pg_list_sort_menu('view_content_review.php', $review_sort_columns, $review_sort_levels, array('sort' => 'review', 'order' => 'asc'), array('filter' => $review_filter), 'view_content_review'),
        pg_filter_select('filter', pg_content_review_list_filters($review_role), $review_filter, array('label' => lang('Show'), 'icon' => 'funnel')),
    ),
));

// The selection bar's buttons; a user (role 3) may only mark items reviewed.
$review_bulk = '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-cr-action="mark_reviewed"><i class="bi bi-check2-circle" aria-hidden="true"></i>' . lang('Mark reviewed') . '</button>';

if ($review_managers) {
    $review_bulk = '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-cr-action="set_person"><i class="bi bi-person" aria-hidden="true"></i>' . lang('Set person') . '</button>'
        . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-cr-action="set_date"><i class="bi bi-calendar-event" aria-hidden="true"></i>' . lang('Set review date') . '</button>'
        . $review_bulk
        . '<button type="button" class="pg-button pg-button-ghost pg-button-sm" data-pg-cr-action="clear"><i class="bi bi-x-circle" aria-hidden="true"></i>' . lang('Clear') . '</button>';
}

$review_dialogs = '';

if ($review_managers) {
    $review_people = '<option value="0">' . h(lang('Nobody')) . '</option>';

    foreach (pg_content_review_list_people() as $review_person_id => $review_person_name) {
        $review_people .= '<option value="' . (int) $review_person_id . '"' . (($review_person_id === (int) $user['id']) ? ' selected' : '') . '>' . h($review_person_name) . '</option>';
    }

    $review_intervals = '';

    foreach (pg_content_review_intervals() as $review_months) {
        $review_intervals .= '<option value="' . (int) $review_months . '">'
            . h(($review_months > 0) ? lang(array('string' => 'Every {var:1} months', 'vars' => array($review_months))) : lang('Not at all')) . '</option>';
    }

    $review_dialog = function ($id, $icon, $title, $body, $button, $button_class) {
        return '<dialog class="dialog pg-dialog-centered" id="' . $id . '" aria-labelledby="' . $id . '_label">
    <div class="dialog-header">
        <h5 class="dialog-title" id="' . $id . '_label"><i class="bi bi-' . $icon . ' me-3" aria-hidden="true"></i>' . h($title) . '</h5>
        <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
    </div>
    <div class="dialog-body">
        <p class="pg-text-2 fg-secondary mt-0" data-pg-cr-count></p>
        ' . $body . '
    </div>
    <div class="dialog-footer">
        <button type="button" class="pg-button pg-button-ghost" data-bs-dismiss="dialog">' . lang('Cancel') . '</button>
        <button type="button" class="pg-button ' . $button_class . '" data-pg-cr-submit>' . h($button) . '</button>
    </div>
</dialog>';
    };

    $review_dialogs = $review_dialog('cr_person_dialog', 'person', lang('Set person'),
            '<label class="pg-field-label" for="cr_person">' . lang('Assigned person') . '</label>
        <select class="pg-input" id="cr_person" data-pg-cr-field="user_id">' . $review_people . '</select>
        <p class="pg-field-help pg-text-meta fg-secondary">' . lang('Only a panel user who may edit the item can be its person; the others are left as they were.') . '</p>',
            lang('Set person'), 'pg-button-fill')
        . $review_dialog('cr_date_dialog', 'calendar-event', lang('Set review date'),
            '<label class="pg-field-label" for="cr_review_on">' . lang('Review by') . '</label>
        <input type="text" class="pg-input mb-5" id="cr_review_on" data-pg-date data-pg-cr-field="review_on" autocomplete="off" />
        <label class="pg-field-label" for="cr_interval">' . lang('Comes round') . '</label>
        <select class="pg-input" id="cr_interval" data-pg-cr-field="interval_months">' . $review_intervals . '</select>
        <p class="pg-field-help pg-text-meta fg-secondary">' . lang('Mark reviewed moves the date on by the interval; without one it clears the date.') . '</p>',
            lang('Set review date'), 'pg-button-fill')
        . $review_dialog('cr_clear_dialog', 'x-circle', lang('Clear'),
            '<p class="mb-0">' . lang('The person, the review date and the last review of these items are removed. The pages and records stay as they are.') . '</p>',
            lang('Clear'), 'pg-button-danger');
}

echo
    pg_page_shell(array(
        'title' => lang('Content review'),
        'extra classes' => 'content_review pg-measure-list',
        'icon' => 'page',
        'heading' => lang('Content review'),
        'heading_description' => $review_managers
            ? lang('Who reads each page and collection record again, and by when. Overdue items come first.')
            : lang('The pages and records you read again, and by when. Overdue items come first.'),
        'breadcrumb' => array(
            array('label' => lang('Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php'),
            array('label' => lang('Content review')),
        ),
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            ' . $review_toolbar . '
            <form name="form" id="cr_form" action="content_review_action.php" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                <input type="hidden" name="action" value="" />
                <input type="hidden" name="selection" value="" />
                <input type="hidden" name="list_params" value="' . h(encode_json(array('filter' => $review_filter))) . '" />
                <input type="hidden" name="user_id" value="" />
                <input type="hidden" name="review_on" value="" />
                <input type="hidden" name="interval_months" value="" />
                ' . pg_list_table_html($list_source, $list_page, lang('Content review'), $review_bulk) . '
            </form>
        </div>
    </div>
</main>
' . $review_dialogs . '
<script>
    // A row\'s menu acts on that row, the selection bar on the selection:
    // both post the same form to content_review_action.php, through a
    // dialog for the person, the date and Clear.
    (function () {
        var form = document.getElementById(\'cr_form\');
        var root = document.querySelector(\'.pg-list-table[data-pg-list-table="view_content_review"]\');
        var selection = \'\';
        var selected = 0;
        var dialogs = {set_person: \'cr_person_dialog\', set_date: \'cr_date_dialog\', clear: \'cr_clear_dialog\'};
        var countText = ' . json_encode(lang('{var:1} items'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';

        if (!form || !root) {
            return;
        }

        root.addEventListener(\'pg-list-table:selection\', function (event) {
            selection = JSON.stringify(event.detail.payload);
            selected = event.detail.count || 0;
        });

        function send(action, payload) {
            form.elements.action.value = action;
            form.elements.selection.value = payload;
            form.submit();
        }

        document.addEventListener(\'click\', function (event) {
            var button = event.target.closest(\'[data-pg-cr-action]\');

            if (!button) {
                return;
            }

            var action = button.getAttribute(\'data-pg-cr-action\');
            var id = button.getAttribute(\'data-pg-cr-id\');
            var payload = id ? JSON.stringify({ids: [parseInt(id, 10)]}) : selection;
            var count = id ? 1 : selected;

            if (!payload) {
                return;
            }

            if (!dialogs[action]) {
                send(action, payload);
                return;
            }

            var dialog = document.getElementById(dialogs[action]);

            if (!dialog || !window.pgUI) {
                return;
            }

            // One row fills the fields with its values; a selection keeps
            // what was chosen last.
            if (id) {
                dialog.querySelectorAll(\'[data-pg-cr-field]\').forEach(function (field) {
                    var name = field.getAttribute(\'data-pg-cr-field\').replace(/_/g, \'-\');
                    var value = button.getAttribute(\'data-pg-cr-value-\' + name);

                    if ((value === null) || ((name === \'user-id\') && (value === \'0\'))) {
                        return;
                    }

                    if (field.hasAttribute(\'data-pg-date\') && window.pgDatePicker && pgDatePicker.set) {
                        pgDatePicker.set(field, value);
                    } else {
                        field.value = value;
                    }
                });
            }

            dialog.setAttribute(\'data-pg-cr-for\', action);
            dialog.setAttribute(\'data-pg-cr-payload\', payload);
            dialog.querySelector(\'[data-pg-cr-count]\').textContent = (count > 1) ? countText.replace(\'{var:1}\', count) : \'\';
            pgUI.dialog.show(dialog);
        });

        document.addEventListener(\'click\', function (event) {
            var button = event.target.closest(\'[data-pg-cr-submit]\');

            if (!button) {
                return;
            }

            var dialog = button.closest(\'dialog\');

            dialog.querySelectorAll(\'[data-pg-cr-field]\').forEach(function (field) {
                var value = field.value;

                // A date field sends the picker\'s ISO date when it has one.
                if (field.hasAttribute(\'data-pg-date\') && window.pgDatePicker && (value.trim() !== \'\')) {
                    value = pgDatePicker.get(field) || value;
                }

                form.elements[field.getAttribute(\'data-pg-cr-field\')].value = value;
            });

            send(dialog.getAttribute(\'data-pg-cr-for\'), dialog.getAttribute(\'data-pg-cr-payload\'));
        });
    }());
</script>
' . pg_fields_script() . '
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
    output_footer();

$liveform->remove_form('view_content_review');
