<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: "I am away" under My e-mail settings. A person keeps a first
 * and a last day, a text and an optional stand-in in ws_profiles.mail_away
 * (JSON). In that range, in their own time zone, a mail arriving in an
 * ongoing conversation they hold gets the text once per sender as an
 * automatic answer, sent the way a box's automatic answer is (never to a
 * forged sender; the loop gate and the hold have stopped automatic mail,
 * bounces and floods before the ingest gets here). A conversation given to
 * them by hand, the API or a rule goes to the stand-in, where the stand-in
 * may answer. The range stands on My calendar as an all-day plan the whole
 * team sees, kept through personal_plans.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The longest range, in days. */
if (!defined('WS_MAIL_AWAY_DAYS')) {
    define('WS_MAIL_AWAY_DAYS', 366);
}

/**
 * Has the database the column (2026.11.0, 11.0.211)? Before it nobody is
 * away and every mail comes in as it did.
 *
 * @return bool
 */
function ws_mail_away_ready()
{
    return pg_schema_has('ws_profiles', 'mail_away');
}

/**
 * A day written Y-m-d, or '' when it is no day. Pure.
 *
 * @param mixed $value
 * @return string
 */
function ws_mail_away_day($value)
{
    $value = is_scalar($value) ? trim((string) $value) : '';

    return (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) ? $value : '';
}

/**
 * Checks the drawer's fields. No first day switches it off. Pure.
 *
 * @param array  $data  start, end ('' = the first day), text, stand_in
 * @param string $today the person's today, Y-m-d
 * @return array ok, error, field, away (start, end, text, stand_in) or null
 */
function ws_mail_away_input($data, $today)
{
    $fail = function ($field, $error) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'away' => null);
    };
    $raw_start = is_scalar($data['start'] ?? '') ? trim((string) ($data['start'] ?? '')) : '';

    if ($raw_start === '') {
        return array('ok' => true, 'error' => '', 'field' => '', 'away' => null);
    }

    $start = ws_mail_away_day($raw_start);

    if ($start === '') {
        return $fail('start', lang('Choose the first day you are away.'));
    }

    $raw_end = is_scalar($data['end'] ?? '') ? trim((string) ($data['end'] ?? '')) : '';
    $end = ($raw_end === '') ? $start : ws_mail_away_day($raw_end);

    if (($end === '') || ($end < $start)) {
        return $fail('end', lang('The last day cannot come before the first.'));
    }

    if ($end < (string) $today) {
        return $fail('end', lang('This range is already over.'));
    }

    if ((strtotime($end . ' 00:00:00 UTC') - strtotime($start . ' 00:00:00 UTC')) / 86400 >= WS_MAIL_AWAY_DAYS) {
        return $fail('end', lang(array('string' => 'At most {var:1} days.', 'vars' => array(WS_MAIL_AWAY_DAYS))));
    }

    $text = is_scalar($data['text'] ?? '') ? trim(str_replace(array("\r\n", "\r"), "\n", (string) ($data['text'] ?? ''))) : null;

    if (($text === null) || ($text === '')) {
        return $fail('text', lang('Write the answer the senders get.'));
    }

    if (mb_strlen($text) > WS_MAIL_AUTO_REPLY_MAX) {
        return $fail('text', lang(array('string' => 'At most {var:1} characters.', 'vars' => array(WS_MAIL_AUTO_REPLY_MAX))));
    }

    $stand_in = is_scalar($data['stand_in'] ?? '') ? trim((string) ($data['stand_in'] ?? '')) : 'x';

    if (($stand_in !== '') && !ctype_digit($stand_in)) {
        return $fail('stand_in', lang('Choose a team member.'));
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'away' => array(
        'start'    => $start,
        'end'      => $end,
        'text'     => $text,
        'stand_in' => (int) $stand_in,
    ));
}

/**
 * The kept JSON read back, or null when nothing whole is kept. Pure.
 *
 * @param string|null $json
 * @return array|null start, end, text, stand_in, plan_id
 */
