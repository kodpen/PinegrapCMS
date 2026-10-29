<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Convert to text" under a voice message (assets/js/pg_voice_text.js): the
 * words its control shows. The browser's own model writes the text; nothing
 * of it reaches the server, so this module holds only the texts the
 * Workspace player (ws_voice_js_strings()) and the panel's chat window
 * (pg_chat_panel_strings()) hand to the script.
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
 * The texts of the "Convert to text" control, by the keys the script asks.
 *
 * @return array key => translated text
 */
function pg_voice_text_strings()
{
    return array(
        'voice_text_convert'  => lang('Convert to text'),
        'voice_text_working'  => lang('Converting to text…'),
        'voice_text_download' => lang('Downloading the browser\'s speech model…'),
        'voice_text_by'       => lang('Written out by this browser\'s built-in AI, for you only'),
        'voice_text_failed'   => lang('The recording could not be converted to text.'),
    );
}
