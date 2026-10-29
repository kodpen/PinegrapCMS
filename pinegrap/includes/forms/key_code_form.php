<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The key code form, shared by the create and the edit screens
 * (add_key_code.php, edit_key_code.php): the checks of a posted key code,
 * the values a save writes and the generated code. Both screens draw
 * includes/templates/edit_key_code.php, chosen with $screen. The screens
 * keep what differs: the create screen's quantity checks and INSERT loop;
 * the edit screen's not-found guard, record load, delete and UPDATE.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}
