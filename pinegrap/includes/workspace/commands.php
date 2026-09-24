<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - commands typed into a channel.
 *
 *   /task Logo revision @ayse friday ~2h !urgent #contact   (also /gorev)
 *   /assign T-42 @mehmet                                     (also /ata)
 *   /done T-42                                               (also /bitti, /tamam)
 *   /postpone T-42 +2d                                       (also /ertele)
 *   /decision The packaging will be kraft                    (also /karar)
 *   /note Accounts contact: Selin, ext. 204                  (also /not)
 *   /plan @ayse                                              the next working week
 *   /help                                                    (also /yardim)
 *
 * A command is a shortcut, never the only way: each has a button somewhere on
 * the screen. Creating a task is always shown back first - "this is the task I
 * would create, with these warnings" - and only an explicit confirmation
 * writes it, so a misread date never becomes a task nobody asked for.
 *
 * The date words are a short fixed list (today, tomorrow, weekday names,
 * 26.09, 26.09.2026, +3d, +2w, next week) in Turkish and English. Anything
 * else stays in the title, where the author will see it in the preview.
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
 * The commands, each under the names it answers to.
 *
 * @return array name => command
 */
function ws_command_names()
{
    return array(
        'gorev' => 'task', 'görev' => 'task', 'task' => 'task', 'g' => 'task', 't' => 'task',
        'ata' => 'assign', 'assign' => 'assign',
        'bitti' => 'done', 'tamam' => 'done', 'done' => 'done',
        'ertele' => 'postpone', 'postpone' => 'postpone',
        'karar' => 'decision', 'decision' => 'decision',
        'not' => 'note', 'note' => 'note',
        'plan' => 'plan',
        'oylama' => 'poll', 'anket' => 'poll', 'sor' => 'poll', 'poll' => 'poll',
        'yardim' => 'help', 'yardım' => 'help', 'help' => 'help',
    );
}

/**
 * The help text the /help command and the composer's hint show.
 *
 * @return array[] command, example, description
 */
function ws_command_help()
{
    return array(
        array('command' => '/task · /gorev', 'example' => lang('/task Logo revision @ayse friday ~2h !urgent'), 'description' => lang('Creates a task: people with @, a day, an estimate after ~, a priority after !. Shown to you before it is created.')),
        array('command' => '/assign · /ata', 'example' => lang('/assign T-42 @mehmet'), 'description' => lang('Adds people to a task.')),
        array('command' => '/done · /bitti', 'example' => lang('/done T-42'), 'description' => lang('Marks a task as done.')),
        array('command' => '/postpone · /ertele', 'example' => lang('/postpone T-42 +2d'), 'description' => lang('Moves a task\'s due date.')),
        array('command' => '/decision · /karar', 'example' => lang('/decision The packaging will be kraft'), 'description' => lang('Writes a decision; it is kept on the Decisions tab.')),
        array('command' => '/note · /not', 'example' => lang('/note Accounts contact: Selin, ext. 204'), 'description' => lang('Writes a note; it is kept on the Decisions tab.')),
        array('command' => '/plan', 'example' => lang('/plan @ayse'), 'description' => lang('Shows the next working week of a person, only to you.')),
        array('command' => '/poll · /oylama', 'example' => lang('/poll Which day for the launch? | Tuesday | Wednesday'), 'description' => lang('Asks the channel to choose; the options go after |. Without options it opens the poll form, where it can also be anonymous, multiple choice or closed at a set time.')),
    );
}

/**
 * Lower case the way Turkish needs it: İ is i, I is ı.
 *
 * @param string $text
 * @return string
 */
function ws_lower($text)
{
    $text = str_replace(array('İ', 'I'), array('i', 'ı'), (string) $text);

    return mb_strtolower($text, 'UTF-8');
}

/**
 * Splits a command off a message.
 *
 * @param string $body
 * @return array|null command, rest
 */
function ws_command_split($body)
{
    if (!preg_match('/^\/([^\s\/]{1,20})(?:\s+|$)(.*)$/su', ltrim((string) $body), $match)) {
        return null;
    }

    $names = ws_command_names();
    $name = ws_lower($match[1]);

    if (!isset($names[$name])) {
        $name = mb_strtolower($match[1], 'UTF-8');
    }

    if (!isset($names[$name])) {
        return null;
    }

    return array('command' => $names[$name], 'rest' => trim($match[2]));
}

