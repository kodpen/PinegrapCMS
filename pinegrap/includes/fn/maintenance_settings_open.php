<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Updates and maintenance, the rows drawn open: the parts
 * every row but Scheduled jobs is built of. A pg-switch with one line of
 * help, a choice of a few as a pg-seg, a group's label. Pure.
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
 * A pg-switch with its label and at most one line of help, posting 1 under
 * its name as the old switch did. Pure.
 *
 * @param string $name       the field's name and id
 * @param bool   $on         it is on
 * @param string $label      plain text
 * @param string $help       plain text, '' for none
 * @param string $attributes more attributes of the input, already escaped
 * @return string HTML, a column of the card's row
 */
function pg_maintenance_settings_switch($name, $on, $label, $help = '', $attributes = '')
{
    return '
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-5">
                            <input type="checkbox" class="pg-switch" value="1"' . ($on ? ' checked="checked"' : '') . ' id="' . h($name) . '" name="' . h($name) . '"' . (($help !== '') ? ' aria-describedby="' . h($name) . '_help"' : '') . $attributes . '/>
                            <label class="pg-text-2" for="' . h($name) . '">' . h($label) . '</label>
                        </div>' . (($help !== '') ? '
                        <p class="pg-field-help pg-text-meta fg-secondary mb-0" id="' . h($name) . '_help">' . h($help) . '</p>' : '') . '
                    </div>';
}

/**
 * A choice of a few as a pg-seg under its legend. The radios post the values
 * the select posted; each radio's id is the name and the value. Pure.
 *
 * @param string $legend  plain text
 * @param string $name    the radios' name
 * @param array  $options value => label (plain text), in order
 * @param string $current the value checked
 * @param string $help    plain text, '' for none
 * @return string HTML, a column of the card's row
 */
function pg_maintenance_settings_seg($legend, $name, $options, $current, $help = '')
{
    $items = '';

    foreach ($options as $value => $label) {
        $id = $name . '_' . $value;
        $items .= '
                                <label class="pg-seg-item" for="' . h($id) . '"><input value="' . h($value) . '" class="visually-hidden" type="radio" id="' . h($id) . '" name="' . h($name) . '"' . (((string) $value === (string) $current) ? ' checked="checked"' : '') . '/>' . h($label) . '</label>';
    }

    return '
                    <div class="col-12">
                        <fieldset' . (($help !== '') ? ' aria-describedby="' . h($name) . '_help"' : '') . '><legend class="form-label pg-field-label">' . h($legend) . '</legend>
                            <div class="pg-seg flex-wrap">' . $items . '
                            </div>' . (($help !== '') ? '
                            <p class="pg-field-help pg-text-meta fg-secondary mt-2 mb-0" id="' . h($name) . '_help">' . h($help) . '</p>' : '') . '
                        </fieldset>
                    </div>';
}

/**
 * A group's label across the column. Pure.
 *
 * @param string $label plain text
 * @return string HTML
 */
function pg_maintenance_settings_group($label)
{
    return '
                    <div class="col-12"><h3 class="pg-group-label mb-0">' . h($label) . '</h3></div>';
}
