<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: a person's own e-mail signature (My e-mail settings on the
 * E-mails screen). Plain text under the rules of a mailbox's Signature
 * (ws_mail_signature_input()), kept in ws_profiles.mail_signature. In every
 * mail the person sends from a mailbox it stands where their name stands,
 * the box's Signature (or its name) under it; it is read when the mail
 * leaves, so Send again carries the signature of that moment.
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
 * Has the database the column of a person's own signature (2026.11.0,
 * 11.0.202)? Before it, every mail keeps the writer's name.
 *
 * @return bool
 */
function ws_mail_own_signature_ready()
{
    return pg_schema_has('ws_profiles', 'mail_signature');
}

/**
 * The lines that stand under "-- " for the writer: their own signature, or
 * their name when they have none. Pure.
 *
 * @param string      $name the writer's name
 * @param string|null $own  their own signature
 * @return string
 */
function ws_mail_writer_lines($name, $own)
{
    $own = trim(str_replace(array("\r\n", "\r"), "\n", (string) $own), "\n ");

    return ($own !== '') ? $own : trim((string) $name);
}

/**
 * A person's own signature, '' when they have none or the column is not
 * there yet.
 *
 * @param int $user_id
 * @return string
 */
function ws_mail_own_signature($user_id)
{
    if (((int) $user_id <= 0) || !ws_mail_own_signature_ready()) {
        return '';
    }

    return (string) db_value("SELECT mail_signature FROM ws_profiles WHERE user_id = '" . (int) $user_id . "'");
}

/**
 * Keeps a person's own signature, cleaned and checked as a mailbox's
 * Signature is; an empty one is kept as NULL (none). A person with no
 * workspace profile yet gets one, its other fields at their defaults.
 *
 * @param array $viewer
 * @param mixed $value
 * @return array ok, error, signature
 */
function ws_mail_own_signature_save($viewer, $value)
{
    if (!ws_mail_own_signature_ready()) {
        return array('ok' => false, 'error' => lang('The database has not been upgraded yet.'), 'signature' => '');
    }

    $input = ws_mail_signature_input($value);

    if (!$input['ok']) {
        return $input;
    }

    $empty = ($input['signature'] === '');
    $values = $empty ? array((int) $viewer['id'], time()) : array((int) $viewer['id'], $input['signature'], time());

    db_q("INSERT INTO ws_profiles (user_id, mail_signature, updated_at)
        VALUES (?, " . ($empty ? 'NULL' : '?') . ", ?)
        ON DUPLICATE KEY UPDATE mail_signature = VALUES(mail_signature)", $values);

    return $input;
}

/**
 * The words of My e-mail settings for the screen script.
 *
 * @return array
 */
function ws_mail_signature_js_strings()
{
    return array(
        'mail_my_settings'          => lang('My e-mail settings'),
        'mail_my_signature'         => lang('My signature'),
        'mail_my_signature_hint'    => lang('Plain text that stands in place of your name under every e-mail you send; the mailbox\'s signature comes under it. Empty, your name stands there.'),
        'mail_my_signature_limits'  => lang(array('string' => 'At most {var:1} characters and {var:2} lines.', 'vars' => array(WS_MAIL_SIGNATURE_MAX, WS_MAIL_SIGNATURE_LINES))),
        'mail_my_signature_preview' => lang('Under an e-mail it reads:'),
        'mail_my_signature_box'     => lang('The mailbox\'s signature'),
        'mail_my_settings_save'     => lang('Save'),
        'mail_my_settings_saved'    => lang('Your e-mail settings are saved.'),
    );
}
