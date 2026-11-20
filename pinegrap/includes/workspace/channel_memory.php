<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a channel's memory: entries the members and the agents keep
 * (a title, a text, tags, pinned, an expiry) in any channel, and the index
 * every agent brief carries (ids, titles and tags, the pinned entries first,
 * then the newest, then the channel's decision lines, within a size the
 * brief can afford): the agent reads the entries it needs on demand. A
 * record may be filed under a project; a record with channel_id 0 is the
 * general memory the agent channels share, kept by whoever may post in an
 * agent channel. Who marked a message (a person or an application) is read
 * here too.
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
 * The longest title, text and tag of an entry, and its most tags.
 */
define('WS_MEMORY_TITLE_MAX', 150);
define('WS_MEMORY_TEXT_MAX', 4000);
define('WS_MEMORY_TAG_MAX', 32);
define('WS_MEMORY_TAGS_MAX', 10);
define('WS_MEMORY_PROJECT_MAX', 80);

/**
 * The digest's default size in characters: room for the brief's other parts.
 */
define('WS_MEMORY_DIGEST_MAX', 6000);

/**
 * How many of the channel's decisions, the newest, the brief lists; a count
 * line names the rest, which the agent lists with workspace.decisions.list.
 */
define('WS_MEMORY_BRIEF_DECISIONS', 10);

// A writer's memory changes in one channel within this many seconds go into
// one note: the channel's last note is extended rather than a new one written.
define('WS_MEMORY_NOTE_BURST', 300);

// A note longer than this starts a new one, so a long burst stays readable.
define('WS_MEMORY_NOTE_MAX', 3000);

/**
 * An entry's tags as stored: trimmed, lower case, without a leading #,
 * each once, in the order given. Pure.
 *
 * @param string|string[] $tags a list, or one text split at commas and spaces
 * @return string[]
 */
function ws_memory_tags($tags)
{
    if (!is_array($tags)) {
        $tags = preg_split('/[\s,]+/u', (string) $tags);
    }

    $out = array();

    foreach ($tags as $tag) {
        $tag = mb_strtolower(ltrim(trim((string) $tag), '#'));

        if (($tag !== '') && !in_array($tag, $out, true)) {
            $out[] = $tag;
        }
    }

    return $out;
}

/**
 * A record's project as stored: trimmed, inner spaces folded to one, ''
 * for none. Its case is kept: a project is a heading people read. Pure.
 *
 * @param mixed $project
 * @return string
 */
function ws_memory_project($project)
{
    return trim((string) preg_replace('/\s+/u', ' ', (string) $project));
}

/**
 * Whether a record (or a channel given for one) is the general memory:
 * channel_id 0, in no channel. Pure.
 *
 * @param array|null $row ws_channel_memory, or a channel row (its id)
 * @param string     $key the field holding the channel id
 * @return bool
 */
function ws_memory_is_general($row, $key = 'channel_id')
{
    return is_array($row) && array_key_exists($key, $row) && ((int) $row[$key] === 0);
}

/**
 * What ws_memory_check() and the routes take for the general memory where
 * they take a channel: an id of 0 and nothing else.
 *
 * @return array id
 */
function ws_memory_general_channel()
{
    return array('id' => 0);
}

/**
 * What is wrong with a memory entry: '' when nothing is. Pure: the time is
 * given so an expiry in the past is refused against the caller's clock.
 *
 * @param array $entry title, text, tags (ws_memory_tags() form), expires_at
 *                     (0 for never), project (optional)
 * @param int   $now
 * @return string
 */
function ws_memory_entry_problem($entry, $now)
{
    $fault = ws_memory_entry_fault($entry, $now);

    return $fault ? $fault['error'] : '';
}

/**
 * The first fault of a memory entry and the field it is in, or null. Pure.
 *
 * @param array $entry as ws_memory_entry_problem()
 * @param int   $now
 * @return array|null field, error
 */
function ws_memory_entry_fault($entry, $now)
{
    $title = trim((string) ($entry['title'] ?? ''));
    $text = trim((string) ($entry['text'] ?? ''));
    $tags = (array) ($entry['tags'] ?? array());
    $expires_at = (int) ($entry['expires_at'] ?? 0);

    $fault = function ($field, $error) {
        return array('field' => $field, 'error' => $error);
    };

    if ($title === '') {
        return $fault('title', lang('A memory entry needs a title.'));
    }

    if (mb_strlen($title) > WS_MEMORY_TITLE_MAX) {
        return $fault('title', lang(array('string' => 'A memory entry\'s title is at most {var:1} characters.', 'vars' => WS_MEMORY_TITLE_MAX)));
    }

    if (mb_strlen(ws_memory_project($entry['project'] ?? '')) > WS_MEMORY_PROJECT_MAX) {
        return $fault('project', lang(array('string' => 'A memory entry\'s project is at most {var:1} characters.', 'vars' => WS_MEMORY_PROJECT_MAX)));
    }

    if (mb_strlen($text) > WS_MEMORY_TEXT_MAX) {
        return $fault('text', lang(array('string' => 'A memory entry\'s text is at most {var:1} characters.', 'vars' => WS_MEMORY_TEXT_MAX)));
    }

    if (count($tags) > WS_MEMORY_TAGS_MAX) {
        return $fault('tags', lang(array('string' => 'A memory entry has at most {var:1} tags.', 'vars' => WS_MEMORY_TAGS_MAX)));
    }

    foreach ($tags as $tag) {
        if (!preg_match('/^[\p{Ll}\p{Lo}\p{N}][\p{Ll}\p{Lo}\p{N}_.\-]*$/u', (string) $tag) || (mb_strlen((string) $tag) > WS_MEMORY_TAG_MAX)) {
            return $fault('tags', lang(array('string' => 'A tag is one word of letters, digits, dots, dashes or underscores, at most {var:1} characters.', 'vars' => WS_MEMORY_TAG_MAX)));
        }
    }

    if (($expires_at < 0) || (($expires_at > 0) && ($expires_at <= (int) $now))) {
        return $fault('expires_at', lang('A memory entry\'s expiry must be in the future, or left empty.'));
    }

    return null;
}

