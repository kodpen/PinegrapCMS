<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Variable chips: a message's variables drawn as chips that name what they
 * stand for instead of the {{1}} a message service reads. The markup of a
 * chip and the script that turns a textarea holding {{n}} into an editor of
 * text and chips (assets/js/pg_var_chips.js), shared by the screens that
 * write such messages: the WhatsApp template designer and the rows that say
 * what fills a template's variables.
 *
 * Loaded where it is needed (require_once), not by functions.php.
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
 * One chip. A palette chip is a button that is clicked or dragged into the
 * editor; a plain chip only shows a variable.
 *
 * @param string $label   what the variable stands for
 * @param bool   $palette a button to click or drag
 * @return string HTML
 */
function pg_var_chip_html($label, $palette = false)
{
    $label = (string) $label;
    $text = '<span class="pg-var-chip-text">' . h($label) . '</span>';

    if ($palette) {
        return '<button type="button" class="pg-var-chip" draggable="true" data-pg-var-label="' . h($label) . '">' . $text . '</button>';
    }

    return '<span class="pg-var-chip" data-pg-var-label="' . h($label) . '">' . $text . '</span>';
}

/**
 * The chips' script, once per page.
 *
 * @return string HTML
 */
function pg_var_chips_script()
{
    static $printed = false;

    if ($printed) {
        return '';
    }

    $printed = true;

    return '<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_var_chips.js?v=' . (int) @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_var_chips.js') . '"></script>';
}
