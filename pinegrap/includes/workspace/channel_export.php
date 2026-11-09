<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a channel's conversation leaving as a file: a Markdown file, or
 * a page the browser prints or saves as PDF (workspace_channel_export.php).
 *
 * The export holds what the reader sees on the screen, oldest first: who
 * wrote, when, the text with names in place of tags, a discussion's messages
 * under the message it is about (when the reader is in it), a poll or an
 * approval request as one line with its outcome, a file as its name and
 * link. Deleted messages and the ones the reader hid stay out
 * (ws_message_payloads()). It opens with the channel's name, the range, who
 * exported and when, then the channel's decisions, and ends with its files.
 *
 * The reading (ws_channel_export_collect()) touches the database; the
 * Markdown is built by pure functions over what it read, so the tests reach
 * them.
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
 * The most messages one export holds; a longer range is refused.
 */
define('WS_CHANNEL_EXPORT_MAX', 5000);

/**
 * The messages read from the database at a time.
 */
define('WS_CHANNEL_EXPORT_PAGE', 200);

/**
 * The range asked for, from the drawer's two dates (Y-m-d, either empty).
 * Pure but for the site's time zone.
 *
 * @param string $from
 * @param string $to
 * @return array from, to (timestamps, 0 for open), from_date, to_date (the
 *               dates kept, '' for open), ok, error
 */
function ws_channel_export_range($from, $to)
{
    $out = array('from' => 0, 'to' => 0, 'from_date' => '', 'to_date' => '', 'ok' => true, 'error' => '');

    foreach (array('from' => $from, 'to' => $to) as $key => $value) {
        $value = trim((string) $value);

        if ($value === '') {
            continue;
        }

        $date = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) ? DateTime::createFromFormat('!Y-m-d', $value) : false;

        if (!$date || ($date->format('Y-m-d') !== $value)) {
            return array_merge($out, array('ok' => false, 'error' => lang('Enter the dates as day, month and year.')));
        }

        // The last day of the range counts to its end.
        $out[$key] = ($key === 'to') ? ((int) $date->format('U') + 86399) : (int) $date->format('U');
        $out[$key . '_date'] = $value;
    }

    if (($out['from'] > 0) && ($out['to'] > 0) && ($out['from'] > $out['to'])) {
        return array_merge($out, array('ok' => false, 'error' => lang('The first date comes after the last one.')));
    }

    return $out;
}

/**
 * The range as the export's head names it.
 *
 * @param array $range ws_channel_export_range()
 * @return string
 */
function ws_channel_export_range_label($range)
{
    $show = function ($date) {
        return ($date === '') ? '' : date('d.m.Y', (int) strtotime($date));
    };

    if (($range['from_date'] === '') && ($range['to_date'] === '')) {
        return lang('All messages');
    }

    if ($range['to_date'] === '') {
        return lang(array('string' => 'Messages from {var:1} on', 'vars' => $show($range['from_date'])));
    }

    if ($range['from_date'] === '') {
        return lang(array('string' => 'Messages up to {var:1}', 'vars' => $show($range['to_date'])));
    }

    return $show($range['from_date']) . ' – ' . $show($range['to_date']);
}

/**
 * Of these messages, the ones the export holds: deleted ones and the ones
 * outside the range stay out.
 *
 * @param array[] $messages payloads (ws_message_payload()), each may carry body
 * @param int     $from     0 for open
 * @param int     $to       0 for open
 * @return array[]
 */
function ws_channel_export_keep($messages, $from, $to)
{
    return array_values(array_filter((array) $messages, function ($message) use ($from, $to) {
        $time = (int) ($message['timestamp'] ?? 0);

        return empty($message['deleted'])
            && (((int) $from === 0) || ($time >= (int) $from))
            && (((int) $to === 0) || ($time <= (int) $to));
    }));
}

/**
 * A link that starts at the site's root, made to open from outside it.
 *
 * @param string $url
 * @param string $base scheme and host
 * @return string
 */
function ws_channel_export_url($url, $base)
{
    $url = (string) $url;

    return (($url !== '') && ($url[0] === '/') && ($base !== '')) ? rtrim($base, '/') . $url : $url;
}

/**
 * The name a message's writer is shown under.
 *
 * @param array $message payload
 * @return string
 */
function ws_channel_export_sender($message)
{
    $name = trim((string) ($message['sender']['name'] ?? ''));

    if ($name !== '') {
        return $name;
    }

    return ((string) ($message['sender_kind'] ?? '') === 'system') ? lang('Workspace') : lang('Unknown');
}

