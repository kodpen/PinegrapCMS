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
        'voice_speak'       => lang('Read assistant answers to me aloud'),
        'voice_speak_on'    => lang('Assistant answers to your messages will be read aloud in this browser.'),
        'voice_speak_off'   => lang('Assistant answers are no longer read aloud.'),
        // The recorder's hint while the assistants will not hear the
        // recording (ws_voice_channel_unheard()).
        'voice_unheard_off' => lang('Assistants will not hear this recording: voice message transcripts are switched off.'),
        'voice_unheard_skipped' => lang('Assistants will not hear this recording: voice messages are not transcribed in this channel.'),
        'voice_unheard_settings' => lang('Switch them on: Workspace Settings › Voice message transcripts'),
        'voice_unheard_menu' => lang('A manager switches it on in the channel menu: Voice messages are transcribed here.'),
        'voice_browser_hint' => lang('This browser writes the recording out on this device; the text goes with it for the assistants.'),
        'voice_browser_working' => lang('Writing the recording out…'),
        'voice_machine_browser' => lang('Machine transcript by the sender\'s browser'),
    ) + pg_voice_text_strings();
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
   The settings card (Site Settings, Workspace)
   --------------------------------------------------------------------------- */

/**
 * What the card posted: voice_* on Workspace Settings, ws_voice_* in Site
 * Settings ($prefix 'ws_'). Pure.
 *
 * @param array  $post
 * @param string $prefix
 * @return array enabled, url, model, key, key_clear, speech_enabled,
 *               speech_url, speech_model, speech_voice, speech_key,
 *               speech_key_clear
 */
function ws_voice_settings_input($post, $prefix = '')
{
    $text = function ($field) use ($post, $prefix) {
        $value = $post[$prefix . 'voice_' . $field] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    };

    return array(
        'enabled'          => ($text('enabled') !== ''),
        'url'              => ws_voice_url_clean($text('url')),
        'model'            => $text('model'),
        'key'              => $text('key'),
        'key_clear'        => ($text('key_clear') !== ''),
        'speech_enabled'   => ($text('speech_enabled') !== ''),
        'speech_url'       => ws_voice_speech_url_clean($text('speech_url')),
        'speech_model'     => $text('speech_model'),
        'speech_voice'     => $text('speech_voice'),
        'speech_key'       => $text('speech_key'),
        'speech_key_clear' => ($text('speech_key_clear') !== ''),
    );
}

/**
 * Every refusal of a posted card, by the field it is for (url, model,
 * key, speech_url, speech_model, speech_voice, speech_key), in the card's
 * order. Pure; whether the site can seal a key is asked by the write.
 *
 * @param array $input ws_voice_settings_input()
 * @return array field => sentence
 */
function ws_voice_settings_check($input)
{
    $refused = array();

    if (($input['url'] !== '') && !ws_voice_url_valid($input['url'])) {
        $refused['url'] = lang('The service\'s address starts with https:// and ends before /audio/transcriptions, for example https://api.openai.com/v1');
    }

    if (($input['model'] !== '') && !ws_voice_model_valid($input['model'])) {
        $refused['model'] = lang('The model name may hold letters, digits and . _ : / - only.');
    }

    if (($input['key'] !== '') && !ws_voice_key_shape_ok($input['key'])) {
        $refused['key'] = lang('The key is 8 to 512 characters without spaces.');
    }

    if (($input['speech_url'] !== '') && !ws_voice_url_valid($input['speech_url'])) {
        $refused['speech_url'] = lang('The speech service\'s address starts with https:// and ends before /audio/speech, for example https://api.openai.com/v1');
    }

    if (($input['speech_model'] !== '') && !ws_voice_model_valid($input['speech_model'])) {
        $refused['speech_model'] = lang('The model name may hold letters, digits and . _ : / - only.');
    }

    if (($input['speech_voice'] !== '') && !ws_voice_speech_voice_valid($input['speech_voice'])) {
        $refused['speech_voice'] = lang('The voice name may hold letters, digits and . _ : / - only.');
    }

    if (($input['speech_key'] !== '') && !ws_voice_key_shape_ok($input['speech_key'])) {
        $refused['speech_key'] = lang('The key is 8 to 512 characters without spaces.');
    }

    return $refused;
}

/**
 * Whether a posted card differs from what is stored. The Site Settings pane
 * posts the card with every Save, and a write clears the services' last
 * errors and the transcripts' wait, so an unchanged card is not written.
 * A typed key or a ticked remove box is a change. Pure.
 *
 * @param array $input      ws_voice_settings_input()
 * @param array $transcript ws_voice_config()
 * @param array $speech     ws_voice_speech_config()
 * @return bool
 */
function ws_voice_settings_changed($input, $transcript, $speech)
{
    return ($input['key'] !== '') || $input['key_clear'] || ($input['speech_key'] !== '') || $input['speech_key_clear']
        || ($input['enabled'] !== (bool) $transcript['enabled'])
        || ($input['url'] !== (string) $transcript['url'])
        || ($input['model'] !== (string) $transcript['model'])
        || ($input['speech_enabled'] !== (bool) $speech['enabled'])
        || ($input['speech_url'] !== (string) $speech['url'])
        || ($input['speech_model'] !== (string) $speech['model'])
        || ($input['speech_voice'] !== (string) $speech['voice']);
}

/**
 * Stores a card that passed ws_voice_settings_check(): the keys sealed (or
 * kept, or removed), the services' last errors and the transcripts' wait
 * cleared, the audit trail and the Site Log written.
 *
 * @param array    $input    ws_voice_settings_input()
 * @param liveform $liveform given the notice
 * @param callable $refuse   takes a refusal
 * @return bool written
 */
function ws_voice_settings_write($input, $liveform, $refuse)
{
    $sealed = ws_voice_settings_seal($input['key'], $input['key_clear'], $refuse);
    $speech_sealed = ws_voice_settings_seal($input['speech_key'], $input['speech_key_clear'], $refuse);

    if (($sealed === false) || ($speech_sealed === false)) {
        return false;
    }

    $before = ws_voice_settings_audit_side();

    $saved = pg_config_text_save('ws_voice_url', $input['url'])
        && pg_config_text_save('ws_voice_model', $input['model'])
        && pg_config_text_save('ws_voice_error', '')
        && (($sealed === null) || pg_config_text_save('ws_voice_key', $sealed))
        && pg_config_text_save('ws_voice_speech_enabled', $input['speech_enabled'] ? '1' : '')
        && pg_config_text_save('ws_voice_speech_url', $input['speech_url'])
        && pg_config_text_save('ws_voice_speech_model', $input['speech_model'])
        && pg_config_text_save('ws_voice_speech_voice', $input['speech_voice'])
        && pg_config_text_save('ws_voice_speech_error', '')
        && (($speech_sealed === null) || pg_config_text_save('ws_voice_speech_key', $speech_sealed));

    if (!$saved) {
        $refuse(lang('The transcription settings could not be saved.'));
        return false;
    }

    db("UPDATE config SET ws_voice_enabled = '" . ($input['enabled'] ? 1 : 0) . "', ws_voice_hold_until = 0");

    $after = array(
        'enabled'        => $input['enabled'] ? 1 : 0,
        'url'            => $input['url'],
        'model'          => $input['model'],
        'api_key'        => ($sealed === null) ? $before['api_key'] : $sealed,
        'speech_enabled' => $input['speech_enabled'] ? 1 : 0,
        'speech_url'     => $input['speech_url'],
        'speech_model'   => $input['speech_model'],
        'speech_voice'   => $input['speech_voice'],
        'speech_api_key' => ($speech_sealed === null) ? $before['speech_api_key'] : $speech_sealed,
    );

    if (function_exists('pg_audit')) {
        pg_audit('update', 'settings', 0, $before, $after, 'workspace_voice');
    }

    log_activity(lang('the voice message transcripts of the workspace were changed'), (string) ($_SESSION['sessionusername'] ?? ''));
    $liveform->add_notice(lang('The transcript settings were saved.'));
    ws_voice_config(true);
    ws_voice_speech_config(true);

    return true;
}

/**
 * Saves the card on Workspace Settings: the switch, the address, the model
 * and the key. The key is written, never shown: left empty, the stored one
 * stays. The first refusal is the form's error and nothing is stored.
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

    $input = ws_voice_settings_input($_POST);
    $refused = ws_voice_settings_check($input);

    if ($refused) {
        $liveform->add_error(reset($refused));
        return;
    }

    ws_voice_settings_write($input, $liveform, function ($sentence) use ($liveform) {
        $liveform->add_error($sentence);
    });
}

/**
 * A key as the card posts it, made what config_text keeps: sealed
 * (<ciphertext>:<iv> of encrypt_string_with_iv()) when one was typed, ''
 * when the box to remove it was ticked, null to keep what is stored.
 *
 * @param string   $key      as typed, trimmed
 * @param bool     $clear    the remove box
 * @param callable $refuse   takes the refusal
 * @return string|null|false false when the key cannot be taken (the refusal is given)
 */
