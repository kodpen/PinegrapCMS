<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - channel templates: what a channel for recurring work ("a new
 * customer", "a website delivery", "the month-end closing") is set up with.
 * A template carries a set of tasks with dates counted from the day it is
 * applied, the rule for whom each task goes to and its checklist, the notes
 * to share, the channel's summary, the message pinned to its top and a first
 * message for a new channel.
 *
 * A template is applied when a channel is made from it, or later to a channel
 * that is already open. It is applied with the rights of whoever applies it:
 * the tasks are made by ws_task_create() as that person, a task that would go
 * to somebody they may not give work to is made with nobody on it, and they
 * are told how many. A summary or a pinned message the channel already has
 * is left as it is. Nothing is written until everything was checked; the
 * module's queries run without a transaction, so a write that fails half way
 * says in the channel which tasks were made before it.
 *
 * Three templates are built in and live in code (ws_templates_builtin()):
 * they are listed beside the site's own, may be copied and changed, and are
 * never written to the database. The site's own live in ws_templates; their
 * tasks and notes are kept as JSON in body (ws_template_body_clean()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most tasks a template holds. */
define('WS_TEMPLATE_TASKS_MAX', 50);

/** The most notes a template holds. */
define('WS_TEMPLATE_NOTES_MAX', 10);

/** The most checklist items one task of a template holds. */
define('WS_TEMPLATE_ITEMS_MAX', 30);

/** The longest body, in bytes of JSON: what a TEXT column keeps. */
define('WS_TEMPLATE_BODY_BYTES', 60000);

/**
 * Is the template table there (2026.4.8, 8.88)?
 *
 * @return bool
 */
function ws_templates_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_templates'") === 1);
    }

    return $ready;
}

/**
 * May this person make, change and archive templates? Staff, and the basic
 * users who may change the workspace settings. Applying one needs no more
 * than being in the team.
 *
 * @param array $viewer
 * @return bool
 */
function ws_can_write_templates($viewer)
{
    return !empty($viewer['member']) && !empty($viewer['settings']) && ws_templates_ready();
}

/* ---------------------------------------------------------------------------
   The body: tasks and notes as JSON
   --------------------------------------------------------------------------- */

/**
 * Is this an assignment rule a template task may carry?
 *
 * creator         whoever applies the template
 * department_lead the lead of the template's (or the channel's) department
 * department:ID   the department's pool, nobody on it yet
 * user:ID         that team member
 * none            nobody
 *
 * @param string $rule
 * @return bool
 */
function ws_template_assign_valid($rule)
{
    return (bool) preg_match('/^(creator|department_lead|none|department:[1-9][0-9]{0,9}|user:[1-9][0-9]{0,9})$/', (string) $rule);
}

/**
 * A number of days as a template keeps it: null for none, otherwise 0-3650.
 *
 * @param mixed $value
 * @return int|null
 */
function ws_template_days($value)
{
    if (($value === null) || ($value === '') || is_array($value) || is_bool($value)) {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    return max(0, min(3650, (int) $value));
}

/**
 * Checks and cleans the tasks and notes of a template. Unknown keys are
 * dropped, numbers are kept in their ranges, texts are cut to their lengths;
 * a task without a title, a task that starts after it is due and a body too
 * large to keep are refused. depends_on keeps only earlier tasks of the same
 * template, which keeps the order free of loops.
 *
 * @param string|array $json
 * @return array ok, error, body (tasks, notes)
 */
function ws_template_body_clean($json)
{
    $empty = array('tasks' => array(), 'notes' => array());
    $fail = function ($error) use ($empty) {
        return array('ok' => false, 'error' => $error, 'body' => $empty);
    };

    if (is_string($json)) {
        $json = trim($json);
        $data = ($json === '') ? array() : json_decode($json, true);

        if (!is_array($data)) {
            return $fail(lang('The tasks and notes of the template could not be read.'));
        }
    } elseif (is_array($json)) {
        $data = $json;
    } else {
        $data = array();
    }

    $tasks_in = (isset($data['tasks']) && is_array($data['tasks'])) ? array_values($data['tasks']) : array();
    $notes_in = (isset($data['notes']) && is_array($data['notes'])) ? array_values($data['notes']) : array();

    if (count($tasks_in) > WS_TEMPLATE_TASKS_MAX) {
        return $fail(lang(array('string' => 'A template can hold at most {var:1} tasks.', 'vars' => WS_TEMPLATE_TASKS_MAX)));
    }

    if (count($notes_in) > WS_TEMPLATE_NOTES_MAX) {
        return $fail(lang(array('string' => 'A template can hold at most {var:1} notes.', 'vars' => WS_TEMPLATE_NOTES_MAX)));
    }

    $line = function ($value, $max) {
        return is_scalar($value) ? mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $value)), 0, $max) : '';
    };
    $text = function ($value, $max) {
        return is_scalar($value) ? mb_substr(trim(str_replace(array("\r\n", "\r"), "\n", (string) $value)), 0, $max) : '';
    };

    $tasks = array();

    foreach ($tasks_in as $index => $task) {
        if (!is_array($task)) {
            return $fail(lang(array('string' => 'Task {var:1} has no title.', 'vars' => $index + 1)));
        }

        $title = $line($task['title'] ?? '', 255);

        if ($title === '') {
            return $fail(lang(array('string' => 'Task {var:1} has no title.', 'vars' => $index + 1)));
        }

        $start = ws_template_days($task['start_in_days'] ?? null);
        $due = ws_template_days($task['due_in_days'] ?? null);

        if (($start !== null) && ($due !== null) && ($start > $due)) {
            return $fail(lang(array('string' => 'Task {var:1} cannot start after it is due.', 'vars' => $index + 1)));
        }

        $priority = (string) ($task['priority'] ?? 'normal');
        $assign = (string) ($task['assign'] ?? 'none');

        $checklist = array();

        foreach ((isset($task['checklist']) && is_array($task['checklist'])) ? $task['checklist'] : array() as $item) {
            // An item written as a checklist line keeps its words only.
            $item = $line(preg_replace('/^\s*[-*]\s\[[ xX]\]\s*/u', '', is_scalar($item) ? (string) $item : ''), 180);

            if (($item !== '') && (count($checklist) < WS_TEMPLATE_ITEMS_MAX)) {
                $checklist[] = $item;
            }
        }

        $depends = array();

        foreach ((isset($task['depends_on']) && is_array($task['depends_on'])) ? $task['depends_on'] : array() as $other) {
            if (is_numeric($other) && ((int) $other >= 0) && ((int) $other < $index)) {
                $depends[(int) $other] = (int) $other;
            }
        }

        ksort($depends);

        $tasks[] = array(
            'title'            => $title,
            'description'      => $text($task['description'] ?? '', 2000),
            'priority'         => in_array($priority, array('low', 'normal', 'high', 'urgent'), true) ? $priority : 'normal',
            'start_in_days'    => $start,
            'due_in_days'      => $due,
            'estimate_minutes' => max(0, min(100000, (int) (is_numeric($task['estimate_minutes'] ?? null) ? $task['estimate_minutes'] : 0))),
            'assign'           => ws_template_assign_valid($assign) ? $assign : 'none',
            'checklist'        => $checklist,
            'depends_on'       => array_values($depends),
        );
    }

    $notes = array();

    foreach ($notes_in as $note) {
        if (!is_array($note)) {
            continue;
        }

        $title = $line($note['title'] ?? '', 200);
        $body = $text($note['body'] ?? '', 20000);

        if (($title === '') && ($body === '')) {
            continue;
        }

        $notes[] = array('title' => $title, 'body' => $body);
    }

    $body = array('tasks' => $tasks, 'notes' => $notes);

    if (strlen(json_encode($body, JSON_UNESCAPED_UNICODE)) > WS_TEMPLATE_BODY_BYTES) {
        return $fail(lang('The template is too long to keep: shorten its notes or take some tasks out.'));
    }

    return array('ok' => true, 'error' => '', 'body' => $body);
}