/**
 * The weekday a word names, Monday = 1, or 0.
 *
 * @param string $word lower case
 * @return int
 */
function ws_weekday_number($word)
{
    $days = array(
        'pazartesi' => 1, 'pzt' => 1, 'monday' => 1, 'mon' => 1,
        'salı' => 2, 'sali' => 2, 'tuesday' => 2, 'tue' => 2,
        'çarşamba' => 3, 'carsamba' => 3, 'çarşambaya' => 3, 'wednesday' => 3, 'wed' => 3,
        'perşembe' => 4, 'persembe' => 4, 'thursday' => 4, 'thu' => 4,
        'cuma' => 5, 'cumaya' => 5, 'friday' => 5, 'fri' => 5,
        'cumartesi' => 6, 'saturday' => 6, 'sat' => 6,
        'pazar' => 7, 'sunday' => 7, 'sun' => 7,
    );

    return $days[$word] ?? 0;
}

/**
 * A date word as Y-m-d, or ''.
 *
 * @param string $word
 * @param string $today Y-m-d
 * @return string
 */
function ws_parse_date_word($word, $today)
{
    // Turkish lower case first; the plain one as well, for English words
    // written in capitals ("FRIDAY" is not "frıday").
    $plain = rtrim(mb_strtolower((string) $word, 'UTF-8'), '.,;');
    $word = rtrim(ws_lower($word), '.,;');

    if (($plain !== $word) && (ws_parse_date_word_lower($plain, $today) !== '')) {
        return ws_parse_date_word_lower($plain, $today);
    }

    return ws_parse_date_word_lower($word, $today);
}

/**
 * ws_parse_date_word() for a word already in lower case.
 *
 * @param string $word
 * @param string $today
 * @return string
 */
function ws_parse_date_word_lower($word, $today)
{
    $base = strtotime($today . ' 12:00:00');

    if (in_array($word, array('bugün', 'bugun', 'today'), true)) {
        return $today;
    }

    if (in_array($word, array('yarın', 'yarin', 'tomorrow'), true)) {
        return date('Y-m-d', strtotime('+1 day', $base));
    }

    if (in_array($word, array('haftaya', 'nextweek'), true)) {
        return date('Y-m-d', strtotime('+7 days', $base));
    }

    $weekday = ws_weekday_number($word);

    if ($weekday > 0) {
        $delta = ($weekday - (int) date('N', $base) + 7) % 7;

        return date('Y-m-d', strtotime('+' . $delta . ' days', $base));
    }

    if (preg_match('/^\+([0-9]{1,3})(g|gün|gun|d|day|days|h|hafta|w|week|weeks)?$/u', $word, $match)) {
        $count = (int) $match[1];
        $unit = $match[2] ?? 'g';
        $days = in_array($unit, array('h', 'hafta', 'w', 'week', 'weeks'), true) ? ($count * 7) : $count;

        return date('Y-m-d', strtotime('+' . $days . ' days', $base));
    }

    if (preg_match('/^([0-9]{1,2})[.\/]([0-9]{1,2})(?:[.\/]([0-9]{2,4}))?$/', $word, $match)) {
        $day = (int) $match[1];
        $month = (int) $match[2];
        $year = isset($match[3]) ? (int) $match[3] : (int) date('Y', $base);

        if ($year < 100) {
            $year += 2000;
        }

        if (!checkdate($month, $day, $year)) {
            return '';
        }

        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

        // A day and a month without a year that already went by this year
        // mean next year's.
        if (!isset($match[3]) && ($date < $today)) {
            $date = sprintf('%04d-%02d-%02d', $year + 1, $month, $day);
        }

        return $date;
    }

    return '';
}

/**
 * An estimate written after ~ ("~90dk", "~2sa", "~1.5h", "~1g"), in minutes.
 *
 * @param string $word
 * @return int
 */
