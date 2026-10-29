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
 * The checks a save runs: name and URL required, and a name not used by
 * another auto dialog. The uniqueness query runs only when a name was given.
 *
 * @param liveform $liveform
 * @param string|null $id  The auto dialog being edited, null on create
 * @return void
 */
function pg_auto_dialog_form_validate($liveform, $id)
{
    $liveform->validate_required_field('name', lang(array('string'=>'{var:1} is required.','vars'=>lang('Name'))));
    $liveform->validate_required_field('url', lang(array('string'=>'{var:1} is required.','vars'=>lang('URL'))));

    if (!$liveform->check_field_error('name')) {
        $query = "SELECT COUNT(*) FROM auto_dialogs WHERE (name = ?)";
        $values = array((string) $liveform->get_field_value('name'));

        if ($id !== null) {
            $query .= " AND (id != ?)";
            $values[] = (string) $id;
        }

        $result = db_q($query, $values) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        if ((int) $row[0] != 0) {
            $liveform->mark_error('name', lang('Sorry, the name that you entered is already in use, so please enter a different name.'));
        }
    }
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
 * enabled, url, width, height, delay, frequency, page.
 *
 * @param liveform $liveform
 * @return array
 */
function pg_auto_dialog_form_values($liveform)
{
    return array(
        (string) $liveform->get_field_value('name'),
        (string) $liveform->get_field_value('enabled'),
        (string) $liveform->get_field_value('url'),
        (string) $liveform->get_field_value('width'),
        (string) $liveform->get_field_value('height'),
        (string) $liveform->get_field_value('delay'),
        (string) $liveform->get_field_value('frequency'),
        pg_auto_dialog_form_page($liveform->get_field_value('page')),
    );
}
