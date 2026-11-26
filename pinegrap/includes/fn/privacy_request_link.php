<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The download link of an information request's answer: the person's
 * exported data is written to the request's private folder
 * (data/privacy_exports, never web-served) and the answer e-mail carries one
 * signed link to it, valid 7 days, never the file. The link names the
 * request, the file and its end, signed with HMAC-SHA256 under the site's
 * key; nothing of the person is in it. The file is the link's state: a new
 * link writes a new file and deletes the old one, withdrawing deletes it,
 * and the job deletes a file whose end has passed. Every download is an
 * audit line of the request. Served by privacy_download.php.
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
 * How long a link works, in seconds. Pure.
 *
 * @return int
 */
function pg_privacy_link_ttl()
{
    return 7 * 86400;
}

/**
 * The folder the exported files are kept in, under data/ which the server
 * never serves.
 *
 * @return string
 */
function pg_privacy_link_dir()
{
    return PG_FUNCTIONS_DIR . '/data/privacy_exports';
}

/**
 * The stored file's name: the request, the link's end and a random part,
 * nothing of the person. Pure.
 *
 * @param int    $id
 * @param int    $expires_at
 * @param string $nonce  32 hex characters
 * @return string  '' when a part cannot be used
 */
function pg_privacy_link_file_name($id, $expires_at, $nonce)
{
    if (((int) $id <= 0) || ((int) $expires_at <= 0) || !is_string($nonce) || (preg_match('/^[0-9a-f]{32}$/', $nonce) !== 1)) {
        return '';
    }

    return (int) $id . '-' . (int) $expires_at . '-' . $nonce . '.json';
}

/**
 * A stored file's name read back. Pure.
 *
 * @param mixed $name
 * @return array|null  id, expires_at, nonce
 */
function pg_privacy_link_file_parse($name)
{
    if (!is_string($name) || (preg_match('/^([1-9][0-9]{0,9})-([1-9][0-9]{0,10})-([0-9a-f]{32})\.json$/', $name, $match) !== 1)) {
        return null;
    }

    return array('id' => (int) $match[1], 'expires_at' => (int) $match[2], 'nonce' => $match[3]);
}

/**
 * The link's signature over the request, the file and the end, under its
 * own prefix so it never matches another HMAC made with the same key. Pure.
 *
 * @param int    $id
 * @param string $nonce
 * @param int    $expires_at
 * @param string $key
 * @return string  '' when a part is missing
 */
function pg_privacy_link_signature($id, $nonce, $expires_at, $key)
{
    if ((pg_privacy_link_file_name($id, $expires_at, $nonce) === '') || ((string) $key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'privacy-request-export:' . (int) $id . ':' . $nonce . ':' . (int) $expires_at, (string) $key);
}

/**
 * The link's values from a query string. Pure.
 *
 * @param mixed $source
 * @return array|null  r (request id), e (end), n (file), s (signature)
 */
function pg_privacy_link_parse($source)
{
    if (!is_array($source)) {
        return null;
    }

    $parsed = array();

    foreach (array('r' => 10, 'e' => 11) as $name => $digits) {
        $value = $source[$name] ?? null;

        if (!is_string($value) || (preg_match('/^[1-9][0-9]{0,' . ($digits - 1) . '}$/', $value) !== 1)) {
            return null;
        }

        $parsed[$name] = (int) $value;
    }

    foreach (array('n' => 32, 's' => 64) as $name => $length) {
        $value = $source[$name] ?? null;

        if (!is_string($value) || (preg_match('/^[0-9a-f]{' . $length . '}$/', $value) !== 1)) {
            return null;
        }

        $parsed[$name] = $value;
    }

    return $parsed;
}

/**
 * Whether a link may be served: signed by this site, not past its end, for
 * an answered information request whose file is still there and whose
 * person has not been erased since (the address became a person token).
 * Every other case reads the same, so the page never tells whether a
 * request exists. Pure.
 *
 * @param array|null $parsed   pg_privacy_link_parse()
 * @param array|null $request  the row the link names: kind, status, email
 * @param string     $key
 * @param int        $now
 * @param bool       $file     the file is there
 * @return bool
 */
function pg_privacy_link_valid($parsed, $request, $key, $now, $file)
{
    if (!is_array($parsed) || !is_array($request)) {
        return false;
    }

    $expected = pg_privacy_link_signature($parsed['r'], $parsed['n'], $parsed['e'], $key);

    return ($expected !== '')
        && hash_equals($expected, (string) $parsed['s'])
        && ((int) $parsed['e'] > (int) $now)
        && ((int) $parsed['e'] <= (int) $now + pg_privacy_link_ttl())
        && (($request['kind'] ?? '') === 'information')
        && (($request['status'] ?? '') === 'answered')
        && !pg_privacy_is_person_token($request['email'] ?? '')
        && (bool) $file;
}

/**
 * The link the e-mail carries.
 *
 * @param int    $id
 * @param int    $expires_at
 * @param string $nonce
 * @param string $signature
 * @return string
 */
function pg_privacy_link_url($id, $expires_at, $nonce, $signature)
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/privacy_download.php?r=' . (int) $id . '&e=' . (int) $expires_at . '&n=' . rawurlencode((string) $nonce) . '&s=' . rawurlencode((string) $signature);
}