/**
 * The index of a channel's memory for an agent's brief: the live entries,
 * pinned first and then the newest, each as "- #id title [tags]" on one
 * line (no texts: the agent reads the entries it needs with
 * workspace.memory.get), then the newest WS_MEMORY_BRIEF_DECISIONS decision
 * lines and a line counting the older ones. In an agent channel the
 * general memory's index comes first, each line naming the record's
 * project, inside the same size. A line that
 * no longer fits is left out whole, never cut, with every line after it,
 * and the count left out closes the digest so the agent knows to read the
 * rest. Pure.
 *
 * @param array[] $entries   id, title, tags, pinned, expires_at, updated_at
 * @param array[]  $decisions body, marked_at (newest first)
 * @param int      $now
 * @param int      $max       characters
 * @param int|null $decisions_total the channel's decisions, when $decisions
 *                                  holds only the newest; null counts $decisions
 * @param array[]  $general   the general memory's records, as $entries plus project
 * @param bool     $across    the decisions are every agent channel's: the
 *                            heading says so and a decision's channel
 *                            (its 'channel' name, '' for this one) leads its line
 * @return string '' when there is nothing to tell
 */
function ws_memory_digest($entries, $decisions, $now, $max = WS_MEMORY_DIGEST_MAX, $decisions_total = null, $general = array(), $across = false)
{
    $given = count((array) $decisions);
    $decisions = array_slice(array_values((array) $decisions), 0, WS_MEMORY_BRIEF_DECISIONS);
    $older = max(0, (($decisions_total === null) ? $given : (int) $decisions_total) - count($decisions));
    $live = function ($rows) use ($now) {
        $out = array();

        foreach ((array) $rows as $entry) {
            $expires_at = (int) ($entry['expires_at'] ?? 0);

            if (($expires_at === 0) || ($expires_at > (int) $now)) {
                $out[] = $entry;
            }
        }

        usort($out, function ($a, $b) {
            $pinned = (int) !empty($b['pinned']) - (int) !empty($a['pinned']);

            if ($pinned !== 0) {
                return $pinned;
            }

            $newer = (int) ($b['updated_at'] ?? 0) - (int) ($a['updated_at'] ?? 0);

            return ($newer !== 0) ? $newer : ((int) ($b['id'] ?? 0) - (int) ($a['id'] ?? 0));
        });

        return $out;
    };

    $one_line = function ($text) {
        return trim(preg_replace('/\s+/u', ' ', (string) $text));
    };

    $sections = array();

    foreach (array('general' => $general, 'memory' => $entries) as $key => $rows) {
        foreach ($live($rows) as $entry) {
            $tags = ws_memory_tags($entry['tags'] ?? array());
            $project = ws_memory_project($entry['project'] ?? '');
            $line = '- #' . (int) ($entry['id'] ?? 0) . ' ' . (($key === 'general') && ($project !== '') ? $one_line($project) . ': ' : '') . $one_line($entry['title'] ?? '');

            if ($tags) {
                $line .= ' [' . implode(', ', $tags) . ']';
            }

            $sections[$key][] = $line;
        }
    }

    foreach ($decisions as $decision) {
        $body = $one_line($decision['body'] ?? '');

        $where = $across ? $one_line($decision['channel'] ?? '') : '';

        if ($body !== '') {
            $sections['decisions'][] = '- ' . (($where !== '') ? '#' . $where . ': ' : '') . $body;
        }
    }

    if (!$sections && ($older === 0)) {
        return '';
    }

    $headings = array(
        'general'   => 'General memory, shared by the agent channels (read an entry with workspace.memory.get, its id without the #):',
        'memory'    => 'Channel memory (read an entry with workspace.memory.get, its id without the #):',
        'decisions' => $across ? 'Decisions in the agent channels (one made in another channel names it first):' : 'Decisions in this channel:',
    );
    $out = array();
    $used = 0;
    $left = 0;
    $left_general = false;
    $full = false;

    foreach ($headings as $key => $heading) {
        if (empty($sections[$key])) {
            continue;
        }

        $kept = array();

        foreach ($sections[$key] as $line) {
            $cost = mb_strlen($line) + 1 + ($kept ? 0 : mb_strlen($heading) + 2);

            // Once a line does not fit, the lines after it wait too, so a
            // short old line never takes the place of a newer one.
            if ($full || ($used + $cost > $max)) {
                $full = true;
                $left++;
                $left_general = $left_general || ($key === 'general');
                continue;
            }

            $kept[] = $line;
            $used += $cost;
        }

        if ($kept) {
            $out[] = $heading . "\n" . implode("\n", $kept);
        }
    }

    // One count closes the digest: the lines the size left out and the
    // older decisions together, or the older decisions alone.
    $decisions_list = $across ? 'workspace.decisions.list (agent_channels only)' : 'workspace.decisions.list';

    if ($left > 0) {
        $out[] = '(' . ($left + $older) . ' more not shown: list them with ' . ($left_general ? 'workspace.memory.general.list, ' : '') . 'workspace.memory.list and ' . $decisions_list . '.)';
    } elseif ($older > 0) {
        $out[] = '(' . $older . ' older decisions not shown: list them with ' . $decisions_list . '.)';
    }

    return implode("\n\n", $out);
}

