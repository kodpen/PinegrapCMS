<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the / commands an application registers for itself (for
 * example /summarize or /report), offered in the channel beside the built-in
 * ones (includes/workspace/commands.php, ws_commands()).
 *
 * A registered name is one word of letters, the shape ws_command_attempt()
 * reads as a command, so the composer sees it as one; it never takes a name a
 * built-in command or an assistant answers to, so a / word means one thing.
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
 * The longest description and usage line an application may register.
 */
const WS_APP_COMMAND_TEXT_MAX = 200;

/**
 * Checks one command an application wants to register and gives it back in
 * the shape it is kept: the name in Turkish lower case without its slash,
 * the texts trimmed with their line breaks folded into spaces.
 *
 * @param mixed $input name, description, optional usage (the arguments after
 *                     the name, as /help shows them)
 * @return array name, description, usage; or error (name_invalid,
 *               name_taken, description_required, description_too_long,
 *               usage_too_long) and field
 */
function ws_app_command_check($input)
{
    $input = is_array($input) ? $input : array();
    $typed = isset($input['name']) && is_string($input['name']) ? ltrim(trim($input['name']), '/') : '';
    $name = ws_lower($typed);

    if (!preg_match('/^\p{Ll}{2,20}$/u', $name)) {
        return array('error' => 'name_invalid', 'field' => 'name');
    }

    if (isset(ws_command_table()[$name]) || ws_command_assistant_word($typed)) {
        return array('error' => 'name_taken', 'field' => 'name');
    }

    $description = ws_app_command_text(isset($input['description']) ? $input['description'] : '');

    if ($description === '') {
        return array('error' => 'description_required', 'field' => 'description');
    }

    if (mb_strlen($description, 'UTF-8') > WS_APP_COMMAND_TEXT_MAX) {
        return array('error' => 'description_too_long', 'field' => 'description');
    }

    $usage = ws_app_command_text(isset($input['usage']) ? $input['usage'] : '');

    if (mb_strlen($usage, 'UTF-8') > WS_APP_COMMAND_TEXT_MAX) {
        return array('error' => 'usage_too_long', 'field' => 'usage');
    }

    return array('name' => $name, 'description' => $description, 'usage' => $usage);
}

/**
 * One line of registered text: anything but a string is empty, control
 * characters and line breaks become single spaces.
 *
 * @param mixed $text
 * @return string
 */
function ws_app_command_text($text)
{
    if (!is_string($text)) {
        return '';
    }

    return trim((string) preg_replace('/[\p{Cc}\s]+/u', ' ', $text));
}