/**
 * The lines under the answer's text that carry the link. Pure but for
 * lang().
 *
 * @param string $url
 * @return string
 */
function pg_privacy_link_mail_lines($url)
{
    return lang('Download a copy of your personal data (the link works for 7 days):') . "\n" . $url;
}

/**
 * Where a request's link stands, from its file and its audit lines (newest
 * first): active until its end, ended, withdrawn or never made; when the
 * file was last downloaded since the link was made. Pure.
 *
 * @param array    $history     audit lines: action, created_at; newest first
 * @param int|null $expires_at  the end of the file kept, null for none
 * @param int      $now
 * @return array  state (none | active | ended | withdrawn), expires_at,
 *                downloaded_at (0 for not yet)
 */
function pg_privacy_link_state(array $history, $expires_at, $now)
{
    $downloaded = 0;
    $last = '';

    foreach ($history as $line) {
        $action = (string) ($line['action'] ?? '');

        // Downloads count back to the link they came through.
        if (($action === 'download') && ($downloaded === 0)) {
            $downloaded = (int) $line['created_at'];
        } elseif (($action === 'revoke') && ($last === '')) {
            $last = 'revoke';
        } elseif ($action === 'link') {
            $last = ($last === '') ? 'link' : $last;
            break;
        }
    }

    if (($expires_at !== null) && ((int) $expires_at > (int) $now)) {
        return array('state' => 'active', 'expires_at' => (int) $expires_at, 'downloaded_at' => $downloaded);
    }

    $state = ($last === 'revoke') ? 'withdrawn' : (($last === 'link') ? 'ended' : 'none');

    return array('state' => $state, 'expires_at' => 0, 'downloaded_at' => ($state === 'none') ? 0 : $downloaded);
}

/**
 * The files kept for a request, or for every request (id 0).
 *
 * @param int $id
 * @return array  name => pg_privacy_link_file_parse()
 */
function pg_privacy_link_files($id = 0)
{
    $files = array();
    $names = is_dir(pg_privacy_link_dir()) ? scandir(pg_privacy_link_dir()) : false;

    foreach (is_array($names) ? $names : array() as $name) {
        $parsed = pg_privacy_link_file_parse($name);

        if (($parsed !== null) && (((int) $id === 0) || ($parsed['id'] === (int) $id))) {
            $files[$name] = $parsed;
        }
    }

    return $files;
}

/**
 * The end of the file kept for a request, null for none.
 *
 * @param int $id
 * @return int|null
 */
function pg_privacy_link_expires_at($id)
{
    $end = null;

    foreach (pg_privacy_link_files($id) as $file) {
        $end = max((int) $end, $file['expires_at']);
    }

    return $end;
}

/**
 * Delete the files kept for a request: the old link ends with them. A new
 * link's file can be kept, so the earlier link ends only once the new one
 * has been sent.
 *
 * @param int    $id
 * @param string $keep  a file name not to delete, '' for none
 * @return int  files deleted
 */
function pg_privacy_link_delete($id, $keep = '')
{
    $deleted = 0;

    foreach (array_keys(pg_privacy_link_files($id)) as $name) {
        if (($keep !== '') && ($name === $keep)) {
            continue;
        }

        $deleted += @unlink(pg_privacy_link_dir() . '/' . $name) ? 1 : 0;
    }

    return $deleted;
}

