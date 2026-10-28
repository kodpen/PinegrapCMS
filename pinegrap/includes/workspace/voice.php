<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - voice messages: the microphone of the writing box records a
 * message that is posted as a sound file, every sound file in a conversation
 * plays in place, and its own microphone answers it with a recording
 * (assets/js/workspace_voice.js).
 *
 * A recording travels like any other file: the panel's upload (ws_attach,
 * ws_message_send_file()) checks and keeps it, so who may post it and who
 * may hear it are the channel's own rules; an answer carries parent_id
 * (ws_message_parent_check()).
 *
 * Transcripts: an administrator connects the site's own key for an
 * OpenAI-compatible /audio/transcriptions service on the settings card here;
 * a recording is written out only in a channel that allows it
 * (ws_voice_channel_allowed(), ws_channels.voice_access). The text is kept in
 * ws_voice, apart from the person's own words.
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
        'voice_reply'       => lang('Reply with voice'),
        'voice_busy'        => lang('Sorry, please save or cancel your changes first.'),
    );
}

/* ---------------------------------------------------------------------------
   Transcripts: the connection and where it may be used
   --------------------------------------------------------------------------- */

/**
 * The longest recording sent to be written out, in seconds: the recorder's
 * own cap (LIMIT in workspace_voice.js).
 */
define('WS_VOICE_MAX_SECONDS', 300);

/**
 * Whether the schema step that brought transcripts in (11.0.89) has run.
 *
 * @return bool
 */
function ws_voice_schema_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_voice')
            && pg_schema_has('ws_channels', 'voice_access')
            && pg_schema_has('config', 'ws_voice_enabled')
            && pg_schema_has('config_text');
    }

    return $ready;
}

/**
 * The service a site without an address of its own sends recordings to, and
 * the model asked for when none is entered.
 *
 * @return string
 */
function ws_voice_default_url()
{
    return 'https://api.openai.com/v1';
}

/**
 * @return string
 */
function ws_voice_default_model()
{
    return 'whisper-1';
}

/**
 * An address as it is entered, made the base the calls are joined to: no
 * spaces round it, no slash at its end, and without /audio/transcriptions
 * when the whole endpoint was pasted.
 *
 * @param string $url
 * @return string
 */
function ws_voice_url_clean($url)
{
    $url = rtrim(trim((string) $url), '/');

    if (preg_match('#/audio/transcriptions$#i', $url)) {
        $url = rtrim(substr($url, 0, -strlen('/audio/transcriptions')), '/');
    }

    return $url;
}

/**
 * Could this be the base address of an OpenAI-compatible service? HTTPS, a
 * host, no name and password, no query or fragment: the key travels in a
 * header and must not leave on a plain connection.
 *
 * @param string $url as ws_voice_url_clean() answers it
 * @return bool
 */