function ws_parse_estimate($word)
{
    if (!preg_match('/^~([0-9]+(?:[.,][0-9]+)?)\s*(dk|dakika|m|min|sa|saat|s|h|hr|g|gün|gun|d|day)?$/u', ws_lower($word), $match)) {
        return 0;
    }

    $amount = (float) str_replace(',', '.', $match[1]);
    $unit = $match[2] ?? 'sa';

    if (in_array($unit, array('dk', 'dakika', 'm', 'min'), true)) {
        return (int) round($amount);
    }

    if (in_array($unit, array('g', 'gün', 'gun', 'd', 'day'), true)) {
        return (int) round($amount * (int) WS_DAY_MINUTES);
    }

    return (int) round($amount * 60);
}

/**
 * A priority written after ! ("!acil", "!urgent", "!yüksek", "!low").
 *
 * @param string $word
 * @return string ''
 */
function ws_parse_priority($word)
{
    $map = array(
        '!acil' => 'urgent', '!urgent' => 'urgent', '!!' => 'urgent',
        '!yüksek' => 'high', '!yuksek' => 'high', '!high' => 'high', '!önemli' => 'high', '!onemli' => 'high',
        '!normal' => 'normal',
        '!düşük' => 'low', '!dusuk' => 'low', '!low' => 'low',
    );

    return $map[ws_lower($word)] ?? '';
}

/**
 * Reads a task out of the words after /task.
 *
 * @param string $text
 * @return array title, assignees, department_id, refs, priority, estimate_minutes,
 *               start_date, due_date
 */
function ws_command_parse_task($text)
{
    $out = array(
        'title'            => '',
        'assignees'        => array(),
        'department_id'    => 0,
        'refs'             => array(),
        'priority'         => 'normal',
        'estimate_minutes' => 0,
        'start_date'       => null,
        'due_date'         => null,
    );

    foreach (ws_tokens($text) as $token) {
        if ($token['type'] === 'user') {
            $out['assignees'][$token['id']] = $token['id'];
        } elseif ($token['type'] === 'dept') {
            if ($out['department_id'] === 0) {
                $out['department_id'] = $token['id'];
            }
        } else {
            $out['refs'][] = '<#' . $token['type'] . ':' . $token['id'] . '>';
        }
    }

    $out['assignees'] = array_values($out['assignees']);

    $text = preg_replace('/<[@#][a-z]+:[0-9]{1,10}>/', ' ', (string) $text);
    $today = date('Y-m-d');
    $dates = array();
    $title = array();

    foreach (preg_split('/\s+/u', trim($text)) as $word) {
        if ($word === '') {
            continue;
        }

        $priority = ws_parse_priority($word);

        if ($priority !== '') {
            $out['priority'] = $priority;
            continue;
        }

        $estimate = ws_parse_estimate($word);

        if ($estimate > 0) {
            $out['estimate_minutes'] = $estimate;
            continue;
        }

        // A range written as one word: 25.09-27.09
        if (preg_match('/^([0-9]{1,2}[.\/][0-9]{1,2}(?:[.\/][0-9]{2,4})?)-([0-9]{1,2}[.\/][0-9]{1,2}(?:[.\/][0-9]{2,4})?)$/', $word, $match)) {
            $from = ws_parse_date_word($match[1], $today);
            $to = ws_parse_date_word($match[2], $today);

            if (($from !== '') && ($to !== '')) {
                $dates[] = $from;
                $dates[] = $to;
                continue;
            }
        }

        $date = ws_parse_date_word($word, $today);

        if ($date !== '') {
            $dates[] = $date;
            continue;
        }

        $title[] = $word;
    }

    if (count($dates) === 1) {
        $out['due_date'] = $dates[0];
    } elseif (count($dates) >= 2) {
        sort($dates);
        $out['start_date'] = $dates[0];
        $out['due_date'] = $dates[count($dates) - 1];
    }

    $out['title'] = trim(implode(' ', $title), " \t-–—:,");

    return $out;
}

/**
 * The task a command points at: "T-42", "G-42", "#42", "42" or a task tag.
 *
 * @param string $text
 * @return array task id, rest of the text
 */