/* ---------------------------------------------------------------------------
   The rows
   --------------------------------------------------------------------------- */

/**
 * Whether the memory table is there: files land before the upgrade runs.
 *
 * @return bool
 */
function ws_memory_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_channel_memory', 'updated_by_id');
    }

    return $ready;
}

/**
 * Whether ws_channel_memory has the project column (step 11.0.236), which
 * the general memory needs too: before it, channel_id 0 is never written.
 *
 * @return bool
 */
function ws_memory_project_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_memory_ready() && pg_schema_has('ws_channel_memory', 'project');
    }

    return $ready;
}

/**
 * Whether this person reads and writes the general memory: they may post in
 * an agent channel (the channel memory's rule carried over). An application
 * acts as the person who owns it.
 *
 * @param array $viewer
 * @return bool
 */
function ws_memory_general_can($viewer)
{
    static $cache = array();

    if (!ws_memory_project_ready() || !function_exists('ws_agent_channel_ready') || !ws_agent_channel_ready() || empty($viewer['member'])) {
        return false;
    }

    $key = (int) ($GLOBALS['ws_membership_generation'] ?? 0) . ':' . (int) ($viewer['id'] ?? 0);

    if (!array_key_exists($key, $cache)) {
        $cache[$key] = false;

        foreach ((array) db_items("SELECT * FROM ws_channels WHERE agent_channel = 1 AND kind IN ('public', 'private', 'guest') AND archived_at = 0 ORDER BY id LIMIT 200") as $channel) {
            if (ws_can_post_channel($viewer, $channel)) {
                $cache[$key] = true;
                break;
            }
        }
    }

    return $cache[$key];
}

/**
 * Whether this person may read a record: its channel's reader, or for a
 * general record whoever keeps the general memory.
 *
 * @param array $viewer
 * @param array $row ws_channel_memory
 * @return bool
 */
function ws_memory_can_read($viewer, $row)
{
    if (ws_memory_is_general($row)) {
        return ws_memory_general_can($viewer);
    }

    return ws_can_read_channel($viewer, ws_channel($row['channel_id']));
}

/**
 * Whether this person may write into a channel's memory, or with
 * ws_memory_general_channel() into the general memory.
 *
 * @param array      $viewer
 * @param array|null $channel
 * @return bool
 */
function ws_memory_can_write($viewer, $channel)
{
    if (ws_memory_is_general($channel, 'id')) {
        return ws_memory_general_can($viewer);
    }

    return is_array($channel) && ws_can_post_channel($viewer, $channel);
}

/**
 * The memory a panel action addresses: general=1 the general memory (for
 * whoever keeps it), otherwise the channel of channel_id as
 * ws_action_channel() reads it.
 *
 * @param array $viewer
 * @param array $request
 * @return array|string the channel (ws_memory_general_channel() for the
 *                      general memory), or the refusal
 */
function ws_memory_action_channel($viewer, $request)
{
    if (empty($request['general'])) {
        return ws_action_channel($viewer, $request);
    }

    if (!ws_memory_project_ready()) {
        return lang('The database has to be updated first.');
    }

    return ws_memory_general_can($viewer) ? ws_memory_general_channel() : lang('The general memory is kept by whoever may post in an agent channel.');
}

/**
 * Whether ws_messages says who marked a message as a kind and an id.
 *
 * @return bool
 */
function ws_marks_kind_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_messages', 'marked_by_kind');
    }

    return $ready;
}

/**
 * Whether an application (an agent) marked the message, not a person.
 *
 * @param array $row ws_messages
 * @return bool
 */
function ws_mark_by_app($row)
{
    return ((string) ($row['marked_by_kind'] ?? 'user') === 'app') && ((int) ($row['marked_by'] ?? 0) > 0);
}

/**
 * The person ids among the markers of these messages, for ws_people().
 *
 * @param array[] $rows ws_messages
 * @return int[]
 */
function ws_mark_people_ids($rows)
{
    $ids = array();

    foreach ((array) $rows as $row) {
        if (((int) ($row['marked_by'] ?? 0) > 0) && !ws_mark_by_app($row)) {
            $ids[] = (int) $row['marked_by'];
        }
    }

    return array_values(array_unique($ids));
}

/**
 * The name of who marked a message: a person from $people, or the
 * application an agent writes through; '' when nobody did.
 *
 * @param array $row    ws_messages
 * @param array $people ws_people()
 * @return string
 */
function ws_mark_by_name($row, $people)
{
    $id = (int) ($row['marked_by'] ?? 0);

    if ($id <= 0) {
        return '';
    }

    if (ws_mark_by_app($row)) {
        return (string) (ws_app_sender($id)['name'] ?? '');
    }

    return (string) ($people[$id]['name'] ?? '');
}

/**
 * The name of a writer of an entry.
 *
 * @param string $kind user | app
 * @param int    $id
 * @return string
 */
