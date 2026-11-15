<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel's framed save bar: one sticky column after a screen's form and
 * status columns that holds the screen's buttons in the floating bar of
 * look A (backend.src.css, "The save bar"). Screens that drew a bare sticky
 * button row draw it through here.
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
 * The framed save bar around a screen's buttons. It is a `col-12 order-3`
 * column so a `.row` puts it under the form and status columns; the column,
 * not the bar, sticks to the window's bottom edge. The `pg-savebar` class
 * draws the frame on any screen, with or without the edit width.
 *
 * Options:
 * - label:  the bar's accessible name, already translated (default
 *           "data edit buttons")
 * - id:     the bar's id
 * - hidden: true starts the bar hidden with an inline display, for a script
 *           that shows it with jQuery's .show()
 * - group:  the classes of the box around the buttons (default
 *           "btn-group flex-wrap justify-content-center"); '' prints the
 *           buttons straight in the bar's container
 * - after:  markup printed after the buttons inside the container (a live
 *           status line)
 *
 * @param string $buttons The buttons' markup, printed as given
 * @param array  $opts
 * @return string HTML
 */
function pg_save_bar($buttons, $opts = array())
{
    $label = isset($opts['label']) ? (string) $opts['label'] : lang('data edit buttons');
    $group = array_key_exists('group', $opts) ? (string) $opts['group'] : 'btn-group flex-wrap justify-content-center';

    $attributes = '';

    if (!empty($opts['id'])) {
        $attributes .= ' id="' . h((string) $opts['id']) . '"';
    }

    if (!empty($opts['hidden'])) {
        $attributes .= ' style="display:none"';
    }

    $inner = ($group !== '')
        ? '
                <div class="' . h($group) . '">' . $buttons . '
                </div>'
        : $buttons;

    return '
        <div class="col-12 order-3 position-sticky pg-savebar" style="bottom:0; z-index:3;">
            <nav class="buttons navigation text-center mb-7"' . $attributes . ' aria-label="' . h($label) . '">
                <div class="container">' . $inner . (isset($opts['after']) ? (string) $opts['after'] : '') . '
                </div>
            </nav>
        </div>';
}

/**
 * The hidden first submit button of a save bar whose buttons run ghost,
 * quiet, filled. Return in a text field presses a form's first submit
 * button; with the delete or a quiet action first in the bar that would be
 * the one pressed. This nameless button comes first instead, and the panel
 * script (backend.src.js) hands its press to the bar's first filled button,
 * the save, so that button's own handlers and question run as before.
 *
 * @param string $form The id of the form the bar's buttons name in their
 *                     form attribute, when the bar sits outside it
 * @return string HTML
 */
function pg_save_bar_return_button($form = '')
{
    return '<button type="submit"' . ($form !== '' ? ' form="' . h($form) . '"' : '') . ' class="visually-hidden" tabindex="-1" aria-hidden="true" data-pg-return-save></button>';
}