function ws_mail_away_decode($json)
{
    $data = json_decode((string) $json, true);

    if (!is_array($data)) {
        return null;
    }

    $start = ws_mail_away_day($data['start'] ?? '');
    $end = ws_mail_away_day($data['end'] ?? '');
    $text = is_string($data['text'] ?? null) ? trim($data['text']) : '';

    if (($start === '') || ($end === '') || ($end < $start) || ($text === '')) {
        return null;
    }

    return array(
        'start'    => $start,
        'end'      => $end,
        'text'     => $text,
        'stand_in' => max(0, (int) ($data['stand_in'] ?? 0)),
        'plan_id'  => max(0, (int) ($data['plan_id'] ?? 0)),
    );
}

/**
 * Is the person away on this day? Pure.
 *
 * @param array|null $away
 * @param string     $today Y-m-d
 * @return bool
 */
function ws_mail_away_on($away, $today)
{
    return is_array($away) && ((string) $today >= $away['start']) && ((string) $today <= $away['end']);
}

/**
 * Today in a time zone, Y-m-d; an empty or unknown zone is UTC. Pure.
 *
 * @param string $timezone
 * @param int    $now
 * @return string
 */
function ws_mail_away_today($timezone, $now)
{
    $date = new DateTime('@' . (int) $now);
    $date->setTimezone(ws_mail_away_zone($timezone));

    return $date->format('Y-m-d');
}

/**
 * The moment a day starts in a time zone. Pure.
 *
 * @param string $day Y-m-d
 * @param string $timezone
 * @return int
 */
function ws_mail_away_day_start($day, $timezone)
{
    $date = new DateTime((string) $day . ' 00:00:00', ws_mail_away_zone($timezone));

    return (int) $date->getTimestamp();
}

/**
 * A time zone by its name; an empty or unknown one is UTC.
 *
 * @param string $timezone
 * @return DateTimeZone
 */
function ws_mail_away_zone($timezone)
{
    $timezone = (string) $timezone;

    if (($timezone !== '') && in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        return new DateTimeZone($timezone);
    }

    return new DateTimeZone('UTC');
}

/**
 * Is the away text due for this mail: the person is away today, the
 * sender is not forged and has not had it in this range. Pure.
 *
 * @param array|null $away
 * @param string     $today    the holder's today
 * @param string     $auth     the mail's pass | fail | none
 * @param int        $answered automatic answers this sender had in the range
 * @return bool
 */
function ws_mail_away_reply_due($away, $today, $auth, $answered)
{
    return ws_mail_away_on($away, $today) && ($away['text'] !== '') && ((string) $auth !== 'fail') && ((int) $answered === 0);
}

/**
 * Who should hold a conversation meant for this person: the stand-in while
 * the person is away and the stand-in may answer there, else the person.
 * Pure.
 *
 * @param array|null $away
 * @param string     $today       the person's today
 * @param int        $user_id     the person it is meant for
 * @param bool       $stand_in_ok may the stand-in answer in its place
 * @return int
 */
function ws_mail_away_hand_to($away, $today, $user_id, $stand_in_ok)
{
    if (!ws_mail_away_on($away, $today) || ((int) $away['stand_in'] <= 0) || !$stand_in_ok) {
        return (int) $user_id;
    }

    return (int) $away['stand_in'];
}

/**
 * A person's kept range, or null.
 *
 * @param int $user_id
 * @return array|null
 */
function ws_mail_away($user_id)
{
    if (((int) $user_id <= 0) || !ws_mail_away_ready()) {
        return null;
    }

    return ws_mail_away_decode(db_value("SELECT mail_away FROM ws_profiles WHERE user_id = '" . (int) $user_id . "'"));
}

/**
 * A person's time zone (user.timezone), '' for the site's.
 *
 * @param int $user_id
 * @return string
 */
function ws_mail_away_timezone($user_id)
{
    $zone = (string) db_value("SELECT timezone FROM user WHERE user_id = '" . (int) $user_id . "'");

    return ($zone !== '') ? $zone : (string) date_default_timezone_get();
}