/**
 * The day a number of days after another, in calendar days: weekends and
 * holidays are counted like any other day.
 *
 * @param string   $today Y-m-d
 * @param int|null $days
 * @return string|null Y-m-d, null for no days
 */
function ws_template_relative_date($today, $days)
{
    if ($days === null) {
        return null;
    }

    // Noon, so a change of the clocks never moves the day.
    return date('Y-m-d', strtotime($today . ' 12:00:00 +' . max(0, (int) $days) . ' days'));
}

/**
 * How many days a date is after another, 0 for a day already past.
 *
 * @param string $today Y-m-d
 * @param string $date  Y-m-d
 * @return int|null null for no date
 */
function ws_template_days_until($today, $date)
{
    $date = (string) $date;

    if (($date === '') || ($date === '0000-00-00')) {
        return null;
    }

    $from = strtotime($today . ' 12:00:00');
    $to = strtotime($date . ' 12:00:00');

    if (($from === false) || ($to === false)) {
        return null;
    }

    return max(0, (int) round(($to - $from) / 86400));
}

/**
 * A task's description split into its text and its checklist items (the
 * "- [ ]" lines outside a block of code).
 *
 * @param string $description
 * @return array text, items
 */
function ws_template_split_checklist($description)
{
    $text = array();
    $items = array();
    $code = false;

    foreach (preg_split('/\r\n|\r|\n/', (string) $description) as $line) {
        if ($code) {
            $code = !preg_match('/^\s*```\s*$/', $line);
            $text[] = $line;
            continue;
        }

        if (preg_match('/^\s*```/', $line)) {
            $code = true;
            $text[] = $line;
            continue;
        }

        if (preg_match('/^\s*[-*]\s\[( |x|X)\]\s+(.+)$/u', $line, $match)) {
            $items[] = trim($match[2]);
            continue;
        }

        $text[] = $line;
    }

    return array('text' => trim(implode("\n", $text)), 'items' => $items);
}

/**
 * The description a task of a template is made with: its text, then its
 * checklist as "- [ ]" lines, which the task counts as its own list.
 *
 * @param string   $description
 * @param string[] $checklist
 * @return string
 */
function ws_template_task_description($description, $checklist)
{
    $description = trim((string) $description);
    $lines = array();

    foreach ((array) $checklist as $item) {
        $lines[] = '- [ ] ' . $item;
    }

    if (empty($lines)) {
        return $description;
    }

    return (($description !== '') ? $description . "\n\n" : '') . implode("\n", $lines);
}

/* ---------------------------------------------------------------------------
   Templates: the built-in ones and the site's own
   --------------------------------------------------------------------------- */

/**
 * A table of a note: a title line and a header with one empty row.
 *
 * @param string   $title
 * @param string[] $columns
 * @param string[] $rows    the first cell of each row, '' for one empty row
 * @return string
 */
function ws_template_note_table($title, $columns, $rows = array(''))
{
    $lines = array('::: ' . $title, '| ' . implode(' | ', $columns) . ' |', '|' . str_repeat(' --- |', count($columns)));

    foreach ($rows as $first) {
        $cells = array_fill(0, count($columns), ' ');
        $cells[0] = ($first !== '') ? $first : ' ';
        $lines[] = '| ' . implode(' | ', $cells) . ' |';
    }

    return implode("\n", $lines);
}

/**
 * The templates that come with the software, in the language of the screen.
 * Kept here, not in the database: they are listed with a "Built-in" mark and
 * may be copied to be changed.
 *
 * @return array[] id => template
 */
