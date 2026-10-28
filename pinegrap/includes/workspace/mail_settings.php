<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the mailboxes card of the settings screen
 * (workspace_settings.php): a mailbox's name, address, channel and the
 * server whose sender checks it believes, and an .eml file brought in by
 * hand, which is how a box is tried before it reads a mailbox of its own.
 * The ingest is mail.php.
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
 * Are the mailbox tables there (the 2026.11.0 upgrade has run)?
 *
 * @return bool
 */
function ws_mail_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_mail_boxes') && pg_schema_has('ws_mail_conversations') && pg_schema_has('ws_mail_items');
    }

    return $ready;
}

/**
 * A mailbox form as posted, checked and cleaned. Pure: whether the channel
 * may be chosen is asked by the caller.
 *
 * @param array $input name, address, channel_id, auth_server, enabled
 * @return array ok, error, values (name, address, channel_id, auth_server, enabled)
 */
function ws_mail_box_input($input)
{
    $name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', (string) ($input['name'] ?? '')));
    $address = strtolower(trim((string) ($input['address'] ?? '')));
    $auth_server = strtolower(trim((string) ($input['auth_server'] ?? '')));
    $values = array(
        'name'        => $name,
        'address'     => $address,
        'channel_id'  => (int) ($input['channel_id'] ?? 0),
        'auth_server' => $auth_server,
        'enabled'     => !empty($input['enabled']) ? 1 : 0,
    );
    $fail = function ($error) use ($values) {
        return array('ok' => false, 'error' => $error, 'values' => $values);
    };

    if (($name === '') || (mb_strlen($name) > 100)) {
        return $fail(lang('Give the mailbox a name of at most 100 characters.'));
    }

    if ((strlen($address) > 255) || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
        return $fail(lang('Enter the mailbox\'s e-mail address.'));
    }

    if ($values['channel_id'] <= 0) {
        return $fail(lang('Choose the channel its mail goes to.'));
    }

    if (($auth_server !== '') && ((strlen($auth_server) > 255) || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $auth_server))) {
        return $fail(lang('The checking server is a host name such as mx.google.com.'));
    }

    return array('ok' => true, 'error' => '', 'values' => $values);
}

/**
 * The channels a mailbox may write into for this person: the open ones they
 * read, never a guest room or a discussion.
 *
 * @param array $viewer
 * @return array[] id => channel row
 */
function ws_mail_box_channels($viewer)
{
    $out = array();

    foreach (ws_channels_for($viewer) as $channel) {
        if (in_array((string) $channel['kind'], array('public', 'private'), true)) {
            $out[(int) $channel['id']] = $channel;
        }
    }

    return $out;
}

/**
 * What the card says after an .eml was brought in.
 *
 * @param array $result ws_mail_ingest()
 * @return array notice (bool), text
 */
function ws_mail_ingest_message($result)
{
    switch ((string) $result['status']) {
        case 'stored':
            return array('notice' => true, 'text' => lang('The e-mail was brought into its channel.'));

        case 'duplicate':
            return array('notice' => true, 'text' => lang('This e-mail is already in the mailbox.'));

        case 'loop':
            return array('notice' => false, 'text' => lang('This e-mail was not brought in: it was sent by this site or automatically, or to a list.'));

        case 'held':
            return array('notice' => false, 'text' => lang(array('string' => 'This sender has sent more than {var:1} e-mails in the last hour; try again later.', 'vars' => WS_MAIL_SENDER_HOURLY)));
    }

    $reasons = array(
        'too_large'  => lang('The e-mail is larger than 25 MB.'),
        'no_sender'  => lang('The e-mail has no sender address.'),
        'no_channel' => lang('The mailbox\'s channel is missing or in the archive.'),
    );

    return array('notice' => false, 'text' => $reasons[(string) $result['reason']] ?? lang('The e-mail could not be saved.'));
}

/**
 * Saves the card: a mailbox added or changed (mail_box_save), or an .eml
 * brought into one (mail_eml).
 *
 * @param array    $viewer
 * @param string   $action
 * @param liveform $liveform
 */