function ws_voice_settings_seal($key, $clear, $refuse)
{
    if ($key === '') {
        return $clear ? '' : null;
    }

    if (!ws_voice_key_shape_ok($key)) {
        $refuse(lang('The key is 8 to 512 characters without spaces.'));
        return false;
    }

    if (!function_exists('encrypt_string_with_iv') || !defined('ENCRYPTION_KEY') || (ENCRYPTION_KEY === '')) {
        $refuse(lang('The key cannot be stored: encryption is not available on this site.'));
        return false;
    }

    list($cipher, $iv) = encrypt_string_with_iv($key);

    return $cipher . ':' . $iv;
}

/**
 * The card's settings as the audit trail compares them; the keys as they
 * are stored, which the trail masks (pg_audit_secret_key()).
 *
 * @return array enabled, url, model, api_key, speech_enabled, speech_url,
 *               speech_model, speech_voice, speech_api_key
 */
function ws_voice_settings_audit_side()
{
    return array(
        'enabled'        => (int) db_value("SELECT ws_voice_enabled FROM config LIMIT 1"),
        'url'            => pg_config_text('ws_voice_url'),
        'model'          => pg_config_text('ws_voice_model'),
        'api_key'        => pg_config_text('ws_voice_key'),
        'speech_enabled' => (pg_config_text('ws_voice_speech_enabled') === '1') ? 1 : 0,
        'speech_url'     => pg_config_text('ws_voice_speech_url'),
        'speech_model'   => pg_config_text('ws_voice_speech_model'),
        'speech_voice'   => pg_config_text('ws_voice_speech_voice'),
        'speech_api_key' => pg_config_text('ws_voice_speech_key'),
    );
}

/**
 * The card on the settings screen: the connection on the left, how it
 * stands on the right. Role-3 settings holders see it without the key and
 * cannot change it.
 *
 * @param string      $self_url
 * @param array       $viewer
 * @param string|null $form the Site Settings form's id: no form of its own,
 *                          fields ws_voice_* saved with the pane's Save
 * @return string
 */
function ws_voice_settings_card($self_url, $viewer, $form = null)
{
    // Site Settings posts the fields as ws_voice_* with the pane's Save.
    $prefix = ($form !== null) ? 'ws_' : '';
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
                                <input class="form-control form-control-sm font-monospace" type="password" name="' . $prefix . 'voice_key" id="ws_voice_key" value="" autocomplete="new-password" placeholder="' . h($config['key_set'] ? lang('Stored. Type a new one to replace it.') : 'sk-…') . '">
                                ' . ($config['key_set'] ? '
                                <div class="form-field mt-1">
                                    <input class="check" type="checkbox" name="' . $prefix . 'voice_key_clear" value="1" id="ws_voice_key_clear">
                                    <label class="small" for="ws_voice_key_clear">' . h(lang('Remove the stored key')) . '</label>
                                </div>' : '') . '
                                <div class="form-text">' . h(lang('It is stored encrypted and never shown again. The site\'s own account at the service pays for the transcripts.')) . '</div>
                            </div>';
    }

    $fields = '
                            <div class="form-field mb-5">
                                <div class="switch"><input class="" type="checkbox" name="' . $prefix . 'voice_enabled" value="1" id="ws_voice_enabled"' . ($config['enabled'] ? ' checked' : '') . $disabled . '></div>
                                <label class="" for="ws_voice_enabled">' . h(lang('Write voice messages out as text')) . '</label>
                                <div class="form-text">' . h(lang(array(
                                    'string' => 'The sound of a voice message is sent to {var:1} with the key below, and the text written from it is kept beside the message. Public channels are written out, private ones only when their manager allows it. Recordings longer than {var:2} minutes are not sent.',
                                    'vars'   => array($config['provider'], (int) (WS_VOICE_MAX_SECONDS / 60)),
                                ))) . '</div>
                            </div>
                            <div class="mb-5">
                                <label class="form-label" for="ws_voice_url">' . h(lang('The service\'s address')) . '</label>
                                <input class="form-control form-control-sm font-monospace" type="url" name="' . $prefix . 'voice_url" id="ws_voice_url" value="' . h($config['url']) . '" placeholder="' . h(ws_voice_default_url()) . '" autocomplete="off"' . $disabled . '>
                                <div class="form-text">' . h(lang('Any service that answers like OpenAI\'s /audio/transcriptions. Left empty, OpenAI is used.')) . '</div>
                            </div>
                            <div class="mb-5">
                                <label class="form-label" for="ws_voice_model">' . h(lang('Model')) . '</label>
                                <input class="form-control form-control-sm font-monospace" type="text" name="' . $prefix . 'voice_model" id="ws_voice_model" value="' . h($config['model']) . '" placeholder="' . h(ws_voice_default_model()) . '" maxlength="100" autocomplete="off"' . $disabled . '>
                            </div>
                            ' . $key_field . '
                            ' . ws_voice_speech_settings_part($admin, $prefix) . '
';
    $status = '
                        <div class="ws-claude-status">
                            <div class="small fw-semibold mb-1">' . h(lang('Recordings are sent to')) . '</div>
                            <div class="small mb-5">' . h($config['provider']) . ' · <code>' . h(($config['model'] !== '') ? $config['model'] : ws_voice_default_model()) . '</code></div>
                            ' . (!empty($missing) ? '<div class="small fw-semibold mb-1">' . h(lang('Still missing')) . '</div><ul class="small mb-5">' . $status_list . '</ul>' : '') . '
                            ' . (($config['error'] !== '') ? '<div class="alert theme-danger small py-3">' . h($config['error']) . '</div>' : '') . '
                            ' . (($config['hold_until'] > time()) ? '<div class="alert theme-warning small py-3">' . h(lang(array('string' => 'Recordings wait until {var:1}.', 'vars' => date('d.m.Y H:i', $config['hold_until'])))) . '</div>' : '') . '
                            ' . ws_voice_speech_settings_status() . '
                        </div>
';

    if ($form !== null) {
        return '
    <div id="pgset-ws-voice" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap align-items-center gap-3">
                <span class="pg-title-section ws-grow">' . h(lang('Voice message transcripts')) . '</span>
                ' . $state . '
            </div>
            <div class="card-body">
                <div class="row g-7">
                    <div class="lg:col-7">' . $fields . ($admin ? '' : '<div class="form-text">' . h(lang('Only an administrator can connect a transcription service.')) . '</div>') . '</div>
                    <div class="lg:col-5">' . $status . '</div>
                </div>
            </div>
        </div>
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
                            ' . $fields . '
                            ' . ($admin ? '<button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h(lang('Save')) . '</button>' : '<div class="form-text">' . h(lang('Only an administrator can connect a transcription service.')) . '</div>') . '
                        </form>
                    </div>
                    <div class="lg:col-5">' . $status . '</div>
                </div>
            </div>
        </div>';
}

/**
 * The card's speech part, inside the transcripts form: the switch, the
 * address and key (empty = the transcripts' own), the model and the voice
 * from a list or typed. The consent line names the provider the text goes
 * to.
 *
 * @param bool   $admin  role 0; others see it disabled without the key
 * @param string $prefix 'ws_' in Site Settings
 * @return string
 */