function ws_templates_builtin()
{
    $task = function ($title, $description, $priority, $start, $due, $estimate, $assign, $checklist, $depends = array()) {
        return array(
            'title'            => $title,
            'description'      => $description,
            'priority'         => $priority,
            'start_in_days'    => $start,
            'due_in_days'      => $due,
            'estimate_minutes' => $estimate,
            'assign'           => $assign,
            'checklist'        => $checklist,
            'depends_on'       => $depends,
        );
    };

    $templates = array(
        'builtin:new_customer' => array(
            'name'        => lang('New customer'),
            'description' => lang('Kick-off, list of needs, offer, contract and the start of work for a new customer.'),
            'icon'        => 'bi-person-plus',
            'color'       => 9,
            'summary'     => lang('Who the customer is, who we talk to there, what they want and by when. Fill this in after the kick-off meeting.'),
            'pinned'      => lang('The list of needs and the offer are followed on the Tasks tab; what is agreed is kept on the Decisions tab.'),
            'welcome'     => lang('Welcome to the channel of our new customer. The work of the first weeks is on the Tasks tab.'),
            'body'        => array(
                'tasks' => array(
                    $task(lang('Kick-off meeting'), lang('Meet the customer and agree on the goals and the way of working.'), 'high', 0, 2, 60, 'creator',
                        array(lang('Agree on a date with the customer'), lang('Prepare the agenda'), lang('Share the meeting notes in the channel'))),
                    $task(lang('List of needs'), lang('Write down what the customer needs, in their own words.'), 'normal', 1, 5, 120, 'creator',
                        array(lang('Goals and scope'), lang('Deadlines'), lang('Budget'), lang('Who decides')), array(0)),
                    $task(lang('Offer'), lang('Prepare the offer and send it.'), 'high', 5, 9, 120, 'department_lead',
                        array(lang('Work out the prices'), lang('Send the offer'), lang('Follow it up')), array(1)),
                    $task(lang('Contract'), lang('Agree on the contract and have it signed.'), 'normal', 9, 14, 60, 'creator',
                        array(lang('Send the draft'), lang('Receive the signed copy')), array(2)),
                    $task(lang('Start of work'), lang('Hand the work over to the team and set the first dates.'), 'high', 14, 16, 60, 'department_lead',
                        array(), array(3)),
                ),
                'notes' => array(
                    array('title' => lang('Meeting notes'), 'body' => ws_template_note_table(lang('Agenda'), array(lang('Topic'), lang('Who'), lang('Decision')))),
                ),
            ),
        ),
        'builtin:website_delivery' => array(
            'name'        => lang('Website delivery'),
            'description' => lang('Content, design approval, testing, going live and training for a website handed over to a customer.'),
            'icon'        => 'bi-globe',
            'color'       => 7,
            'summary'     => lang('The delivery plan of the website: what the customer sends, when the design is approved and when the site goes live.'),
            'pinned'      => lang('The site goes live only once the design is approved in writing and the tests are done.'),
            'welcome'     => lang('This channel follows the website from the first content to the training.'),
            'body'        => array(
                'tasks' => array(
                    $task(lang('Collect the content'), lang('Everything the pages need, from the customer.'), 'normal', 0, 5, 240, 'creator',
                        array(lang('Texts'), lang('Pictures and logo'), lang('Contact details and addresses'))),
                    $task(lang('Design approval'), lang('Show the design and have it approved in writing.'), 'high', 5, 10, 120, 'department_lead',
                        array(lang('Send the design'), lang('Collect the changes'), lang('Get the written approval')), array(0)),
                    $task(lang('Testing'), lang('Go through the site before it goes live.'), 'normal', 11, 17, 240, 'department_lead',
                        array(lang('Phones and tablets'), lang('Forms and e-mails'), lang('Page speed'), lang('Broken links')), array(1)),
                    $task(lang('Going live'), lang('Put the site on its address.'), 'high', 18, 19, 120, 'department_lead',
                        array(lang('Domain and SSL'), lang('Redirects from the old site'), lang('Search engine settings')), array(2)),
                    $task(lang('Training'), lang('Show the customer how to keep the site up to date.'), 'normal', 20, 21, 90, 'creator',
                        array(lang('Show the panel'), lang('Hand the accounts over safely')), array(3)),
                ),
                'notes' => array(
                    array('title' => lang('Launch notes'), 'body' => ws_template_note_table(lang('Launch'), array(lang('Item'), lang('Status'), lang('Note')))),
                ),
            ),
        ),
        'builtin:month_end' => array(
            'name'        => lang('Month-end closing'),
            'description' => lang('Checking the invoices, following up the payments due and the report of the month.'),
            'icon'        => 'bi-calendar-check',
            'color'       => 3,
            'summary'     => lang('Every month-end: check the invoices, follow up what is due and report the month.'),
            'pinned'      => lang('The report of the month is shared here once the invoices and the collections are checked.'),
            'welcome'     => '',
            'body'        => array(
                'tasks' => array(
                    $task(lang('Invoice check'), lang('Every sale invoiced, every purchase entered.'), 'high', 0, 2, 120, 'creator',
                        array(lang('Every order invoiced'), lang('Purchase invoices entered'), lang('VAT amounts checked'))),
                    $task(lang('Follow up the payments due'), lang('The customers whose payment is late.'), 'normal', 1, 5, 120, 'creator',
                        array(lang('List the overdue invoices'), lang('Call or write to the customers'), lang('Note the dates they promised'))),
                    $task(lang('Report of the month'), lang('The month in figures, shared in the channel.'), 'normal', 5, 7, 90, 'department_lead',
                        array(lang('Income and expenses'), lang('Collections'), lang('Open balances')), array(0, 1)),
                ),
                'notes' => array(
                    array('title' => lang('Figures of the month'), 'body' => ws_template_note_table(lang('Figures'), array(lang('Item'), lang('Amount')), array(lang('Income'), lang('Expenses'), lang('Collections')))),
                ),
            ),
        ),
    );

    $out = array();

    foreach ($templates as $id => $template) {
        $out[$id] = ws_template_normalise(array_merge($template, array(
            'id'      => $id,
            'builtin' => true,
            'kind'    => 'public',
        )));
    }

    return $out;
}

/**
 * A template in the one shape every caller reads, from a row or a built-in.
 *
 * @param array $row
 * @return array
 */
function ws_template_normalise($row)
{
    $body = $row['body'] ?? array();
    $clean = ws_template_body_clean(is_array($body) ? $body : (string) $body);

    return array(
        'id'            => !empty($row['builtin']) ? (string) $row['id'] : (int) $row['id'],
        'builtin'       => !empty($row['builtin']),
        'name'          => (string) ($row['name'] ?? ''),
        'description'   => (string) ($row['description'] ?? ''),
        'icon'          => (string) ($row['icon'] ?? ''),
        'color'         => ws_palette_place($row['color'] ?? 0),
        'kind'          => (($row['kind'] ?? 'public') === 'private') ? 'private' : 'public',
        'department_id' => (int) ($row['department_id'] ?? 0),
        'summary'       => (string) ($row['summary'] ?? ''),
        'pinned'        => (string) ($row['pinned'] ?? ''),
        'welcome'       => (string) ($row['welcome'] ?? ''),
        'body'          => $clean['body'],
        'uses'          => (int) ($row['uses'] ?? 0),
        'archived'      => !empty($row['archived']),
        'created_by'    => (int) ($row['created_by'] ?? 0),
        'updated_at'    => (int) ($row['updated_at'] ?? 0),
    );
}