/**
 * Delete the file a prepared link was written to, when its e-mail could
 * not be sent; an earlier link keeps working.
 *
 * @param array $prepared  pg_privacy_link_prepare()
 * @return void
 */
function pg_privacy_link_discard(array $prepared)
{
    if (pg_privacy_link_file_parse($prepared['name'] ?? '') !== null) {
        @unlink(pg_privacy_link_dir() . '/' . $prepared['name']);
    }
}

/**
 * Build the person's export and keep it as a new file for the request,
 * beside any earlier one: the caller deletes that once the new link has
 * been sent. The operator's rule of who may export whom holds.
 *
 * @param array $request   id, email, contact_id, user_id
 * @param array $operator  role, id
 * @return array  status (ok | empty | not_found | refused | file), url,
 *                expires_at, document, name (the file)
 */
function pg_privacy_link_prepare(array $request, array $operator)
{
    $out = array('status' => 'file', 'url' => '', 'expires_at' => 0, 'document' => null, 'name' => '');
    $built = pg_privacy_export_build(
        array('email' => (string) $request['email'], 'contact_id' => (int) $request['contact_id'], 'user_id' => (int) $request['user_id']),
        $operator,
        array('site' => pg_privacy_request_site(), 'prepared_by' => '')
    );

    if ($built['status'] !== 'ok') {
        $out['status'] = $built['status'];

        return $out;
    }

    $dir = pg_privacy_link_dir();

    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    $id = (int) $request['id'];
    $expires_at = time() + pg_privacy_link_ttl();
    $nonce = bin2hex(random_bytes(16));
    $name = pg_privacy_link_file_name($id, $expires_at, $nonce);
    $signature = pg_privacy_link_signature($id, $nonce, $expires_at, pg_fs_key());

    if (($name === '') || ($signature === '') || !is_dir($dir)) {
        return $out;
    }

    // Written beside it and renamed: a half-written file is never served.
    if ((@file_put_contents($dir . '/' . $name . '.tmp', pg_privacy_export_json($built['document'])) === false) || !@rename($dir . '/' . $name . '.tmp', $dir . '/' . $name)) {
        @unlink($dir . '/' . $name . '.tmp');

        return $out;
    }

    @chmod($dir . '/' . $name, 0600);

    return array('status' => 'ok', 'url' => pg_privacy_link_url($id, $expires_at, $nonce, $signature), 'expires_at' => $expires_at, 'document' => $built['document'], 'name' => $name);
}

/**
 * The link was made and sent: the request's audit line (its end only) and
 * the export's own line.
 *
 * @param array $request
 * @param array $prepared  pg_privacy_link_prepare()
 * @return void
 */
function pg_privacy_link_record(array $request, array $prepared)
{
    pg_audit('link', 'privacy_request', (int) $request['id'], array(), array('expires_at' => (int) $prepared['expires_at']), '');
    pg_privacy_export_log($prepared['document'], 'json');
}

/**
 * Whether a closed request's link can be sent again or withdrawn: an
 * answered information request. Pure.
 *
 * @param array $request
 * @return bool
 */
function pg_privacy_link_applies(array $request)
{
    return (($request['kind'] ?? '') === 'information') && (($request['status'] ?? '') === 'answered');
}

/**
 * "Send a new link": a fresh export, a new 7 days, the old link ends, and
 * an e-mail with the new link.
 *
 * @param array $request
 * @param array $operator  role, id
 * @return string  ok | closed | refused | empty | file | mail
 */