/**
 * Who should hold a conversation given to this person in this channel: the
 * stand-in while the person is away and the stand-in is a member who may
 * answer there, else the person. Asked when a conversation is given by
 * hand, by the API or by an incoming mail rule.
 *
 * @param int   $user_id
 * @param array $channel ws_channels row
 * @return int
 */
function ws_mail_away_stand_in($user_id, $channel)
{
    $away = ws_mail_away((int) $user_id);

    if (!$away || ((int) $away['stand_in'] <= 0) || ((int) $away['stand_in'] === (int) $user_id)) {
        return (int) $user_id;
    }

    $today = ws_mail_away_today(ws_mail_away_timezone((int) $user_id), time());

    if (!ws_mail_away_on($away, $today)) {
        return (int) $user_id;
    }

    $person = ws_change_viewer_for_id((int) $away['stand_in']);
    $ok = is_array($person) && !empty($person['member']) && is_array($channel) && ws_mail_can_answer($person, $channel);

    return ws_mail_away_hand_to($away, $today, (int) $user_id, $ok);
}

/**
 * The away text for a mail in an ongoing conversation whose holder is away
 * today: once per sender in the range, through the box's route, kept as an
 * automatic answer (user_id 0) under the card. Called by ws_mail_ingest()
 * after the loop gate, the block list and the hold have let the mail in.
 *
 * @param array $box          ws_mail_boxes row
 * @param int   $item_id      the incoming mail
 * @param array $conversation ws_mail_conversations row as it was found
 * @return bool sent
 */
