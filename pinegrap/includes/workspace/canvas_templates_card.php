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
 * The card, or '' where the Workspace cannot hold canvases.
 *
 * @param array $viewer
 * @return string
 */
function ws_canvas_templates_settings_card($viewer)
{
    if (!function_exists('pg_file_versions_ready') || !pg_file_versions_ready()) {
        return '';
    }

    $admin = ((int) $viewer['role'] === 0);
    $rows = pg_canvas_templates_rows();
    $items = '';

    foreach ($rows as $row) {
        $names = array();

        foreach ($row['summary']['layers'] as $layer) {
            $names[] = $layer['name'] . ' (' . $layer['kind'] . ')';
        }

        $id = 'ws_canvas_template_' . (int) $row['id'];
        $items .= '
                    <div class="form-field mb-3">
                        <div class="switch"><input type="checkbox" name="ws_canvas_template[]" value="' . (int) $row['id'] . '" id="' . $id . '" checked' . ($admin ? '' : ' disabled') . '></div>
                        <label for="' . $id . '">' . h($row['name']) . ' · ' . (int) $row['summary']['width'] . ' × ' . (int) $row['summary']['height'] . '</label>
                        <div class="form-text">' . h($names ? lang(array('string' => 'Named layers: {var:1}', 'vars' => implode(', ', $names))) : lang('No named layers: an agent can copy it but fill nothing.')) . '</div>
                    </div>';
    }

    $body = '
                    <p class="small fg-secondary">' . h(lang('Canvases marked with Save as template in the Design Canvas. An agent lists them and makes a canvas from one, giving the words and pictures of its named layers. Untick one and save to take it off the list.')) . '</p>'
        . (($items !== '') ? $items : '
                    <p class="small fg-secondary mb-0">' . h(lang('No templates yet: open a canvas from Files in the Design Canvas and choose Save as template in the save menu.')) . '</p>');

    return '
    <div id="pgset-ws-canvas-templates" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . h(lang('Canvas templates')) . '
            </div>
            <div class="card-body">
                ' . (($admin && $rows) ? '<input type="hidden" name="ws_canvas_templates_card" value="1">' : '') . $body . '
                    ' . (($admin || !$rows) ? '' : '<div class="form-text">' . h(lang('Only an administrator can change this.')) . '</div>') . '
            </div>
        </div>
    </div>';
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