/**
 * One template: a built-in one by its key ("builtin:new_customer"), the
 * site's own by its number.
 *
 * @param int|string $id
 * @return array|null
 */
function ws_template($id)
{
    if (!ws_templates_ready()) {
        return null;
    }

    $id = trim((string) $id);

    if (strpos($id, 'builtin:') === 0) {
        $builtin = ws_templates_builtin();

        return $builtin[$id] ?? null;
    }

    if (!ctype_digit($id) || ((int) $id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_templates WHERE id = '" . (int) $id . "'");

    return is_array($row) ? ws_template_normalise($row) : null;
}

/**
 * A template as the pickers, the settings list and the API show it.
 *
 * @param array $template ws_template()
 * @return array
 */
function ws_template_present($template)
{
    return array(
        'id'            => (string) $template['id'],
        'builtin'       => (bool) $template['builtin'],
        'name'          => (string) $template['name'],
        'description'   => (string) $template['description'],
        'icon'          => (string) $template['icon'],
        'color'         => (int) $template['color'],
        'hex'           => ws_palette_hex($template['color']),
        'kind'          => (string) $template['kind'],
        'department_id' => (int) $template['department_id'],
        'tasks'         => count($template['body']['tasks']),
        'notes'         => count($template['body']['notes']),
        'summary'       => (trim($template['summary']) !== ''),
        'pinned'        => (trim($template['pinned']) !== ''),
        'welcome'       => (trim($template['welcome']) !== ''),
        'uses'          => (int) $template['uses'],
        'archived'      => (bool) $template['archived'],
    );
}

/**
 * The templates a person may apply: the built-in ones, then the site's own
 * by name; with the archived ones for the settings list.
 *
 * @param array $viewer
 * @param bool  $with_archived
 * @return array[] ws_template() rows
 */
function ws_templates_list($viewer, $with_archived = false)
{
    if (empty($viewer['member']) || !ws_templates_ready()) {
        return array();
    }

    $out = array_values(ws_templates_builtin());

    foreach ((array) db_items("SELECT * FROM ws_templates" . ($with_archived ? '' : " WHERE archived = 0") . " ORDER BY archived, name, id") as $row) {
        $out[] = ws_template_normalise($row);
    }

    return $out;
}

/**
 * Keeps a template: a new one, or a change to one of the site's own. The
 * built-in ones are not changed; they are copied.
 *
 * @param array $viewer
 * @param array $data id (0 for a new one), name, description, icon, color, kind,
 *                    department_id, summary, pinned, welcome, body (JSON or array)
 * @return array ok, error, field, id
 */
function ws_template_save($viewer, $data)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'id' => 0);
    };

    if (!ws_can_write_templates($viewer)) {
        return $fail(lang('Only staff and the people who may change the workspace settings can keep templates.'));
    }

    $id = (int) ($data['id'] ?? 0);
    $current = ($id > 0) ? ws_template($id) : null;

    if (($id > 0) && !$current) {
        return $fail(lang('That template could not be found.'));
    }

    $name = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($data['name'] ?? ''))), 0, 100);

    if ($name === '') {
        return $fail(lang('A template needs a name.'), 'name');
    }

    $icon = strtolower(trim((string) ($data['icon'] ?? '')));
    $icon = preg_replace('/^bi\s+/', '', $icon);

    if (($icon !== '') && !preg_match('/^bi-[a-z0-9-]{1,37}$/', $icon)) {
        return $fail(lang('Write the icon as a Bootstrap Icons class, such as bi-briefcase.'), 'icon');
    }

    $department_id = max(0, (int) ($data['department_id'] ?? 0));

    if (($department_id > 0) && !ws_department($department_id)) {
        return $fail(lang('That department could not be found.'), 'department_id');
    }

    $texts = array();

    foreach (array('summary' => 16000, 'pinned' => WS_MESSAGE_MAX, 'welcome' => WS_MESSAGE_MAX) as $field => $max) {
        $value = ws_tokens_normalise(trim(str_replace(array("\r\n", "\r"), "\n", (string) ($data[$field] ?? ''))));

        if (mb_strlen($value) > $max) {
            return $fail(lang(array('string' => 'This text can be at most {var:1} characters long.', 'vars' => $max)), $field);
        }

        $texts[$field] = $value;
    }

    $clean = ws_template_body_clean($data['body'] ?? '');

    if (!$clean['ok']) {
        return $fail($clean['error'], 'body');
    }

    $now = time();
    $set = "name = '" . e($name) . "',
        description = '" . e(mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($data['description'] ?? ''))), 0, 255)) . "',
        icon = '" . e($icon) . "',
        color = '" . ws_palette_place($data['color'] ?? 0) . "',
        kind = '" . ((($data['kind'] ?? 'public') === 'private') ? 'private' : 'public') . "',
        department_id = '" . $department_id . "',
        summary = '" . e($texts['summary']) . "',
        pinned = '" . e($texts['pinned']) . "',
        welcome = '" . e($texts['welcome']) . "',
        body = '" . e(json_encode($clean['body'], JSON_UNESCAPED_UNICODE)) . "',
        updated_at = '" . $now . "'";

    if ($current) {
        db("UPDATE ws_templates SET " . $set . " WHERE id = '" . $id . "'");
        log_activity(lang(array('string' => 'workspace template ({var:1}) was changed', 'vars' => $name)), (string) ($_SESSION['sessionusername'] ?? ''));

        return array('ok' => true, 'error' => '', 'field' => '', 'id' => $id);
    }

    db("INSERT INTO ws_templates SET " . $set . ", created_by = '" . (int) $viewer['id'] . "', created_at = '" . $now . "'");
    $id = (int) mysqli_insert_id(db::$con);

    if ($id <= 0) {
        return $fail(lang('The template could not be saved.'));
    }

    log_activity(lang(array('string' => 'workspace template ({var:1}) was created', 'vars' => $name)), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '', 'field' => '', 'id' => $id);
}

