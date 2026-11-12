<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The edit form item view screen's layout (edit_form_item_view.php): the
 * form column with the layout card, the sticky status column with the
 * Summary and the clickable variable list, and the sticky save bar. The
 * screen keeps its gates, the record load and the save.
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
 * The form column: the layout card with the code editor.
 *
 * @param string $layout The stored layout, plain text
 * @param string $marker The collection field marker, HTML ('' when the
 *                       activated style has the same collection)
 * @param string $editor The code editor attach markup (pg_code_editor_attach())
 * @return string  HTML
 */
function pg_form_item_view_form_card($layout, $marker, $editor)
{
    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Layout') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <p class="form-text text-start mt-0">' . lang(array('string' => 'Display layout of submitted form data fields within View{var:1}', 'vars' => $marker)) . '</p>
                                    <textarea id="layout" name="layout" aria-label="' . h(lang('Layout')) . '">' . h($layout) . '</textarea>
                                    ' . $editor . '
                                </div>
                            </div>
                        </div>';
}

/**
 * The Summary rows: the page, the form whose submissions it shows, the
 * collection being edited and the page's last change.
 *
 * @param array $values 'page_name', 'form_name' (plain text, '' for none),
 *                      'collection' ('a' or 'b'), 'changed' (HTML, '' for none)
 * @return string  HTML
 */
function pg_form_item_view_form_summary($values)
{
    $form_name = (string) ($values['form_name'] ?? '');
    $changed = (string) ($values['changed'] ?? '');

    return pg_form_item_view_form_status_row(lang('Page'), h((string) ($values['page_name'] ?? '')), false)
        . pg_form_item_view_form_status_row(lang('Custom Form'), ($form_name !== '') ? h($form_name) : '<span class="fg-secondary">' . h(lang('None')) . '</span>', false)
        . pg_form_item_view_form_status_row(lang('Collection'), h(strtoupper((string) ($values['collection'] ?? 'a'))), false)
        . pg_form_item_view_form_status_row(lang('Last Modified'), ($changed !== '') ? $changed : '-');
}

/**
 * One Summary row of the status column: a label and its value.
 *
 * @param string $label The label, plain text
 * @param string $value The value, already HTML
 * @param bool   $last  False draws the line under the row
 * @return string  HTML
 */
function pg_form_item_view_form_status_row($label, $value, $last = true)
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                            </div>';
}

/**
 * The entries of one variable list: each a button that types its token in
 * at the layout's cursor, plus a second button for the token with its
 * format when the field has one.
 *
 * @param array $items Entries of pg_cf_variables_help(): 'token', 'label',
 *                     'type_label', 'example'
 * @return string  HTML ('' for no entries)
 */
function pg_form_item_view_form_variable_items($items)
{
    $output = '';
    foreach ($items as $item) {
        $detail = h($item['label']) . (($item['type_label'] !== '') ? ' · ' . h($item['type_label']) : '');
        $output .= '<li class="list-group-item py-1 px-3 d-flex align-items-center gap-3">'
            . '<button type="button" class="btn btn-link btn-sm p-0 text-start text-decoration-none flex-grow-1 flex-column align-items-start text-wrap text-break" data-pg-insert="' . h($item['token']) . '">'
            . '<code>' . h($item['token']) . '</code><span class="d-block small fg-secondary">' . $detail . '</span></button>';
        if ($item['example'] !== '') {
            $output .= '<button type="button" class="btn btn-sm btn-outline theme-secondary no-popover" data-bs-toggle="tooltip" data-pg-insert="' . h($item['example']) . '" title="' . h($item['example']) . '"><i class="bi bi-braces" aria-hidden="true"></i><span class="visually-hidden">' . h(lang('Insert with format')) . '</span></button>';
        }
        $output .= '</li>';
    }
    return $output;
}