function ws_mail_away_reply($box, $item_id, $conversation)
{
    $holder = (int) ($conversation['assignee_id'] ?? 0);

    if (($holder <= 0) || pg_demo()) {
        return false;
    }

    $away = ws_mail_away($holder);

    if (!$away) {
        return false;
    }

    $zone = ws_mail_away_timezone($holder);
    $today = ws_mail_away_today($zone, time());

    if (!ws_mail_away_on($away, $today)) {
        return false;
    }

    $item = db_item("SELECT * FROM ws_mail_items WHERE id = '" . (int) $item_id . "'");
    // Read again: the ingest may have given it a new card.
    $conversation = db_item("SELECT * FROM ws_mail_conversations WHERE id = '" . (int) $conversation['id'] . "'");
    $sender = $item ? (string) $item['from_email'] : '';

    if (!$conversation || !filter_var($sender, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Once per sender: this text sent to them since the first day began.
    $answered = (int) db_value("SELECT COUNT(*) FROM ws_mail_items
        WHERE box_id = '" . (int) $box['id'] . "' AND direction = 'out' AND user_id = 0
            AND to_list = '" . e($sender) . "' AND body_text = '" . e($away['text']) . "'
            AND created_at >= '" . ws_mail_away_day_start($away['start'], $zone) . "'");

    if (!ws_mail_away_reply_due($away, $today, (string) $item['auth'], $answered)) {
        return false;
    }

    return ws_mail_auto_reply_send($box, $item, $conversation, $away['text'], $sender, (string) $item['from_name']);
}

/**
 * The range as the drawer shows it: null once it is over, so saving the
 * drawer again does not refuse a past range.
 *
 * @param int $user_id
 * @return array|null start, end, text, stand_in
 */
function ws_mail_away_offer($user_id)
{
    $away = ws_mail_away((int) $user_id);

    if (!$away || ($away['end'] < ws_mail_away_today(ws_mail_away_timezone((int) $user_id), time()))) {
        return null;
    }

    return array('start' => $away['start'], 'end' => $away['end'], 'text' => $away['text'], 'stand_in' => $away['stand_in']);
}

/**
 * The team members who may stand in for the viewer, for the drawer's list.
 *
 * @param array $viewer
 * @return array[] id, name
 */
function ws_mail_away_people($viewer)
{
    $out = array();

    foreach (ws_team_members() as $person) {
        if ((int) $person['id'] !== (int) $viewer['id']) {
            $out[] = array('id' => (int) $person['id'], 'name' => (string) $person['name']);
        }
    }

    return $out;
}

/**
 * Keeps the viewer's range, text and stand-in, and the all-day "Away" plan
 * the whole team sees on My calendar; no first day clears both. A plan of
 * a range already over stays on the calendar.
 *
 * @param array $viewer
 * @param array $data away_start, away_end, away_text, away_stand_in
 * @return array ok, error, field
 */
function ws_mail_away_save($viewer, $data)
{
    if (!ws_mail_away_ready()) {
        return array('ok' => false, 'error' => lang('The database has not been upgraded yet.'), 'field' => '');
    }

    $user_id = (int) $viewer['id'];
    $today = ws_mail_away_today(ws_mail_away_timezone($user_id), time());
    $input = ws_mail_away_input(array(
        'start'    => $data['away_start'] ?? '',
        'end'      => $data['away_end'] ?? '',
        'text'     => $data['away_text'] ?? '',
        'stand_in' => $data['away_stand_in'] ?? '',
    ), $today);

    if (!$input['ok']) {
        return $input;
    }

    $away = $input['away'];

    if ($away && ($away['stand_in'] > 0) && (($away['stand_in'] === $user_id) || !in_array($away['stand_in'], ws_team_ids(), true))) {
        return array('ok' => false, 'error' => lang('Choose a team member.'), 'field' => 'stand_in');
    }

    $old = ws_mail_away($user_id);
    $old_plan = $old ? (int) $old['plan_id'] : 0;
    $plans = function_exists('ws_personal_plans_ready') && ws_personal_plans_ready();

    if (!$away) {
        // A range not over yet leaves the calendar with it.
        if ($plans && ($old_plan > 0) && ($old['end'] >= $today)) {
            ws_personal_plan_delete($viewer, $old_plan);
        }

        db_q("INSERT INTO ws_profiles (user_id, mail_away, updated_at) VALUES (?, NULL, ?)
            ON DUPLICATE KEY UPDATE mail_away = NULL", array($user_id, time()));

        return array('ok' => true, 'error' => '', 'field' => '');
    }

    $plan_id = 0;

    if ($plans) {
        $plan = array('title' => lang('Away'), 'note' => '', 'start' => $away['start'], 'end' => $away['end'], 'all_day' => '1',
            'remind' => '', 'visibility' => 'team');
        $saved = ws_personal_plan_save($viewer, $plan, $old_plan);

        // The old plan was deleted on the calendar: a new one.
        if (empty($saved['ok']) && ($old_plan > 0)) {
            $saved = ws_personal_plan_save($viewer, $plan, 0);
        }

        $plan_id = !empty($saved['ok']) ? (int) ($saved['plan']['id'] ?? 0) : 0;
    }

    $json = json_encode(array_merge($away, array('plan_id' => $plan_id)), JSON_UNESCAPED_UNICODE);

    db_q("INSERT INTO ws_profiles (user_id, mail_away, updated_at) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE mail_away = VALUES(mail_away)", array($user_id, $json, time()));

    return array('ok' => true, 'error' => '', 'field' => '');
}

/**
 * The words of the drawer's "I am away" part for the screen script.
 *
 * @return array
 */
function ws_mail_away_js_strings()
{
    return array(
        'mail_away_title'     => lang('I am away'),
        'mail_away_hint'      => lang('Between these days a sender writing again in a conversation you hold gets this text once, and a conversation given to you goes to your stand-in. The days stand on My calendar for the whole team.'),
        'mail_away_start'     => lang('First day'),
        'mail_away_end'       => lang('Last day'),
        'mail_away_text'      => lang('Answer'),
        'mail_away_text_hint' => lang(array('string' => 'At most {var:1} characters.', 'vars' => array(WS_MAIL_AUTO_REPLY_MAX))),
        'mail_away_stand_in'  => lang('Stand-in'),
        'mail_away_nobody'    => lang('Nobody'),
        'mail_away_clear'     => lang('Clear'),
    );
}