/**
 * A poll as one line: its question, each answer with its votes, and whether
 * it is closed.
 *
 * @param string $question the question, tags written out already
 * @param array  $poll     the payload's poll
 * @return string
 */
function ws_channel_export_poll_line($question, $poll)
{
    $answers = array();

    foreach ((array) ($poll['options'] ?? array()) as $option) {
        $answers[] = trim((string) $option['label']) . ' ' . (int) $option['count'];
    }

    return lang(array('string' => empty($poll['closed']) ? 'Poll: {var:1} — {var:2}' : 'Poll (closed): {var:1} — {var:2}',
        'vars' => array(trim($question), implode(' · ', $answers))));
}

/**
 * An approval request as one line: its title and outcome.
 *
 * @param array $approval the payload's approval
 * @return string
 */
function ws_channel_export_approval_line($approval)
{
    $sentences = array(
        'approved'  => 'Approved: {var:1}',
        'rejected'  => 'Rejected: {var:1}',
        'expired'   => 'Expired without a decision: {var:1}',
        'withdrawn' => 'Closed without a decision: {var:1}',
    );
    $outcome = (string) ($approval['outcome'] ?? '');
    $title = trim((string) ($approval['title'] ?? ''));

    if (!empty($approval['open']) || !isset($sentences[$outcome])) {
        return lang(array('string' => 'Approval request: {var:1} (waiting)', 'vars' => $title));
    }

    return lang(array('string' => $sentences[$outcome], 'vars' => $title));
}

/**
 * One message's words as Markdown: the text with its tags written out, or
 * the line of its poll or approval request, and its file as a link.
 *
 * @param array  $message payload with body (the stored text)
 * @param array  $refs    ws_refs_resolve() output for the export's tokens
 * @param string $base
 * @return string
 */
function ws_channel_export_text($message, $refs, $base)
{
    $body = rtrim(str_replace("\r\n", "\n", (string) ($message['body'] ?? '')));
    $text = ws_note_export_tokens($body, $refs, $base);

    if (!empty($message['poll'])) {
        $text = ws_channel_export_poll_line($text, $message['poll']);
    } elseif (!empty($message['approval'])) {
        $text = ws_channel_export_approval_line($message['approval']);
    }

    if (!empty($message['file']['name'])) {
        $link = '[' . str_replace(array('[', ']'), array('(', ')'), (string) $message['file']['name']) . '](' . str_replace(' ', '%20', ws_channel_export_url($message['file']['url'] ?? '', $base)) . ')';
        $text = ($text === '') ? $link : $text . "\n\n" . $link;
    }

    return $text;
}

/**
 * One message as a block of Markdown: the writer and the time, then its words.
 *
 * @param array  $message
 * @param array  $refs
 * @param string $base
 * @param string $time_format date() format of the time shown
 * @return string
 */
function ws_channel_export_block($message, $refs, $base, $time_format)
{
    $head = '**' . str_replace('*', '\\*', ws_channel_export_sender($message)) . '** · ' . date($time_format, (int) $message['timestamp']);
    $text = ws_channel_export_text($message, $refs, $base);

    return ($text === '') ? $head : $head . "\n" . $text;
}

/**
 * The whole export as one Markdown file.
 *
 * @param array   $head      channel, range (label), by, at (timestamp)
 * @param array[] $messages  payloads with body, oldest first; one may carry
 *                           discussion: title, messages
 * @param array[] $decisions payloads with body
 * @param array[] $files     payloads
 * @param array   $refs
 * @param string  $base
 * @return string
 */
