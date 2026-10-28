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
        'voice_transcript'  => lang('Transcript'),
        'voice_machine'     => ws_js_template('Machine transcript by {var:1}', 1),
        'voice_transcribing' => lang('Transcribing…'),
        'voice_allow'       => lang('Voice messages are transcribed here'),
        'voice_allow_private' => ws_js_template('Voice messages in this private channel will be sent to {var:1} to be transcribed. Allow it?', 1),
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
 * The largest recording sent, in bytes: five minutes at 160 kbit/s, well
 * above the recorder's 32 kbit/s, and under every service's own limit.
 */
define('WS_VOICE_MAX_BYTES', 6 * 1024 * 1024);

/**
 * Seconds one call to the service may take.
 */
define('WS_VOICE_CALL_TIMEOUT', 120);

/**
 * Calls a recording gets before it is given up on.
 */
define('WS_VOICE_ATTEMPTS', 3);

/**
 * A recording taken this long ago and still running was cut off with its
 * request: it waits again.
 */
define('WS_VOICE_STALE', 900);

/**
 * A recording not sent within a day is given up on.
 */
define('WS_VOICE_QUEUE_TTL', 86400);

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

/**
 * What the channel screen's menu shows of transcripts here: null while they
 * are off, in a guest room, a channel shared with guests or a discussion
 * (which follows its channel); otherwise whether its recordings are written
 * out and the service they go to, named in the private channel's question.
 *
 * @param array $channel
 * @return array|null allowed, provider
 */
function ws_voice_channel_state($channel)
{
    if (!ws_voice_schema_ready() || !ws_voice_config()['enabled'] || !ws_voice_channel_switchable($channel)) {
        return null;
    }

    return array(
        'allowed'  => ws_voice_channel_allowed($channel),
        'provider' => (string) ws_voice_config()['provider'],
    );
}

/**
 * Can a manager switch transcripts on or off in this channel? Not where the
 * rule never allows them (a guest room, a channel shared with guests), nor
 * in a discussion, which follows its channel.
 *
 * @param array $channel
 * @return bool
 */
function ws_voice_channel_switchable($channel)
{
    $kind = (string) ($channel['kind'] ?? '');

    if (($kind === 'guest') || ($kind === 'thread')) {
        return false;
    }

    return !(function_exists('ws_channel_share_open') && ws_channel_share_open($channel));
}

/**
 * Lets a channel's recordings be written out, or stops it, as a manager of
 * the channel chooses; the channel reads a line saying who did it. What was
 * already written out stays.
 *
 * @param array $viewer
 * @param array $channel
 * @param bool  $allowed
 * @return array ok, error
 */
function ws_voice_channel_set($viewer, $channel, $allowed)
{
    if (!ws_voice_schema_ready() || !ws_voice_channel_switchable($channel)) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    if (!ws_can_manage_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('Access denied.'));
    }

    db("UPDATE ws_channels SET voice_access = '" . ($allowed ? 1 : 2) . "' WHERE id = '" . (int) $channel['id'] . "'");

    ws_message_system($channel['id'], $allowed
        ? lang(array('string' => '{var:1} allowed voice messages to be transcribed in this channel.', 'vars' => ws_person_name($viewer['id'])))
        : lang(array('string' => '{var:1} stopped voice messages being transcribed in this channel.', 'vars' => ws_person_name($viewer['id']))));

    return array('ok' => true, 'error' => '');
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

/* ---------------------------------------------------------------------------
   Transcripts: the queue and the work
   --------------------------------------------------------------------------- */

/**
 * Is this the name the recorder gives a voice message
 * (voice-YYYYMMDD-HHMMSS with a sound extension)? Only those are written
 * out and taken away with their message: a sound file somebody attached is
 * left alone.
 *
 * @param string $name
 * @return bool
 */
function ws_voice_is_recording($name)
{
    return (bool) preg_match('/^voice-[A-Za-z0-9_-]{1,80}\.(weba|ogg|oga|opus|m4a|mp3|wav|aac)$/i', (string) $name);
}

/**
 * The most waveform levels a recording keeps (ws_voice.peaks).
 */
define('WS_VOICE_PEAKS', 120);

/**
 * The length and waveform the recorder measured, as they are kept: the
 * length in milliseconds within the recorder's cap, the levels one digit
 * each (0 silence to 9 loudest), at most WS_VOICE_PEAKS of them. Anything
 * else the browser sent is dropped, never stored.
 *
 * @param mixed $duration_ms
 * @param mixed $peaks
 * @return array duration_ms, peaks
 */