/**
 * The variables card: form fields, system fields, e-mail links and format
 * samples, with the Hints button. The form list view draws it too, without
 * the e-mail links: only the e-mail to the person who sent the form fills
 * them, so a list always prints them empty.
 *
 * @param array $help    pg_cf_variables_help() result: 'fields', 'standard',
 *                       'mail', 'syntax' ('conditional', 'date', 'checkbox')
 * @param array $options 'mail' false leaves the e-mail links group out;
 *                       'hint' replaces the line under the heading (plain text);
 *                       'id' the card's id (pg_fiv_variables), its list is
 *                       '<id>_list'; 'hints' false leaves out the Hints button
 *                       on a screen without a #hints dialog; 'open' false
 *                       starts the list folded
 * @return string  HTML
 */
function pg_form_item_view_form_variables($help, $options = array())
{
    $hint = (string) ($options['hint'] ?? lang('Click a variable to insert it at the cursor in the layout.'));
    $id = (string) ($options['id'] ?? 'pg_fiv_variables');
    $open = (($options['open'] ?? true) !== false);

    $hints = '';
    if (($options['hints'] ?? true) !== false) {
        $hints = '
                                        <button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-toggle="dialog" data-bs-target="#hints"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>' . lang('Hints') . '</button>';
    }

    $mail = '';
    if (($options['mail'] ?? true) !== false) {
        $mail = '
                                        <p class="mb-0">' . lang('E-mail links') . '</p>
                                        <p class="form-text mt-0 mb-1">' . lang('Filled in only in the e-mail to the person who sent the form; empty on the page and in other copies.') . '</p>
                                        <ul class="list-group mb-5">
                                            ' . pg_form_item_view_form_variable_items($help['mail']) . '
                                        </ul>';
    }

    $fields = pg_form_item_view_form_variable_items($help['fields']);
    if ($fields === '') {
        $fields = '<li class="list-group-item py-1 px-3 small fg-secondary">' . h(lang('This form has no fields yet.')) . '</li>';
    }

    $formats = '';
    foreach (array(
        'conditional' => lang('Only when there is a value'),
        'date' => lang('Date format'),
        'checkbox' => lang('Check box as text'),
    ) as $format => $format_label) {
        $formats .= '<li class="list-group-item py-1 px-3">'
            . '<button type="button" class="btn btn-link btn-sm p-0 text-start text-decoration-none w-100 flex-column align-items-start text-wrap text-break" data-pg-insert="' . h($help['syntax'][$format]) . '">'
            . '<code>' . h($help['syntax'][$format]) . '</code><span class="d-block small fg-secondary">' . h($format_label) . '</span></button></li>';
    }

    return '
                                <div class="card mb-7" id="' . h($id) . '">
                                    <div class="card-header bg-reset border-0 d-flex flex-wrap align-items-center justify-content-between gap-3 row-gap-1">
                                        <h2 class="h5 mb-0"><button type="button" class="btn btn-sm btn-text theme-secondary px-1 fw-bold fg-primary text-uppercase" data-bs-toggle="collapse" data-bs-target="#' . h($id) . '_list" aria-expanded="' . ($open ? 'true' : 'false') . '" aria-controls="' . h($id) . '_list"><i class="bi bi-chevron-down me-1" aria-hidden="true"></i>' . lang('Available variables') . '</button></h2>' . $hints . '
                                    </div>
                                    <div class="card-body pt-0 collapse' . ($open ? ' show' : '') . '" id="' . h($id) . '_list">
                                        <p class="form-text mt-0 mb-3">' . h($hint) . '</p>
                                        <p class="mb-0 forms-color">' . lang('Form Fields') . '</p>
                                        <ul class="list-group overflow-auto pe-1 mb-5" style="min-height:100px;height:220px;max-height:600px;resize:vertical;">
                                            ' . $fields . '
                                        </ul>
                                        <p class="mb-0 fg-primary">' . lang('System Fields') . '</p>
                                        <ul class="list-group overflow-auto pe-1 mb-5" style="min-height:100px;height:220px;max-height:600px;resize:vertical;">
                                            ' . pg_form_item_view_form_variable_items($help['standard']) . '
                                        </ul>' . $mail . '
                                        <p class="mb-0">' . lang('Formats') . '</p>
                                        <ul class="list-group">
                                            ' . $formats . '
                                        </ul>
                                    </div>
                                </div>';
}