function ws_channel_export_markdown($head, $messages, $decisions, $files, $refs, $base = '')
{
    $lines = array('# ' . (string) $head['channel'], '');
    $lines[] = lang(array('string' => 'Range: {var:1}', 'vars' => (string) $head['range'])) . '  ';
    $lines[] = lang(array('string' => 'Exported by {var:1} on {var:2}', 'vars' => array((string) $head['by'], date('d.m.Y H:i', (int) $head['at']))));

    if (!empty($decisions)) {
        $lines[] = '';
        $lines[] = '## ' . lang('Decisions');
        $lines[] = '';

        foreach ($decisions as $decision) {
            $text = preg_replace('/\s*\n\s*/', ' ', ws_channel_export_text($decision, $refs, $base));
            $lines[] = '- ' . date('d.m.Y', (int) $decision['timestamp']) . ' · ' . ws_channel_export_sender($decision) . ': ' . $text;
        }
    }

    $lines[] = '';
    $lines[] = '## ' . lang('Messages');

    if (empty($messages)) {
        $lines[] = '';
        $lines[] = lang('No messages in this range.');
    }

    $day = '';

    foreach ($messages as $message) {
        $date = date('d.m.Y', (int) $message['timestamp']);

        if ($date !== $day) {
            $day = $date;
            $lines[] = '';
            $lines[] = '### ' . $date;
        }

        $lines[] = '';
        $lines[] = ws_channel_export_block($message, $refs, $base, 'H:i');

        // A discussion under its message, quoted.
        if (!empty($message['discussion']['messages'])) {
            $quoted = array('**' . lang(array('string' => 'Discussion: {var:1}', 'vars' => (string) $message['discussion']['title'])) . '**');

            foreach ($message['discussion']['messages'] as $reply) {
                $quoted[] = '';
                $quoted[] = ws_channel_export_block($reply, $refs, $base, 'd.m.Y H:i');
            }

            $lines[] = '';

            foreach (explode("\n", implode("\n", $quoted)) as $line) {
                $lines[] = rtrim('> ' . $line);
            }
        }
    }

    if (!empty($files)) {
        $lines[] = '';
        $lines[] = '## ' . lang('Files');
        $lines[] = '';

        foreach ($files as $file) {
            if (empty($file['file']['name'])) {
                continue;
            }

            $lines[] = '- ' . ws_channel_export_text(array('file' => $file['file']), $refs, $base) . ' — ' . ws_channel_export_sender($file) . ', ' . date('d.m.Y', (int) $file['timestamp']);
        }
    }

    return implode("\n", $lines) . "\n";
}

/**
 * The name of the file offered: the channel's name made safe, and the date.
 *
 * @param string $channel
 * @return string without extension
 */
function ws_channel_export_name($channel)
{
    $name = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', '-', (string) $channel), " .-\t");

    if ($name === '') {
        $name = 'channel';
    }

    return mb_substr($name, 0, 80) . '-' . date('Y-m-d');
}

/**
 * The stored texts of these messages: the payloads carry only the reader's
 * own (raw).
 *
 * @param int[] $ids
 * @return array id => body
 */
function ws_channel_export_bodies($ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
    $out = array();

    foreach (array_chunk($ids, WS_CHANNEL_EXPORT_PAGE) as $chunk) {
        foreach ((array) db_items("SELECT id, body FROM ws_messages WHERE id IN (" . implode(',', $chunk) . ")") as $row) {
            $out[(int) $row['id']] = (string) $row['body'];
        }
    }

    return $out;
}

/**
 * The rows of a channel's current conversation within the range, oldest
 * first, read page by page from the newest.
 *
 * @param int $channel_id
 * @param int $from
 * @param int $to
 * @param int $room how many may still be taken
 * @return array[]|null null when more than $room are in the range
 */
function ws_channel_export_rows($channel_id, $from, $to, $room)
{
    $rows = array();
    $before = 0;

    while (true) {
        $page = ws_messages_page($channel_id, $before, WS_CHANNEL_EXPORT_PAGE);

        if (empty($page)) {
            break;
        }

        $before = (int) $page[0]['id'];

        for ($i = count($page) - 1; $i >= 0; $i--) {
            $time = (int) $page[$i]['created_at'];

            if (($to > 0) && ($time > $to)) {
                continue;
            }

            if (($from > 0) && ($time < $from)) {
                break 2;
            }

            if ((int) $page[$i]['deleted_at'] > 0) {
                continue;
            }

            $rows[] = $page[$i];

            if (count($rows) > $room) {
                return null;
            }
        }

        if (count($page) < WS_CHANNEL_EXPORT_PAGE) {
            break;
        }
    }

    return array_reverse($rows);
}

/**
 * Rows as the reader sees them, each payload with its stored text.
 *
 * @param array   $viewer
 * @param array[] $rows
 * @return array[]
 */
function ws_channel_export_payloads($viewer, $rows)
{
    $out = array();

    foreach (array_chunk((array) $rows, WS_CHANNEL_EXPORT_PAGE) as $chunk) {
        $bodies = array();

        foreach ($chunk as $row) {
            $bodies[(int) $row['id']] = (string) $row['body'];
        }

        foreach (ws_message_payloads($viewer, $chunk) as $payload) {
            $payload['body'] = $bodies[(int) $payload['id']] ?? '';
            $out[] = $payload;
        }
    }

    return $out;
}

/**
 * Everything an export of a channel holds, for this reader.
 *
 * @param array $viewer
 * @param array $channel
 * @param array $range ws_channel_export_range()
 * @return array ok, error, messages, decisions, files, refs, count
 */