function ws_voice_speech_settings_part($admin, $prefix = '')
{
    $config = ws_voice_speech_config(true);
    $disabled = $admin ? '' : ' disabled';
    $models = '';
    $voices = '';

    foreach (ws_voice_speech_models() as $name) {
        $models .= '<option value="' . h($name) . '"></option>';
    }

    foreach (ws_voice_speech_voices() as $name) {
        $voices .= '<option value="' . h($name) . '"></option>';
    }

    $key_field = '';

    if ($admin) {
        $key_field = '
                            <div class="mb-5">
                                <label class="form-label" for="ws_voice_speech_key">' . h(lang('The speech service\'s key')) . '</label>
                                <input class="form-control form-control-sm font-monospace" type="password" name="' . $prefix . 'voice_speech_key" id="ws_voice_speech_key" value="" autocomplete="new-password" placeholder="' . h($config['own_key'] ? lang('Stored. Type a new one to replace it.') : lang('Left empty, the transcripts\' key is used.')) . '">
                                ' . ($config['own_key'] ? '
                                <div class="form-field mt-1">
                                    <input class="check" type="checkbox" name="' . $prefix . 'voice_speech_key_clear" value="1" id="ws_voice_speech_key_clear">
                                    <label class="small" for="ws_voice_speech_key_clear">' . h(lang('Remove the stored speech key')) . '</label>
                                </div>' : '') . '
                            </div>';
    }

    return '
                            <h3 class="h6 mt-7 mb-3"><i class="bi bi-volume-up me-1" aria-hidden="true"></i>' . h(lang('Spoken answers')) . '</h3>
                            <div class="form-field mb-5">
                                <div class="switch"><input class="" type="checkbox" name="' . $prefix . 'voice_speech_enabled" value="1" id="ws_voice_speech_enabled"' . ($config['enabled'] ? ' checked' : '') . $disabled . '></div>
                                <label class="" for="ws_voice_speech_enabled">' . h(lang('Assistants answer a voice message by voice')) . '</label>
                                <div class="form-text">' . h(lang(array(
                                    'string' => 'The text of an assistant\'s answer to a voice message is sent to {var:1} with the key below, and the sound made from it is kept beside the answer as a voice message. Answers longer than {var:2} characters are spoken up to the sentence that fits.',
                                    'vars'   => array($config['provider'], WS_VOICE_SPEECH_MAX_CHARS),
                                ))) . '</div>
                            </div>
                            <div class="mb-5">
                                <label class="form-label" for="ws_voice_speech_url">' . h(lang('The speech service\'s address')) . '</label>
                                <input class="form-control form-control-sm font-monospace" type="url" name="' . $prefix . 'voice_speech_url" id="ws_voice_speech_url" value="' . h($config['url']) . '" placeholder="' . h($config['base']) . '" autocomplete="off"' . $disabled . '>
                                <div class="form-text">' . h(lang('Any service that answers like OpenAI\'s /audio/speech. Left empty, the transcripts\' address is used.')) . '</div>
                            </div>
                            ' . $key_field . '
                            <div class="row g-5 mb-5">
                                <div class="sm:col-6">
                                    <label class="form-label" for="ws_voice_speech_model">' . h(lang('Model')) . '</label>
                                    <input class="form-control form-control-sm font-monospace" type="text" name="' . $prefix . 'voice_speech_model" id="ws_voice_speech_model" list="ws_voice_speech_models" value="' . h($config['model']) . '" placeholder="' . h(ws_voice_speech_default_model()) . '" maxlength="100" autocomplete="off"' . $disabled . '>
                                    <datalist id="ws_voice_speech_models">' . $models . '</datalist>
                                </div>
                                <div class="sm:col-6">
                                    <label class="form-label" for="ws_voice_speech_voice">' . h(lang('Voice')) . '</label>
                                    <input class="form-control form-control-sm font-monospace" type="text" name="' . $prefix . 'voice_speech_voice" id="ws_voice_speech_voice" list="ws_voice_speech_voices" value="' . h($config['voice']) . '" placeholder="' . h(ws_voice_speech_default_voice()) . '" maxlength="100" autocomplete="off"' . $disabled . '>
                                    <datalist id="ws_voice_speech_voices">' . $voices . '</datalist>
                                </div>
                            </div>';
}

/**
 * How spoken answers stand, on the card's right: where the text goes, what
 * is still missing, the service's last refusal.
 *
 * @return string
 */
function ws_voice_speech_settings_status()
{
    $config = ws_voice_speech_config();
    $missing = ws_voice_speech_missing();
    $list = '';

    foreach ($missing as $sentence) {
        $list .= '<li>' . h($sentence) . '</li>';
    }

    return '
                            <div class="small fw-semibold mb-1">' . h(lang('Spoken answers are made by')) . '</div>
                            <div class="small mb-5">' . h($config['provider']) . ' · <code>' . h(($config['model'] !== '') ? $config['model'] : ws_voice_speech_default_model()) . '</code> · <code>' . h(($config['voice'] !== '') ? $config['voice'] : ws_voice_speech_default_voice()) . '</code></div>
                            ' . (!empty($missing) ? '<div class="small fw-semibold mb-1">' . h(lang('Still missing')) . '</div><ul class="small mb-5">' . $list . '</ul>' : '') . '
                            ' . (($config['error'] !== '') ? '<div class="alert theme-danger small py-3">' . h($config['error']) . '</div>' : '');
}

/* ---------------------------------------------------------------------------
   Spoken answers: the speech service
   --------------------------------------------------------------------------- */

/**
 * The most characters of an answer that are spoken; a longer answer is cut
 * at the last sentence end that fits (ws_voice_speech_cut()).
 */
define('WS_VOICE_SPEECH_MAX_CHARS', 1500);

/**
 * The model asked for when none is chosen, and the models the card offers:
 * OpenAI's; any other name that passes ws_voice_model_valid() may be typed
 * for another service.
 *
 * @return string
 */
function ws_voice_speech_default_model()
{
    return 'gpt-4o-mini-tts';
}

/**
 * @return string[]
 */
function ws_voice_speech_models()
{
    return array('gpt-4o-mini-tts', 'tts-1', 'tts-1-hd');
}

/**
 * The voice spoken with when none is chosen, and the voices the card offers.
 *
 * @return string
 */
function ws_voice_speech_default_voice()
{
    return 'alloy';
}

/**
 * @return string[]
 */
function ws_voice_speech_voices()
{
    return array('alloy', 'ash', 'ballad', 'coral', 'echo', 'fable', 'nova', 'onyx', 'sage', 'shimmer', 'verse');
}

/**
 * Could this be a voice's name? The model shape: letters, digits and
 * . _ : / - only, so another service's names (Fritz-PlayAI) fit too.
 *
 * @param string $voice
 * @return bool
 */
function ws_voice_speech_voice_valid($voice)
{
    return ws_voice_model_valid($voice);
}

/**
 * The speech address as it is entered, made the base the call is joined
 * to: as ws_voice_url_clean(), with /audio/speech cut off when the whole
 * endpoint was pasted.
 *
 * @param string $url
 * @return string
 */
function ws_voice_speech_url_clean($url)
{
    $url = rtrim(trim((string) $url), '/');

    if (preg_match('#/audio/speech$#i', $url)) {
        $url = rtrim(substr($url, 0, -strlen('/audio/speech')), '/');
    }

    return $url;
}

/**
 * The speech service: config_text rows beside the transcripts' (the config
 * row has no room for more columns). An address or key left empty means the
 * transcripts' own, so one OpenAI key serves both ways.
 *
 * @param bool $fresh read again
 * @return array enabled, url (the entered one, may be ''), base (the address
 *               called), own_key (one of its own is stored), key_set (one is
 *               there, own or the transcripts'), model, voice (entered, may
 *               be ''), error, provider
 */
function ws_voice_speech_config($fresh = false)
{
    static $config = null;

    if (!ws_voice_schema_ready()) {
        return array(
            'enabled' => false, 'url' => '', 'base' => ws_voice_default_url(), 'own_key' => false, 'key_set' => false,
            'model' => '', 'voice' => '', 'error' => '', 'provider' => ws_voice_provider_name(''),
        );
    }

    if (($config === null) || $fresh) {
        $transcripts = ws_voice_config($fresh);
        $url = pg_config_text('ws_voice_speech_url');
        $base = ($url !== '') ? $url : (($transcripts['url'] !== '') ? $transcripts['url'] : ws_voice_default_url());
        $own_key = (strpos(pg_config_text('ws_voice_speech_key'), ':') !== false);

        $config = array(
            'enabled'  => (pg_config_text('ws_voice_speech_enabled') === '1'),
            'url'      => $url,
            'base'     => $base,
            'own_key'  => $own_key,
            'key_set'  => $own_key || $transcripts['key_set'],
            'model'    => pg_config_text('ws_voice_speech_model'),
            'voice'    => pg_config_text('ws_voice_speech_voice'),
            'error'    => pg_config_text('ws_voice_speech_error'),
            'provider' => ws_voice_provider_name($base),
        );
    }

    return $config;
}

/**
 * The key the speech service is called with: the service's own when one is
 * stored, else the transcripts' (ws_voice_key()). Empty when neither can be
 * read.
 *
 * @return string
 */
function ws_voice_speech_key()
{
    $sealed = ws_voice_schema_ready() ? pg_config_text('ws_voice_speech_key') : '';

    if (($sealed === '') || (strpos($sealed, ':') === false)) {
        return ws_voice_key();
    }

    if (!function_exists('decode_ssl_keys') || !defined('ENCRYPTION_KEY')) {
        return '';
    }

    list($cipher, $iv) = explode(':', $sealed, 2);

    return (string) decode_ssl_keys($cipher, $iv);
}

/**
 * What still stands between an answer and its sound, in sentences.
 *
 * @return string[] empty when spoken answers are ready
 */