function ws_voice_measure_clean($duration_ms, $peaks)
{
    // A second over the cap: the recorder stops on its own tick. Held there
    // before the cast, so a huge number cannot wrap round.
    $duration = (is_numeric($duration_ms) && ((float) $duration_ms > 0)) ? min((float) $duration_ms, (WS_VOICE_MAX_SECONDS + 1) * 1000) : 0;
    $levels = is_string($peaks) ? $peaks : '';

    return array(
        'duration_ms' => (int) round($duration),
        'peaks'       => preg_match('/^[0-9]{1,' . WS_VOICE_PEAKS . '}$/', $levels) ? $levels : '',
    );
}

/**
 * Notes a recording just posted: queued to be written out when transcripts
 * are on and its channel allows them, otherwise kept as never asked. Called
 * by both uploads (ws_message_send_file_bytes(), the API's files.create).
 *
 * @param array  $channel
 * @param int    $message_id
 * @param int    $file_id
 * @param string $name    the name it was posted with
 * @param array  $measure duration_ms and peaks the recorder sent, if any
 */
function ws_voice_queue($channel, $message_id, $file_id, $name, $measure = array())
{
    if (!ws_voice_schema_ready() || ((int) $file_id <= 0) || !ws_voice_is_recording($name)) {
        return;
    }

    $queued = ws_voice_config()['enabled'] && ws_voice_channel_allowed($channel);
    $measured = ws_voice_measure_clean($measure['duration_ms'] ?? 0, $measure['peaks'] ?? '');
    $now = time();

    db("INSERT IGNORE INTO ws_voice (file_id, message_id, channel_id, duration_ms, peaks, status, created_at, updated_at)
        VALUES ('" . (int) $file_id . "', '" . (int) $message_id . "', '" . (int) $channel['id'] . "', '" . (int) $measured['duration_ms'] . "', '" . e($measured['peaks']) . "',
            '" . ($queued ? 'queued' : '') . "', '" . $now . "', '" . $now . "')");

    if ($queued) {
        ws_voice_carry_on();
    }
}

/**
 * Carries the queue on once the answer has gone back, where the server can
 * send it first (PHP-FPM); elsewhere the hourly job does
 * (workspace_recurring_job.php). The session is closed first, so a call that
 * waits for the service does not hold the person's other screens up.
 */
function ws_voice_carry_on()
{
    static $registered = false;

    if ($registered || !function_exists('fastcgi_finish_request')) {
        return;
    }

    $registered = true;

    register_shutdown_function(function () {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        fastcgi_finish_request();
        ws_voice_kick();
    });
}

/**
 * The hourly job: the work nobody's upload carried on.
 */
function ws_voice_job()
{
    ws_voice_kick(300);
}

/**
 * Gives up on recordings that waited a day, and puts back the ones whose
 * call was cut off with its request.
 */
function ws_voice_watchdog()
{
    $now = time();

    db("UPDATE ws_voice SET status = 'failed', error = '" . e(lang('It could not be sent to the transcription service within a day.')) . "', updated_at = '" . $now . "'
        WHERE status = 'queued' AND created_at < '" . ($now - WS_VOICE_QUEUE_TTL) . "'");

    db("UPDATE ws_voice SET status = IF(attempts >= '" . WS_VOICE_ATTEMPTS . "', 'failed', 'queued'), updated_at = '" . $now . "'
        WHERE status = 'running' AND updated_at < '" . ($now - WS_VOICE_STALE) . "'");
}

/**
 * Writes the waiting recordings out, one call at a time site-wide, and
 * another only while the budget lasts. A call that finds the work under way
 * elsewhere returns at once.
 *
 * @param int $budget seconds after which no new call is started
 * @return string idle | busy | held | working
 */
function ws_voice_kick($budget = 60)
{
    if (!ws_voice_schema_ready() || !ws_voice_config()['enabled']) {
        return 'idle';
    }

    ws_voice_watchdog();

    if (((int) db_value("SELECT COUNT(*) FROM ws_voice WHERE status = 'queued'") === 0) || !empty(ws_voice_missing())) {
        return 'idle';
    }

    if (ws_voice_config(true)['hold_until'] > time()) {
        return 'held';
    }

    // The lock goes with the database connection, so a request the web
    // server cuts off lets it go too.
    if ((int) db_value("SELECT GET_LOCK('pg_ws_voice_run', 0)") !== 1) {
        return 'busy';
    }

    if (function_exists('ignore_user_abort')) {
        @ignore_user_abort(true);
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(WS_VOICE_CALL_TIMEOUT + 60);
    }

    $started = time();
    $status = 'working';
    $guard = 0;

    do {
        $row = db_item("SELECT * FROM ws_voice WHERE status = 'queued' ORDER BY id LIMIT 1");

        if (!is_array($row)) {
            $status = 'idle';
            break;
        }

        db("UPDATE ws_voice SET status = 'running', attempts = attempts + 1, updated_at = '" . time() . "'
            WHERE id = '" . (int) $row['id'] . "' AND status = 'queued'");

        if (mysqli_affected_rows(db::$con) < 1) {
            continue;
        }

        if (ws_voice_run($row) === 'held') {
            $status = 'held';
            break;
        }
    } while (((time() - $started) < $budget) && (++$guard < 20));

    db_value("SELECT RELEASE_LOCK('pg_ws_voice_run')");

    return $status;
}

/**
 * Writes one recording out: still posted, still allowed where it is, not
 * too long; then the call, and the row as the answer leaves it.
 *
 * @param array $row ws_voice, claimed (status running)
 * @return string done | skipped | failed | retry | held
 */
function ws_voice_run($row)
{
    $message = ws_message((int) $row['message_id']);

    if (!$message || ((int) $message['deleted_at'] > 0) || ((int) $message['file_id'] !== (int) $row['file_id'])) {
        return ws_voice_finish($row, 'skipped', lang('The voice message was deleted.'));
    }

    $channel = ws_channel((int) $message['channel_id']);

    if (!$channel || !ws_voice_channel_allowed($channel)) {
        return ws_voice_finish($row, 'skipped', lang('The channel does not allow transcripts.'));
    }

    $stored = (string) db_value("SELECT name FROM files WHERE id = '" . (int) $row['file_id'] . "'");
    $path = FILE_DIRECTORY_PATH . '/' . basename($stored);
    $size = ($stored !== '') ? @filesize($path) : false;

    if (($size === false) || ($size <= 0)) {
        return ws_voice_finish($row, 'failed', lang('The recording could not be read.'));
    }

    if ($size > WS_VOICE_MAX_BYTES) {
        return ws_voice_finish($row, 'skipped', lang(array('string' => 'Recordings longer than {var:1} minutes are not sent.', 'vars' => (int) (WS_VOICE_MAX_SECONDS / 60))));
    }

    $bytes = @file_get_contents($path);

    if (!is_string($bytes) || ($bytes === '')) {
        return ws_voice_finish($row, 'failed', lang('The recording could not be read.'));
    }

    $config = ws_voice_config();
    $model = ($config['model'] !== '') ? $config['model'] : ws_voice_default_model();
    $answer = ws_voice_call($bytes, (string) $message['file_name'], $model);
    $outcome = ws_voice_answer($answer['status'], $answer['body'], $answer['error']);

    switch ($outcome['outcome']) {
        case 'done':
            db("UPDATE ws_voice SET status = 'done', transcript = '" . e($outcome['text']) . "', language = '" . e(mb_substr($outcome['language'], 0, 16)) . "',
                    provider = '" . e(mb_substr($config['provider'], 0, 100)) . "', model = '" . e(mb_substr($model, 0, 100)) . "', error = '',
                    updated_at = '" . time() . "', transcribed_at = '" . time() . "'
                WHERE id = '" . (int) $row['id'] . "'");

            // Screens and apps holding the message read it again.
            ws_message_touch((int) $row['message_id']);
            ws_voice_announce($message, $channel, $row, $outcome, $config['provider'], $model);

            if ($config['error'] !== '') {
                pg_config_text_save('ws_voice_error', '');
            }

            return 'done';

        case 'hold':
            // The key or the account: every recording would fail the same
            // way, so they all wait and the card says why.
            db("UPDATE ws_voice SET status = 'queued', attempts = GREATEST(attempts, 1) - 1, updated_at = '" . time() . "' WHERE id = '" . (int) $row['id'] . "'");
            ws_voice_hold(3600, $outcome['error']);

            return 'held';

        case 'retry':
            if ((int) $row['attempts'] + 1 >= WS_VOICE_ATTEMPTS) {
                return ws_voice_finish($row, 'failed', $outcome['error']);
            }

            db("UPDATE ws_voice SET status = 'queued', error = '" . e(mb_substr($outcome['error'], 0, 250)) . "', updated_at = '" . time() . "' WHERE id = '" . (int) $row['id'] . "'");
            ws_voice_hold(($answer['status'] === 429) ? 300 : 60, $outcome['error']);

            return 'held';

        default:
            return ws_voice_finish($row, 'failed', $outcome['error']);
    }
}

/**
 * Tells the subscriptions a recording was written out, in a public channel
 * only, as workspace.message.created is: a private channel's words never
 * leave the site through a subscription, and a discussion is not announced.
 *
 * @param array  $message  the message carrying the recording
 * @param array  $channel  its channel
 * @param array  $row      ws_voice
 * @param array  $outcome  ws_voice_answer()'s, done
 * @param string $provider
 * @param string $model
 */
function ws_voice_announce($message, $channel, $row, $outcome, $provider, $model)
{
    if (((string) ($channel['kind'] ?? '') !== 'public') || !function_exists('pg_announce')) {
        return;
    }

    $app = ((string) $message['sender_kind'] === 'app');

    pg_announce('workspace.message.transcribed', array(
        'id'          => (int) $message['id'],
        'channel_id'  => (int) $channel['id'],
        'channel'     => (string) $channel['name'],
        'sender_id'   => $app ? 0 : (int) $message['sender_id'],
        'app_id'      => $app ? (int) $message['sender_id'] : 0,
        'file_id'     => (int) $row['file_id'],
        'file_name'   => (string) $message['file_name'],
        'duration_ms' => (int) $row['duration_ms'],
        'transcript'  => (string) $outcome['text'],
        'language'    => ($outcome['language'] !== '') ? (string) $outcome['language'] : null,
        'provider'    => (string) $provider,
        'model'       => (string) $model,
    ));
}

/**
 * Leaves a recording as it ends without a text.
 *
 * @param array  $row
 * @param string $status skipped | failed
 * @param string $error
 * @return string the status
 */
function ws_voice_finish($row, $status, $error)
{
    db("UPDATE ws_voice SET status = '" . e($status) . "', error = '" . e(mb_substr((string) $error, 0, 250)) . "', updated_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "'");

    return $status;
}

/**
 * Holds the queue back for a while after the service refused or could not
 * be reached, and says why on the card.
 *
 * @param int    $seconds
 * @param string $error
 */
function ws_voice_hold($seconds, $error)
{
    db("UPDATE config SET ws_voice_hold_until = '" . (time() + (int) $seconds) . "'");
    pg_config_text_save('ws_voice_error', mb_substr((string) $error, 0, 250));
}

/**
 * One call to the service's /audio/transcriptions, through the outgoing
 * client that refuses addresses inside the network and pins the one it
 * checked (includes/api/outbound/http.php).
 *
 * @param string $bytes the recording
 * @param string $name  its name, which tells the service its format
 * @param string $model
 * @return array status, body, error
 */
function ws_voice_call($bytes, $name, $model)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $config = ws_voice_config();
    $base = ($config['url'] !== '') ? $config['url'] : ws_voice_default_url();
    $boundary = 'pgvoice' . bin2hex(random_bytes(12));
    $type = ws_file_type($name);

    $answer = api_http_request('POST', $base . '/audio/transcriptions', array(
        'body'     => ws_voice_multipart($boundary, array('model' => $model, 'response_format' => 'json'), 'file', $name, $type['mime'], $bytes),
        'headers'  => array(
            'Authorization: Bearer ' . ws_voice_key(),
            'Content-Type: multipart/form-data; boundary=' . $boundary,
            'Accept: application/json',
        ),
        'timeout'  => WS_VOICE_CALL_TIMEOUT,
        'max_body' => 0,
        'agent'    => 'workspace-voice',
    ));

    return array('status' => (int) $answer['status'], 'body' => (string) $answer['body'], 'error' => (string) $answer['error']);
}

/**
 * A multipart/form-data body: the fields, then the file.
 *
 * @param string $boundary
 * @param array  $fields   name => value
 * @param string $field    the file's field name
 * @param string $name     the file's name
 * @param string $mime
 * @param string $bytes
 * @return string
 */
function ws_voice_multipart($boundary, $fields, $field, $name, $mime, $bytes)
{
    $body = '';

    foreach ($fields as $key => $value) {
        $body .= '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="' . str_replace(array('"', "\r", "\n"), '', (string) $key) . '"' . "\r\n\r\n"
            . $value . "\r\n";
    }

    return $body . '--' . $boundary . "\r\n"
        . 'Content-Disposition: form-data; name="' . str_replace(array('"', "\r", "\n"), '', (string) $field) . '"; filename="' . str_replace(array('"', "\r", "\n", '\\'), '', (string) $name) . '"' . "\r\n"
        . 'Content-Type: ' . $mime . "\r\n\r\n"
        . $bytes . "\r\n"
        . '--' . $boundary . "--\r\n";
}

/**
 * What a service's answer means for the recording: done with its text, try
 * again later (limits, the service down, no answer), hold the whole queue
 * (the key or the account refused), or fail this one (the service could not
 * read it).
 *
 * @param int    $status HTTP status, 0 when no answer came
 * @param string $body
 * @param string $error  the client's own error when no answer came
 * @return array outcome (done | retry | hold | fail), text, language, error
 */
function ws_voice_answer($status, $body, $error = '')
{
    $json = json_decode((string) $body, true);
    $message = (is_array($json) && isset($json['error']['message']) && is_string($json['error']['message'])) ? trim($json['error']['message']) : '';
    $out = array('outcome' => 'fail', 'text' => '', 'language' => '', 'error' => '');

    if ((int) $status === 200) {
        if (is_array($json) && isset($json['text']) && is_string($json['text'])) {
            $out['outcome'] = 'done';
            $out['text'] = mb_substr(trim($json['text']), 0, 100000);
            $out['language'] = (isset($json['language']) && is_string($json['language'])) ? $json['language'] : '';

            return $out;
        }

        $out['error'] = lang('The transcription service gave an answer that could not be read.');

        return $out;
    }

    $said = ($message !== '') ? $message : (((int) $status > 0) ? 'HTTP ' . (int) $status : (string) $error);

    if (in_array((int) $status, array(401, 402, 403), true)) {
        $out['outcome'] = 'hold';
    } elseif (((int) $status === 0) || ((int) $status === 408) || ((int) $status === 429) || ((int) $status >= 500)) {
        $out['outcome'] = 'retry';
    }

    $out['error'] = lang(array('string' => 'The transcription service said: {var:1}', 'vars' => mb_substr($said, 0, 200)));

    return $out;
}

/* ---------------------------------------------------------------------------
   A deleted recording, and the transcript in the API
   --------------------------------------------------------------------------- */

/**
 * After a voice message was deleted for everyone (ws_message_delete()): the
 * recording and its transcript go too. A copy that still carries the file (a
 * forward, a discussion's copy kept) keeps both, and the row moves to it.
 *
 * @param array $message the row as it was before the delete
 */
function ws_voice_message_deleted($message)
{
    $file_id = (int) ($message['file_id'] ?? 0);

    if (!ws_voice_schema_ready() || ($file_id <= 0)) {
        return;
    }

    $row = db_item("SELECT id FROM ws_voice WHERE file_id = '" . $file_id . "'");

    if (!is_array($row) && !ws_voice_is_recording((string) ($message['file_name'] ?? ''))) {
        return;
    }

    $other = db_item("SELECT id, channel_id FROM ws_messages
        WHERE file_id = '" . $file_id . "' AND id <> '" . (int) $message['id'] . "' AND deleted_at = 0
        ORDER BY id LIMIT 1");

    if (is_array($other)) {
        db("UPDATE ws_voice SET message_id = '" . (int) $other['id'] . "', channel_id = '" . (int) $other['channel_id'] . "' WHERE file_id = '" . $file_id . "'");
        return;
    }

    db("DELETE FROM ws_voice WHERE file_id = '" . $file_id . "'");
    ws_upload_discard($file_id);
}

/**
 * The transcript of a recording as the API shows it, inside the message's
 * file; null for any other file.
 *
 * @param int $file_id
 * @return array|null
 */
function ws_voice_api_present($file_id)
{
    if (!ws_voice_schema_ready() || ((int) $file_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT status, transcript, language, provider, model, transcribed_at FROM ws_voice WHERE file_id = '" . (int) $file_id . "'");

    if (!is_array($row)) {
        return null;
    }

    $done = ($row['status'] === 'done');

    return array(
        'status'         => ((string) $row['status'] !== '') ? (string) $row['status'] : 'none',
        'transcript'     => $done ? (string) $row['transcript'] : null,
        'language'       => ($done && ($row['language'] !== '')) ? (string) $row['language'] : null,
        'provider'       => $done ? (string) $row['provider'] : null,
        'model'          => $done ? (string) $row['model'] : null,
        'transcribed_at' => $done ? api_time((int) $row['transcribed_at']) : null,
    );
}

/**
 * A recording as the channel screen's player draws it, inside the message's
 * file: its length and waveform, and once written out the transcript, marked
 * as machine text with the service that wrote it. null for any other file.
 *
 * @param int $file_id
 * @return array|null status, duration_ms, peaks, transcript, language, provider, model
 */
function ws_voice_panel_present($file_id)
{
    if (!ws_voice_schema_ready() || ((int) $file_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT status, duration_ms, peaks, transcript, language, provider, model FROM ws_voice WHERE file_id = '" . (int) $file_id . "'");

    if (!is_array($row)) {
        return null;
    }

    $done = ($row['status'] === 'done');

    return array(
        'status'      => ((string) $row['status'] !== '') ? (string) $row['status'] : 'none',
        'duration_ms' => (int) $row['duration_ms'],
        'peaks'       => (string) $row['peaks'],
        'transcript'  => $done ? (string) $row['transcript'] : '',
        'language'    => $done ? (string) $row['language'] : '',
        'provider'    => $done ? (string) $row['provider'] : '',
        'model'       => $done ? (string) $row['model'] : '',
    );
}

/**
 * The transcripts of the recordings among some messages, for the readers
 * that take a message as words (the assistants, recaps): file id =>
 * transcript, written-out ones only. One query for all of them; the reader
 * has already been allowed the messages, and a transcript is read as its
 * message is.
 *
 * @param array[] $rows ws_messages rows
 * @return string[]
 */
function ws_voice_transcripts($rows)
{
    $ids = array();

    foreach ((array) $rows as $row) {
        if (is_array($row) && ((int) ($row['file_id'] ?? 0) > 0) && ((int) ($row['deleted_at'] ?? 0) === 0)) {
            $ids[(int) $row['file_id']] = (int) $row['file_id'];
        }
    }

    if (empty($ids) || !ws_voice_schema_ready()) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT file_id, transcript FROM ws_voice
        WHERE file_id IN ('" . implode("', '", $ids) . "') AND status = 'done'") as $row) {
        if (trim((string) $row['transcript']) !== '') {
            $out[(int) $row['file_id']] = (string) $row['transcript'];
        }
    }

    return $out;
}

/**
 * The transcript of one message's recording, '' when it has none (yet).
 *
 * @param array|null $row ws_messages row
 * @return string
 */
function ws_voice_transcript_of($row)
{
    if (!is_array($row) || ((int) ($row['file_id'] ?? 0) <= 0)) {
        return '';
    }

    return (string) (ws_voice_transcripts(array($row))[(int) $row['file_id']] ?? '');
}

/**
 * A transcript as a reader is given it: one line, tag-shaped text taken out
 * (a tag is what a person chose to point at; a speaking voice points at
 * nothing, and the service may write anything), cut at $limit characters.
 *
 * @param string $transcript
 * @param int    $limit characters, 0 for all
 * @return string
 */
function ws_voice_transcript_clean($transcript, $limit = 0)
{
    $text = preg_replace('/<[@#][a-z_]{1,30}:[0-9]{1,10}>/i', '', (string) $transcript);
    $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

    if (($limit > 0) && (mb_strlen($text) > $limit)) {
        $text = rtrim(mb_substr($text, 0, $limit)) . '…';
    }

    return $text;
}

/**
 * A message as words with its recording's transcript after the person's own
 * words, marked as machine text so that it never passes as theirs. Without a
 * transcript the words are returned as they came.
 *
 * @param string $text       the message's words as the reader reads them
 * @param string $transcript ws_voice.transcript, '' for none
 * @param int    $limit      characters of the transcript, 0 for all
 * @return string
 */
function ws_voice_with_transcript($text, $transcript, $limit = 0)
{
    $heard = ws_voice_transcript_clean($transcript, $limit);

    if ($heard === '') {
        return (string) $text;
    }

    return ((trim((string) $text) !== '') ? $text . ' ' : '') . '[voice message, machine transcript: ' . $heard . ']';
}
