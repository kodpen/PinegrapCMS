<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The name rule of the regions a page style places by a tag of their name
 * (<menu>name</menu>, <ad>name</ad>), shared by their create and edit
 * forms (menu_form.php, ad_region_form.php).
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
 * Why a tag name breaks the name rule, or '' when it does not. A new name
 * takes letters, numbers and dashes; underscores stay allowed only for a
 * record whose stored name already has one, because the theme designer
 * does not support them and renaming would break its styles.
 *
 * @param string $name
 * @param string|null $previous_name  Stored name on edit, null on create
 * @return string
 */
function pg_tag_name_error($name, $previous_name)
{
    if ($previous_name === null) {
        $regex = '/[^A-Za-z0-9-]/';
        $message = lang('Please only enter letters, numbers, and dashes for the name.');
    } elseif (mb_strpos($previous_name, '_') !== false) {
        $regex = '/[^A-Za-z0-9_-]/';
        $message = lang('Please only enter letters, numbers, dashes, and underscores for the name.');
    } else {
        $regex = '/[^A-Za-z0-9-]/';
        $message = lang('Please only enter letters, numbers, or dashes for the name.');
    }

    return (preg_match($regex, (string) $name) == 1) ? $message : '';
}
