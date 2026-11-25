<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - sending hours of a flow's sending boxes (Post a message, Send
 * an e-mail, Send an SMS, Send a WhatsApp message): the days and the hours
 * in the site's time zone a box may send in. Reached outside them, the box
 * waits as a Wait box does (wake_at) and is made ready again when they open,
 * or, set so, settles done with nothing sent. Off, the box sends at once.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------------
   The setting (pure)
   --------------------------------------------------------------------------- */

/**
 * The boxes that take sending hours.
 *
 * @return string[]
 */
function ws_flow_hours_kinds()
{
    return array('post', 'email', 'sms', 'whatsapp');
}

/**
 * A box's sending hours as they are kept: on (off at first), days (ISO
 * weekdays, 1 Monday to 7 Sunday, Monday to Friday when not given), from
 * and to (HH:MM, 09:00 and 20:00 when not a time of day), outside (wait for
 * the next hour that fits, or skip: do not send). Pure.
 *
 * @param mixed $hours
 * @return array on, days, from, to, outside
 */
function ws_flow_hours_normalize($hours)
{
    $hours = is_array($hours) ? $hours : array();
    $days = array(1, 2, 3, 4, 5);

    if (is_array($hours['days'] ?? null)) {
        $days = array();

        foreach ($hours['days'] as $day) {
            if (!is_bool($day) && is_numeric($day) && ((int) $day >= 1) && ((int) $day <= 7)) {
                $days[(int) $day] = (int) $day;
            }
        }

        ksort($days);
        $days = array_values($days);
    }

    $from = ws_flow_delay_clock($hours['from'] ?? '');
    $to = ws_flow_delay_clock($hours['to'] ?? '');

    return array(
        'on'      => !empty($hours['on']),
        'days'    => $days,
        'from'    => ($from !== '') ? $from : '09:00',
        'to'      => ($to !== '') ? $to : '20:00',
        'outside' => (($hours['outside'] ?? '') === 'skip') ? 'skip' : 'wait',
    );
}

/**
 * A sending box's settings with its sending hours (ws_step_config_normalize()).
 *
 * @param array $config the box's settings as sent
 * @return array hours
 */
function ws_flow_hours_config($config)
{
    return array('hours' => ws_flow_hours_normalize($config['hours'] ?? null));
}

/**
 * Why a box's sending hours keep it from being published: on with no day,
 * or an end that is not after the start. Pure but for lang().
 *
 * @param array  $config the box's settings
 * @param string $name   the box's name in the sentences
 * @return string[]
 */
function ws_flow_hours_problems($config, $name)
{
    $hours = ws_flow_hours_normalize(is_array($config) ? ($config['hours'] ?? null) : null);

    if (!$hours['on']) {
        return array();
    }

    if (empty($hours['days'])) {
        return array(lang(array('string' => 'The box {var:1} has sending hours but no day to send on.', 'vars' => $name)));
    }

    if (strcmp($hours['from'], $hours['to']) >= 0) {
        return array(lang(array('string' => 'The sending hours of the box {var:1} end before they start.', 'vars' => $name)));
    }

    return array();
}

/**
 * The first moment at or after $now inside the hours, in $zone: $now when
 * it is inside them or they are off, the start of the next listed day's
 * hours otherwise, 0 when they can never open (no day, or an end not after
 * the start). The hours are from inclusive, to exclusive. Pure.
 *
 * @param mixed  $hours ws_flow_hours_normalize()
 * @param int    $now   unix time
 * @param string $zone  a time zone name, '' for the site's (PHP's default)
 * @return int unix time, 0 for never
 */
function ws_flow_hours_next($hours, $now, $zone = '')
{
    $hours = ws_flow_hours_normalize($hours);
    $now = (int) $now;

    if (!$hours['on']) {
        return $now;
    }

    if (empty($hours['days']) || (strcmp($hours['from'], $hours['to']) >= 0)) {
        return 0;
    }

    try {
        $place = new DateTimeZone(((string) $zone !== '') ? (string) $zone : date_default_timezone_get());
    } catch (\Exception $exception) {
        $place = new DateTimeZone('UTC');
    }

    $today = new DateTime('@' . $now);
    $today->setTimezone($place);
    $from = array_map('intval', explode(':', $hours['from']));
    $to = array_map('intval', explode(':', $hours['to']));

    // Eight days reach the same weekday a week on.
    for ($step = 0; $step <= 7; $step++) {
        $start = new DateTime($today->format('Y-m-d') . ' 00:00:00', $place);
        $start->modify('+' . $step . ' day');

        if (!in_array((int) $start->format('N'), $hours['days'], true)) {
            continue;
        }

        $end = clone $start;
        $start->setTime($from[0], $from[1], 0);
        $end->setTime($to[0], $to[1], 0);

        if ($now >= $end->getTimestamp()) {
            continue;
        }

        return max($now, $start->getTimestamp());
    }

    return 0;
}