/**
 * The script behind the variables card: a click on a [data-pg-insert] button
 * types its text in at the cursor of the code editor last focused among
 * $editor_ids (the first one until another was focused). Without the editor
 * it goes into that textarea, as a last resort to the clipboard.
 *
 * @param string $card_id    The card's id (pg_fiv_variables)
 * @param array  $editor_ids Textarea ids of the code editors (or plain fields),
 *                           the default first
 * @param array  $options    'transient' true for markup that is replaced while
 *                           the page stays (the designer's page type pane): the
 *                           focus listener drops itself once the card is gone,
 *                           so reopening does not pile listeners up
 * @return string  HTML
 */
function pg_form_item_view_form_insert_script($card_id, $editor_ids, $options = array())
{
    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    $listen = '
        document.addEventListener("focusin", function (event) {';
    if (!empty($options['transient'])) {
        $listen = '
        document.addEventListener("focusin", function onFocus(event) {
            if (!panel.isConnected) {
                document.removeEventListener("focusin", onFocus);
                return;
            }';
    }

    return '
<script>
    (function () {
        var panel = document.getElementById(' . json_encode((string) $card_id, $flags) . ');
        if (!panel) return;
        var ids = ' . json_encode(array_values(array_map('strval', $editor_ids)), $flags) . ';
        var last = ids[0];
        var editorOf = function (id) { return window.pgCodeEditors ? window.pgCodeEditors[id] : null; };' . $listen . '
            for (var i = 0; i < ids.length; i++) {
                var editor = editorOf(ids[i]);
                var host = editor && (editor.host || editor.container);
                if (event.target.id === ids[i] || (host && host.contains(event.target))) {
                    last = ids[i];
                    return;
                }
            }
        });
        panel.addEventListener("click", function (event) {
            var button = event.target.closest("[data-pg-insert]");
            if (!button) return;
            var text = button.getAttribute("data-pg-insert");
            var tip = window.pgUI ? pgUI.tooltip.find(button) : null;
            if (tip) tip.hide();
            var editor = editorOf(last);
            if (editor && typeof editor.insertAtCursor === "function") {
                editor.insertAtCursor(text);
                return;
            }
            var field = document.getElementById(last);
            if (field && field.offsetParent !== null && typeof field.setRangeText === "function") {
                field.setRangeText(text, field.selectionStart, field.selectionEnd, "end");
                field.dispatchEvent(new Event("input", { bubbles: true }));
                field.focus();
                return;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    if (typeof window.pgToast === "function") window.pgToast({ message: ' . json_encode(lang('Copied to clipboard'), JSON_HEX_TAG | JSON_HEX_AMP) . ', variant: "success" });
                });
            }
        });
    })();
</script>';
}

/**
 * The sticky status column: the Summary, then the variables card. From lg
 * the box scrolls on its own when it is taller than the window, so the
 * variables stay reachable beside a long layout.
 *
 * @param string $summary   pg_form_item_view_form_summary(), HTML
 * @param string $variables pg_form_item_view_form_variables(), HTML
 * @return string  HTML
 */
function pg_form_item_view_form_status($summary, $variables)
{
    return '
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <style>
                                /* From lg, where the columns stand side by side: the box sticks
                                   below the fixed control panel header (3.3rem) and scrolls on
                                   its own. Stacked on a narrow screen it is in the page flow, so
                                   a height cap there would only cut the lists off. */
                                @layer custom {
                                    @media (min-width: 1024px) {
                                        #pg_fiv_status { position: sticky; top: 3.3rem; max-height: calc(100vh - 4.3rem); overflow-y: auto; }
                                    }
                                }
                            </style>
                            <div id="pg_fiv_status">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">' . $summary . '
                                        </dl>
                                    </div>
                                </div>' . $variables . '
                            </div>
                        </div>';
}

/**
 * The screen row: the form column, the status column and the sticky save
 * bar with the given buttons.
 *
 * @param string $columns pg_form_item_view_form_card() . pg_form_item_view_form_status()
 * @param string $buttons The buttons inside the bar's group
 * @return string  HTML
 */
function pg_form_item_view_form_layout($columns, $buttons)
{
    return '
                    <div class="row">' . $columns . '
                        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">' . $buttons . '
                                    </div>
                                </div>
                            </nav>
                        </div>
                    </div>';
}