function pg_privacy_link_resend(array $request, array $operator)
{
    if (!pg_privacy_link_applies($request)) {
        return 'closed';
    }

    $prepared = pg_privacy_link_prepare($request, $operator);

    if ($prepared['status'] !== 'ok') {
        return in_array($prepared['status'], array('refused', 'file'), true) ? $prepared['status'] : 'empty';
    }

    $site = pg_privacy_request_site();
    $name = (string) $request['name'];
    $body = (($name !== '') ? lang(array('string' => 'Hello {var:1},', 'vars' => array($name))) : lang('Hello,')) . "\n\n"
        . lang('Here is a new link to your personal data. An earlier link no longer works.') . "\n\n"
        . pg_privacy_link_mail_lines($prepared['url']) . "\n\n"
        . lang('Kind regards,') . "\n" . $site;

    $sent = (bool) email(array(
        'to'                 => (string) $request['email'],
        'from_name'          => $site,
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => lang(array('string' => '{var:1}: your request about your personal data', 'vars' => array($site))),
        'format'             => 'plain_text',
        'body'               => $body,
        'type'               => 'system',
        'secret'             => true,
    ));

    if (!$sent) {
        pg_privacy_link_discard($prepared);

        return 'mail';
    }

    // The new link has gone: the earlier one ends with its file.
    pg_privacy_link_delete((int) $request['id'], $prepared['name']);
    pg_privacy_link_record($request, $prepared);

    return 'ok';
}

/**
 * "Withdraw the link": the file is deleted and the link ends now.
 *
 * @param array $request
 * @return string  ok | closed | none
 */
function pg_privacy_link_withdraw(array $request)
{
    if (!pg_privacy_link_applies($request)) {
        return 'closed';
    }

    if (pg_privacy_link_delete((int) $request['id']) === 0) {
        return 'none';
    }

    pg_audit('revoke', 'privacy_request', (int) $request['id'], array(), array(), '');

    return 'ok';
}

/**
 * The job's clean-up: delete every file whose link has ended.
 *
 * @param int|null $now
 * @return int  files deleted
 */
function pg_privacy_link_sweep($now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $deleted = 0;

    foreach (pg_privacy_link_files() as $name => $file) {
        if ($file['expires_at'] <= $now) {
            $deleted += @unlink(pg_privacy_link_dir() . '/' . $name) ? 1 : 0;
        }
    }

    return $deleted;
}

/**
 * The drawer's part of a closed information request: when the link ends or
 * how it ended, whether it was downloaded, "Send a new link" and, while it
 * works, "Withdraw the link".
 *
 * @param array $request
 * @param array $history  pg_privacy_request_history()
 * @param int   $now
 * @return string  '' when the request has no link
 */
function pg_privacy_link_card(array $request, array $history, $now)
{
    if (!pg_privacy_link_applies($request)) {
        return '';
    }

    $link = pg_privacy_link_state($history, pg_privacy_link_expires_at((int) $request['id']), $now);
    $time = function ($at) {
        return h(get_absolute_time(array('timestamp' => (int) $at, 'format' => 'plain_text')));
    };
    $states = array(
        'active'    => lang(array('string' => 'Works until {var:1}', 'vars' => array(get_absolute_time(array('timestamp' => $link['expires_at'], 'format' => 'plain_text'))))),
        'ended'     => lang('Ended'),
        'withdrawn' => lang('Withdrawn'),
        'none'      => lang('No link was sent'),
    );

    $html = '<h6 class="pg-title-status mt-6">' . h(lang('Download link')) . '</h6>'
        . '<dl class="pg-kv mb-4"><dt>' . h(lang('Link')) . '</dt><dd>' . h($states[$link['state']]) . '</dd>'
        . (($link['state'] !== 'none') ? '<dt>' . h(lang('Downloaded')) . '</dt><dd>' . (($link['downloaded_at'] > 0) ? $time($link['downloaded_at']) : h(lang('Not downloaded yet'))) . '</dd>' : '')
        . '</dl><div class="d-flex flex-wrap gap-3">'
        . '<button type="submit" name="action" value="link_new" class="pg-button pg-button-quiet pg-button-sm" data-confirm-content="' . h(lang('A new link valid 7 days goes to the person; an earlier link stops working.')) . '"><i class="bi bi-send" aria-hidden="true"></i>' . h(lang('Send a new link')) . '</button>'
        . (($link['state'] === 'active') ? '<button type="submit" name="action" value="link_withdraw" class="pg-button pg-button-quiet pg-button-sm" data-confirm-content="' . h(lang('The link stops working now and the file is deleted.')) . '"><i class="bi bi-x-circle" aria-hidden="true"></i>' . h(lang('Withdraw the link')) . '</button>' : '')
        . '</div>';

    return $html;
}