function ws_memory_writer_name($kind, $id)
{
    if ((int) $id <= 0) {
        return '';
    }

    return ($kind === 'app') ? (string) (ws_app_sender((int) $id)['name'] ?? '') : ws_person_name((int) $id);
}

/**
 * Whether a channel shows the Memory tab: an agent is in it (an allowed
 * channel-agent row). Elsewhere its memory opens from the channel settings.
 *
 * @param int $channel_id
 * @return bool
 */
function ws_memory_tab_shown($channel_id)
{
    if (!function_exists('ws_agents_ready') || !ws_agents_ready()) {
        return false;
    }

    return (int) db_value("SELECT COUNT(*) FROM ws_channel_agents WHERE channel_id = '" . (int) $channel_id . "' AND allowed = 1") > 0;
}

/**
 * One entry, or null.
 *
 * @param int $id
 * @return array|null
 */
function ws_memory_row($id)
{
    if (!ws_memory_ready() || ((int) $id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_channel_memory WHERE id = '" . (int) $id . "'");

    return (is_array($row) && $row) ? $row : null;
}

/**
 * A channel's entries, pinned first and then the newest.
 *
 * @param int  $channel_id
 * @param bool $expired the expired ones too
 * @return array[]
 */
function ws_memory_rows($channel_id, $expired = true)
{
    if (!ws_memory_ready()) {
        return array();
    }

    return (array) db_items("SELECT * FROM ws_channel_memory
        WHERE channel_id = '" . (int) $channel_id . "'"
        . ($expired ? '' : " AND (expires_at = 0 OR expires_at > '" . time() . "')") . "
        ORDER BY pinned DESC, updated_at DESC, id DESC
        LIMIT 500");
}

/**
 * The first line of a record's text as a row of the Memory tab shows it:
 * the first line that is not blank, trimmed, cut at $max characters with an
 * ellipsis. Pure.
 *
 * @param string $text
 * @param int    $max
 * @return string
 */
function ws_memory_first_line($text, $max = 160)
{
    foreach (preg_split('/\R/u', (string) $text) as $line) {
        $line = trim((string) preg_replace('/\s+/u', ' ', $line));

        if ($line !== '') {
            return (mb_strlen($line) > $max) ? rtrim(mb_substr($line, 0, max(1, $max - 1))) . '…' : $line;
        }
    }

    return '';
}

/**
 * Records under their project headings, as the Memory tab folds them: the
 * projects in alphabetical order (case left aside), the records without a
 * project last, each heading's records in the order given (pinned first,
 * then the newest, as ws_memory_rows() reads them). Pure.
 *
 * @param array[] $entries ws_memory_present() (project)
 * @return array[] project ('' for none), entries
 */
function ws_memory_groups($entries)
{
    $groups = array();

    foreach ((array) $entries as $entry) {
        $project = ws_memory_project($entry['project'] ?? '');
        $key = mb_strtolower($project);

        if (!isset($groups[$key])) {
            $groups[$key] = array('project' => $project, 'entries' => array());
        }

        $groups[$key]['entries'][] = $entry;
    }

    uksort($groups, function ($a, $b) {
        if (($a === '') || ($b === '')) {
            return ($a === '') - ($b === '');
        }

        return strcmp((string) $a, (string) $b);
    });

    return array_values($groups);
}

/**
 * One part of the Memory tab: a memory's records with their text and first
 * line, under their project headings (ws_memory_groups()).
 *
 * @param string $key      general | channel
 * @param int    $channel_id 0 for the general memory
 * @param bool   $can_post whoever reads it may write it
 * @param bool   $can_move its records move to the general memory
 * @return array key, can_post, can_move, count, groups
 */
function ws_memory_panel_part($key, $channel_id, $can_post, $can_move = false)
{
    $entries = array();

    foreach (ws_memory_rows($channel_id) as $row) {
        $entries[] = ws_memory_present($row) + array('first_line' => ws_memory_first_line($row['text']));
    }

    return array(
        'key'      => $key,
        'can_post' => (bool) $can_post,
        'can_move' => (bool) $can_move,
        'count'    => count($entries),
        'groups'   => ws_memory_groups($entries),
    );
}

/**
 * An entry as the panel and the API give it.
 *
 * @param array $row ws_channel_memory
 * @param bool  $text with its text (the list leaves it out)
 * @return array
 */
function ws_memory_present($row, $text = true)
{
    $expires_at = (int) $row['expires_at'];
    $out = array(
        'id'         => (int) $row['id'],
        'channel_id' => (int) $row['channel_id'],
        'general'    => ws_memory_is_general($row),
        'project'    => ws_memory_project($row['project'] ?? ''),
        'title'      => (string) $row['title'],
        'tags'       => ws_memory_tags((string) $row['tags']),
        'pinned'     => !empty($row['pinned']),
        'expires_at' => $expires_at,
        'expired'    => ($expires_at > 0) && ($expires_at <= time()),
        'author'     => array('kind' => (string) $row['author_kind'], 'id' => (int) $row['author_id'], 'name' => ws_memory_writer_name((string) $row['author_kind'], (int) $row['author_id'])),
        'updated_by' => array('kind' => (string) $row['updated_by_kind'], 'id' => (int) $row['updated_by_id'], 'name' => ws_memory_writer_name((string) $row['updated_by_kind'], (int) $row['updated_by_id'])),
        'created_at' => (int) $row['created_at'],
        'updated_at' => (int) $row['updated_at'],
    );

    if ($text) {
        $out['text'] = (string) $row['text'];
    }

    return $out;
}

/**
 * What a write of an entry would store, checked: the fields given over the
 * entry as it is (a new one: empty, not pinned, never expiring, no project).
 * Whoever may post in the channel writes its memory, whoever may post in an
 * agent channel the general memory; the panel and the API share this.
 *
 * @param array      $viewer
 * @param array      $channel a channel row, or ws_memory_general_channel()
 * @param array      $input   title, text, tags (a list or one text), pinned, expires_at (0 never), project
 * @param array|null $current the entry changed, null for a new one
 * @return array ok, error, field, forbidden, values (title, text, tags as text, pinned, expires_at, project)
 */
function ws_memory_check($viewer, $channel, $input, $current = null)
{
    $fail = function ($error, $field = '', $forbidden = false) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'forbidden' => $forbidden, 'values' => array());
    };

    if (!ws_memory_ready()) {
        return $fail(lang('The database has to be updated first.'));
    }

    $general = ws_memory_is_general($channel, 'id');

    if ($general && !ws_memory_project_ready()) {
        return $fail(lang('The database has to be updated first.'));
    }

    if (!ws_memory_can_write($viewer, $channel)) {
        return $general
            ? $fail(lang('The general memory is kept by whoever may post in an agent channel.'), '', true)
            : $fail(lang('You cannot post in that channel.'), '', true);
    }

    if (!ws_memory_project_ready() && array_key_exists('project', (array) $input) && (ws_memory_project($input['project']) !== '')) {
        return $fail(lang('The database has to be updated first.'), 'project');
    }

    $pick = function ($key, $default) use ($input, $current) {
        if (array_key_exists($key, (array) $input) && ($input[$key] !== null)) {
            return $input[$key];
        }

        return ($current && array_key_exists($key, $current)) ? $current[$key] : $default;
    };

    $entry = array(
        'title'      => trim((string) $pick('title', '')),
        'text'       => trim(str_replace("\r\n", "\n", (string) $pick('text', ''))),
        'tags'       => ws_memory_tags($pick('tags', '')),
        'expires_at' => (int) $pick('expires_at', 0),
        'project'    => ws_memory_project($pick('project', '')),
    );

    // An expiry kept as it was may have passed meanwhile: only a new one is
    // held against the clock.
    $check = $entry;

    if ($current && ((int) $current['expires_at'] === $entry['expires_at'])) {
        $check['expires_at'] = 0;
    }

    $fault = ws_memory_entry_fault($check, time());

    if ($fault) {
        return $fail($fault['error'], $fault['field']);
    }

    $entry['tags'] = implode(',', $entry['tags']);
    $entry['pinned'] = (int) (bool) $pick('pinned', 0);

    return array('ok' => true, 'error' => '', 'field' => '', 'forbidden' => false, 'values' => $entry);
}

/**
 * Writes an entry checked by ws_memory_check().
 *
 * @param int        $channel_id 0 for the general memory
 * @param array      $values  ws_memory_check()['values']
 * @param string     $kind    user | app: who writes
 * @param int        $id      their user or application id
 * @param array|null $current the entry changed, null for a new one
 * @return int the entry's id
 */
function ws_memory_save($channel_id, $values, $kind, $id, $current = null)
{
    $kind = ($kind === 'app') ? 'app' : 'user';
    $now = time();
    $set = "title = '" . e($values['title']) . "',
        text = '" . e($values['text']) . "',
        tags = '" . e($values['tags']) . "',
        pinned = '" . (int) $values['pinned'] . "',
        expires_at = '" . (int) $values['expires_at'] . "',
        updated_by_kind = '" . $kind . "',
        updated_by_id = '" . (int) $id . "',
        updated_at = '" . $now . "'";

    if (ws_memory_project_ready()) {
        $set .= ",
        project = '" . e(ws_memory_project($values['project'] ?? '')) . "'";
    }

    if ($current) {
        db("UPDATE ws_channel_memory SET " . $set . " WHERE id = '" . (int) $current['id'] . "'");
        ws_memory_note((int) $current['channel_id'], (int) $current['id'], (string) $values['title'], 'changed', $kind, $id);

        return (int) $current['id'];
    }

    db("INSERT INTO ws_channel_memory SET " . $set . ",
        channel_id = '" . (int) $channel_id . "',
        author_kind = '" . $kind . "',
        author_id = '" . (int) $id . "',
        created_at = '" . $now . "'");

    $new_id = (int) mysqli_insert_id(db::$con);

    if ($new_id > 0) {
        ws_memory_note((int) $channel_id, $new_id, (string) $values['title'], 'added', $kind, $id);
    }

    return $new_id;
}

/**
 * Deletes an entry: whoever may post in its channel, or for a general record
 * whoever may post in an agent channel.
 *
 * @param array      $viewer
 * @param array      $row    ws_channel_memory
 * @param array|null $writer kind (user | app) and id of who deletes, for the
 *                           channel's note; null for the viewer as a person
 * @return array ok, error, forbidden
 */
function ws_memory_delete($viewer, $row, $writer = null)
{
    if (ws_memory_is_general($row)) {
        if (!ws_memory_general_can($viewer)) {
            return array('ok' => false, 'error' => lang('The general memory is kept by whoever may post in an agent channel.'), 'forbidden' => true);
        }
    } elseif (!ws_can_post_channel($viewer, ws_channel($row['channel_id']))) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'), 'forbidden' => true);
    }

    db("DELETE FROM ws_channel_memory WHERE id = '" . (int) $row['id'] . "'");

    $writer = is_array($writer) ? $writer : array('kind' => 'user', 'id' => (int) $viewer['id']);
    ws_memory_note((int) $row['channel_id'], (int) $row['id'], (string) $row['title'], 'deleted', (string) $writer['kind'], (int) $writer['id']);

    return array('ok' => true, 'error' => '', 'forbidden' => false);
}

/**
 * Why a channel's record may not move to the general memory, '' when it
 * may: a record of an agent channel, moved by whoever may post in that
 * channel and keeps the general memory. Pure.
 *
 * @param array|null $row         ws_channel_memory
 * @param array|null $channel     the record's channel
 * @param bool       $can_post    the mover may post in the channel
 * @param bool       $can_general the mover keeps the general memory
 * @return array|null error, forbidden; null when the move may go
 */
function ws_memory_move_fault($row, $channel, $can_post, $can_general)
{
    if (!is_array($row) || ws_memory_is_general($row)) {
        return array('error' => lang('This entry is in the general memory already.'), 'forbidden' => false);
    }

    if (!$can_post) {
        return array('error' => lang('You cannot post in that channel.'), 'forbidden' => true);
    }

    if (!ws_agent_channel_is($channel)) {
        return array('error' => lang('Only an agent channel\'s entries move to the general memory.'), 'forbidden' => false);
    }

    if (!$can_general) {
        return array('error' => lang('The general memory is kept by whoever may post in an agent channel.'), 'forbidden' => true);
    }

    return null;
}

/**
 * Checks moving a channel's record to the general memory.
 *
 * @param array $viewer
 * @param array $row ws_channel_memory
 * @return array ok, error, forbidden, unavailable (the database not updated yet)
 */
function ws_memory_move_check($viewer, $row)
{
    if (!ws_memory_project_ready() || !function_exists('ws_agent_channel_ready') || !ws_agent_channel_ready()) {
        return array('ok' => false, 'error' => lang('The database has to be updated first.'), 'forbidden' => false, 'unavailable' => true);
    }

    $channel = is_array($row) && !ws_memory_is_general($row) ? ws_channel($row['channel_id']) : null;
    $fault = ws_memory_move_fault($row, $channel, is_array($channel) && ws_can_post_channel($viewer, $channel), ws_memory_general_can($viewer));

    return $fault ? array('ok' => false, 'unavailable' => false) + $fault : array('ok' => true, 'error' => '', 'forbidden' => false, 'unavailable' => false);
}

/**
 * Moves a record checked by ws_memory_move_check() to the general memory:
 * the same id, project, tags and text, in no channel.
 *
 * @param array  $row  ws_channel_memory
 * @param string $kind user | app: who moves it
 * @param int    $id   their user or application id
 * @return void
 */
function ws_memory_move_general($row, $kind, $id)
{
    db("UPDATE ws_channel_memory SET
        channel_id = 0,
        updated_by_kind = '" . (($kind === 'app') ? 'app' : 'user') . "',
        updated_by_id = '" . (int) $id . "',
        updated_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "'");

    ws_memory_note(0, (int) $row['id'], (string) $row['title'], 'changed', $kind, $id);
}

/* ---------------------------------------------------------------------------
   The channel's note when its memory changes
   --------------------------------------------------------------------------- */

/**
 * The note's first line: which memory changed. Pure but for lang().
 *
 * @param bool $general the general memory (channel_id 0)
 * @return string
 */
function ws_memory_note_head($general)
{
    return $general ? lang('General memory updated') : lang('Channel memory updated');
}

/**
 * One entry's line of the note. Pure but for lang().
 *
 * @param int    $entry_id
 * @param string $title
 * @param string $action added | changed | deleted
 * @return string
 */
function ws_memory_note_entry_line($entry_id, $title, $action)
{
    // One line whatever the title holds.
    $title = trim(preg_replace('/\s+/u', ' ', (string) $title));
    $vars = array((string) (int) $entry_id, $title);

    if ($action === 'deleted') {
        return lang(array('string' => '#{var:1} {var:2} - deleted', 'vars' => $vars));
    }

    if ($action === 'changed') {
        return lang(array('string' => '#{var:1} {var:2} - changed', 'vars' => $vars));
    }

    return lang(array('string' => '#{var:1} {var:2} - added', 'vars' => $vars));
}

/**
 * The note's last line: who changed it. A person by their mention tag, an
 * application by its name. Pure but for lang().
 *
 * @param string $kind user | app
 * @param int    $id
 * @param string $app_name the application's name, for kind app
 * @return string
 */
function ws_memory_note_by_line($kind, $id, $app_name = '')
{
    $who = ($kind === 'app') ? (((string) $app_name !== '') ? (string) $app_name : ('#' . (int) $id)) : ('<@user:' . (int) $id . '>');

    return lang(array('string' => 'by {var:1}', 'vars' => $who));
}

/**
 * The note's whole text. Pure.
 *
 * @param string   $head  ws_memory_note_head()
 * @param string[] $lines ws_memory_note_entry_line() lines
 * @param string   $by    ws_memory_note_by_line()
 * @return string
 */
function ws_memory_note_body($head, $lines, $by)
{
    return $head . "\n" . implode("\n", (array) $lines) . "\n" . $by;
}

/**
 * The burst rule: the text the channel's last note takes with one more
 * entry line, or null when a new note is written instead. The last
 * message is extended only when it is a system note of the same memory
 * (its head) by the same writer (its last line), written within
 * WS_MEMORY_NOTE_BURST seconds and still short enough. A line already in
 * it (the same entry and action) leaves it as it is. Pure.
 *
 * @param array|null $last  the channel's last message: kind, sender_kind, body, created_at, deleted_at
 * @param string     $head
 * @param string     $line
 * @param string     $by
 * @param int        $now
 * @return string|null
 */
function ws_memory_note_merge($last, $head, $line, $by, $now)
{
    if (!is_array($last) || (($last['sender_kind'] ?? '') !== 'system') || (($last['kind'] ?? '') !== 'system')
        || ((int) ($last['deleted_at'] ?? 0) > 0) || (((int) $now - (int) ($last['created_at'] ?? 0)) > WS_MEMORY_NOTE_BURST)) {
        return null;
    }

    $parts = explode("\n", (string) $last['body']);

    if ((count($parts) < 3) || ($parts[0] !== $head) || ($parts[count($parts) - 1] !== $by)) {
        return null;
    }

    $lines = array_slice($parts, 1, -1);

    if (in_array($line, $lines, true)) {
        return (string) $last['body'];
    }

    $lines[] = $line;
    $body = ws_memory_note_body($head, $lines, $by);

    return (mb_strlen($body) > WS_MEMORY_NOTE_MAX) ? null : $body;
}

/**
 * The channels a note goes to: the entry's own channel, or for the general
 * memory every agent channel that is not archived.
 *
 * @param int $channel_id 0 for the general memory
 * @return int[]
 */
function ws_memory_note_channels($channel_id)
{
    if ((int) $channel_id > 0) {
        return array((int) $channel_id);
    }

    if (!function_exists('ws_agent_channel_ready') || !ws_agent_channel_ready()) {
        return array();
    }

    return array_map('intval', array_column((array) db_items("SELECT id FROM ws_channels
        WHERE agent_channel = 1 AND kind IN ('public', 'private', 'guest') AND archived_at = 0 ORDER BY id LIMIT 200"), 'id'));
}

/**
 * Tells the channel that its memory changed: a system note (drawn as
 * ws-msg-system) naming the entry, the change and who made it. A system
 * message wakes no agent, makes no agent request and is no step of a reply
 * chain, so the loop guard never counts it. A burst by the same writer
 * extends the channel's last note (ws_memory_note_merge()).
 *
 * @param int    $channel_id 0 for the general memory: the note goes into every agent channel
 * @param int    $entry_id
 * @param string $title
 * @param string $action added | changed | deleted
 * @param string $kind   user | app: who changed it
 * @param int    $id     their user or application id
 * @return int how many channels were told
 */
function ws_memory_note($channel_id, $entry_id, $title, $action, $kind, $id)
{
    $kind = ($kind === 'app') ? 'app' : 'user';
    $head = ws_memory_note_head((int) $channel_id === 0);
    $line = ws_memory_note_entry_line($entry_id, $title, $action);
    $by = ws_memory_note_by_line($kind, $id, ($kind === 'app') ? (string) (ws_app_sender((int) $id)['name'] ?? '') : '');
    $now = time();
    $told = 0;

    foreach (ws_memory_note_channels($channel_id) as $target) {
        $last_id = (int) db_value("SELECT last_message_id FROM ws_channels WHERE id = '" . $target . "'");
        $last = ($last_id > 0) ? db_item("SELECT id, kind, sender_kind, body, created_at, deleted_at FROM ws_messages WHERE id = '" . $last_id . "' AND channel_id = '" . $target . "'") : null;
        $merged = ws_memory_note_merge($last ? $last : null, $head, $line, $by, $now);

        if ($merged !== null) {
            if ($merged !== (string) $last['body']) {
                db("UPDATE ws_messages SET body = '" . e($merged) . "' WHERE id = '" . (int) $last['id'] . "'");

                // Open screens read the message again (interact.php).
                if (function_exists('ws_message_touch')) {
                    ws_message_touch((int) $last['id']);
                }
            }
        } else {
            ws_message_system($target, ws_memory_note_body($head, array($line), $by));
        }

        $told++;
    }

    return $told;
}

/**
 * Deletes the entries expired more than 30 days ago and those of channels
 * that are gone (a discussion deleted with what was said in it). A general
 * record (channel_id 0) has no channel to lose: only its expiry ends it.
 *
 * @param int $limit
 * @return int how many
 */
function ws_memory_sweep($limit = 500)
{
    if (!ws_memory_ready()) {
        return 0;
    }

    $ids = array_map('intval', (array) db_values("SELECT m.id FROM ws_channel_memory m
        LEFT JOIN ws_channels c ON c.id = m.channel_id
        WHERE (m.expires_at > 0 AND m.expires_at < '" . (time() - 30 * 86400) . "') OR (c.id IS NULL AND m.channel_id > 0)
        LIMIT " . max(1, (int) $limit)));

    if ($ids) {
        db("DELETE FROM ws_channel_memory WHERE id IN (" . implode(',', $ids) . ")");
    }

    return count($ids);
}

/**
 * The memory part of an agent's brief for a channel: in an agent channel the
 * index of the general memory first (for an asker who keeps it), then the
 * index of its live entries and its decision lines (ws_memory_digest()),
 * an agent channel's taken from every agent channel the asker may read,
 * the text as the asker reads it.
 *
 * @param array $viewer
 * @param array $channel
 * @return string '' when there is nothing
 */
function ws_memory_brief($viewer, $channel)
{
    if (!$channel) {
        return '';
    }

    // An agent channel's brief reads the decisions of every agent channel
    // the asker may read, each line naming the channel it was made in.
    $readable = ws_agent_channel_is($channel) ? ws_agent_channel_readable($viewer) : array();
    $ids = ws_agent_channel_decision_ids($channel, $readable, 'agents');
    $across = (count($ids) > 1);
    $named = function ($channel_id) use ($channel, $readable) {
        return ((int) $channel_id !== (int) $channel['id']) ? (string) ($readable[(int) $channel_id]['name'] ?? '') : '';
    };
    $decisions = array();
    $where = "channel_id IN (" . implode(',', $ids) . ") AND kind = 'decision' AND deleted_at = 0";

    foreach ((array) db_items("SELECT channel_id, body, marked_at FROM ws_messages
        WHERE " . $where . "
        ORDER BY marked_at DESC, id DESC
        LIMIT " . (int) WS_MEMORY_BRIEF_DECISIONS) as $row) {
        $decisions[] = array('body' => ws_plain_text($viewer, (string) $row['body']), 'marked_at' => (int) $row['marked_at'], 'channel' => $named($row['channel_id']));
    }

    $total = (count($decisions) < WS_MEMORY_BRIEF_DECISIONS) ? count($decisions) : (int) db_value("SELECT COUNT(*) FROM ws_messages WHERE " . $where);

    // An answered agent question is a decision with no outcome line
    // (agent_question.php): question — answer (who), in time order with the rest.
    if (function_exists('ws_agent_question_decisions')) {
        $answered = ws_agent_question_decisions($viewer, $channel, WS_MEMORY_BRIEF_DECISIONS, $ids);

        foreach ($answered['lines'] as $index => $line) {
            $answered['lines'][$index]['channel'] = $named($line['channel_id'] ?? $channel['id']);
        }

        $decisions = ws_agent_question_decisions_merge($decisions, $answered['lines'], WS_MEMORY_BRIEF_DECISIONS);
        $total += $answered['total'];
    }

    $general = ws_memory_brief_general($viewer, $channel) ? ws_memory_rows(0, false) : array();

    return ws_memory_digest(ws_memory_rows($channel['id'], false), $decisions, time(), WS_MEMORY_DIGEST_MAX, $total, $general, $across);
}

/**
 * Whether a brief asked in this channel carries the general memory's index:
 * an agent channel, asked by someone who keeps the general memory.
 *
 * @param array $viewer
 * @param array $channel
 * @return bool
 */
function ws_memory_brief_general($viewer, $channel)
{
    return function_exists('ws_agent_channel_is') && ws_agent_channel_is($channel) && ws_memory_general_can($viewer);
}

/**
 * The words of the Memory tab (assets/js/workspace_memory.js).
 *
 * @return array key => text
 */
function ws_memory_js_strings()
{
    return array(
        'tab_memory'        => lang('Memory'),
        'mem_open'          => lang('Channel memory'),
        'mem_help'          => lang('What the members and the agents of this channel should keep knowing. Agents see the titles and tags in every request and read the entries they need.'),
        'mem_empty'         => lang('Nothing in this channel\'s memory yet.'),
        'mem_add'           => lang('New entry'),
        'mem_edit'          => lang('Edit'),
        'mem_delete'        => lang('Delete'),
        'mem_delete_ask'    => lang('Delete this memory entry? It cannot be undone.'),
        'mem_title'         => lang('Title'),
        'mem_text'          => lang('Text'),
        'mem_tags'          => lang('Tags'),
        'mem_tags_help'     => lang('Up to 10 words, separated by commas.'),
        'mem_pinned'        => lang('Pinned: first in the list agents see'),
        'mem_expires'       => lang('Expires on'),
        'mem_expires_help'  => lang('Left empty, it never expires. An expired entry leaves the agents\' list and is deleted 30 days later.'),
        'mem_save'          => lang('Save'),
        'mem_cancel'        => lang('Cancel'),
        'mem_saved'         => lang('Saved.'),
        'mem_expired'       => lang('Expired'),
        'mem_pin_mark'      => lang('Pinned'),
        'mem_by'            => ws_js_template('Written by {var:1}', 1),
        'mem_changed_by'    => ws_js_template('Changed by {var:1}', 1),
        'mem_until'         => ws_js_template('Until {var:1}', 1),
        'mem_general'       => lang('General memory'),
        'mem_general_help'  => lang('Shared by every agent channel.'),
        'mem_general_empty' => lang('Nothing in the general memory yet.'),
        'mem_channel'       => lang('This channel\'s memory'),
        'mem_project'       => lang('Project'),
        'mem_project_help'  => lang('Entries are listed under their project. Left empty, under No project.'),
        'mem_no_project'    => lang('No project'),
        'mem_changed_on'    => ws_js_template('Changed on {var:1}', 1),
        'mem_move'          => lang('Move to the general memory'),
        'mem_move_ask'      => lang('Move this entry to the general memory? Every agent channel sees it then.'),
        'mem_moved'         => lang('Moved to the general memory.'),
    );
}