function ws_channel_export_collect($viewer, $channel, $range)
{
    $too_many = lang(array('string' => 'This range holds more than {var:1} messages. Choose a shorter range.', 'vars' => number_format(WS_CHANNEL_EXPORT_MAX, 0, ',', '.')));
    $refused = array('ok' => false, 'error' => $too_many, 'messages' => array(), 'decisions' => array(), 'files' => array(), 'refs' => array(), 'count' => 0);

    $rows = ws_channel_export_rows((int) $channel['id'], $range['from'], $range['to'], WS_CHANNEL_EXPORT_MAX);

    if ($rows === null) {
        return $refused;
    }

    $messages = ws_channel_export_keep(ws_channel_export_payloads($viewer, $rows), $range['from'], $range['to']);
    $count = count($messages);
    $tokens = array();

    // A discussion's messages under the message it is about, when the
    // reader is in it (ws_can_read_channel()); they count to the limit.
    foreach ($messages as $index => $message) {
        if (empty($message['thread']['id'])) {
            continue;
        }

        $thread = ws_channel((int) $message['thread']['id']);

        if (!$thread || !ws_can_read_channel($viewer, $thread)) {
            continue;
        }

        $thread_rows = ws_channel_export_rows((int) $thread['id'], 0, 0, WS_CHANNEL_EXPORT_MAX - $count);

        if ($thread_rows === null) {
            return $refused;
        }

        $replies = ws_channel_export_keep(ws_channel_export_payloads($viewer, $thread_rows), 0, 0);
        $count += count($replies);

        if (!empty($replies)) {
            $messages[$index]['discussion'] = array('title' => (string) $thread['name'], 'messages' => $replies);
        }
    }

    // Oldest first, as the messages.
    $decisions = array_reverse(ws_channel_export_keep(ws_channel_decisions($viewer, (int) $channel['id']), $range['from'], $range['to']));
    $bodies = ws_channel_export_bodies(array_map(function ($decision) { return (int) $decision['id']; }, $decisions));

    foreach ($decisions as $index => $decision) {
        $decisions[$index]['body'] = $bodies[(int) $decision['id']] ?? '';
    }

    $files = ws_channel_export_keep(ws_channel_files($viewer, (int) $channel['id']), $range['from'], $range['to']);

    // Every tag of the export resolved once, for this reader.
    $texts = array();

    foreach ($messages as $message) {
        $texts[] = $message['body'];

        foreach ($message['discussion']['messages'] ?? array() as $reply) {
            $texts[] = $reply['body'];
        }
    }

    foreach ($decisions as $decision) {
        $texts[] = $decision['body'];
    }

    foreach ($texts as $text) {
        foreach (ws_tokens($text) as $token) {
            $tokens[] = $token;
        }
    }

    return array(
        'ok'        => true,
        'error'     => '',
        'messages'  => $messages,
        'decisions' => $decisions,
        'files'     => array_reverse($files),
        'refs'      => ws_refs_resolve($viewer, $tokens),
        'count'     => $count,
    );
}

/**
 * One message on the print page: the writer, the time, and what the screen
 * draws of it (ws_render_body() through the payload), or its line.
 *
 * @param array  $message payload
 * @param string $time_format
 * @return string HTML
 */
function ws_channel_export_print_message($message, $time_format)
{
    $html = '<div class="msg"><div class="who"><strong>' . h(ws_channel_export_sender($message)) . '</strong> · '
        . h(date($time_format, (int) $message['timestamp'])) . '</div>';

    if (!empty($message['poll'])) {
        $html .= '<p>' . h(ws_channel_export_poll_line(trim(strip_tags((string) $message['html'])), $message['poll'])) . '</p>';
    } elseif (!empty($message['approval'])) {
        $html .= '<p>' . h(ws_channel_export_approval_line($message['approval'])) . '</p>';
    } else {
        $html .= '<div class="body ws-body">' . (string) $message['html'] . '</div>';
    }

    if (!empty($message['file']['name'])) {
        $html .= '<div class="file"><a href="' . h((string) $message['file']['url']) . '">' . h((string) $message['file']['name']) . '</a></div>';
    }

    if (!empty($message['task_html'])) {
        $html .= '<div class="body ws-body">' . (string) $message['task_html'] . '</div>';
    }

    if (!empty($message['discussion']['messages'])) {
        $html .= '<div class="thread"><div class="thread-title">' . h(lang(array('string' => 'Discussion: {var:1}', 'vars' => (string) $message['discussion']['title']))) . '</div>';

        foreach ($message['discussion']['messages'] as $reply) {
            $html .= ws_channel_export_print_message($reply, 'd.m.Y H:i');
        }

        $html .= '</div>';
    }

    return $html . '</div>';
}
