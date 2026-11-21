<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the Canvas templates card of Site Settings (Assistants and
 * agents): the canvases people marked as templates in the image editor,
 * each with its size and named layers, and a box an administrator unticks
 * to take one off the list. Marking is done in the editor (Save as
 * template); the list itself is includes/fn/canvas_templates.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/canvas_templates.php');

/**
 * The list after a save of the card: a template shown on the card stays
 * only while its box is ticked; one not shown (its file gone or changed
 * outside the editor) goes too, as nothing can make it a template again but
 * the editor. Pure.
 *
 * @param int[]  $stored  the list as kept
 * @param int[]  $shown   the ids the card drew
 * @param mixed  $checked the ids whose boxes came ticked
 * @return int[]
 */
function ws_canvas_templates_settings_keep($stored, $shown, $checked)
{
    $ticked = array_map('intval', is_array($checked) ? array_values($checked) : array());
    $kept = array();

    foreach ((array) $stored as $id) {
        if (in_array((int) $id, array_map('intval', (array) $shown), true) && in_array((int) $id, $ticked, true)) {
            $kept[] = (int) $id;
        }
    }

    return $kept;
}

/**
 * One template's row: its switch, name, size and named layers. The copy in
 * the drawer of every template carries the pane's switch under another id
 * and posts with the settings form by its id; ws_canvas_templates_settings_script()
 * keeps the two switches of a template alike.
 *
 * @param array       $row   pg_canvas_templates_rows()'s
 * @param bool        $admin
 * @param bool        $copy  the drawer's copy
 * @param string|null $form  the settings form's id, which the copy joins
 * @return string HTML
 */
function ws_canvas_templates_settings_row_html($row, $admin, $copy = false, $form = null)
{
    $names = array();

    foreach ($row['summary']['layers'] as $layer) {
        $names[] = $layer['name'] . ' (' . $layer['kind'] . ')';
    }

    $id = 'ws_canvas_template_' . ($copy ? 'all_' : '') . (int) $row['id'];

    return '
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <input class="pg-switch" type="checkbox" name="ws_canvas_template[]" value="' . (int) $row['id'] . '" id="' . $id . '" aria-describedby="' . $id . '-help" data-ws-canvas-template="' . (int) $row['id'] . '"'
                            . (($copy && ($form !== null)) ? ' form="' . h($form) . '"' : '') . ' checked' . ($admin ? '' : ' disabled') . '>
                        <div><label class="pg-text-2" for="' . $id . '">' . h($row['name']) . ' · ' . (int) $row['summary']['width'] . ' × ' . (int) $row['summary']['height'] . '</label>
                        <div class="pg-field-help m-0" id="' . $id . '-help">' . h($names ? lang(array('string' => 'Named layers: {var:1}', 'vars' => implode(', ', $names))) : lang('No named layers: an agent can copy it but fill nothing.')) . '</div></div>
                    </div>';
}

/**
 * The card, or '' where the Workspace cannot hold canvases. Five templates
 * stand in it; with more, every one is in the drawer "Show all N" opens.
 *
 * @param array       $viewer
 * @param string|null $form the settings form's id
 * @param string|null $all  set to every template's row for that drawer, ''
 *                          while all fit
 * @return string
 */
function ws_canvas_templates_settings_card($viewer, $form = null, &$all = null)
{
    $all = '';

    if (!function_exists('pg_file_versions_ready') || !pg_file_versions_ready()) {
        return '';
    }

    $admin = ((int) $viewer['role'] === 0);
    $rows = pg_canvas_templates_rows();
    $items = '';
    $copies = '';

    // No template asks for anything: the first five in the list's order.
    $shown = ws_assistants_list_cut(array_fill(0, count($rows), false));

    foreach (array_values($rows) as $index => $row) {
        if (in_array($index, $shown, true)) {
            $items .= ws_canvas_templates_settings_row_html($row, $admin);
        }

        $copies .= ws_canvas_templates_settings_row_html($row, $admin, true, $form);
    }

    $cut = (count($shown) < count($rows));
    $all = $cut ? $copies : '';

    $body = ws_assistants_help_html('ws-canvas-templates')
        . (($items !== '') ? $items . ws_assistants_show_all_html('ws-canvas-templates', count($rows)) : '
                    <p class="small fg-secondary mb-0">' . h(lang('No templates yet.')) . '</p>');

    return '
    <div id="pgset-ws-canvas-templates" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . h(lang('Canvas templates')) . '
            </div>
            <div class="card-body">
                ' . (($admin && $rows) ? '<input type="hidden" name="ws_canvas_templates_card" value="1">' : '') . $body . '
                    ' . (($admin || !$rows) ? '' : '<div class="pg-field-help">' . h(lang('Only an administrator can change this.')) . '</div>') . '
            </div>
        </div>
    </div>' . ($cut ? ws_canvas_templates_settings_script() : '');
}

/**
 * Keeps a template's two switches (the pane's and the drawer's) alike, so
 * an unticked one is not posted ticked by its twin. Delegated to the
 * document and bound once.
 *
 * @return string
 */
function ws_canvas_templates_settings_script()
{
    return '
        <script>
        (function () {
            if (window.pgWsCanvasTemplatesBound) {
                return;
            }

            window.pgWsCanvasTemplatesBound = true;

            document.addEventListener(\'change\', function (event) {
                var box = event.target;

                if (!box.matches || !box.matches(\'[data-ws-canvas-template]\')) {
                    return;
                }

                document.querySelectorAll(\'[data-ws-canvas-template="\' + box.getAttribute(\'data-ws-canvas-template\') + \'"]\').forEach(function (twin) {
                    twin.checked = box.checked;
                });
            });
        })();
        </script>';
}

/**
 * Writes the card's save: the templates whose boxes were unticked are taken
 * off the list.
 *
 * @param mixed    $checked the ticked ids
 * @param liveform $liveform
 */
function ws_canvas_templates_settings_save($checked, $liveform)
{
    $stored = pg_canvas_templates_ids();
    $shown = array_column(pg_canvas_templates_rows(), 'id');
    $kept = ws_canvas_templates_settings_keep($stored, $shown, $checked);

    if ($kept === $stored) {
        return;
    }

    if (!pg_canvas_templates_save($kept)) {
        $liveform->add_warning(lang('Sorry, the file could not be saved.'));
        return;
    }

    log_activity(lang(array('string' => '{var:1} canvas template(s) were taken off the list', 'vars' => count($stored) - count($kept))), (string) ($_SESSION['sessionusername'] ?? ''));
}