function ws_voice_url_valid($url)
{
    $url = (string) $url;

    if (($url === '') || (strlen($url) > 255) || preg_match('/\s/', $url)) {
        return false;
    }

    $parts = parse_url($url);

    if (!is_array($parts) || (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') || ((string) ($parts['host'] ?? '') === '')) {
        return false;
    }

    return !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment']);
}

/**
 * Could this be a model name? Letters, digits and . _ : / - only.
 *
 * @param string $model
 * @return bool
 */
function ws_voice_model_valid($model)
{
    return (bool) preg_match('#^[A-Za-z0-9][A-Za-z0-9._:/-]{0,99}$#', (string) $model);
}

/**
 * Could this be a service's key? Printable characters without spaces.
 *
 * @param string $key
 * @return bool
 */
function ws_voice_key_shape_ok($key)
{
    return (bool) preg_match('/^[\x21-\x7e]{8,512}$/', (string) $key);
}

/**
 * The name the consent lines give the service a recording is sent to: the
 * company for the services people know, otherwise the host itself.
 *
 * @param string $url the base address, '' for the default
 * @return string
 */
function ws_voice_provider_name($url)
{
    $host = strtolower((string) parse_url(($url !== '') ? (string) $url : ws_voice_default_url(), PHP_URL_HOST));

    if (($host === 'openai.com') || (substr($host, -11) === '.openai.com')) {
        return 'OpenAI';
    }

    if (substr($host, -17) === '.openai.azure.com') {
        return 'Azure OpenAI';
    }

    if (($host === 'groq.com') || (substr($host, -9) === '.groq.com')) {
        return 'Groq';
    }

    return $host;
}

/**
 * The connection: the switch and the hold from config, the texts from
 * config_text. The key is never part of it, only whether one is stored.
 *
 * @param bool $fresh read config again
 * @return array enabled, url (the entered one, may be ''), model, key_set,
 *               error, hold_until, provider
 */
function ws_voice_config($fresh = false)
{
    static $config = null;

    if (!ws_voice_schema_ready()) {
        return array(
            'enabled' => false, 'url' => '', 'model' => '', 'key_set' => false, 'error' => '', 'hold_until' => 0,
            'provider' => ws_voice_provider_name(''),
        );
    }

    if (($config === null) || $fresh) {
        $row = (array) db_item("SELECT ws_voice_enabled, ws_voice_hold_until FROM config LIMIT 1");
        $url = pg_config_text('ws_voice_url');

        $config = array(
            'enabled'    => ((int) ($row['ws_voice_enabled'] ?? 0) === 1),
            'url'        => $url,
            'model'      => pg_config_text('ws_voice_model'),
            'key_set'    => (strpos(pg_config_text('ws_voice_key'), ':') !== false),
            'error'      => pg_config_text('ws_voice_error'),
            'hold_until' => (int) ($row['ws_voice_hold_until'] ?? 0),
            'provider'   => ws_voice_provider_name($url),
        );
    }

    return $config;
}

/**
 * The service's key, read back; empty when none is stored or it cannot be
 * read (the site's encryption key was changed).
 *
 * @return string
 */
function ws_voice_key()
{
    $sealed = ws_voice_schema_ready() ? pg_config_text('ws_voice_key') : '';

    if (($sealed === '') || (strpos($sealed, ':') === false) || !function_exists('decode_ssl_keys') || !defined('ENCRYPTION_KEY')) {
        return '';
    }

    list($cipher, $iv) = explode(':', $sealed, 2);

    return (string) decode_ssl_keys($cipher, $iv);
}

/**
 * What still stands between a recording and its text, in sentences.
 *
 * @return string[] empty when transcripts are ready
 */
function ws_voice_missing()
{
    if (!ws_voice_schema_ready()) {
        return array(lang('The database has not been upgraded yet.'));
    }

    $config = ws_voice_config();
    $missing = array();

    if (!$config['enabled']) {
        $missing[] = lang('Transcripts are switched off.');
    }

    if (!$config['key_set']) {
        $missing[] = lang('No key is entered for the transcription service.');
    } elseif (ws_voice_key() === '') {
        $missing[] = lang('The stored key cannot be read any more: enter it again.');
    }

    if (!function_exists('curl_init')) {
        $missing[] = lang('This server cannot make outgoing HTTPS requests (cURL is missing).');
    }

    return $missing;
}

/**
 * May the recordings of this channel be sent to be written out? A manager's
 * choice when there is one, otherwise public channels yes and private ones
 * no: a private channel's sound leaves the site only when its people want it
 * to. Never where a guest listens along, and a discussion follows its
 * channel, as for Pinegrap AI (ws_ai_channel_allowed()).
 *
 * @param array $channel
 * @return bool
 */
function ws_voice_channel_allowed($channel)
{
    if ((($channel['kind'] ?? '') === 'guest') || (function_exists('ws_channel_share_open') && ws_channel_share_open($channel))) {
        return false;
    }

    if (($channel['kind'] ?? '') === 'thread') {
        $parent = function_exists('ws_thread_parent_channel') ? ws_thread_parent_channel($channel) : null;

        return $parent ? ws_voice_channel_allowed($parent) : false;
    }

    return ws_voice_channel_rule((string) ($channel['kind'] ?? ''), (int) ($channel['voice_access'] ?? 0));
}

/**
 * The rule of ws_voice_channel_allowed() for a channel of the team, by its
 * kind and its voice_access: 1 allowed, 2 closed, 0 public yes and every
 * other kind no.
 *
 * @param string $kind
 * @param int    $access
 * @return bool
 */
function ws_voice_channel_rule($kind, $access)
{
    if ($kind === 'guest') {
        return false;
    }

    if ((int) $access === 1) {
        return true;
    }

    if ((int) $access === 2) {
        return false;
    }

    return ($kind === 'public');
}

/* ---------------------------------------------------------------------------
   The settings card (workspace_settings.php)
   --------------------------------------------------------------------------- */

/**
 * Saves the card: the switch, the address, the model and the key. The key is
 * written, never shown: left empty, the stored one stays.
 *
 * @param array    $viewer
 * @param liveform $liveform
 */
function ws_voice_settings_post($viewer, $liveform)
{
    if (!ws_voice_schema_ready()) {
        $liveform->add_error(lang('The database has not been upgraded yet.'));
        return;
    }

    if ((int) $viewer['role'] !== 0) {
        $liveform->add_error(lang('Only an administrator can connect a transcription service.'));
        return;
    }

    $enabled = !empty($_POST['voice_enabled']);
    $url = ws_voice_url_clean($_POST['voice_url'] ?? '');
    $model = trim((string) ($_POST['voice_model'] ?? ''));
    $key = trim((string) ($_POST['voice_key'] ?? ''));

    if (($url !== '') && !ws_voice_url_valid($url)) {
        $liveform->add_error(lang('The service\'s address starts with https:// and ends before /audio/transcriptions, for example https://api.openai.com/v1'));
        return;
    }

    if (($model !== '') && !ws_voice_model_valid($model)) {
        $liveform->add_error(lang('The model name may hold letters, digits and . _ : / - only.'));
        return;
    }

    $sealed = null;

    if ($key !== '') {
        if (!ws_voice_key_shape_ok($key)) {
            $liveform->add_error(lang('The key is 8 to 512 characters without spaces.'));
            return;
        }

        if (!function_exists('encrypt_string_with_iv') || !defined('ENCRYPTION_KEY') || (ENCRYPTION_KEY === '')) {
            $liveform->add_error(lang('The key cannot be stored: encryption is not available on this site.'));
            return;
        }

        list($cipher, $iv) = encrypt_string_with_iv($key);
        $sealed = $cipher . ':' . $iv;
    } elseif (!empty($_POST['voice_key_clear'])) {
        $sealed = '';
    }

    $before = ws_voice_settings_audit_side();

    $saved = pg_config_text_save('ws_voice_url', $url)
        && pg_config_text_save('ws_voice_model', $model)
        && pg_config_text_save('ws_voice_error', '')
        && (($sealed === null) || pg_config_text_save('ws_voice_key', $sealed));

    if (!$saved) {
        $liveform->add_error(lang('The transcription settings could not be saved.'));
        return;
    }

    db("UPDATE config SET ws_voice_enabled = '" . ($enabled ? 1 : 0) . "', ws_voice_hold_until = 0");

    $after = array(
        'enabled' => $enabled ? 1 : 0,
        'url'     => $url,
        'model'   => $model,
        'api_key' => ($sealed === null) ? $before['api_key'] : $sealed,
    );

    if (function_exists('pg_audit')) {
        pg_audit('update', 'settings', 0, $before, $after, 'workspace_voice');
    }

    log_activity(lang('the voice message transcripts of the workspace were changed'), (string) ($_SESSION['sessionusername'] ?? ''));
    $liveform->add_notice(lang('The transcript settings were saved.'));
}

/**
 * The card's settings as the audit trail compares them; the key as it is
 * stored, which the trail masks (pg_audit_secret_key()).
 *
 * @return array enabled, url, model, api_key
 */
function ws_voice_settings_audit_side()
{
    return array(
        'enabled' => (int) db_value("SELECT ws_voice_enabled FROM config LIMIT 1"),
        'url'     => pg_config_text('ws_voice_url'),
        'model'   => pg_config_text('ws_voice_model'),
        'api_key' => pg_config_text('ws_voice_key'),
    );
}

/**
 * The card on the settings screen: the connection on the left, how it
 * stands on the right. Role-3 settings holders see it without the key and
 * cannot change it.
 *
 * @param string $self_url
 * @param array  $viewer
 * @return string
 */
function ws_voice_settings_card($self_url, $viewer)
{
    if (!ws_voice_schema_ready()) {
        return '';
    }

    $config = ws_voice_config(true);
    $admin = ((int) $viewer['role'] === 0);
    $disabled = $admin ? '' : ' disabled';
    $missing = ws_voice_missing();

    if (empty($missing)) {
        $state = '<span class="badge theme-success">' . h(lang('Ready')) . '</span>';
    } elseif (!$config['enabled']) {
        $state = '<span class="badge theme-secondary">' . h(lang('Off')) . '</span>';
    } else {
        $state = '<span class="badge theme-warning">' . h(lang('Not ready')) . '</span>';
    }

    $status_list = '';

    foreach ($missing as $sentence) {
        $status_list .= '<li>' . h($sentence) . '</li>';
    }

    $key_field = '';

    if ($admin) {
        $key_field = '
                            <div class="mb-5">
                                <label class="form-label" for="ws_voice_key">' . h(lang('The service\'s key')) . '</label>
                                <input class="form-control form-control-sm font-monospace" type="password" name="voice_key" id="ws_voice_key" value="" autocomplete="new-password" placeholder="' . h($config['key_set'] ? lang('Stored. Type a new one to replace it.') : 'sk-…') . '">
                                ' . ($config['key_set'] ? '
                                <div class="form-field mt-1">
                                    <input class="check" type="checkbox" name="voice_key_clear" value="1" id="ws_voice_key_clear">
                                    <label class="small" for="ws_voice_key_clear">' . h(lang('Remove the stored key')) . '</label>
                                </div>' : '') . '
                                <div class="form-text">' . h(lang('It is stored encrypted and never shown again. The site\'s own account at the service pays for the transcripts.')) . '</div>
                            </div>';
    }

    return '
        <div class="card mt-7" id="ws-voice">
            <div class="card-header d-flex align-items-center gap-3">
                <h2 class="h6 mb-0 ws-grow"><i class="bi bi-mic me-1" aria-hidden="true"></i>' . h(lang('Voice message transcripts')) . '</h2>
                ' . $state . '
            </div>
            <div class="card-body">
                <div class="row g-7">
                    <div class="lg:col-7">
                        <form method="post" action="' . h($self_url) . '#ws-voice">
                            ' . get_token_field() . '
                            <input type="hidden" name="ws_action" value="voice">
                            <div class="form-field mb-5">
                                <div class="switch"><input class="" type="checkbox" name="voice_enabled" value="1" id="ws_voice_enabled"' . ($config['enabled'] ? ' checked' : '') . $disabled . '></div>
                                <label class="" for="ws_voice_enabled">' . h(lang('Write voice messages out as text')) . '</label>
                                <div class="form-text">' . h(lang(array(
                                    'string' => 'The sound of a voice message is sent to {var:1} with the key below, and the text written from it is kept beside the message. Public channels are written out, private ones only when their manager allows it. Recordings longer than {var:2} minutes are not sent.',
                                    'vars'   => array($config['provider'], (int) (WS_VOICE_MAX_SECONDS / 60)),
                                ))) . '</div>
                            </div>
                            <div class="mb-5">
                                <label class="form-label" for="ws_voice_url">' . h(lang('The service\'s address')) . '</label>
                                <input class="form-control form-control-sm font-monospace" type="url" name="voice_url" id="ws_voice_url" value="' . h($config['url']) . '" placeholder="' . h(ws_voice_default_url()) . '" autocomplete="off"' . $disabled . '>
                                <div class="form-text">' . h(lang('Any service that answers like OpenAI\'s /audio/transcriptions. Left empty, OpenAI is used.')) . '</div>
                            </div>
                            <div class="mb-5">
                                <label class="form-label" for="ws_voice_model">' . h(lang('Model')) . '</label>
                                <input class="form-control form-control-sm font-monospace" type="text" name="voice_model" id="ws_voice_model" value="' . h($config['model']) . '" placeholder="' . h(ws_voice_default_model()) . '" maxlength="100" autocomplete="off"' . $disabled . '>
                            </div>
                            ' . $key_field . '
                            ' . ($admin ? '<button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h(lang('Save')) . '</button>' : '<div class="form-text">' . h(lang('Only an administrator can connect a transcription service.')) . '</div>') . '
                        </form>
                    </div>
                    <div class="lg:col-5">
                        <div class="ws-claude-status">
                            <div class="small fw-semibold mb-1">' . h(lang('Recordings are sent to')) . '</div>
                            <div class="small mb-5">' . h($config['provider']) . ' · <code>' . h(($config['model'] !== '') ? $config['model'] : ws_voice_default_model()) . '</code></div>
                            ' . (!empty($missing) ? '<div class="small fw-semibold mb-1">' . h(lang('Still missing')) . '</div><ul class="small mb-5">' . $status_list . '</ul>' : '') . '
                            ' . (($config['error'] !== '') ? '<div class="alert theme-danger small py-3">' . h($config['error']) . '</div>' : '') . '
                            ' . (($config['hold_until'] > time()) ? '<div class="alert theme-warning small py-3">' . h(lang(array('string' => 'Recordings wait until {var:1}.', 'vars' => date('d.m.Y H:i', $config['hold_until'])))) . '</div>' : '') . '
                        </div>
                    </div>
                </div>
            </div>
        </div>';
}