function ws_mail_settings_post($viewer, $action, $liveform)
{
    if (!ws_mail_ready()) {
        $liveform->add_error(lang('The database has not been upgraded yet.'));
        return;
    }

    $username = (string) ($_SESSION['sessionusername'] ?? '');
    $box_id = (int) ($_POST['box_id'] ?? 0);
    $box = ($box_id > 0) ? ws_mail_box($box_id) : null;

    if (($box_id > 0) && !$box) {
        $liveform->add_error(lang('That mailbox could not be found.'));
        return;
    }

    if ($action === 'mail_eml') {
        $file = $_FILES['eml'] ?? null;

        if (!$box || !is_array($file) || is_array($file['error']) || ((int) $file['error'] !== UPLOAD_ERR_OK) || !is_uploaded_file((string) $file['tmp_name'])) {
            $liveform->add_error(lang('Choose an .eml file to bring in.'));
            return;
        }

        $result = ws_mail_ingest((string) file_get_contents((string) $file['tmp_name']), $box);
        $said = ws_mail_ingest_message($result);

        if ($said['notice']) {
            $liveform->add_notice($said['text']);
        } else {
            $liveform->add_error($said['text']);
        }

        if ($result['status'] === 'stored') {
            log_activity(lang(array('string' => 'an e-mail was brought into the mailbox {var:1} by hand', 'vars' => (string) $box['name'])), $username);
        }

        return;
    }

    $checked = ws_mail_box_input($_POST);

    if (!$checked['ok']) {
        $liveform->add_error($checked['error']);
        return;
    }

    $values = $checked['values'];
    $channels = ws_mail_box_channels($viewer);

    // A box keeps a channel its editor cannot read, as long as nobody picks
    // another one.
    if (!isset($channels[$values['channel_id']]) && !($box && ((int) $box['channel_id'] === $values['channel_id']))) {
        $liveform->add_error(lang('Choose the channel its mail goes to.'));
        return;
    }

    $settings = $box ? json_decode((string) $box['settings'], true) : null;
    $settings = is_array($settings) ? $settings : array();
    $settings['auth_server'] = $values['auth_server'];
    $now = time();

    $set = "name = '" . e($values['name']) . "', address = '" . e($values['address']) . "', channel_id = '" . (int) $values['channel_id'] . "',
        settings = '" . e(json_encode($settings)) . "', enabled = '" . (int) $values['enabled'] . "', updated_at = '" . $now . "'";

    if ($box) {
        db("UPDATE ws_mail_boxes SET " . $set . " WHERE id = '" . $box_id . "'");
        log_activity(lang(array('string' => 'the mailbox {var:1} was changed', 'vars' => $values['name'])), $username);
    } else {
        db("INSERT INTO ws_mail_boxes SET " . $set . ", created_by = '" . (int) $viewer['id'] . "', created_at = '" . $now . "'");
        log_activity(lang(array('string' => 'the mailbox {var:1} was added', 'vars' => $values['name'])), $username);
    }

    $liveform->add_notice(lang('The mailbox was saved.'));
}

/**
 * One mailbox's form, empty for a new one.
 *
 * @param string     $self_url
 * @param array|null $box
 * @param array[]    $channels ws_mail_box_channels()
 * @return string
 */
function ws_mail_box_form($self_url, $box, $channels)
{
    $id = $box ? (int) $box['id'] : 0;
    $prefix = 'ws_mail_' . $id . '_';
    $settings = $box ? json_decode((string) $box['settings'], true) : null;
    $channel_id = $box ? (int) $box['channel_id'] : 0;
    $options = '<option value="">' . h(lang('Choose')) . '</option>';

    if ($box && ($channel_id > 0) && !isset($channels[$channel_id])) {
        $options .= '<option value="' . $channel_id . '" selected>' . h(lang('A channel you do not see')) . '</option>';
    }

    foreach ($channels as $channel) {
        $options .= '<option value="' . (int) $channel['id'] . '"' . (((int) $channel['id'] === $channel_id) ? ' selected' : '') . '>'
            . h('#' . $channel['name']) . '</option>';
    }

    return '
        <form method="post" action="' . h($self_url) . '#ws-mail" class="py-5">
            ' . get_token_field() . '
            <input type="hidden" name="ws_action" value="mail_box_save">
            <input type="hidden" name="box_id" value="' . $id . '">
            <div class="row g-5">
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'name">' . h(lang('Name')) . '</label>
                    <input class="form-control form-control-sm" type="text" name="name" id="' . $prefix . 'name" maxlength="100" value="' . h($box ? (string) $box['name'] : '') . '" placeholder="' . h(lang('Support')) . '">
                </div>
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'address">' . h(lang('E-mail address')) . '</label>
                    <input class="form-control form-control-sm" type="email" name="address" id="' . $prefix . 'address" maxlength="255" value="' . h($box ? (string) $box['address'] : '') . '" placeholder="support@example.com">
                </div>
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'channel">' . h(lang('Channel')) . '</label>
                    <select class="form-control form-control-sm" name="channel_id" id="' . $prefix . 'channel">' . $options . '</select>
                    <div class="form-text">' . h(lang('Each new conversation becomes a card here; its answers go under the card.')) . '</div>
                </div>
                <div class="md:col-6">
                    <label class="form-label" for="' . $prefix . 'auth">' . h(lang('Checking server')) . '</label>
                    <input class="form-control form-control-sm font-monospace" type="text" name="auth_server" id="' . $prefix . 'auth" maxlength="255" value="' . h(is_array($settings) ? (string) ($settings['auth_server'] ?? '') : '') . '" placeholder="mx.google.com" autocomplete="off">
                    <div class="form-text">' . h(lang('The server that receives this mailbox\'s mail and writes Authentication-Results. Only its verdict is believed; left empty, every sender stays unverified and no mail is tied to a customer.')) . '</div>
                </div>
                <div class="md:col-12">
                    <div class="form-field">
                        <div class="switch"><input type="checkbox" name="enabled" value="1" id="' . $prefix . 'enabled"' . ((!$box || (int) $box['enabled'] === 1) ? ' checked' : '') . '></div>
                        <label for="' . $prefix . 'enabled">' . h(lang('On')) . '</label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5 mt-5"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h(lang('Save')) . '</button>
        </form>';
}