/**
 * Moves one of the site's templates to the archive, out of the pickers, or
 * brings it back. The channels made from it keep what they were given.
 *
 * @param array      $viewer
 * @param int|string $id
 * @param bool       $archive
 * @return array ok, error, name
 */
function ws_template_archive($viewer, $id, $archive = true)
{
    if (!ws_can_write_templates($viewer)) {
        return array('ok' => false, 'error' => lang('Only staff and the people who may change the workspace settings can keep templates.'), 'name' => '');
    }

    $template = ws_template($id);

    if (!$template || $template['builtin']) {
        return array('ok' => false, 'error' => lang('That template could not be found.'), 'name' => '');
    }

    db("UPDATE ws_templates SET archived = '" . ($archive ? 1 : 0) . "', updated_at = '" . time() . "' WHERE id = '" . (int) $template['id'] . "'");

    log_activity($archive
        ? lang(array('string' => 'workspace template ({var:1}) was archived', 'vars' => $template['name']))
        : lang(array('string' => 'workspace template ({var:1}) was brought back', 'vars' => $template['name'])), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '', 'name' => $template['name']);
}

/* ---------------------------------------------------------------------------
   Applying a template
   --------------------------------------------------------------------------- */

/**
 * The people a task of a template goes to, and the department it is given,
 * by its rule - before anybody's right to give it is asked.
 *
 * @param array  $viewer
 * @param string $rule
 * @param int    $department_id the template's department, else the channel's
 * @return array user_ids, department_id, wanted (somebody was meant to be on it)
 */