/**
 * The key a box waiting for its sending hours keeps (wait_key): hours:<until>.
 *
 * @param int $until
 * @return string
 */
function ws_flow_hours_key($until)
{
    return 'hours:' . (int) $until;
}

/**
 * The moment a sending-hours key names, 0 for a key of another kind.
 *
 * @param string $key
 * @return int
 */
function ws_flow_hours_key_until($key)
{
    return preg_match('/^hours:([1-9][0-9]{0,11})$/', (string) $key, $match) ? (int) $match[1] : 0;
}

/* ---------------------------------------------------------------------------
   The run and Try
   --------------------------------------------------------------------------- */

/**
 * A sending box claimed by this request outside its hours does not begin:
 * set to skip, it settles done with nothing sent (sent 0) and says why, as
 * a person without consent does; else it waits, in one conditional write,
 * until the hours open (wake_at, keyed hours:<until>), and
 * ws_flow_retry_recheck() makes it ready then. The wait gives back the
 * claim's count of tries and of the run's boxes, so the send that follows is
 * its first. Hours that can never open (published before the check) fail it.
 *
 * @param array $step  ws_flow_run_steps row, running
 * @param array $node  the box
 * @param array $graph the run's graph
 * @return bool whether the box was held (the caller stops)
 */
function ws_flow_hours_hold($step, $node, $graph)
{
    if (!in_array((string) $node['kind'], ws_flow_hours_kinds(), true)) {
        return false;
    }

    $hours = ws_flow_hours_normalize($node['config']['hours'] ?? null);
    $now = time();
    $until = ws_flow_hours_next($hours, $now);

    if ($until === $now) {
        return false;
    }

    if ($until === 0) {
        $end = ws_step_settlement(false, ws_flow_outcome_routed($graph, $node['id'], 'failed'));
        ws_flow_step_settle($step, 'running', $end['status'], $end['outcome'], lang('The box has sending hours that never open, so it did not send.'));

        return true;
    }

    if ($hours['outside'] === 'skip') {
        ws_flow_step_settle($step, 'running', 'done', 'done', lang('Outside its sending hours, so nothing was sent.'), array('sent' => 0));

        return true;
    }

    $key = ws_flow_hours_key($until);
    $text = lang(array('string' => 'Outside its sending hours: sends at {var:1}.', 'vars' => ws_scheduled_moment($until)));

    db("UPDATE ws_flow_run_steps SET status = 'waiting', effect = '', wait_event = '',
            wait_key = '" . e($key) . "', wake_at = '" . $until . "',
            attempt = IF(attempt > 0, attempt - 1, 0),
            result = '" . e(ws_flow_result_pack($text)) . "'
        WHERE id = '" . (int) $step['id'] . "' AND status = 'running'");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return true;
    }

    db("UPDATE ws_flow_runs SET boxes_run = IF(boxes_run > 0, boxes_run - 1, 0)
        WHERE id = '" . (int) $step['run_id'] . "'");

    ws_flow_run_refresh((int) $step['run_id']);
    ws_flow_run_announce_waiting((int) $step['id'], $key);

    return true;
}

/**
 * What Try and the trial add for a sending box outside its hours now: when
 * it would send (next) and a sentence after what it would do, or that it
 * would not send. Nothing when the hours are off or open.
 *
 * @param array $answer ws_flow_box_try() of the box
 * @param array $node   the box
 * @return array the answer
 */
function ws_flow_hours_try($answer, $node)
{
    if (!in_array((string) ($node['kind'] ?? ''), ws_flow_hours_kinds(), true) || empty($answer['ok']) || (($answer['outcome'] ?? '') !== 'done')) {
        return $answer;
    }

    $hours = ws_flow_hours_normalize($node['config']['hours'] ?? null);
    $now = time();
    $until = ws_flow_hours_next($hours, $now);

    if ($until === $now) {
        return $answer;
    }

    if ($until === 0) {
        $said = lang('Its sending hours never open, so it would not send.');
    } elseif ($hours['outside'] === 'skip') {
        $said = lang('It is outside its sending hours now, so it would not send.');
    } else {
        $said = lang(array('string' => 'It is outside its sending hours now: it would send at {var:1}.', 'vars' => ws_scheduled_moment($until)));
        $answer['next'] = array(ws_scheduled_moment($until));
    }

    $answer['said'] = trim(rtrim((string) ($answer['said'] ?? '')) . ' ' . $said);

    return $answer;
}
