<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The auto dialog save, shared by the create and the edit screens
 * (add_auto_dialog.php, edit_auto_dialog.php): the required and name
 * checks, the page clean-up and the values a save writes. Both screens draw
 * includes/templates/edit_auto_dialog.php; they keep the record load, the
 * preview link, delete, the INSERT/UPDATE, the log and the redirects.
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
 * The checks a save runs: name and URL required, the text fields no longer
 * than their columns, the four number fields blank or a whole number inside
 * their limit, and a name not used by another auto dialog. The uniqueness
 * query runs only when a name was given.
 *
 * @param liveform $liveform
 * @param string|null $id  The auto dialog being edited, null on create
 * @return void
 */
function pg_auto_dialog_form_validate($liveform, $id)
{
    $liveform->validate_required_field('name', lang(array('string'=>'{var:1} is required.','vars'=>lang('Name'))));
    $liveform->validate_required_field('url', lang(array('string'=>'{var:1} is required.','vars'=>lang('URL'))));

    // The column lengths: a longer value would be cut silently on save.
    foreach (array('name' => array('Name', 100), 'url' => array('URL', 255), 'page' => array('Only on Page', 100)) as $field => $rule) {
        if (!$liveform->check_field_error($field)
            && (mb_strlen((string) $liveform->get_field_value($field), 'UTF-8') > $rule[1])) {
            $liveform->mark_error($field, lang(array('string'=>'{var:1} can be at most {var:2} characters long.','vars'=>array(lang($rule[0]), $rule[1]))));
        }
    }

    // The columns are INT UNSIGNED and the session sql_mode is not strict,
    // so a negative, decimal or oversized value would be clamped or rounded
    // into another meaning (a negative frequency became 0: one-time).
    $labels = array('width' => lang('Width'), 'height' => lang('Height'), 'delay' => lang('Delay'), 'frequency' => lang('Frequency'));

    foreach (pg_auto_dialog_form_number_limits() as $field => $max) {
        if (pg_auto_dialog_form_whole_number($liveform->get_field_value($field), $max) === null) {
            $liveform->mark_error($field, lang(array('string'=>'{var:1} must be a whole number from {var:2} to {var:3}.','vars'=>array($labels[$field], 0, pg_format_number($max, 0)))));
        }
    }

    if (!$liveform->check_field_error('name')) {
        $taken = pg_auto_dialog_name_in_use((string) $liveform->get_field_value('name'), ($id === null) ? null : (int) $id);

        if ($taken === null) {
            output_error('Query failed.');
        }

        if ($taken) {
            $liveform->mark_error('name', lang('Sorry, the name that you entered is already in use, so please enter a different name.'));
        }
    }
}

/**
 * The upper limit of each number field. Width and height are pixels (four
 * digits); the delay is seconds and must stay inside setTimeout's 2^31 - 1
 * milliseconds, past which the browser opens the dialog at once; the
 * frequency is hours.
 *
 * @return array  field => maximum
 */
function pg_auto_dialog_form_number_limits()
{
    return array(
        'width' => 9999,
        'height' => 9999,
        'delay' => 2147483,
        'frequency' => 999999,
    );
}

/**
 * A number field's typed value as a whole number from 0 to $max: '' when
 * blank, the digits as a string when valid, null otherwise. Thousands
 * separators are read in groups of three ("2.400" or "2,400" is 2400); a
 * sign, a decimal part or any other character is refused instead of being
 * clamped or rounded.
 *
 * @param mixed $raw
 * @param int $max
 * @return string|null
 */
function pg_auto_dialog_form_whole_number($raw, $max)
{
    $value = trim(str_replace(array("\u{00A0}", ' '), '', (string) $raw));

    if ($value === '') {
        return '';
    }

    if (!preg_match('/^[0-9]+$/', $value) && !preg_match('/^[0-9]{1,3}(?:([.,])[0-9]{3})(?:\1[0-9]{3})*$/', $value)) {
        return null;
    }

    $digits = ltrim(str_replace(array('.', ','), '', $value), '0');

    if ($digits === '') {
        return '0';
    }

    if ((strlen($digits) > strlen((string) $max)) || ((int) $digits > $max)) {
        return null;
    }

    return $digits;
}

/**
 * The page name a save stores: a leading slash (a URL typed instead of a
 * page name) is removed.
 *
 * @param string $page
 * @return string
 */
function pg_auto_dialog_form_page($page)
{
    $page = (string) $page;

    if (mb_substr($page, 0, 1) == '/') {
        $page = mb_substr($page, 1);
    }

    return $page;
}

/**
 * The eight auto dialog values a save writes, in INSERT/UPDATE order: name,
 * enabled, url, width, height, delay, frequency, page. The number fields are
 * stored as read by pg_auto_dialog_form_whole_number() ("2.400" -> 2400).
 *
 * @param liveform $liveform
 * @return array
 */
function pg_auto_dialog_form_values($liveform)
{
    $numbers = array();

    foreach (pg_auto_dialog_form_number_limits() as $field => $max) {
        $numbers[$field] = (string) pg_auto_dialog_form_whole_number($liveform->get_field_value($field), $max);
    }

    return array(
        (string) $liveform->get_field_value('name'),
        (string) $liveform->get_field_value('enabled'),
        (string) $liveform->get_field_value('url'),
        $numbers['width'],
        $numbers['height'],
        $numbers['delay'],
        $numbers['frequency'],
        pg_auto_dialog_form_page($liveform->get_field_value('page')),
    );
}