function ws_command_task_ref($text)
{
    $text = trim((string) $text);

    if (preg_match('/<#task:([0-9]{1,10})>/', $text, $match)) {
        return array((int) $match[1], trim(str_replace($match[0], ' ', $text)));
    }

    if (preg_match('/^(?:[a-zçğıöşüA-ZÇĞİÖŞÜ]{1,2}-|#)?([0-9]{1,10})\b(.*)$/su', $text, $match)) {
        return array((int) $match[1], trim($match[2]));
    }

    return array(0, $text);
}

/**
 * Runs a command typed into a channel.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $body
 * @param array  $options confirm (bool), force (bool), assignees (ids, replacing
 *                        the parsed ones - the "give it to somebody lighter" button)
 * @return array|null null when the text is not a command; otherwise type =
 *                    preview | done | ephemeral | error and what goes with it
 */
function ws_command_run($viewer, $channel, $body, $options = array())
{
    $split = ws_command_split($body);

    if ($split === null) {
        return null;
    }

    $rest = $split['rest'];

    switch ($split['command']) {

        case 'task':
            return ws_command_task($viewer, $channel, $rest, $options);

        case 'assign':
            list($task_id, $rest_text) = ws_command_task_ref($rest);
            $task = ws_task($task_id);

            if (!$task || !ws_can_see_task($viewer, $task)) {
                return array('type' => 'error', 'error' => lang('That task could not be found.'));
            }

            $people = array();

            foreach (ws_tokens($rest_text) as $token) {
                if ($token['type'] === 'user') {
                    $people[] = $token['id'];
                }
            }

            if (empty($people)) {
                return array('type' => 'error', 'error' => lang('Name the people with @.'));
            }

            $result = ws_task_update($viewer, $task, array('assignees' => array_values(array_unique(array_merge(ws_task_assignee_ids($task_id), $people)))));

            if (!$result['ok']) {
                return array('type' => 'error', 'error' => $result['error']);
            }

            ws_message_system($channel['id'], lang(array(
                'string' => '{var:1} gave {var:2} to {var:3}',
                'vars'   => array('<@user:' . (int) $viewer['id'] . '>', '<#task:' . $task_id . '>', implode(', ', array_map(function ($id) {
                    return '<@user:' . (int) $id . '>';
                }, $people))),
            )), $task_id);

            return array('type' => 'done');

        case 'done':
            list($task_id) = ws_command_task_ref($rest);
            $task = ws_task($task_id);

            if (!$task || !ws_can_see_task($viewer, $task)) {
                return array('type' => 'error', 'error' => lang('That task could not be found.'));
            }

            $result = ws_task_set_status($viewer, $task, 'done');

            if (!$result['ok']) {
                return array('type' => 'error', 'error' => $result['error']);
            }

            // The completion line goes to the task's own channel; a task from
            // another channel, or from none, is still said here.
            if ((int) $task['channel_id'] !== (int) $channel['id']) {
                ws_message_system($channel['id'], lang(array(
                    'string' => '{var:1} completed {var:2}',
                    'vars'   => array('<@user:' . (int) $viewer['id'] . '>', '<#task:' . $task_id . '>'),
                )), $task_id);
            }

            return array('type' => 'done');

        case 'postpone':
            list($task_id, $rest_text) = ws_command_task_ref($rest);
            $task = ws_task($task_id);

            if (!$task || !ws_can_see_task($viewer, $task)) {
                return array('type' => 'error', 'error' => lang('That task could not be found.'));
            }

            $new_due = '';

            foreach (preg_split('/\s+/u', $rest_text) as $word) {
                $new_due = ws_parse_date_word($word, $task['due_date'] ?: date('Y-m-d'));

                if (($new_due !== '') && (strpos($word, '+') !== 0)) {
                    // An absolute day is read from today, not from the old due date.
                    $new_due = ws_parse_date_word($word, date('Y-m-d'));
                }

                if ($new_due !== '') {
                    break;
                }
            }

            if ($new_due === '') {
                return array('type' => 'error', 'error' => lang('Say when: a day, a date or +2d.'));
            }

            $data = array('due_date' => $new_due);

            // The start moves with the due date, so the task keeps its length.
            if ($task['start_date'] && $task['due_date']) {
                $shift = (int) round((strtotime($new_due) - strtotime($task['due_date'])) / 86400);
                $data['start_date'] = date('Y-m-d', strtotime($task['start_date'] . ' 12:00:00 ' . (($shift >= 0) ? '+' : '') . $shift . ' days'));
            }

            $result = ws_task_update($viewer, $task, $data);

            if (!$result['ok']) {
                return array('type' => 'error', 'error' => $result['error']);
            }

            ws_message_system($channel['id'], lang(array(
                'string' => '{var:1} moved {var:2} to {var:3}',
                'vars'   => array('<@user:' . (int) $viewer['id'] . '>', '<#task:' . $task_id . '>', ws_day_label($new_due)),
            )), $task_id);

            return array('type' => 'done');

        case 'decision':
        case 'note':
            if ($rest === '') {
                return array('type' => 'error', 'error' => lang('Write the text after the command.'));
            }

            $result = ws_message_send($viewer, $channel, $rest, array('kind' => $split['command']));

            return $result['ok']
                ? array('type' => 'done', 'message_id' => $result['message_id'])
                : array('type' => 'error', 'error' => $result['error']);

        case 'plan':
            return ws_command_plan($viewer, $rest);

        case 'poll':
            $parts = array_values(array_filter(array_map('trim', explode('|', $rest)), 'strlen'));

            // A question with fewer than two options goes to the form, with
            // what was written already in it.
            if (count($parts) < 3) {
                return array('type' => 'poll_form', 'question' => $parts[0] ?? '', 'options' => array_slice($parts, 1));
            }

            $result = ws_poll_create($viewer, $channel, array('question' => array_shift($parts), 'options' => $parts));

            return $result['ok']
                ? array('type' => 'done', 'message_id' => $result['message_id'])
                : array('type' => 'error', 'error' => $result['error']);

        case 'help':
        default:
            $html = '<div class="ws-ephemeral-title">' . h(lang('Slash commands')) . '</div><dl class="ws-help">';

            foreach (ws_command_help() as $item) {
                $html .= '<dt><code>' . h($item['command']) . '</code></dt><dd>' . h($item['description']) . '<br><span class="text-body-secondary">' . h($item['example']) . '</span></dd>';
            }

            return array('type' => 'ephemeral', 'html' => $html . '</dl>');
    }
}

