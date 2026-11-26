<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// An outside juror's signed link to their reviews (application_juror.php)
// and a not eligible entry's correction link (application_fix.php). A
// juror's link is an HMAC of the reviewer row under the site's key, so the
// same link can be mailed again and copied; only its sha256 is stored. A
// correction link is random, mailed once, and only its sha256 is stored.
// Loaded with require_once by includes/fn/application_my_reviews.php, the
// programme's screen and the submitted form's screen.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The raw token of an outside juror's link: an HMAC of the reviewer row and
 * the time it was added, under the site's key.
 *
 * @param int    $reviewer_id
 * @param int    $created_at
 * @param string $key the site's ENCRYPTION_KEY
 * @return string 64 hex characters, '' without a row or a key
 */
function pg_application_juror_token($reviewer_id, $created_at, $key)
{
    $reviewer_id = (int) $reviewer_id;
    $key = (string) $key;

    if (($reviewer_id <= 0) || ($key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'application-juror:' . $reviewer_id . ':' . (int) $created_at, $key);
}

/**
 * Whether a link's token matches a stored hash.
 *
 * @param string $token  from the link
 * @param string $stored sha256 of the token
 * @return bool
 */
function pg_application_link_token_valid($token, $stored)
{
    $token = (string) $token;
    $stored = (string) $stored;

    if ((preg_match('/^[0-9a-f]{64}$/', $token) !== 1) || (strlen($stored) !== 64)) {
        return false;
    }

    return hash_equals($stored, hash('sha256', $token));
}

/**
 * Whether a programme's current round is over for its reviewers: the
 * programme is closed and the round's end, when one is set, has passed.
 * Closing alone stops the entries, not the reviewing, so a round with no
 * end set runs until the programme moves on.
 *
 * @param int $status  the programme's status
 * @param int $ends_at the current round's end, 0 for none
 * @param int $now
 * @return bool
 */
function pg_application_round_over($status, $ends_at, $now)
{
    return ((int) $status === 2) && ((int) $ends_at > 0) && ((int) $now >= (int) $ends_at);
}

/**
 * Where an outside juror's link stands: 'invalid' (no such link),
 * 'withdrawn' (the juror was removed), 'expired' (the programme is closed
 * and its current round has ended, so nothing can be scored any more,
 * pg_application_round_over()) or 'open'.
 *
 * @param array|null $reviewer application_reviewers row, null when not found
 * @param int        $status   the programme's status
 * @param int        $ends_at  the current round's end, 0 for none
 * @param int        $now
 * @return string
 */
function pg_application_juror_link_state($reviewer, $status, $ends_at, $now)
{
    if (!is_array($reviewer) || ((int) ($reviewer['user_id'] ?? 0) !== 0)) {
        return 'invalid';
    }

    if ((int) ($reviewer['revoked_at'] ?? 0) > 0) {
        return 'withdrawn';
    }

    if (pg_application_round_over($status, $ends_at, $now)) {
        return 'expired';
    }

    return 'open';
}

/**
 * The address of an outside juror's page; its hash is stored on the row the
 * first time (a juror added before the link existed has none).
 *
 * @param array $reviewer application_reviewers row: id, user_id, created_at,
 *                        token_hash
 * @return string '' for a panel user or a site without a key
 */
function pg_application_juror_url(array $reviewer)
{
    $token = ((int) $reviewer['user_id'] === 0) ? pg_application_juror_token($reviewer['id'], $reviewer['created_at'], pg_booking_key()) : '';

    if ($token === '') {
        return '';
    }

    if ((string) ($reviewer['token_hash'] ?? '') !== hash('sha256', $token)) {
        db_q("UPDATE application_reviewers SET token_hash = ? WHERE id = ? AND user_id = 0", array(hash('sha256', $token), (int) $reviewer['id']));
    }

    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/application_juror.php?t=' . $token;
}

/**
 * The reviewer row a juror's link opens, removed ones too (their page says
 * so); null for none.
 *
 * @param string $token
 * @return array|null
 */
function pg_application_juror_find($token)
{
    $token = (string) $token;

    if ((preg_match('/^[0-9a-f]{64}$/', $token) !== 1) || !pg_application_program_ready()) {
        return null;
    }

    $rows = _pg_application_rows("SELECT * FROM application_reviewers WHERE token_hash = ? AND user_id = 0", array(hash('sha256', $token)));

    return ($rows && pg_application_link_token_valid($token, $rows[0]['token_hash'])) ? $rows[0] : null;
}

/**
 * Mails an outside juror their link.
 *
 * @param array $program
 * @param array $reviewer application_reviewers row
 * @return bool false for a panel user, a bad address or no link
 */
function pg_application_juror_send(array $program, array $reviewer)
{
    $url = pg_application_juror_url($reviewer);

    if (($url === '') || !validate_email_address((string) $reviewer['email'])) {
        return false;
    }

    $body = lang(array('string' => 'Hello {var:1},', 'vars' => array((string) $reviewer['name']))) . "\n\n"
        . lang(array('string' => 'You are a reviewer of "{var:1}". Your entries to score open with this link. It needs no sign-in and is yours alone: do not pass it on.', 'vars' => array($program['name']))) . "\n\n"
        . $url;

    email(array(
        'to'                 => (string) $reviewer['email'],
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => lang(array('string' => '{var:1}: your link to review entries', 'vars' => array($program['name']))),
        'format'             => 'plain_text',
        'body'               => $body,
        'type'               => 'system',
        'queue'              => true,
    ));
    pg_audit('juror_link', 'application_program', (int) $program['id'], array(), array('reviewer_id' => (int) $reviewer['id']), $program['name']);

    return true;
}

/**
 * One reviewer row of a programme, still in it.
 *
 * @param array $program
 * @param int   $reviewer_id
 * @return array|null
 */
function pg_application_reviewer_get(array $program, $reviewer_id)
{
    $rows = _pg_application_rows("SELECT * FROM application_reviewers WHERE id = ? AND program_id = ? AND revoked_at = 0", array((int) $reviewer_id, (int) $program['id']));

    return $rows ? $rows[0] : null;
}

/**
 * Who a reminder goes to and the link it carries: a panel user's own
 * address and "My reviews", an outside juror's address and their page.
 *
 * @param array  $row       user_id, user_email, email, juror_url
 * @param string $my_reviews the address of my_reviews.php
 * @return array array('to', 'link'); 'to' is '' when there is nobody to mail
 */
function pg_application_reminder_recipient(array $row, $my_reviews)
{
    if ((int) $row['user_id'] > 0) {
        return array('to' => (string) ($row['user_email'] ?? ''), 'link' => (string) $my_reviews);
    }

    $link = (string) ($row['juror_url'] ?? '');

    return array('to' => ($link !== '') ? (string) $row['email'] : '', 'link' => $link);
}

/**
 * The fields a correction link opens: those the entry's eligibility reasons
 * name, each once, in the reasons' order.
 *
 * @param array $reasons pg_application_eligibility() reasons
 * @return array list of field names
 */
function pg_application_fix_fields(array $reasons)
{
    $fields = array();

    foreach ($reasons as $reason) {
        $field = is_array($reason) ? (string) ($reason['field'] ?? '') : '';

        if (($field !== '') && !in_array($field, $fields, true)) {
            $fields[] = $field;
        }
    }

    return $fields;
}

/**
 * Where a correction link stands: 'invalid', 'expired' (the programme no
 * longer takes entries: it closed, or its closing time has passed), 'done'
 * (the entry is no longer "not eligible") or 'open'.
 *
 * @param array|null $entry     application_entries row, null when not found
 * @param int        $status    the programme's status
 * @param int        $closes_at the programme's closing time
 * @param int        $now
 * @return string
 */
function pg_application_fix_link_state($entry, $status, $closes_at, $now)
{
    if (!is_array($entry)) {
        return 'invalid';
    }

    if ((int) $entry['eligible'] !== 2) {
        return 'done';
    }

    if (((int) $status !== 1) || (((int) $closes_at > 0) && ((int) $now >= (int) $closes_at))) {
        return 'expired';
    }

    return 'open';
}

/**
 * Mails a not eligible entry's submitter a correction link for the fields
 * its reasons name. A new link replaces the one sent before.
 *
 * @param array $application pg_application_entry_for_form()
 * @return string '' when sent, else what stopped it, in words
 */
function pg_application_fix_send(array $application)
{
    $entry = $application['entry'];
    $program = $application['program'];
    $fields = pg_application_fix_fields($entry['note']['reasons'] ?? array());

    if (pg_application_fix_link_state($entry, $program['status'], $program['closes_at'], time()) !== 'open' || !$fields) {
        return lang('A correction link is sent only for a not eligible entry while the programme takes entries.');
    }

    $to = (string) get_submitter_email_address((int) $entry['form_id']);

    if (!validate_email_address($to)) {
        return lang('The entry has no e-mail address to send the link to.');
    }

    $token = bin2hex(random_bytes(32));
    $ok = db_q("UPDATE application_entries SET fix_token_hash = ?, fix_fields = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ? AND eligible = 2", array(hash('sha256', $token), json_encode($fields, JSON_UNESCAPED_UNICODE), (int) $entry['id']));

    if (!$ok || (mysqli_affected_rows(db::$con) < 1)) {
        return lang('The correction link could not be made.');
    }

    $labels = array();
    $form_fields = pg_application_program_fields($program['page_id']);

    foreach ($fields as $field) {
        $labels[] = '- ' . ((($form_fields[$field]['label'] ?? '') !== '') ? $form_fields[$field]['label'] : $field);
    }

    $until = ((int) $program['closes_at'] > 0) ? ' ' . lang(array('string' => 'The link works until {var:1}.', 'vars' => array(get_absolute_time(array('timestamp' => (int) $program['closes_at'], 'format' => 'plain_text'))))) : '';

    email(array(
        'to'                 => $to,
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => lang(array('string' => '{var:1}: please correct your entry', 'vars' => array($program['name']))),
        'format'             => 'plain_text',
        'body'               => lang(array('string' => 'Your entry to "{var:1}" does not meet the programme\'s rules yet. Correct these answers with the link below:', 'vars' => array($program['name']))) . "\n\n"
            . implode("\n", $labels) . "\n\n"
            . URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/application_fix.php?t=' . $token . "\n\n" . trim($until),
        'type'               => 'system',
        'queue'              => true,
    ));
    pg_audit('fix_link', 'application_entry', (int) $entry['id'], array(), array('fields' => $fields));

    return '';
}

/**
 * The entry and programme a correction link opens; null for none.
 *
 * @param string $token
 * @return array|null array('entry', 'program', 'fields' => list of names)
 */
function pg_application_fix_find($token)
{
    $token = (string) $token;

    if ((preg_match('/^[0-9a-f]{64}$/', $token) !== 1) || !pg_application_program_ready()) {
        return null;
    }

    $rows = _pg_application_rows("SELECT * FROM application_entries WHERE fix_token_hash = ?", array(hash('sha256', $token)));
    $program = $rows ? pg_application_program_get($rows[0]['program_id']) : null;

    if (!$rows || ($program === null) || !pg_application_link_token_valid($token, $rows[0]['fix_token_hash'])) {
        return null;
    }

    $fields = json_decode((string) $rows[0]['fix_fields'], true);

    return array('entry' => $rows[0], 'program' => $program, 'fields' => is_array($fields) ? array_values(array_map('strval', $fields)) : array());
}

/**
 * The form fields a correction link opens, with their choices and the
 * answers stored now.
 *
 * @param array $fix pg_application_fix_find()
 * @return array list of array('id', 'name', 'label', 'type', 'upload_folder_id',
 *               'options' => value => label, 'values' => list, 'file' => name)
 */
function pg_application_fix_form_fields(array $fix)
{
    $fields = array();

    foreach (_pg_application_rows("SELECT id, name, label, type, upload_folder_id FROM form_fields WHERE page_id = ? AND type != 'information' ORDER BY sort_order", array((int) $fix['program']['page_id'])) as $row) {
        if (!in_array((string) $row['name'], $fix['fields'], true)) {
            continue;
        }

        $row['options'] = array();

        foreach (_pg_application_rows("SELECT label, value FROM form_field_options WHERE form_field_id = ? ORDER BY sort_order, id", array((int) $row['id'])) as $option) {
            $row['options'][(string) $option['value']] = ((string) $option['label'] !== '') ? (string) $option['label'] : (string) $option['value'];
        }

        $row['values'] = array();
        $row['file'] = '';

        foreach (_pg_application_rows("SELECT form_data.data, files.name AS file_name FROM form_data LEFT JOIN files ON files.id = form_data.file_id AND form_data.file_id > 0 WHERE form_data.form_id = ? AND form_data.form_field_id = ? ORDER BY form_data.id", array((int) $fix['entry']['form_id'], (int) $row['id'])) as $data) {
            if ((string) ($data['file_name'] ?? '') !== '') {
                $row['file'] = (string) $data['file_name'];
            } elseif ((string) $data['data'] !== '') {
                $row['values'][] = (string) $data['data'];
            }
        }

        $fields[] = $row;
    }

    return $fields;
}

/**
 * The answers posted on the correction page, field id => list of values;
 * a choice not among the field's options is dropped, and an empty answer
 * is no answer. A file is not read here.
 *
 * @param array $fields pg_application_fix_form_fields()
 * @param array $post   field id => value or list of values
 * @return array
 */
function pg_application_fix_input(array $fields, array $post)
{
    $answers = array();

    foreach ($fields as $field) {
        if ((string) $field['type'] === 'file upload') {
            continue;
        }

        $id = (int) $field['id'];
        $values = array();

        foreach ((array) ($post[$id] ?? array()) as $value) {
            $value = is_string($value) ? mb_substr(trim($value), 0, 1000) : '';

            if (($value !== '') && (!$field['options'] || isset($field['options'][$value]))) {
                $values[] = $value;
            }
        }

        $answers[$id] = array_values(array_unique($values));
    }

    return $answers;
}

/**
 * Writes the corrected answers, stores an uploaded file, checks the entry
 * again against the programme's rules, and spends nothing: the link stays
 * until the close, so a still failing entry can be corrected again.
 *
 * @param array $fix     pg_application_fix_find(), open
 * @param array $fields  pg_application_fix_form_fields()
 * @param array $answers pg_application_fix_input()
 * @param array $files   $_FILES
 * @return array array('ok' => bool, 'eligible' => bool, 'error' => words)
 */
function pg_application_fix_save(array $fix, array $fields, array $answers, array $files)
{
    $form_id = (int) $fix['entry']['form_id'];

    foreach ($fields as $field) {
        $id = (int) $field['id'];

        if ((string) $field['type'] === 'file upload') {
            if (empty($files[$id]['name']) || !is_string($files[$id]['name'])) {
                continue;
            }

            $file_name = get_unique_name(array('name' => prepare_file_name($files[$id]['name']), 'type' => 'file'));

            if (!pg_cf_place_upload($files[$id], FILE_DIRECTORY_PATH . '/' . $file_name)) {
                return array('ok' => false, 'eligible' => false, 'error' => lang(array('string' => 'The server could not accept {var:1}. Please try again.', 'vars' => pg_basename((string) $files[$id]['name']))));
            }

            $dot = mb_strrpos($file_name, '.');
            db_q("INSERT INTO files (name, folder, type, size, user, design, attachment, timestamp) VALUES (?, ?, ?, ?, 0, '0', '1', UNIX_TIMESTAMP())", array(
                $file_name, (string) $field['upload_folder_id'], ($dot !== false) ? mb_substr($file_name, $dot + 1) : '', (string) (int) ($files[$id]['size'] ?? 0)));
            $file_id = (int) mysqli_insert_id(db::$con);
            db_q("DELETE FROM form_data WHERE form_id = ? AND form_field_id = ?", array($form_id, $id));
            db_q("INSERT INTO form_data (form_id, form_field_id, file_id, data, name, type) VALUES (?, ?, ?, '', ?, 'standard')", array($form_id, $id, $file_id, (string) $field['name']));
            continue;
        }

        if (!isset($answers[$id])) {
            continue;
        }

        $type = in_array($field['type'], array('date', 'date and time', 'time'), true) ? $field['type'] : 'standard';
        db_q("DELETE FROM form_data WHERE form_id = ? AND form_field_id = ?", array($form_id, $id));

        foreach ($answers[$id] as $value) {
            db_q("INSERT INTO form_data (form_id, form_field_id, file_id, data, name, type) VALUES (?, ?, 0, ?, ?, ?)", array($form_id, $id, prepare_form_data_for_input($value, $field['type']), (string) $field['name'], $type));
        }
    }

    db_q("UPDATE forms SET last_modified_timestamp = UNIX_TIMESTAMP() WHERE id = ?", array($form_id));

    $answers_now = pg_application_entry_answers(_pg_application_rows("SELECT name, data, file_id FROM form_data WHERE form_id = ? ORDER BY id", array($form_id)));
    $check = pg_application_eligibility($fix['program']['eligibility'], $answers_now);
    $note = $check['eligible'] ? null : json_encode(array('reasons' => $check['reasons']), JSON_UNESCAPED_UNICODE);
    db_q("UPDATE application_entries SET eligible = ?, eligibility_note = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ? AND eligible = 2", array($check['eligible'] ? 1 : 2, $note, (int) $fix['entry']['id']));
    pg_audit('fix', 'application_entry', (int) $fix['entry']['id'], array('eligible' => 2), array('eligible' => $check['eligible'] ? 1 : 2));

    return array('ok' => true, 'eligible' => $check['eligible'], 'error' => '');
}

/**
 * The files posted as answer[<field id>], PHP's nested $_FILES shape turned
 * into field id => one $_FILES entry.
 *
 * @param mixed $files $_FILES['answer']
 * @return array
 */
function pg_application_fix_files($files)
{
    $by_field = array();

    if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
        return $by_field;
    }

    foreach ($files['name'] as $id => $name) {
        if (!is_string($name)) {
            continue;
        }

        $entry = array();

        foreach (array('name', 'type', 'tmp_name', 'error', 'size') as $key) {
            $entry[$key] = $files[$key][$id] ?? null;
        }

        $by_field[(int) $id] = $entry;
    }

    return $by_field;
}