/**
 * The mailboxes card: each box with its channel and last mail, its form,
 * and the .eml upload.
 *
 * @param string $self_url
 * @param array  $viewer
 * @return string
 */
function ws_mail_settings_card($self_url, $viewer)
{
    if (!ws_mail_ready()) {
        return '';
    }

    $channels = ws_mail_box_channels($viewer);
    $rows = '';

    foreach ((array) db_items("SELECT b.*, (SELECT MAX(i.created_at) FROM ws_mail_items i WHERE i.box_id = b.id) AS last_mail_at
        FROM ws_mail_boxes b ORDER BY b.name") as $box) {
        $id = (int) $box['id'];
        $channel = $channels[(int) $box['channel_id']] ?? null;

        $rows .= '
            <div class="ws-dept-row' . ((int) $box['enabled'] === 1 ? '' : ' opacity-50') . '">
                <div class="ws-grow">
                    <div class="fw-semibold">' . h((string) $box['name']) . ' <span class="small fg-secondary">' . h((string) $box['address']) . '</span></div>
                    <div class="small fg-secondary">'
                        . ($channel ? '<a href="workspace.php?channel=' . (int) $channel['id'] . '">#' . h((string) $channel['name']) . '</a>' : h(lang('A channel you do not see')))
                        . ' · ' . h(((int) $box['last_mail_at'] > 0) ? lang(array('string' => 'Last e-mail {var:1}', 'vars' => date('d.m.Y H:i', (int) $box['last_mail_at']))) : lang('No e-mail yet')) . '
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-toggle="collapse" data-bs-target="#ws-mail-' . $id . '" aria-expanded="false"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . h(lang('Edit')) . '</button>
            </div>
            <div class="collapse" id="ws-mail-' . $id . '">
                ' . ws_mail_box_form($self_url, $box, $channels) . '
                <form method="post" action="' . h($self_url) . '#ws-mail" enctype="multipart/form-data" class="pb-5 d-flex flex-wrap align-items-end gap-3">
                    ' . get_token_field() . '
                    <input type="hidden" name="ws_action" value="mail_eml">
                    <input type="hidden" name="box_id" value="' . $id . '">
                    <div>
                        <label class="form-label" for="ws_mail_eml_' . $id . '">' . h(lang('Bring in an .eml file')) . '</label>
                        <input class="form-control form-control-sm" type="file" name="eml" id="ws_mail_eml_' . $id . '" accept=".eml,message/rfc822">
                    </div>
                    <button type="submit" class="btn btn-sm btn-text theme-primary"><i class="bi bi-upload me-1" aria-hidden="true"></i>' . h(lang('Bring in')) . '</button>
                </form>
            </div>';
    }

    if ($rows === '') {
        $rows = '<div class="ws-empty py-5"><i class="bi bi-envelope" aria-hidden="true"></i>' . h(lang('No mailboxes yet. A mailbox brings the e-mail sent to an address into a channel, where the team talks it over.')) . '</div>';
    }

    return '
        <div class="card mt-7" id="ws-mail">
            <div class="card-header d-flex align-items-center gap-3">
                <h2 class="h6 mb-0 ws-grow"><i class="bi bi-envelope me-1" aria-hidden="true"></i>' . h(lang('Mailboxes')) . '</h2>
                <button type="button" class="btn btn-sm btn-text theme-primary" data-bs-toggle="collapse" data-bs-target="#ws-mail-new" aria-expanded="false"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('Add a mailbox')) . '</button>
            </div>
            <div class="card-body">
                <div class="collapse" id="ws-mail-new">' . ws_mail_box_form($self_url, null, $channels) . '</div>
                ' . $rows . '
                <div class="form-text mt-5">' . h(lang('Reading a mailbox by itself comes later; until then an .eml file saved from a mail program can be brought in by hand.')) . '</div>
            </div>
        </div>';
}