/**
 * /task: shown back first, created on confirmation.
 *
 * @return array
 */
function ws_command_task($viewer, $channel, $text, $options)
{
    $parsed = ws_command_parse_task($text);

    if ($parsed['title'] === '') {
        return array('type' => 'error', 'error' => lang('Write what the task is after the command.'));
    }

    if (isset($options['assignees']) && is_array($options['assignees'])) {
        $parsed['assignees'] = array_values(array_unique(array_map('intval', $options['assignees'])));
    }

    // A task given to nobody is the author's own.
    if (empty($parsed['assignees']) && ($parsed['department_id'] === 0)) {
        $parsed['assignees'] = array((int) $viewer['id']);
    }

    if (!ws_can_assign_to($viewer, $parsed['assignees'])) {
        return array('type' => 'error', 'error' => lang('You may only give tasks to yourself or to the people in the departments you lead.'));
    }

    $check = ws_assignment_check($viewer, $parsed, $parsed['assignees']);
    $may_override = ($viewer['board'] || ($viewer['role'] < 3) || !empty(array_intersect(ws_lead_departments($viewer['id']), array($parsed['department_id']))));

    // Shown back unless confirmed; a clash with somebody's leave is shown
    // back again until it is explicitly overridden.
    if (empty($options['confirm']) || ($check['hard'] && empty($options['force']))) {
        $people = ws_people($parsed['assignees']);
        $refs = ws_refs_resolve($viewer, ws_tokens(implode(' ', $parsed['refs'])));
        $chips = '';

        foreach ($refs as $ref) {
            $chips .= ws_chip_html($ref);
        }

        $department = ($parsed['department_id'] > 0) ? ws_department($parsed['department_id']) : null;

        return array(
            'type'    => 'preview',
            'preview' => array(
                'title'      => $parsed['title'],
                'people'     => array_values($people),
                'department' => $department ? $department['name'] : '',
                'due'        => $parsed['due_date'] ? ws_task_due_label(array('due_date' => $parsed['due_date'], 'status' => 'todo')) : lang('No due date'),
                'start'      => $parsed['start_date'] ? ws_day_label($parsed['start_date']) : '',
                'estimate'   => ($parsed['estimate_minutes'] > 0) ? ws_minutes_label($parsed['estimate_minutes']) : lang(array('string' => 'not estimated (counted as {var:1})', 'vars' => ws_minutes_label(WS_DEFAULT_TASK_MINUTES))),
                'priority'   => ws_task_priorities()[$parsed['priority']],
                'refs_html'  => $chips,
            ),
            'check'         => $check,
            'may_override'  => $may_override,
            'assignees'     => $parsed['assignees'],
        );
    }

    if ($check['hard'] && !$may_override) {
        return array('type' => 'error', 'error' => lang('Somebody on this task is away then. Only a lead or somebody with the team board can hand it over anyway.'));
    }

    $result = ws_task_create($viewer, array(
        'title'            => $parsed['title'],
        'priority'         => $parsed['priority'],
        'start_date'       => $parsed['start_date'],
        'due_date'         => $parsed['due_date'],
        'estimate_minutes' => $parsed['estimate_minutes'],
        'department_id'    => $parsed['department_id'],
        'channel_id'       => $channel['id'],
        'assignees'        => $parsed['assignees'],
        'refs'             => $parsed['refs'],
    ));

    if (!$result['ok']) {
        return array('type' => 'error', 'error' => $result['error']);
    }

    $sent = ws_message_send($viewer, $channel, '', array('kind' => 'task', 'task_id' => $result['task_id']));

    if ($sent['ok']) {
        db("UPDATE ws_tasks SET source_message_id = '" . (int) $sent['message_id'] . "' WHERE id = '" . (int) $result['task_id'] . "'");
    }

    return array('type' => 'done', 'task_id' => $result['task_id'], 'message_id' => $sent['message_id'] ?? 0);
}