function ws_voice_speech_missing()
{
    if (!ws_voice_schema_ready()) {
        return array(lang('The database has not been upgraded yet.'));
    }

    $config = ws_voice_speech_config();
    $missing = array();

    if (!$config['enabled']) {
        $missing[] = lang('Spoken answers are switched off.');
    }

    if (!$config['key_set']) {
        $missing[] = lang('No key is entered for the speech service.');
    } elseif (ws_voice_speech_key() === '') {
        $missing[] = lang('The stored speech key cannot be read any more: enter it again.');
    }

    if (!function_exists('curl_init')) {
        $missing[] = lang('This server cannot make outgoing HTTPS requests (cURL is missing).');
    }

    return $missing;
}

/**
 * Can an answer be spoken now? The switch, a key and cURL. A caller without
 * it (the panel's spoken answer, the voice message route) falls back or
 * answers voice_unavailable.
 *
 * @return bool
 */
function ws_voice_speech_ready()
{
    return count(ws_voice_speech_missing()) === 0;
}

/**
 * The two-letter language code of a locale (tr_TR, en-US), '' when it has
 * none; what the row's language column keeps for a spoken answer.
 *
 * @param string $locale
 * @return string
 */
function ws_voice_speech_language_code($locale)
{
    return preg_match('/^([A-Za-z]{2})(?![A-Za-z])/', (string) $locale, $found) ? strtolower($found[1]) : '';
}

/**
 * The name of a locale's language in English, as a model's instructions
 * take it; '' for one without a name here (nothing is asked then).
 *
 * @param string $locale
 * @return string
 */
function ws_voice_speech_language_name($locale)
{
    $names = array(
        'tr' => 'Turkish', 'en' => 'English', 'de' => 'German', 'fr' => 'French', 'es' => 'Spanish', 'it' => 'Italian',
        'nl' => 'Dutch', 'pt' => 'Portuguese', 'ru' => 'Russian', 'ar' => 'Arabic', 'az' => 'Azerbaijani', 'pl' => 'Polish',
        'uk' => 'Ukrainian', 'el' => 'Greek', 'ja' => 'Japanese', 'ko' => 'Korean', 'zh' => 'Chinese',
    );

    return $names[ws_voice_speech_language_code($locale)] ?? '';
}

/**
 * The text an answer is spoken from: white space round it gone, and over
 * the limit cut at the last sentence end (. ! ? … followed by white space,
 * or a line end) that fits, else at a word end, else at the limit itself.
 * The transcript kept beside the sound is this text, so what is heard and
 * what is read are the same.
 *
 * @param string $text
 * @param int    $limit characters
 * @return string
 */
function ws_voice_speech_cut($text, $limit = WS_VOICE_SPEECH_MAX_CHARS)
{
    $text = trim(str_replace("\r\n", "\n", (string) $text));
    $limit = max(1, (int) $limit);

    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    $head = mb_substr($text, 0, $limit);
    // One character more, so a sentence that ends exactly at the limit is
    // seen with the white space after it.
    $probe = $head . mb_substr($text, $limit, 1);
    $cut = 0;

    if (preg_match_all('/[.!?\x{2026}](?=\s)|(?=\n)/u', $probe, $found, PREG_OFFSET_CAPTURE)) {
        foreach ($found[0] as $match) {
            $end = $match[1] + strlen($match[0]);

            if (($end > 0) && ($end <= strlen($head))) {
                $cut = $end;
            }
        }
    }

    if ($cut === 0) {
        $space = mb_strrpos($head, ' ');
        $cut = ($space !== false) && ($space > 0) ? strlen(mb_substr($head, 0, $space)) : strlen($head);
    }

    return rtrim(substr($head, 0, $cut));
}

/**
 * The request a speech service gets, without the key: POST /audio/speech
 * under the base with a JSON body in OpenAI's shape (model, input, voice,
 * response_format mp3). A model that takes instructions (not tts-1 or
 * tts-1-hd, which refuse them) is asked to speak in the request's language.
 *
 * @param string $text   as ws_voice_speech_cut() answers it
 * @param string $locale the language to speak in
 * @param string $base   the service's base address
 * @param string $model
 * @param string $voice
 * @return array url, body (JSON), headers (without Authorization), format, timeout
 */