function ws_template_assignees($viewer, $rule, $department_id)
{
    $out = array('user_ids' => array(), 'department_id' => 0, 'wanted' => false);

    if ($rule === 'creator') {
        $out['user_ids'] = array((int) $viewer['id']);
        $out['wanted'] = true;
    } elseif ($rule === 'department_lead') {
        $out['wanted'] = true;
        $department = ($department_id > 0) ? ws_department($department_id) : null;

        if ($department && empty($department['archived'])) {
            $out['department_id'] = (int) $department['id'];
            $lead = (int) db_value("SELECT user_id FROM ws_department_members
                WHERE department_id = '" . (int) $department['id'] . "' AND is_lead = 1 ORDER BY user_id LIMIT 1");

            if (($lead > 0) && ws_is_team_member($lead)) {
                $out['user_ids'] = array($lead);
            }
        }
    } elseif (strpos($rule, 'department:') === 0) {
        $department = ws_department((int) substr($rule, 11));

        // The department's pool: nobody on it until its lead hands it out.
        if ($department && empty($department['archived'])) {
            $out['department_id'] = (int) $department['id'];
        }
    } elseif (strpos($rule, 'user:') === 0) {
        $out['wanted'] = true;
        $user_id = (int) substr($rule, 5);

        if (ws_is_team_member($user_id)) {
            $out['user_ids'] = array($user_id);
        }
    }

    return $out;
}

/**
 * Applies a template to a channel: its tasks are made in the channel, its
 * notes are written as the person's own and shared there, the summary and
 * the pinned message fill what is empty, and a line in the channel says what
 * came in. Everything is checked before the first write.
 *
 * @param array $viewer
 * @param array $channel
 * @param array $template ws_template()
 * @param array $options  new_channel (the channel was made from the template
 *                        a moment ago: the welcome is written), app_id (an API
 *                        application acting for $viewer writes the pinned
 *                        message)
 * @return array ok, error, message, warning, tasks, notes, unassigned (made
 *               with nobody on them), denied (of those, for want of the
 *               right), summary_kept, pinned_kept, links_skipped, task_ids,
 *               note_ids
 */
function ws_template_apply($viewer, $channel, $template, $options = array())
{
    $result = array(
        'ok' => false, 'error' => '', 'message' => '', 'warning' => false,
        'tasks' => 0, 'notes' => 0, 'unassigned' => 0, 'denied' => 0, 'summary_kept' => false, 'pinned_kept' => false,
        'links_skipped' => 0, 'task_ids' => array(), 'note_ids' => array(),
    );
    $fail = function ($error) use ($result) {
        $result['error'] = $error;

        return $result;
    };

    if (!ws_templates_ready()) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    if (!is_array($template) || !empty($template['archived'])) {
        return $fail(lang('That template could not be found.'));
    }

    if (!is_array($channel) || !in_array((string) $channel['kind'], array('public', 'private'), true) || !ws_can_post_channel($viewer, $channel)) {
        return $fail(lang('You cannot post in that channel.'));
    }

    $new_channel = !empty($options['new_channel']);
    $app_id = (int) ($options['app_id'] ?? 0);
    $clean = ws_template_body_clean($template['body']);

    if (!$clean['ok']) {
        return $fail($clean['error']);
    }

    $body = $clean['body'];
    $today = date('Y-m-d');
    $department_id = ((int) $template['department_id'] > 0) ? (int) $template['department_id'] : (int) $channel['department_id'];

    // Every task as ws_task_create() will take it, checked now.
    $planned = array();

    foreach ($body['tasks'] as $index => $task) {
        $assign = ws_template_assignees($viewer, $task['assign'], $department_id);
        $people = $assign['user_ids'];

        // Meant for somebody: nobody found to give it to, or somebody the
        // person may not give work to. Either way it is made with nobody on
        // it, and counted.
        if ($assign['wanted'] && empty($people)) {
            $result['unassigned']++;
        } elseif ($assign['wanted'] && !ws_can_assign_to($viewer, $people)) {
            $people = array();
            $result['unassigned']++;
            $result['denied']++;
        }

        $data = array(
            'title'            => $task['title'],
            'description'      => ws_template_task_description($task['description'], $task['checklist']),
            'priority'         => $task['priority'],
            'start_date'       => (string) ws_template_relative_date($today, $task['start_in_days']),
            'due_date'         => (string) ws_template_relative_date($today, $task['due_in_days']),
            'estimate_minutes' => $task['estimate_minutes'],
            'department_id'    => $assign['department_id'],
            'channel_id'       => (int) $channel['id'],
            'assignees'        => $people,
        );

        $check = ws_task_validate($viewer, $data);

        if (!$check['ok']) {
            return $fail(lang(array('string' => 'Task {var:1} of the template: {var:2}', 'vars' => array($index + 1, $check['error']))));
        }

        $planned[] = $data;
    }

    // The notes are the person's own: there has to be room for them.
    if (!empty($body['notes'])) {
        if (!ws_notes_ready()) {
            return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
        }

        if ((int) db_value("SELECT COUNT(*) FROM ws_notes WHERE user_id = '" . (int) $viewer['id'] . "'") + count($body['notes']) > WS_NOTE_LIMIT) {
            return $fail(lang(array('string' => 'You can keep at most {var:1} notes.', 'vars' => WS_NOTE_LIMIT)));
        }
    }

    $summary_empty = (trim((string) $channel['summary']) === '');
    $write_summary = (trim($template['summary']) !== '') && $summary_empty;
    $result['summary_kept'] = (trim($template['summary']) !== '') && !$summary_empty;

    $pin_free = ((int) ($channel['pinned_message_id'] ?? 0) === 0);
    $write_pin = (trim($template['pinned']) !== '') && $pin_free && ws_can_pin($viewer, $channel);
    $result['pinned_kept'] = (trim($template['pinned']) !== '') && !$pin_free;

    $write_welcome = $new_channel && (trim($template['welcome']) !== '');

    // The messages written in the person's name count against the limit of
    // twenty a minute (ws_rate_limited()); asked once, before anything is
    // written, so the channel is not left with half of them.
    $messages = count($body['notes']) + ($write_welcome ? 1 : 0) + (($write_pin && ($app_id === 0)) ? 1 : 0);

    if (($messages > 0) && ((int) db_value("SELECT COUNT(*) FROM ws_messages
        WHERE sender_kind = 'user' AND sender_id = '" . (int) $viewer['id'] . "' AND created_at > '" . (time() - 60) . "'") + $messages > 20)) {
        return $fail(lang('You are sending messages too quickly. Please wait a moment.'));
    }

    // Writing starts here.
    $made = array();
    $broke = '';
    $finished = false;

    // db() ends the request on a failed query, and the module writes without
    // a transaction: if that happens half way, the channel is still told
    // which tasks were made before it.
    register_shutdown_function(function () use (&$made, &$finished, $channel, $template) {
        if ($finished) {
            return;
        }

        $numbers = array();

        foreach ($made as $task_id) {
            $numbers[] = ws_task_number($task_id);
        }

        ws_message_system($channel['id'], lang(array(
            'string' => 'Template {var:1} stopped half way: {var:2} Made before it stopped: {var:3}.',
            'vars'   => array($template['name'], lang('the request ended before it was done.'), empty($numbers) ? lang('nothing') : implode(', ', $numbers)),
        )));
    });

    if ($write_welcome) {
        $sent = ws_message_send($viewer, $channel, $template['welcome']);

        if (!$sent['ok']) {
            $broke = $sent['error'];
        }
    }

    foreach ($planned as $data) {
        if ($broke !== '') {
            break;
        }

        $created = ws_task_create($viewer, $data);

        if (!$created['ok']) {
            $broke = $created['error'];
            break;
        }

        $made[] = (int) $created['task_id'];
    }

    // The order of the tasks (task links are kept by ws_task_link_add(),
    // where the module has them).
    if ($broke === '') {
        foreach ($body['tasks'] as $index => $task) {
            foreach ($task['depends_on'] as $other) {
                if (!isset($made[$index], $made[$other])) {
                    continue;
                }

                if (function_exists('ws_task_link_add')) {
                    ws_task_link_add($viewer, ws_task($made[$index]), $made[$other]);
                } else {
                    $result['links_skipped']++;
                }
            }
        }
    }

    foreach ($body['notes'] as $note) {
        if ($broke !== '') {
            break;
        }

        $saved = ws_note_save($viewer, array('title' => $note['title'], 'body' => $note['body']));

        if (!$saved['ok']) {
            $broke = $saved['error'];
            break;
        }

        $result['note_ids'][] = (int) $saved['note_id'];
        $shared = ws_note_share_channel($viewer, ws_note($saved['note_id']), $channel);

        if (!$shared['ok']) {
            $broke = $shared['error'];
            break;
        }
    }

    if (($broke === '') && $write_summary) {
        $summary = ws_channel_set_summary($viewer, $channel, $template['summary']);

        if (!$summary['ok']) {
            $broke = $summary['error'];
        }
    }

    if (($broke === '') && $write_pin) {
        $sent = ws_message_send($viewer, $channel, $template['pinned'], array('app_id' => $app_id));

        if ($sent['ok']) {
            ws_channel_pin_message($viewer, ws_channel($channel['id']), $sent['message_id']);
        } else {
            $broke = $sent['error'];
        }
    }

    $result['task_ids'] = $made;
    $result['tasks'] = count($made);
    $result['notes'] = count($result['note_ids']);

    $finished = true;

    if ($broke !== '') {
        $numbers = array();

        foreach ($made as $task_id) {
            $numbers[] = ws_task_number($task_id);
        }

        ws_message_system($channel['id'], lang(array(
            'string' => 'Template {var:1} stopped half way: {var:2} Made before it stopped: {var:3}.',
            'vars'   => array($template['name'], $broke, empty($numbers) ? lang('nothing') : implode(', ', $numbers)),
        )));

        $result['error'] = lang(array('string' => 'The template stopped half way: {var:1}', 'vars' => $broke));

        return $result;
    }

    if (!$template['builtin']) {
        db("UPDATE ws_templates SET uses = uses + 1 WHERE id = '" . (int) $template['id'] . "'");
    }

    ws_message_system($channel['id'], lang(array(
        'string' => 'Template applied: {var:1} — {var:2} tasks, {var:3} notes',
        'vars'   => array($template['name'], $result['tasks'], $result['notes']),
    )));

    // What the person is told: what came in, then what did not.
    $parts = array(lang(array('string' => 'Template applied: {var:1} — {var:2} tasks, {var:3} notes', 'vars' => array($template['name'], $result['tasks'], $result['notes']))) . '.');

    if ($result['denied'] > 0) {
        $parts[] = lang(array('string' => '{var:1} tasks could not be assigned and have nobody on them: you may give tasks only to yourself or to the people in the departments you lead.', 'vars' => $result['denied']));
    }

    if ($result['unassigned'] > $result['denied']) {
        $parts[] = lang(array('string' => '{var:1} tasks have nobody on them: the person they were meant for was not found (a department without a lead, or somebody no longer in the team).', 'vars' => $result['unassigned'] - $result['denied']));
    }

    if ($result['summary_kept']) {
        $parts[] = lang('The channel already had a summary; it was left as it was.');
    }

    if ($result['pinned_kept']) {
        $parts[] = lang('The channel already had a pinned message; it was left as it was.');
    }

    $result['ok'] = true;
    $result['message'] = implode(' ', $parts);
    $result['warning'] = ($result['unassigned'] > 0) || $result['summary_kept'] || $result['pinned_kept'];

    pg_announce('workspace.template.applied', array(
        'channel_id'  => (int) $channel['id'],
        'template_id' => (string) $template['id'],
        'template'    => (string) $template['name'],
        'applied_by'  => (int) $viewer['id'],
        'app_id'      => $app_id,
        'task_ids'    => $made,
        'notes'       => $result['notes'],
        'unassigned'  => $result['unassigned'],
    ));

    log_activity(lang(array('string' => 'workspace template ({var:1}) was applied to channel ({var:2})', 'vars' => array($template['name'], $channel['name']))), (string) ($_SESSION['sessionusername'] ?? ''));

    return $result;
}

/**
 * A draft template taken from a channel, for its editing screen to show
 * before anything is kept: the open tasks (their dates counted from today,
 * a date already past as 0), the notes shared in the channel, its summary
 * and its pinned message.
 *
 * @param array $viewer
 * @param array $channel
 * @return array ws_template() shape, id 0
 */
function ws_template_from_channel($viewer, $channel)
{
    $today = date('Y-m-d');
    $tasks = array();

    foreach ((array) db_items("SELECT * FROM ws_tasks
        WHERE channel_id = '" . (int) $channel['id'] . "' AND status IN ('todo', 'doing', 'waiting')
        ORDER BY (due_date IS NULL), due_date, id LIMIT " . WS_TEMPLATE_TASKS_MAX) as $row) {

        $split = ws_template_split_checklist($row['description']);
        $start = ws_template_days_until($today, (string) $row['start_date']);
        $due = ws_template_days_until($today, (string) $row['due_date']);

        if (($start !== null) && ($due !== null) && ($start > $due)) {
            $start = $due;
        }

        $assignees = ws_task_assignee_ids($row['id']);

        $tasks[] = array(
            'title'            => (string) $row['title'],
            'description'      => $split['text'],
            'priority'         => (string) $row['priority'],
            'start_in_days'    => $start,
            'due_in_days'      => $due,
            'estimate_minutes' => (int) $row['estimate_minutes'],
            'assign'           => !empty($assignees) ? 'user:' . (int) $assignees[0] : (((int) $row['department_id'] > 0) ? 'department:' . (int) $row['department_id'] : 'none'),
            'checklist'        => $split['items'],
            'depends_on'       => array(),
        );
    }

    $notes = array();

    if (ws_notes_ready()) {
        foreach ((array) db_values("SELECT note_id FROM ws_note_shares
            WHERE channel_id = '" . (int) $channel['id'] . "'
            GROUP BY note_id ORDER BY MIN(id) LIMIT " . WS_TEMPLATE_NOTES_MAX) as $note_id) {

            $row = ws_note($note_id);

            if (!$row || (ws_note_access($viewer, $row) === '')) {
                continue;
            }

            $notes[] = array('title' => ws_note_title($viewer, $row), 'body' => (string) $row['body']);
        }
    }

    $pinned = '';

    if ((int) ($channel['pinned_message_id'] ?? 0) > 0) {
        $message = ws_message($channel['pinned_message_id']);

        if ($message && ((int) $message['deleted_at'] === 0) && ((int) $message['channel_id'] === (int) $channel['id'])) {
            $pinned = (string) $message['body'];
        }
    }

    // Cut to what a template keeps; the screen shows the draft as it will be
    // kept.
    $clean = ws_template_body_clean(array('tasks' => $tasks, 'notes' => $notes));

    if (!$clean['ok']) {
        $clean = ws_template_body_clean(array('tasks' => $tasks, 'notes' => array()));
    }

    return array(
        'id'            => 0,
        'builtin'       => false,
        'name'          => (string) $channel['name'],
        'description'   => (string) $channel['topic'],
        'icon'          => '',
        'color'         => ws_palette_place($channel['color'] ?? 0),
        'kind'          => ((string) $channel['kind'] === 'private') ? 'private' : 'public',
        'department_id' => (int) $channel['department_id'],
        'summary'       => mb_substr((string) $channel['summary'], 0, 16000),
        'pinned'        => mb_substr($pinned, 0, WS_MESSAGE_MAX),
        'welcome'       => '',
        'body'          => $clean['body'],
        'uses'          => 0,
        'archived'      => false,
        'created_by'    => (int) $viewer['id'],
        'updated_at'    => 0,
    );
}

/* ---------------------------------------------------------------------------
   Screens
   --------------------------------------------------------------------------- */

/**
 * What the script is told about templates (ws_screen_config()).
 *
 * @param array $viewer
 * @return array ready, manage, edit_url, settings_url
 */
function ws_templates_js_config($viewer)
{
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';

    return array(
        'ready'        => ws_templates_ready(),
        'manage'       => ws_can_write_templates($viewer),
        'edit_url'     => $base . 'workspace_template.php',
        'settings_url' => $base . 'workspace_settings.php#ws-templates',
    );
}

/**
 * The words the screens need for templates (assets/js/workspace_templates.js).
 *
 * @return array
 */
function ws_templates_js_strings()
{
    return array(
        'tpl_from'            => lang('From a template'),
        'tpl_none'            => lang('No template'),
        'tpl_none_help'       => lang('An empty channel'),
        'tpl_counts'          => ws_js_template('{var:1} tasks · {var:2} notes', 2),
        'tpl_builtin'         => lang('Built-in'),
        'tpl_suggest_help'    => lang('The template suggests the name, who can see it, the department and the colour; change them as you like.'),
        'tpl_empty'           => lang('No templates yet.'),
        'tpl_apply'           => lang('Apply a template'),
        'tpl_apply_help'      => lang('Its tasks and notes are added to this channel. The summary and the pinned message are filled in only where they are empty.'),
        'tpl_apply_button'    => lang('Apply the template'),
        'tpl_pick_first'      => lang('Choose a template first.'),
        'tpl_from_channel'    => lang('Make a template of this channel'),
        'tpl_manage'          => lang('Channel templates'),
        'tpl_add_task'        => lang('Add a task'),
        'tpl_remove_task'     => lang('Remove the task'),
        'tpl_remove_note'     => lang('Remove the note'),
        'tpl_drag'            => lang('Drag to change the order'),
        'tpl_start_in'        => lang('Starts after (days)'),
        'tpl_due_in'          => lang('Due after (days)'),
        'tpl_estimate'        => lang('Estimate (minutes)'),
        'tpl_assign'          => lang('Given to'),
        'tpl_assign_creator'  => lang('Whoever applies the template'),
        'tpl_assign_lead'     => lang('The lead of the department'),
        'tpl_assign_none'     => lang('Nobody yet'),
        'tpl_assign_dept'     => ws_js_template('Pool of {var:1}', 1),
        'tpl_after'           => lang('After these tasks'),
        'tpl_after_help'      => lang('Only an earlier task can be chosen; drag the task down to wait for a later one.'),
        'tpl_task_n'          => ws_js_template('Task {var:1}', 1),
        'tpl_note_body'       => lang('Text'),
        'tpl_no_tasks'        => lang('No tasks in the template yet.'),
        'tpl_no_notes'        => lang('No notes in the template yet.'),
        'tpl_item_placeholder' => lang('An item of the checklist'),
        'tpl_departments'     => lang('Departments'),
        'tpl_people'          => lang('Team members'),
    );
}

/**
 * The Channel templates card of the workspace settings: the built-in ones to
 * copy, the site's own to change or archive.
 *
 * @param string $self_url
 * @param array  $viewer
 * @return string
 */
function ws_templates_settings_card($self_url, $viewer)
{
    if (!ws_can_write_templates($viewer)) {
        return '';
    }

    $edit_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace_template.php';
    $rows = '';

    foreach (ws_templates_list($viewer, true) as $template) {
        $present = ws_template_present($template);
        $icon = ($present['icon'] !== '') ? $present['icon'] : 'bi-layout-text-window';
        $actions = '';

        if ($present['builtin']) {
            $actions .= '<a class="btn btn-sm btn-ghost" href="' . h($edit_url . '?copy=' . rawurlencode($present['id'])) . '"><i class="bi bi-copy me-1" aria-hidden="true"></i>' . h(lang('Copy')) . '</a>';
        } else {
            $actions .= '<a class="btn btn-sm btn-ghost" href="' . h($edit_url . '?id=' . (int) $present['id']) . '"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . h(lang('Edit')) . '</a>'
                . '<form method="post" action="' . h($self_url) . '" class="d-inline">' . get_token_field()
                . '<input type="hidden" name="ws_action" value="template_archive">'
                . '<input type="hidden" name="template_id" value="' . (int) $present['id'] . '">'
                . '<input type="hidden" name="archive" value="' . ($present['archived'] ? 0 : 1) . '">'
                . '<button type="submit" class="btn btn-sm btn-ghost" title="' . h($present['archived'] ? lang('Bring it back') : lang('Move to the archive')) . '" aria-label="' . h($present['archived'] ? lang('Bring it back') : lang('Move to the archive')) . '"><i class="bi ' . ($present['archived'] ? 'bi-arrow-counterclockwise' : 'bi-archive') . '" aria-hidden="true"></i></button>'
                . '</form>';
        }

        $rows .= '
            <div class="ws-tpl-row' . ($present['archived'] ? ' opacity-50' : '') . '">
                <span class="ws-tpl-icon"' . ($present['hex'] !== '' ? ' style="background:' . h($present['hex']) . '"' : '') . '><i class="bi ' . h($icon) . '" aria-hidden="true"></i></span>
                <div class="ws-grow">
                    <div class="fw-semibold">' . h($present['name'])
                        . ($present['builtin'] ? ' <span class="badge text-bg-light ms-1">' . h(lang('Built-in')) . '</span>' : '')
                        . ($present['archived'] ? ' <span class="badge text-bg-secondary ms-1">' . h(lang('In the archive')) . '</span>' : '') . '</div>
                    <div class="small text-body-secondary">'
                        . h(lang(array('string' => '{var:1} tasks · {var:2} notes', 'vars' => array($present['tasks'], $present['notes']))))
                        . ($present['builtin'] ? '' : ' · ' . h(lang(array('string' => 'used {var:1} times', 'vars' => $present['uses']))))
                        . ($present['description'] !== '' ? ' · ' . h($present['description']) : '') . '
                    </div>
                </div>
                ' . $actions . '
            </div>';
    }

    return '
            <div class="card mt-4" id="ws-templates">
                <div class="card-header d-flex align-items-center gap-2">
                    <h2 class="h6 mb-0 ws-grow"><i class="bi bi-layout-text-window me-1" aria-hidden="true"></i>' . h(lang('Channel templates')) . '</h2>
                    <a class="btn btn-sm btn-primary rounded-pill px-3" href="' . h($edit_url) . '"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('New template')) . '</a>
                </div>
                <div class="card-body">
                    <p class="small text-body-secondary">' . h(lang('A template sets a channel up with its tasks, notes, summary and pinned message: when a channel is made from it, or later from the channel\'s menu.')) . '</p>
                    ' . $rows . '
                </div>
            </div>';
}

/**
 * The settings screen's form for the template card (archive, bring back).
 *
 * @param array   $viewer
 * @param object  $liveform
 */
function ws_templates_settings_post($viewer, $liveform)
{
    $archive = !empty($_POST['archive']);
    $result = ws_template_archive($viewer, (int) ($_POST['template_id'] ?? 0), $archive);

    if (!$result['ok']) {
        $liveform->add_error($result['error']);
        return;
    }

    $liveform->add_notice($archive
        ? lang(array('string' => 'The template was archived: {var:1}. The channels made from it keep what they were given.', 'vars' => $result['name']))
        : lang(array('string' => 'The template is back: {var:1}.', 'vars' => $result['name'])));
}