/**
 * /plan: somebody's next working week, only to the one who asked.
 *
 * @return array
 */
function ws_command_plan($viewer, $text)
{
    $user_id = (int) $viewer['id'];

    foreach (ws_tokens($text) as $token) {
        if ($token['type'] === 'user') {
            $user_id = (int) $token['id'];
            break;
        }
    }

    $scope = ws_board_scope($viewer);

    if (($scope !== true) && !in_array($user_id, $scope, true)) {
        return array('type' => 'error', 'error' => lang('You can only see your own plan and the plans of the departments you lead.'));
    }

    $from = date('Y-m-d');
    $to = date('Y-m-d', strtotime('+6 days'));
    $grid = ws_plan_grid(array($user_id), $from, $to);
    $name = ws_person_name($user_id);

    $html = '<div class="ws-ephemeral-title">' . h(lang(array('string' => 'The coming week of {var:1}', 'vars' => $name))) . '</div><table class="ws-plan-mini">';

    foreach ($grid[$user_id]['days'] ?? array() as $date => $cell) {
        if (!$cell['workday'] && empty($cell['tasks'])) {
            continue;
        }

        $state = $cell['leave'] ? lang('Away') : ($cell['holiday'] ? lang('Holiday') : lang(array('string' => '{var:1}%', 'vars' => $cell['load'])));
        $class = ($cell['leave'] || $cell['holiday']) ? 'ws-load-away' : (($cell['load'] > 100) ? 'ws-load-over' : (($cell['load'] >= 80) ? 'ws-load-full' : 'ws-load-ok'));
        $titles = array();

        foreach ($cell['tasks'] as $task) {
            $titles[] = h($task['title']);
        }

        $html .= '<tr><th>' . h(ws_day_label($date)) . '</th><td><span class="ws-load ' . $class . '">' . h($state) . '</span></td><td>' . implode(', ', $titles) . '</td></tr>';
    }

    $html .= '</table>';

    return array('type' => 'ephemeral', 'html' => $html);
}