function ws_voice_speech_request($text, $locale, $base, $model, $voice)
{
    $body = array(
        'model'           => (string) $model,
        'input'           => (string) $text,
        'voice'           => (string) $voice,
        'response_format' => 'mp3',
    );
    $language = ws_voice_speech_language_name($locale);

    if (($language !== '') && !preg_match('/^tts-1/i', (string) $model)) {
        $body['instructions'] = 'Speak in ' . $language . '.';
    }

    return array(
        'url'     => rtrim((string) $base, '/') . '/audio/speech',
        'body'    => (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'headers' => array('Content-Type: application/json', 'Accept: audio/mpeg'),
        'format'  => 'mp3',
        'timeout' => WS_VOICE_CALL_TIMEOUT,
    );
}

/**
 * What a speech service's answer means: a sound (200 with bytes that are
 * not JSON), or a refusal in its own words. The key or the account refusing
 * (401, 402, 403) is marked, so the card can say so.
 *
 * @param int    $status HTTP status, 0 when no answer came
 * @param string $body
 * @param string $type   the answer's Content-Type
 * @param string $error  the client's own error when no answer came
 * @return array ok, error, refused
 */
function ws_voice_speech_answer($status, $body, $type, $error = '')
{
    $body = (string) $body;
    $json = json_decode($body, true);
    $message = (is_array($json) && isset($json['error']['message']) && is_string($json['error']['message'])) ? trim($json['error']['message']) : '';
    $out = array('ok' => false, 'error' => '', 'refused' => false);

    if ((int) $status === 200) {
        if (($body !== '') && !is_array($json) && (stripos((string) $type, 'json') === false)) {
            $out['ok'] = true;

            return $out;
        }

        $out['error'] = ($message !== '')
            ? lang(array('string' => 'The speech service said: {var:1}', 'vars' => mb_substr($message, 0, 200)))
            : lang('The speech service gave no sound.');

        return $out;
    }

    $said = ($message !== '') ? $message : (((int) $status > 0) ? 'HTTP ' . (int) $status : (string) $error);
    $out['refused'] = in_array((int) $status, array(401, 402, 403), true);
    $out['error'] = lang(array('string' => 'The speech service said: {var:1}', 'vars' => mb_substr($said, 0, 200)));

    return $out;
}

/**
 * The name a spoken answer is kept under: the recorder's shape
 * (voice-YYYYMMDD-HHMMSS.<ext>), so ws_voice_is_recording() takes it and
 * the player, search, quotes and the delete treat it as a voice message.
 *
 * @param string $extension
 * @param int    $time
 * @return string
 */
function ws_voice_speech_file_name($extension, $time)
{
    return 'voice-' . gmdate('Ymd-His', (int) $time) . '.' . strtolower(preg_replace('/[^A-Za-z0-9]/', '', (string) $extension));
}

/**
 * Speaks a text with the speech service: the request built by
 * ws_voice_speech_request(), sent with the key through api_http_request()
 * (pinned address, no internal hosts). Answers the sound and the name it is
 * to be kept under; ws_voice_speak_keep() makes it a message's voice
 * message. The service's last refusal is kept in config_text
 * ws_voice_speech_error for the card, and cleared by the next sound.
 *
 * @param string $text   the answer; over WS_VOICE_SPEECH_MAX_CHARS it is cut at a sentence end
 * @param string $locale the language to speak in (tr, en-US, ...)
 * @param string $voice  a voice's name in place of the card's, '' for the card's
 * @return array ok, error, bytes, name, mime, text (what was spoken),
 *               language (code), model, voice, provider
 */
function ws_voice_speak($text, $locale, $voice = '')
{
    $out = array(
        'ok' => false, 'error' => '', 'bytes' => '', 'name' => '', 'mime' => '', 'text' => '', 'language' => '',
        'model' => '', 'voice' => '', 'provider' => '',
    );
    $missing = ws_voice_speech_missing();

    if (!empty($missing)) {
        $out['error'] = (string) reset($missing);

        return $out;
    }

    $spoken = ws_voice_speech_cut($text);

    if ($spoken === '') {
        $out['error'] = lang('There is nothing to say.');

        return $out;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $config = ws_voice_speech_config();
    $model = ($config['model'] !== '') ? $config['model'] : ws_voice_speech_default_model();
    $voice = trim((string) $voice);
    $voice = ($voice !== '') ? $voice : (($config['voice'] !== '') ? $config['voice'] : ws_voice_speech_default_voice());
    $request = ws_voice_speech_request($spoken, $locale, $config['base'], $model, $voice);

    $answer = api_http_request('POST', $request['url'], array(
        'body'             => $request['body'],
        'headers'          => array_merge(array('Authorization: Bearer ' . ws_voice_speech_key()), $request['headers']),
        'timeout'          => $request['timeout'],
        'max_body'         => 0,
        'agent'            => 'workspace-voice',
        'response_headers' => true,
    ));

    $outcome = ws_voice_speech_answer((int) $answer['status'], (string) $answer['body'], (string) ($answer['headers']['content-type'] ?? ''), (string) $answer['error']);

    if (!$outcome['ok']) {
        pg_config_text_save('ws_voice_speech_error', mb_substr($outcome['error'], 0, 250));
        $out['error'] = $outcome['error'];

        return $out;
    }

    if ($config['error'] !== '') {
        pg_config_text_save('ws_voice_speech_error', '');
    }

    $out['ok'] = true;
    $out['bytes'] = (string) $answer['body'];
    $out['name'] = ws_voice_speech_file_name($request['format'], time());
    $out['mime'] = ws_file_type($out['name'])['mime'];
    $out['text'] = $spoken;
    $out['language'] = ws_voice_speech_language_code($locale);
    $out['model'] = $model;
    $out['voice'] = $voice;
    $out['provider'] = $config['provider'];

    return $out;
}

/**
 * Keeps a sound ws_voice_speak() answered as the voice message of a message
 * already written and carrying no file: the file in the channel's folder
 * (ws_store_file_bytes()), the message's file, and a done ws_voice row whose
 * transcript is the spoken text, so the player, search, recaps, bells and
 * the delete for everyone treat it as any recording. Nothing is queued: the
 * text is known.
 *
 * @param array $channel
 * @param array $message the ws_messages row, as ws_message() reads it
 * @param array $spoken  ws_voice_speak()'s answer, ok
 * @param int   $user_id the owner of the file manager row (the person spoken to)
 * @return array ok, error, file_id, name
 */
function ws_voice_speak_keep($channel, $message, $spoken, $user_id)
{
    $out = array('ok' => false, 'error' => '', 'file_id' => 0, 'name' => '');

    if (!ws_voice_schema_ready() || empty($spoken['ok']) || ((string) ($spoken['bytes'] ?? '') === '')) {
        $out['error'] = lang('The speech service gave no sound.');

        return $out;
    }

    if ((int) ($message['file_id'] ?? 0) > 0) {
        $out['error'] = lang('The message already carries a file.');

        return $out;
    }

    $stored = ws_voice_speak_store($channel, $spoken, (int) $user_id);

    if (!$stored['ok']) {
        $out['error'] = $stored['error'];

        return $out;
    }

    db("UPDATE ws_messages SET file_id = '" . (int) $stored['file_id'] . "', file_name = '" . e($stored['name']) . "'
        WHERE id = '" . (int) $message['id'] . "' AND file_id = 0");

    if (mysqli_affected_rows(db::$con) !== 1) {
        ws_upload_discard((int) $stored['file_id']);
        $out['error'] = lang('The spoken answer could not be saved.');

        return $out;
    }

    ws_voice_speak_row($channel, (int) $message['id'], (int) $stored['file_id'], $spoken);

    $out['ok'] = true;
    $out['file_id'] = (int) $stored['file_id'];
    $out['name'] = (string) $stored['name'];

    return $out;
}

/**
 * Keeps the sound ws_voice_speak() answered as a file of the channel, in its
 * folder with a row in the file manager, inspected as an upload is
 * (ws_upload_inspect(), ws_store_file_bytes()).
 *
 * @param array $channel
 * @param array $spoken  ws_voice_speak()'s answer, ok
 * @param int   $user_id the owner of the file manager row
 * @return array ok, error, file_id, name
 */
function ws_voice_speak_store($channel, $spoken, $user_id)
{
    $checked = ws_upload_inspect((string) ($spoken['name'] ?? ''), (string) ($spoken['bytes'] ?? ''));

    if (!$checked['ok']) {
        return array('ok' => false, 'error' => $checked['error'], 'file_id' => 0, 'name' => '');
    }

    return ws_store_file_bytes($channel, $checked['name'], $checked['extension'], (string) $spoken['bytes'], (int) $user_id);
}

/**
 * The done ws_voice row of a spoken file a message carries: transcript =
 * the spoken text, the language, provider and model, no length (the player
 * reads the file's own), and the message marked changed so that screens
 * and apps holding it read it again with its sound.
 *
 * @param array $channel
 * @param int   $message_id
 * @param int   $file_id
 * @param array $spoken ws_voice_speak()'s answer, ok
 */
function ws_voice_speak_row($channel, $message_id, $file_id, $spoken)
{
    $now = time();

    db("INSERT INTO ws_voice (file_id, message_id, channel_id, duration_ms, peaks, status, transcript, language, provider, model, created_at, updated_at, transcribed_at)
        VALUES ('" . (int) $file_id . "', '" . (int) $message_id . "', '" . (int) $channel['id'] . "', 0, '', 'done',
            '" . e((string) $spoken['text']) . "', '" . e(mb_substr((string) $spoken['language'], 0, 16)) . "',
            '" . e(mb_substr((string) $spoken['provider'], 0, 100)) . "', '" . e(mb_substr((string) $spoken['model'], 0, 100)) . "',
            '" . $now . "', '" . $now . "', '" . $now . "')");

    ws_message_touch((int) $message_id);
}

/* ---------------------------------------------------------------------------
   Spoken answers: an answer to a recording
   --------------------------------------------------------------------------- */

/**
 * Whether a message is a recording still there: the recorder's name on its
 * file (or a spoken message, which is named the same) and not deleted. Only
 * an answer to such a message is spoken; a sound somebody attached is not a
 * question asked by voice.
 *
 * @param array|null $message the ws_messages row
 * @return bool
 */
function ws_voice_answer_to_recording($message)
{
    return is_array($message)
        && ((int) ($message['file_id'] ?? 0) > 0)
        && ((int) ($message['deleted_at'] ?? 0) === 0)
        && ws_voice_is_recording((string) ($message['file_name'] ?? ''));
}

/**
 * The words of an answer as a voice reads them: tags out (the mention of
 * the asker, a record tag: a voice reads no tags), code blocks out, Markdown
 * marks off (emphasis, code, headings, bullets, quotes), white space within
 * a line folded and empty lines folded to one break.
 *
 * @param string $body the answer as it is kept
 * @return string '' when nothing is left to say
 */
function ws_voice_answer_text($body)
{
    $text = str_replace("\r\n", "\n", (string) $body);
    $text = (string) preg_replace('/```.*?```/s', '', $text);
    $text = (string) preg_replace('/<[@#][a-z_]{1,30}:[0-9]{1,10}>/i', '', $text);
    $text = (string) preg_replace('/^[ \t]*(#{1,6}[ \t]+|[-*+][ \t]+|>[ \t]*|[0-9]{1,3}\.[ \t]+)/m', '', $text);
    $text = (string) preg_replace('/(\*\*|__|`)(.*?)\1/s', '$2', $text);
    $text = (string) preg_replace('/(?<![\p{L}\p{N}])[*_](?=\S)(.*?)(?<=\S)[*_](?![\p{L}\p{N}])/su', '$1', $text);
    $text = (string) preg_replace('/[ \t]+/', ' ', $text);
    $text = (string) preg_replace('/ *\n */', "\n", $text);
    $text = (string) preg_replace('/\n{2,}/', "\n", $text);

    return trim($text);
}

/**
 * The language an answer is spoken in: Turkish when its letters say so,
 * whatever the site speaks; otherwise the site's own language; English for
 * a code that is no code.
 *
 * @param string $text
 * @param string $site the site's language as lang(array('info' => true)) gives it
 * @return string a two-letter code
 */
function ws_voice_answer_locale_rule($text, $site)
{
    if (preg_match('/[çğıöşüÇĞİÖŞÜ]/u', (string) $text)) {
        return 'tr';
    }

    $code = ws_voice_speech_language_code($site);

    return ($code !== '') ? $code : 'en';
}

/**
 * @param string $text
 * @return string
 */
function ws_voice_answer_locale($text)
{
    return ws_voice_answer_locale_rule($text, (string) lang(array('info' => true)));
}

/**
 * After an assistant's answer was written: when it answers a recording and
 * the speech service is ready, its sound is made (ws_voice_speak()) and kept
 * on the same message (ws_voice_speak_keep()), so the person who spoke hears
 * it back; the answer's text stays as it is. Without a service, or when the
 * service refuses, nothing is kept and the asker's browser reads the answer
 * aloud (speakAnswer() in workspace.js); the refusal shows on the card.
 * A channel that keeps its recordings (ws_voice_channel_allowed(): a private
 * channel without the switch, a guest room, a channel shared with a guest, a
 * discussion of such) sends no answer out either. Shared by Claude's answer
 * route (api_claude.php), Pinegrap AI (ai.php) and an MCP agent
 * (mcp_agent.php).
 *
 * @param array  $channel    where the answer was written
 * @param int    $message_id the answer
 * @param int    $parent_id  the message it answers
 * @param string $text       the answer as it was written
 * @param int    $user_id    the person who asked, owner of the file
 * @return array spoken (bool), error ('' when nothing was tried or it went well)
 */
function ws_voice_answer_spoken($channel, $message_id, $parent_id, $text, $user_id)
{
    $out = array('spoken' => false, 'error' => '');

    if (!ws_voice_schema_ready() || ((int) $parent_id <= 0) || ((int) $message_id <= 0) || !ws_voice_speech_ready()) {
        return $out;
    }

    if (!is_array($channel) || !ws_voice_channel_allowed($channel)) {
        return $out;
    }

    if (!ws_voice_answer_to_recording(ws_message((int) $parent_id))) {
        return $out;
    }

    $words = ws_voice_answer_text($text);

    if ($words === '') {
        return $out;
    }

    $spoken = ws_voice_speak($words, ws_voice_answer_locale($words));

    if (!$spoken['ok']) {
        $out['error'] = $spoken['error'];

        return $out;
    }

    $message = ws_message((int) $message_id);

    if (!$message) {
        return $out;
    }

    $kept = ws_voice_speak_keep($channel, $message, $spoken, (int) $user_id);
    $out['spoken'] = $kept['ok'];
    $out['error'] = $kept['error'];

    return $out;
}

/* ---------------------------------------------------------------------------
   A voice message from a text
   --------------------------------------------------------------------------- */

/**
 * Where a voice message goes: channel_id or thread_id, one of them, each of
 * its own kind. Pure: the handler reads the channel.
 *
 * @param int    $channel_id
 * @param int    $thread_id
 * @param string $kind the kind of the channel the id given names ('' for none)
 * @return array|null null when it may go there, else field and error
 */
function ws_voice_message_target_rule($channel_id, $thread_id, $kind)
{
    $channel_id = (int) $channel_id;
    $thread_id = (int) $thread_id;

    if (($channel_id > 0) === ($thread_id > 0)) {
        return array('field' => 'channel_id', 'error' => lang('Give channel_id or thread_id, one of them.'));
    }

    if (($thread_id > 0) && ((string) $kind !== 'thread')) {
        return array('field' => 'thread_id', 'error' => lang('thread_id is not a discussion: send a channel as channel_id.'));
    }

    if (($channel_id > 0) && ((string) $kind === 'thread')) {
        return array('field' => 'channel_id', 'error' => lang('channel_id is a discussion: send it as thread_id.'));
    }

    return null;
}

/**
 * The HTTP status of a refusal of ws_voice_message_check() or
 * ws_voice_message_send(), by its code.
 *
 * @param string $code
 * @return int
 */
function ws_voice_message_status($code)
{
    switch ((string) $code) {
        case 'voice_unavailable':
        case 'voice_closed':
            return 409;

        case 'voice_failed':
            return 503;

        default:
            return 422;
    }
}

/**
 * Whether a voice message can be made from a text for this channel, with
 * nothing made: the speech service ready (voice_unavailable), the channel
 * not keeping its recordings (voice_closed: a private channel without the
 * switch, a guest room, a channel shared with a guest), the voice's name,
 * something left to say once tags and marks are out, the message checks of
 * ws_message_send_check() and the answered message (ws_message_parent_check()).
 * Shared by the API route, its dry run and Pinegrap AI's tool.
 *
 * @param array  $viewer  who writes
 * @param array  $channel the channel or discussion
 * @param string $text    what is to be said
 * @param array  $options app_id, parent_id (the message answered), voice
 * @return array ok, error, code ('' for a field's 422), field, text (what
 *               will be spoken), voice
 */
function ws_voice_message_check($viewer, $channel, $text, $options = array())
{
    $out = array('ok' => false, 'error' => '', 'code' => '', 'field' => '', 'text' => '', 'voice' => '');
    $missing = ws_voice_speech_missing();

    if (!empty($missing)) {
        $out['code'] = 'voice_unavailable';
        $out['error'] = lang('No speech service is set.') . ' ' . (string) reset($missing);

        return $out;
    }

    if (!ws_voice_channel_allowed($channel)) {
        $out['code'] = 'voice_closed';
        $out['error'] = lang('No voice message is made for this channel: it keeps its recordings on the site.');

        return $out;
    }

    $voice = trim((string) ($options['voice'] ?? ''));

    if (($voice !== '') && !ws_voice_speech_voice_valid($voice)) {
        $out['field'] = 'voice';
        $out['error'] = lang('The voice name may hold letters, digits and . _ : / - only.');

        return $out;
    }

    $spoken = ws_voice_speech_cut(ws_voice_answer_text($text));

    if ($spoken === '') {
        $out['field'] = 'text';
        $out['error'] = lang('There is nothing to say.');

        return $out;
    }

    // The file it is about to carry stands in for its id: the message has
    // no words of its own, the sound is its message.
    $message = ws_message_send_check($viewer, $channel, '', array('app_id' => (int) ($options['app_id'] ?? 0), 'file_id' => PHP_INT_MAX));

    if (!$message['ok']) {
        $out['field'] = 'text';
        $out['error'] = $message['error'];

        return $out;
    }

    $refused = ws_message_parent_check($channel, (int) ($options['parent_id'] ?? 0));

    if ($refused !== '') {
        $out['field'] = 'reply_to';
        $out['error'] = $refused;

        return $out;
    }

    $out['ok'] = true;
    $out['text'] = $spoken;
    $out['voice'] = $voice;

    return $out;
}

/**
 * Makes a voice message from a text and posts it: the checks above, the
 * sound from the speech service (ws_voice_speak()), the file in the
 * channel's folder, a message carrying it with no words of its own (as a
 * recording is posted), and a done ws_voice row whose transcript is the
 * text, so the player, search, recaps and bells treat it as any voice
 * message. The service refusing (voice_failed) leaves nothing behind.
 *
 * @param array  $viewer  who writes
 * @param array  $channel the channel or discussion
 * @param string $text
 * @param array  $options app_id, parent_id, voice
 * @return array ok, error, code, field, message_id
 */
function ws_voice_message_send($viewer, $channel, $text, $options = array())
{
    $checked = ws_voice_message_check($viewer, $channel, $text, $options);
    $out = array('ok' => false, 'error' => $checked['error'], 'code' => $checked['code'], 'field' => $checked['field'], 'message_id' => 0);

    if (!$checked['ok']) {
        return $out;
    }

    $spoken = ws_voice_speak($checked['text'], ws_voice_answer_locale($checked['text']), $checked['voice']);

    if (!$spoken['ok']) {
        $out['code'] = 'voice_failed';
        $out['error'] = $spoken['error'];

        return $out;
    }

    $stored = ws_voice_speak_store($channel, $spoken, (int) $viewer['id']);

    if (!$stored['ok']) {
        $out['code'] = 'voice_failed';
        $out['error'] = $stored['error'];

        return $out;
    }

    $result = ws_message_send($viewer, $channel, '', array(
        'app_id'    => (int) ($options['app_id'] ?? 0),
        'via_client_id' => (int) ($options['via_client_id'] ?? 0),
        'file_id'   => (int) $stored['file_id'],
        'file_name' => (string) $stored['name'],
        'parent_id' => (int) ($options['parent_id'] ?? 0),
    ));

    if (!$result['ok']) {
        ws_upload_discard((int) $stored['file_id']);
        $out['field'] = 'text';
        $out['error'] = $result['error'];

        return $out;
    }

    ws_voice_speak_row($channel, (int) $result['message_id'], (int) $stored['file_id'], $spoken);

    $out['ok'] = true;
    $out['error'] = '';
    $out['message_id'] = (int) $result['message_id'];

    return $out;
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
 * are on and its channel allows them; written out already when the sender's
 * browser did so where no service does (ws_voice_browser_rule(), kept as
 * the machine transcript with provider 'browser'); otherwise kept as never
 * asked. Called by both uploads (ws_message_send_file_bytes(), the API's
 * files.create).
 *
 * @param array  $channel
 * @param int    $message_id
 * @param int    $file_id
 * @param string $name    the name it was posted with
 * @param array  $measure duration_ms and peaks the recorder sent, if any;
 *                        transcript, the text the sender's browser wrote
 * @return bool it waits to be written out
 */
function ws_voice_queue($channel, $message_id, $file_id, $name, $measure = array())
{
    if (!ws_voice_schema_ready() || ((int) $file_id <= 0) || !ws_voice_is_recording($name)) {
        return false;
    }

    $enabled = ws_voice_config()['enabled'];
    $allowed = ws_voice_channel_allowed($channel);
    $queued = $enabled && $allowed;
    $measured = ws_voice_measure_clean($measure['duration_ms'] ?? 0, $measure['peaks'] ?? '');
    $now = time();

    // The sender's browser wrote it out where no service would: kept done,
    // so the assistants its words ask hear it at once.
    $heard = ws_voice_browser_rule(true, $enabled, $allowed) ? ws_voice_browser_transcript($measure['transcript'] ?? '', $name) : '';

    if ($heard !== '') {
        db("INSERT IGNORE INTO ws_voice (file_id, message_id, channel_id, duration_ms, peaks, status, transcript, provider, created_at, updated_at, transcribed_at)
            VALUES ('" . (int) $file_id . "', '" . (int) $message_id . "', '" . (int) $channel['id'] . "', '" . (int) $measured['duration_ms'] . "', '" . e($measured['peaks']) . "',
                'done', '" . e($heard) . "', 'browser', '" . $now . "', '" . $now . "', '" . $now . "')");

        return false;
    }

    db("INSERT IGNORE INTO ws_voice (file_id, message_id, channel_id, duration_ms, peaks, status, created_at, updated_at)
        VALUES ('" . (int) $file_id . "', '" . (int) $message_id . "', '" . (int) $channel['id'] . "', '" . (int) $measured['duration_ms'] . "', '" . e($measured['peaks']) . "',
            '" . ($queued ? 'queued' : '') . "', '" . $now . "', '" . $now . "')");

    if ($queued) {
        ws_voice_carry_on();
    }

    return $queued;
}

/**
 * After a recording was posted: the assistants its words ask (a tag typed
 * beside it, an answer to one of their answers) are asked once it is
 * written out, so that they hear what was said (ws_voice_ask()); at once
 * when it will not be (transcripts off, the channel closed to them). Any
 * other file asks nobody, as before.
 *
 * @param array  $viewer     who posted it
 * @param array  $channel
 * @param int    $message_id
 * @param string $body       the words posted with it
 * @param string $name       the name it was posted with
 * @param bool   $queued     ws_voice_queue()
 * @return array claude, agent, ai: the requests queued now
 */
function ws_voice_posted($viewer, $channel, $message_id, $body, $name, $queued)
{
    if ($queued || !ws_voice_is_recording($name) || !function_exists('ws_message_ask_assistants')) {
        return array('claude' => false, 'agent' => false, 'ai' => false);
    }

    return ws_message_ask_assistants($viewer, $channel, $message_id, $body);
}

/**
 * A recording was written out, or ended without a text: the assistants its
 * words ask are asked now, in the name of the person who posted it while
 * they may still write in the channel. Called once, when the row leaves the
 * queue for good. Pinegrap AI and an MCP agent are carried on by the screens
 * showing the message; Claude's routine is called here.
 *
 * @param int $message_id
 */
function ws_voice_ask($message_id)
{
    $message = ws_message((int) $message_id);

    if (!$message || ((int) $message['deleted_at'] > 0) || ((string) $message['sender_kind'] !== 'user') || !function_exists('ws_message_ask_assistants')) {
        return;
    }

    $channel = ws_channel((int) $message['channel_id']);
    $viewer = function_exists('ws_ai_viewer') ? ws_ai_viewer((int) $message['sender_id']) : null;

    if (!$channel || !$viewer || !ws_can_post_channel($viewer, $channel)) {
        return;
    }

    $asked = ws_message_ask_assistants($viewer, $channel, (int) $message['id'], (string) $message['body']);

    if (!empty($asked['claude']) && function_exists('ws_claude_dispatch')) {
        ws_claude_dispatch();
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
    $old = "status = 'queued' AND created_at < '" . ($now - WS_VOICE_QUEUE_TTL) . "'";
    $stale = "status = 'running' AND updated_at < '" . ($now - WS_VOICE_STALE) . "'";
    $error = e(lang('It could not be sent to the transcription service within a day.'));

    // Every kick runs this before the run lock, and every screen showing a
    // waiting recording kicks, so two watchdogs often read the same rows.
    // Each row is moved on its own under the condition it was read with:
    // only the watchdog whose UPDATE moved it asks what its words ask
    // (ws_voice_ask()), or the assistants are asked twice.
    foreach ((array) db_items("SELECT id, message_id, status, attempts FROM ws_voice
        WHERE (" . $old . ") OR (" . $stale . ") ORDER BY id LIMIT 500") as $row) {
        $where = "id = '" . (int) $row['id'] . "' AND ";

        if ((string) $row['status'] === 'queued') {
            db("UPDATE ws_voice SET status = 'failed', error = '" . $error . "', updated_at = '" . $now . "' WHERE " . $where . $old);
            $failed = ws_voice_changed();
        } else {
            $give_up = ((int) $row['attempts'] >= WS_VOICE_ATTEMPTS);

            db("UPDATE ws_voice SET status = '" . ($give_up ? 'failed' : 'queued') . "', updated_at = '" . $now . "'
                WHERE " . $where . $stale . " AND attempts = '" . (int) $row['attempts'] . "'");
            $failed = $give_up && ws_voice_changed();
        }

        if ($failed) {
            ws_voice_ask((int) $row['message_id']);
        }
    }
}

/**
 * Did the last UPDATE change a row? Tells the watchdog that moved a row
 * from one that found it already moved.
 *
 * @return bool
 */
function ws_voice_changed()
{
    return mysqli_affected_rows(db::$con) > 0;
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
    if (!ws_voice_schema_ready()) {
        return 'idle';
    }

    // Before the switch: recordings left queued when transcripts were
    // switched off still fail after a day and ask what they ask.
    ws_voice_watchdog();

    if (!ws_voice_config()['enabled']) {
        return 'idle';
    }

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
    $path = ($stored !== '') ? pg_file_reader_path(basename($stored)) : '';
    $size = ($path !== '') ? @filesize($path) : false;

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
            ws_voice_ask((int) $row['message_id']);

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

    // Asked without a transcript: the assistant is told it was not heard.
    ws_voice_ask((int) $row['message_id']);

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

/**
 * A message in one short line for a bell row or a device banner
 * (ws_inbox_describe()): a voice message reads its transcript once written
 * out, "Voice message" before that, so a recording sent without words is
 * never an empty banner. Read when the banner is drawn, after its wait, so a
 * transcript written out meanwhile is already in it.
 *
 * @param array $viewer
 * @param array $message ws_messages row
 * @param int   $length  characters
 * @return string
 */
function ws_voice_excerpt($viewer, $message, $length = 140)
{
    $text = ws_plain_excerpt($viewer, (string) $message['body'], $length);
    $transcript = ws_voice_transcript_of($message);
    $recording = ws_voice_is_recording((string) ($message['file_name'] ?? ''));

    return ws_voice_excerpt_text($text, $recording, $transcript, $length, lang('Voice message'));
}

/**
 * The line ws_voice_excerpt() gives, from its parts: the words as they are
 * for any other message; for a recording the words, then the label and the
 * transcript, cut at $length characters.
 *
 * @param string $text       the message's words in one line
 * @param bool   $recording  is its file a recording
 * @param string $transcript ws_voice.transcript, '' for none (yet)
 * @param int    $length     characters
 * @param string $label      "Voice message" in the reader's language
 * @return string
 */
function ws_voice_excerpt_text($text, $recording, $transcript, $length, $label)
{
    $text = trim((string) $text);
    $heard = ws_voice_transcript_clean($transcript);

    if (!$recording && ($heard === '')) {
        return $text;
    }

    if ($heard === '') {
        return ($text !== '') ? $text : (string) $label;
    }

    $line = (($text !== '') ? $text . ' · ' : '') . $label . ': ' . $heard;

    if (($length > 1) && (mb_strlen($line) > $length)) {
        $line = rtrim(mb_substr($line, 0, $length - 1)) . '…';
    }

    return $line;
}

/**
 * The condition that finds a message by its recording's transcript, to be
 * joined with OR to a search on the body: '' while transcripts have no
 * table. The reach rules of the search stay as they are: a transcript is
 * found as its message is.
 *
 * @param string $like the search words, already through e(escape_like())
 * @param string $alias the ws_messages alias, '' for none
 * @return string
 */
function ws_voice_search_or($like, $alias = '')
{
    if (!ws_voice_schema_ready()) {
        return '';
    }

    return ' OR ' . (($alias !== '') ? $alias . '.' : '') . "file_id IN (SELECT v.file_id FROM ws_voice v WHERE v.status = 'done' AND v.transcript LIKE '%" . $like . "%')";
}

/**
 * Was a message found by its transcript rather than by its words? The
 * transcript in one line when the search words are in it and not in the
 * words, '' otherwise: the search's answer shows where the words were found.
 *
 * @param string $text       the message's words
 * @param string $transcript ws_voice.transcript, '' for none
 * @param string $query      the search words
 * @return string
 */
function ws_voice_found_in_transcript($text, $transcript, $query)
{
    $query = trim((string) $query);
    $heard = ws_voice_transcript_clean($transcript);

    if (($query === '') || ($heard === '') || (mb_stripos($heard, $query) === false)) {
        return '';
    }

    return (mb_stripos((string) $text, $query) === false) ? $heard : '';
}

/**
 * A message as an assistant reads it: its words with the recording's
 * transcript (ws_voice_with_transcript()), or a note when it carries a
 * recording that was not written out, with the reason when it is known
 * (ws_voice_unheard_reason()). The assistant is then asked without what was
 * said (transcripts off, the channel closed to them, the service failed) and
 * must say it could not hear it rather than guess.
 *
 * @param string $text       the message's words as the assistant reads them
 * @param string $transcript ws_voice.transcript, '' for none
 * @param string $name       the name its file was posted with, '' for none
 * @param int    $limit      characters of the transcript, 0 for all
 * @param string $reason     off | skipped | failed | pending, '' for none
 * @return string
 */
function ws_voice_heard($text, $transcript, $name, $limit = 0, $reason = '')
{
    if ((ws_voice_transcript_clean($transcript) !== '') || !ws_voice_is_recording((string) $name)) {
        return ws_voice_with_transcript($text, $transcript, $limit);
    }

    $words = ws_voice_unheard_words($reason);

    return trim($text . ' [voice message, not transcribed' . (($words !== '') ? ': ' . $words : '') . ']');
}

/* ---------------------------------------------------------------------------
   Why nobody heard a recording
   --------------------------------------------------------------------------- */

/**
 * Why an assistant does not hear a recording, by its row's status and the
 * site's and the channel's state now: '' once written out; off while
 * transcripts are switched off on the site (first, whatever the row says:
 * the switch is where it is put right); failed when the service could not
 * write it out; pending while it still waits; skipped where the channel
 * keeps its recordings, the run left it out, or it was posted before
 * transcripts were allowed.
 *
 * @param string $status  ws_voice.status ('' for no row)
 * @param bool   $enabled transcripts switched on
 * @param bool   $allowed the channel sends its recordings
 * @return string '' | off | failed | pending | skipped
 */
function ws_voice_unheard_rule($status, $enabled, $allowed)
{
    $status = (string) $status;

    if ($status === 'done') {
        return '';
    }

    if (!$enabled) {
        return 'off';
    }

    if ($status === 'failed') {
        return 'failed';
    }

    if (($status === 'queued') || ($status === 'running')) {
        return 'pending';
    }

    return 'skipped';
}

/**
 * The recorder's reason before anything is recorded: off while transcripts
 * are switched off, skipped where the channel keeps its recordings, ''
 * where a recording made now is written out.
 *
 * @param bool $enabled
 * @param bool $allowed
 * @return string
 */
function ws_voice_channel_unheard_rule($enabled, $allowed)
{
    return ($enabled && $allowed) ? '' : ws_voice_unheard_rule('', $enabled, $allowed);
}

/**
 * ws_voice_channel_unheard_rule() for a channel, for the channel screen
 * (channel.voice_unheard): the recorder tells the person that the assistants
 * will not hear what they record.
 *
 * @param array $channel
 * @return string
 */
function ws_voice_channel_unheard($channel)
{
    return ws_voice_channel_unheard_rule(ws_voice_schema_ready() && ws_voice_config()['enabled'], ws_voice_channel_allowed($channel));
}

/**
 * The longest text a sender's browser may hand in as a recording's
 * transcript, in characters: the service's own limit (ws_voice_run()).
 */
define('WS_VOICE_BROWSER_MAX_CHARS', 100000);

/**
 * Does the sender's browser write a recording out before it is posted?
 * Only where no service does (transcripts switched off on the site) and the
 * channel lets its recordings be written out (ws_voice_channel_allowed()),
 * once the schema step has run. The browser's own model writes it on the
 * device; nothing is sent to a third party (owner decision #5390).
 *
 * @param bool $schema_ready ws_voice_schema_ready()
 * @param bool $enabled      transcripts switched on on the site
 * @param bool $allowed      ws_voice_channel_allowed()
 * @return bool
 */
function ws_voice_browser_rule($schema_ready, $enabled, $allowed)
{
    return $schema_ready && !$enabled && $allowed;
}

/**
 * ws_voice_browser_rule() for a channel now: channel.voice_browser on the
 * channel screen, and the check an upload's transcript passes.
 *
 * @param array $channel
 * @return bool
 */
function ws_voice_browser_channel($channel)
{
    $ready = ws_voice_schema_ready();

    return ws_voice_browser_rule($ready, $ready && ws_voice_config()['enabled'], ws_voice_channel_allowed($channel));
}

/**
 * The transcript a sender's browser handed in with a recording, as it is
 * kept: one trimmed line without control characters, at most
 * WS_VOICE_BROWSER_MAX_CHARS characters; '' for nothing heard, a value that
 * is not text, or a file the recorder did not name (a sound somebody
 * attached is never given a text).
 *
 * @param mixed  $text
 * @param string $name the name the file was posted with
 * @return string
 */
function ws_voice_browser_transcript($text, $name)
{
    if (!is_string($text) || !ws_voice_is_recording($name)) {
        return '';
    }

    $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));

    return (mb_strlen($text) > WS_VOICE_BROWSER_MAX_CHARS) ? rtrim(mb_substr($text, 0, WS_VOICE_BROWSER_MAX_CHARS)) : $text;
}

/**
 * The state of one message's recording: status, transcript and error of its
 * ws_voice row, null for a message without one.
 *
 * @param array|null $row ws_messages row
 * @return array|null
 */
function ws_voice_state_of($row)
{
    if (!is_array($row) || ((int) ($row['file_id'] ?? 0) <= 0) || !ws_voice_schema_ready()) {
        return null;
    }

    $state = db_item("SELECT status, transcript, error FROM ws_voice WHERE file_id = '" . (int) $row['file_id'] . "'");

    return $state ? array('status' => (string) $state['status'], 'transcript' => (string) $state['transcript'], 'error' => (string) $state['error']) : null;
}

/**
 * Why an assistant does not hear this message's recording
 * (ws_voice_unheard_rule()), '' for a message that is no recording or one
 * written out.
 *
 * @param array|null $channel the message's channel
 * @param array|null $row     ws_messages row
 * @param array|null $state   ws_voice_state_of($row), read here when null
 * @return string
 */
function ws_voice_unheard_reason($channel, $row, $state = null)
{
    if (!is_array($row) || !ws_voice_is_recording((string) ($row['file_name'] ?? ''))) {
        return '';
    }

    $state = ($state === null) ? ws_voice_state_of($row) : $state;

    if ($state && ($state['status'] === 'done') && (ws_voice_transcript_clean($state['transcript']) !== '')) {
        return '';
    }

    return ws_voice_unheard_rule(
        $state ? $state['status'] : '',
        ws_voice_schema_ready() && ws_voice_config()['enabled'],
        is_array($channel) && ws_voice_channel_allowed($channel)
    );
}

/**
 * The reason in the words the model reads inside the message's note
 * (ws_voice_heard()): English, like the prompt, '' for none.
 *
 * @param string $reason
 * @return string
 */
function ws_voice_unheard_words($reason)
{
    switch ((string) $reason) {
        case 'off':
            return 'voice message transcripts are switched off on this site';
        case 'skipped':
            return 'this channel does not send its recordings to be transcribed';
        case 'failed':
            return 'the transcription service could not write it out';
        case 'pending':
            return 'it is still being written out';
    }

    return '';
}

/**
 * The line a briefing puts after the request when the request was a
 * recording nobody heard: what to answer and where the setting is. Outside
 * the data blocks, as the site's own instruction; '' for none.
 *
 * @param string $reason
 * @return string
 */
function ws_voice_unheard_line($reason)
{
    $words = ws_voice_unheard_words($reason);

    if ($words === '') {
        return '';
    }

    switch ((string) $reason) {
        case 'off':
            $place = 'an administrator switches them on under Workspace Settings › Voice message transcripts';
            break;
        case 'skipped':
            $place = 'a manager of the channel allows them in the channel menu, "Voice messages are transcribed here"';
            break;
        case 'failed':
            $place = 'the person can record it again or write it; an administrator sees the service under Workspace Settings › Voice message transcripts';
            break;
        default:
            $place = 'it will be heard once it is written out';
    }

    return 'The request is a voice message that was not written out: ' . $words . '. Answer that you could not hear it and why, and that ' . $place . '. Do not guess at what was said.';
}
