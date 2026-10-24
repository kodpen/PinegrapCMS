<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - voice messages: the microphone of the writing box records a
 * message that is posted as a sound file, and every sound file in a
 * conversation plays in place (assets/js/workspace_voice.js).
 *
 * A recording travels like any other file: the panel's upload (ws_attach,
 * ws_message_send_file()) checks and keeps it, so who may post it and who
 * may hear it are the channel's own rules. Nothing here is stored; this file
 * holds the texts the script shows.
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
 * The texts of the recording row and the player (assets/js/workspace_voice.js).
 *
 * @return array key => text
 */
function ws_voice_js_strings()
{
    return array(
        'voice_record'      => lang('Record voice message'),
        'voice_stop'        => lang('Stop recording'),
        'voice_discard'     => lang('Discard recording'),
        'voice_denied'      => lang('Microphone permission was denied.'),
        'voice_unsupported' => lang('Voice recording is not supported in this browser.'),
        'voice_ending'      => ws_js_template('The recording stops and is sent in {var:1} seconds.', 1),
        'voice_message'     => lang('Voice message'),
        'voice_play'        => lang('Play'),
        'voice_pause'       => lang('Pause'),
        'voice_speed'       => lang('Speed'),
    );
}
