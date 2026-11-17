<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the command registry: one entry per built-in / command, read
 * by the parser (ws_command_table()), the picker and /help (ws_commands()),
 * the gate (ws_command_offered()), the runner (ws_command_run()), the live
 * line (ws_command_suggest()) and the API (ws_api_command_kind()). Each
 * group's entries are written in a function of their own; functions are
 * named as strings, so the list is read without running anything.
 *
 * An entry:
 *   key          the command's own name
 *   tr, en       the name shown first on a Turkish and on any other screen
 *   label        both names, as /help heads the entry
 *   description  what it does
 *   example      a line as it would be typed
 *   args         ws_command_registry_arg() items, in the order typed
 *   names        every name the parser answers to, without the slash; empty
 *                for /claude and /ai, which become the assistant's tag
 *                before the commands are read
 *   group        ws_command_groups() value
 *   where        where it runs (ws_command_runs_here()): any, or channel
 *                (the channel itself, never one of its discussions)
 *   gate         function ($viewer, $key) => bool, '' for anybody; called
 *                with a null viewer as well
 *   runner       function ($viewer, $channel, $rest, $options, $body, $key)
 *   live         function ($viewer, $channel, $rest) => line, warnings; ''
 *                for none
 *   api          what the API's run route makes of it: preview, run, or a
 *                function ($rest, $options, $key) that answers one of them
 *   order        its place in the picker and /help
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
 * The groups the commands are kept in.
 *
 * @return string[]
 */
function ws_command_groups()
{
    return array('tasks', 'plan', 'channel', 'records', 'agents');
}

/**
 * The header of each group, as /help and the "/" list show it.
 *
 * @return array group => label
 */
function ws_command_group_labels()
{
    return array(
        'tasks'   => lang('Tasks'),
        'plan'    => lang('Plan'),
        'channel' => lang('The channel'),
        'records' => lang('Records'),
        'agents'  => lang('Agents and applications'),
    );
}

/**
 * May a command whose entry says $where run in this channel? Without a
 * channel, everywhere.
 *
 * @param string     $where   an entry's where: any | channel
 * @param array|null $channel ws_channels row
 * @return bool
 */
function ws_command_runs_here($where, $channel)
{
    if (!is_array($channel) || ($where !== 'channel')) {
        return true;
    }

    return (string) ($channel['kind'] ?? '') !== 'thread';
}

/**
 * Every built-in command, in the order the picker and /help list them. Read
 * once per language a request.
 *
 * @return array key => entry
 */
function ws_command_registry()
{
    static $cache = array();

    $language = (string) lang(array('info' => true));

    if (isset($cache[$language])) {
        return $cache[$language];
    }

    $entries = array_merge(
        ws_command_registry_tasks(),
        ws_command_registry_plan(),
        ws_command_registry_channel(),
        ws_command_registry_records(),
        ws_command_registry_agents()
    );

    $registry = array();

    foreach ($entries as $entry) {
        $registry[$entry['key']] = $entry + array('where' => 'any', 'gate' => '', 'live' => '', 'api' => 'run');
    }

    uasort($registry, function ($a, $b) {
        return $a['order'] - $b['order'];
    });

    return $cache[$language] = $registry;
}

/**
 * One argument of a command.
 *
 * @param string $name
 * @param string $kind     ws_command_argument_kinds() value
 * @param string $label
 * @param bool   $optional
 * @param string $suggest  where the composer finds values, '' for none
 * @param array  $more     multiple, anywhere
 * @return array
 */
function ws_command_registry_arg($name, $kind, $label, $optional, $suggest = '', $more = array())
{
    return array_merge(array(
        'name'     => $name,
        'kind'     => $kind,
        'label'    => $label,
        'optional' => (bool) $optional,
        'suggest'  => $suggest,
        'multiple' => false,
        'anywhere' => false,
    ), $more);
}

/**
 * The task commands.
 *
 * @return array[]
 */
function ws_command_registry_tasks()
{
    $arg = 'ws_command_registry_arg';
    $task = $arg('task', 'task', lang('task'), false, 'tasks');

    return array(
        array(
            'key' => 'task', 'tr' => 'görev', 'en' => 'task', 'label' => '/task · /görev',
            'description' => lang('Creates a task: people with @, a day, an estimate after ~, a priority after !. Shown to you before it is created.'),
            'example'     => lang('/task Logo revision @ayse friday ~2h !urgent'),
            'args'        => array(
                $arg('title', 'text', lang('title'), false),
                $arg('people', 'people', lang('@people'), true, 'people_departments', array('multiple' => true, 'anywhere' => true)),
                $arg('due', 'date', lang('day'), true, 'dates', array('anywhere' => true)),
                $arg('estimate', 'estimate', lang('~estimate'), true, 'estimates', array('anywhere' => true)),
                $arg('priority', 'priority', lang('!priority'), true, 'priorities', array('anywhere' => true)),
                $arg('records', 'record', lang('#record'), true, 'records', array('multiple' => true, 'anywhere' => true)),
            ),
            'names' => array('gorev', 'görev', 'task', 'g', 't'), 'group' => 'tasks', 'order' => 10,
            'runner' => 'ws_command_task', 'live' => 'ws_command_task_suggest', 'api' => 'preview',
        ),
        array(
            'key' => 'assign', 'tr' => 'ata', 'en' => 'assign', 'label' => '/assign · /ata',
            'description' => lang('Adds people to a task.'),
            'example'     => lang('/assign T-42 @mehmet'),
            'args'        => array($task, $arg('people', 'people', lang('@people'), false, 'people', array('multiple' => true))),
            'names' => array('ata', 'assign'), 'group' => 'tasks', 'order' => 20,
            'runner' => 'ws_command_run_assign',
        ),
        array(
            'key' => 'done', 'tr' => 'bitti', 'en' => 'done', 'label' => '/done · /bitti',
            'description' => lang('Marks a task as done.'),
            'example'     => lang('/done T-42'),
            'args'        => array($task),
            'names' => array('bitti', 'tamam', 'done'), 'group' => 'tasks', 'order' => 30,
            'runner' => 'ws_command_run_done',
        ),
        array(
            'key' => 'postpone', 'tr' => 'ertele', 'en' => 'postpone', 'label' => '/postpone · /ertele',
            'description' => lang('Moves a task\'s due date.'),
            'example'     => lang('/postpone T-42 +2d'),
            'args'        => array($task, $arg('due', 'date', lang('day'), false, 'dates')),
            'names' => array('ertele', 'postpone'), 'group' => 'tasks', 'order' => 40,
            'runner' => 'ws_command_run_postpone',
        ),
        array(
            'key' => 'status', 'tr' => 'durum', 'en' => 'status', 'label' => '/status · /durum',
            'description' => lang('Moves a task to another status: to do, in progress, waiting, done or cancelled.'),
            'example'     => lang('/status T-42 waiting'),
            'args'        => array($task, $arg('status', 'status', lang('status'), false, 'statuses')),
            'names' => array('durum', 'status'), 'group' => 'tasks', 'order' => 50,
            'runner' => 'ws_command_status', 'live' => 'ws_command_live_status',
        ),
        array(
            'key' => 'timer', 'tr' => 'süre', 'en' => 'timer', 'label' => '/timer · /süre',
            'description' => lang('Starts your timer on a task, stopping the one that runs; /timer stop stops it and keeps the time.'),
            'example'     => lang('/timer T-42'),
            'args'        => array($arg('task', 'task', lang('task'), true, 'tasks')),
            'names' => array('süre', 'sure', 'timer'), 'group' => 'tasks', 'order' => 60,
            'gate' => 'ws_command_gate_timer', 'runner' => 'ws_command_run_timer', 'live' => 'ws_command_live_timer',
        ),
    );
}

/**
 * The plan commands: leave, meetings, a person's week, a reminder.
 *
 * @return array[]
 */
function ws_command_registry_plan()
{
    $arg = 'ws_command_registry_arg';

    return array(
        array(
            'key' => 'away', 'tr' => 'izin', 'en' => 'away', 'label' => '/away · /izin',
            'description' => lang('Writes your leave on the plan: the first day, the last day if it is longer, then a note if you like.'),
            'example'     => lang('/away friday monday Family visit'),
            'args'        => array(
                $arg('from', 'date', lang('first day'), false, 'dates'),
                $arg('to', 'date', lang('last day'), true, 'dates'),
                $arg('title', 'text', lang('note'), true),
            ),
            'names' => array('izin', 'away'), 'group' => 'plan', 'order' => 80,
            'runner' => 'ws_command_run_away', 'live' => 'ws_command_live_away',
        ),
        array(
            'key' => 'meeting', 'tr' => 'toplantı', 'en' => 'meeting', 'label' => '/meeting · /toplantı',
            'description' => lang('Puts a meeting on the plan: a day, the start, the end or ~1h (an hour if left out), a title and the people with @.'),
            'example'     => lang('/meeting tomorrow 14:00 ~1h Kick-off @ayse'),
            'args'        => array(
                $arg('day', 'date', lang('day'), true, 'dates'),
                $arg('start', 'time', lang('start'), false),
                $arg('end', 'time', lang('end or ~length'), true),
                $arg('title', 'text', lang('title'), false),
                $arg('people', 'people', lang('@people'), true, 'people', array('multiple' => true, 'anywhere' => true)),
            ),
            'names' => array('toplantı', 'toplanti', 'meeting'), 'group' => 'plan', 'order' => 90,
            'runner' => 'ws_command_meeting', 'live' => 'ws_command_live_meeting',
        ),
        array(
            'key' => 'plan', 'tr' => 'plan', 'en' => 'plan', 'label' => '/plan',
            'description' => lang('Shows the next working week of a person, only to you.'),
            'example'     => lang('/plan @ayse'),
            'args'        => array($arg('person', 'person', lang('@person'), true, 'people')),
            'names' => array('plan'), 'group' => 'plan', 'order' => 120,
            'runner' => 'ws_command_run_plan',
        ),
        array(
            'key' => 'remind', 'tr' => 'hatırlat', 'en' => 'remind', 'label' => '/remind · /hatırlat',
            'description' => lang('Puts a plan on My calendar that reminds you at its start: a day, a time such as 10:00, then what to remind. Without a time the New plan form opens filled.'),
            'example'     => lang('/remind tomorrow 10:00 Send the quote'),
            'args'        => array(
                $arg('day', 'date', lang('day'), true, 'dates'),
                $arg('time', 'time', lang('time'), true),
                $arg('title', 'text', lang('what to remind'), false),
            ),
            'names' => array('hatırlat', 'hatirlat', 'remind'), 'group' => 'plan', 'order' => 125,
            'gate' => 'ws_command_gate_remind', 'runner' => 'ws_command_run_remind', 'live' => 'ws_command_live_remind',
            'api' => 'ws_api_command_kind_remind',
        ),
    );
}

/**
 * The channel's own commands: a later message, decisions, notes, polls,
 * the help.
 *
 * @return array[]
 */
function ws_command_registry_channel()
{
    $arg = 'ws_command_registry_arg';

    return array(
        array(
            'key' => 'schedule', 'tr' => 'zamanla', 'en' => 'schedule', 'label' => '/schedule · /zamanla',
            'description' => lang('Posts a message later: a day, a time such as 14:00 or +30m, then the message. Only you see it until then.'),
            'example'     => lang('/schedule tomorrow 09:00 Stand-up in ten minutes'),
            'args'        => array(
                $arg('day', 'date', lang('day'), true, 'dates'),
                $arg('time', 'time', lang('time'), true),
                $arg('message', 'text', lang('message'), false),
            ),
            'names' => array('zamanla', 'schedule'), 'group' => 'channel', 'order' => 70,
            'gate' => 'ws_command_gate_schedule', 'runner' => 'ws_command_schedule', 'live' => 'ws_command_schedule_suggest',
        ),
        array(
            'key' => 'decision', 'tr' => 'karar', 'en' => 'decision', 'label' => '/decision · /karar',
            'description' => lang('Writes a decision; it is kept on the Decisions tab.'),
            'example'     => lang('/decision The packaging will be kraft'),
            'args'        => array($arg('text', 'text', lang('text'), false)),
            'names' => array('karar', 'decision'), 'group' => 'channel', 'order' => 100,
            'runner' => 'ws_command_run_noted',
        ),
        array(
            'key' => 'note', 'tr' => 'not', 'en' => 'note', 'label' => '/note · /not',
            'description' => lang('Writes a note; it is kept on the Decisions tab.'),
            'example'     => lang('/note Accounts contact: Selin, ext. 204'),
            'args'        => array($arg('text', 'text', lang('text'), false)),
            'names' => array('not', 'note'), 'group' => 'channel', 'order' => 110,
            'runner' => 'ws_command_run_noted',
        ),
        array(
            'key' => 'poll', 'tr' => 'oylama', 'en' => 'poll', 'label' => '/poll · /oylama',
            'description' => lang('Asks the channel to choose; the options go after |. Without options it opens the poll form, where it can also be anonymous, multiple choice or closed at a set time.'),
            'example'     => lang('/poll Which day for the launch? | Tuesday | Wednesday'),
            'args'        => array($arg('question', 'text', lang('question'), false), $arg('options', 'options', lang('| options'), true, '', array('multiple' => true))),
            'names' => array('oylama', 'anket', 'sor', 'poll'), 'group' => 'channel', 'order' => 130,
            'runner' => 'ws_command_run_poll', 'api' => 'ws_api_command_kind_poll',
        ),
        array(
            'key' => 'recap', 'tr' => 'özet', 'en' => 'recap', 'label' => '/recap · /özet',
            'description' => lang('Pinegrap AI sums up what you missed here since your last visit, only to you. Offered where the line over the conversation offers it.'),
            'example'     => lang('/recap'),
            'args'        => array(),
            'names' => array('özet', 'ozet', 'recap'), 'group' => 'channel', 'order' => 135,
            'gate' => 'ws_command_gate_recap', 'runner' => 'ws_command_run_recap',
        ),
        array(
            'key' => 'help', 'tr' => 'yardım', 'en' => 'help', 'label' => '/help · /yardım',
            'description' => lang('Lists the commands and what they do.'),
            'example'     => lang('/help'),
            'args'        => array(),
            'names' => array('yardim', 'yardım', 'help'), 'group' => 'channel', 'order' => 1000,
            'runner' => 'ws_command_run_help',
        ),
    );
}

/**
 * The commands that change or make a site record: a price, a stock, a
 * product or a product group.
 *
 * @return array[]
 */
function ws_command_registry_records()
{
    $arg = 'ws_command_registry_arg';
    $product = $arg('product', 'record', lang('#product'), false, 'products');

    return array(
        array(
            'key' => 'price', 'tr' => 'fiyat', 'en' => 'price', 'label' => '/price · /fiyat',
            'description' => lang('Changes the price of a product. Shown to you, old and new, before it is written.'),
            'example'     => lang('/price #mug 149,90'),
            'args'        => array($product, $arg('amount', 'amount', lang('new price'), false)),
            'names' => array('fiyat', 'price'), 'group' => 'records', 'order' => 140,
            'gate' => 'ws_command_gate_product', 'runner' => 'ws_command_run_record', 'api' => 'preview',
        ),
        array(
            'key' => 'stock', 'tr' => 'stok', 'en' => 'stock', 'label' => '/stock · /stok',
            'description' => lang('Sets the stock of a product, or moves it with +5 or -3. Shown to you, old and new, before it is written.'),
            'example'     => lang('/stock #mug 12'),
            'args'        => array($product, $arg('quantity', 'quantity', lang('quantity'), false)),
            'names' => array('stok', 'stock'), 'group' => 'records', 'order' => 150,
            'gate' => 'ws_command_gate_product', 'runner' => 'ws_command_run_record', 'api' => 'preview',
        ),
        array(
            'key' => 'create', 'tr' => 'oluştur', 'en' => 'create', 'label' => '/create · /oluştur',
            'description' => lang('Creates a product or a product group, off sale: the kind, then its name; a product takes a price and a #group if you like. Shown to you before it is created. Several product lines, one per line, make one card of drafts for you to apply.'),
            'example'     => lang('/create product Blue mug 149,90'),
            'args'        => array(
                $arg('kind', 'record_kind', lang('kind'), false, 'kinds'),
                // The rest of the line: the popup stays quiet on it.
                $arg('name', 'text', lang('name'), false, '', array('multiple' => true)),
                $arg('price', 'amount', lang('price'), true),
                $arg('group', 'record', lang('#group'), true, 'records', array('anywhere' => true)),
            ),
            'names' => array('oluştur', 'olustur', 'create'), 'group' => 'records', 'order' => 160,
            'gate' => 'ws_command_gate_product', 'runner' => 'ws_command_run_create', 'api' => 'ws_api_command_kind_create',
        ),
        array(
            'key' => 'product', 'tr' => 'ürün', 'en' => 'product', 'label' => '/product · /ürün',
            'description' => lang('Creates a product, off sale: its name, then a price and a #group if you like. Shown to you before it is created. Several lines, one product per line, make one card of drafts for you to apply.'),
            'example'     => lang('/product Blue mug 149,90'),
            'args'        => array(
                $arg('name', 'text', lang('product name'), false),
                $arg('price', 'amount', lang('price'), true),
                $arg('group', 'record', lang('#group'), true, 'records', array('anywhere' => true)),
            ),
            'names' => array('urun', 'ürün', 'product'), 'group' => 'records', 'order' => 170,
            'gate' => 'ws_command_gate_product', 'runner' => 'ws_command_run_create', 'api' => 'ws_api_command_kind_create',
        ),
    );
}

/**
 * The commands of agents and assistants.
 *
 * @return array[]
 */
function ws_command_registry_agents()
{
    $arg = 'ws_command_registry_arg';
    $question = $arg('question', 'text', lang('question'), false);

    return array(
        array(
            'key' => 'wake', 'tr' => 'uyandır', 'en' => 'wake', 'label' => '/wake · /uyandır',
            'description' => lang('Wakes an application agent of this channel on a schedule: every 30m, at 21:00, with a note; off stops it, the agent alone shows it.'),
            'example'     => lang('/wake @ci every 30m Look at the queue'),
            'args'        => array(
                $arg('agent', 'text', lang('@agent'), false),
                $arg('when', 'text', lang('every 30m | at 21:00 | off'), true),
                $arg('note', 'text', lang('note'), true),
            ),
            'names' => array('uyandır', 'uyandir', 'wake'), 'group' => 'agents', 'order' => 180,
            'gate' => 'ws_command_gate_wake', 'runner' => 'ws_command_run_wake',
        ),
        array(
            'key' => 'agent_channel', 'tr' => 'ajan-kanalı', 'en' => 'agent-channel', 'label' => '/agent-channel · /ajan-kanalı',
            'description' => lang('Makes this an agent channel (on) or a team channel again (off): an agent channel\'s decisions stay apart from the other channels\' decisions. A channel manager switches it; nothing after it says the state.'),
            'example'     => lang('/agent-channel on'),
            'args'        => array(
                $arg('state', 'text', lang('on | off'), true),
            ),
            'names' => array('ajan-kanalı', 'ajan-kanali', 'agent-channel'), 'group' => 'agents', 'where' => 'channel', 'order' => 190,
            'gate' => 'ws_command_gate_agent_channel', 'runner' => 'ws_command_agent_channel',
        ),
        array(
            'key' => 'claude', 'tr' => 'claude', 'en' => 'claude', 'label' => '/claude',
            'description' => lang('Asks Claude, the same as writing @Claude.'),
            'example'     => lang('/claude summarise this week'),
            'args'        => array($question),
            'names' => array(), 'group' => 'agents', 'order' => 200,
            'gate' => 'ws_command_gate_assistant', 'runner' => 'ws_command_run_help',
        ),
        array(
            'key' => 'ai', 'tr' => 'ai', 'en' => 'ai', 'label' => '/ai',
            'description' => lang('Asks Pinegrap AI, the same as writing @ai.'),
            'example'     => lang('/ai summarise this week'),
            'args'        => array($question),
            'names' => array(), 'group' => 'agents', 'order' => 210,
            'gate' => 'ws_command_gate_assistant', 'runner' => 'ws_command_run_help',
        ),
    );
}

/**
 * The API class of /create and /product: several lines make a bulk card
 * and nothing after the command in an answer reads the answered file, both
 * written at once; otherwise a preview.
 *
 * @param string $rest
 * @param array  $options
 * @param string $key create | product
 * @return string preview | run
 */
function ws_api_command_kind_create($rest, $options, $key)
{
    $list = function ($text) {
        return function_exists('ws_bulk_text_is_list') ? ws_bulk_text_is_list($text) : (strpos(trim($text), "\n") !== false);
    };

    $product = ($key === 'create') ? ws_command_parse_create($rest)['rest'] : $rest;

    if ($list($rest) || ((trim($product) === '') && ((int) ($options['parent_id'] ?? 0) > 0))) {
        return 'run';
    }

    return 'preview';
}

/**
 * The API class of /poll: fewer than two options open the form; more post
 * the poll.
 *
 * @param string $rest
 * @return string preview | run
 */
function ws_api_command_kind_poll($rest)
{
    $parts = array_filter(array_map('trim', explode('|', $rest)), 'strlen');

    return (count($parts) < 3) ? 'preview' : 'run';
}

/**
 * The API class of /remind: without a time it opens the New plan form, so
 * nothing is written; with one the plan is saved.
 *
 * @param string $rest
 * @return string preview | run
 */
function ws_api_command_kind_remind($rest)
{
    return (ws_command_remind_data($rest)['time'] === '') ? 'preview' : 'run';
}

/**
 * Is an application's command name a built-in one, or an assistant's? Such
 * a command is shadowed: its row is kept, the built-in runs, the picker
 * never offers it and the API lists it as shadowed. A new name is refused
 * at registration for the same reason.
 *
 * @param string $name without its slash
 * @return bool
 */
function ws_command_shadowed($name)
{
    return in_array(ws_command_fold($name), array_map('ws_command_fold', array_keys(ws_command_table())), true)
        || ws_command_assistant_word($name);
}

/**
 * Registered commands as ws_commands() entries, each marked shadowed or
 * not; a shadowed one only when asked for.
 *
 * @param array[] $rows     ws_app_commands_rows() rows
 * @param bool    $shadowed keep the shadowed ones (the API's list)
 * @return array[]
 */
function ws_command_app_entries($rows, $shadowed = false)
{
    $out = array();

    foreach ($rows as $row) {
        $entry = ws_app_command_entry($row);
        $entry['shadowed'] = ws_command_shadowed((string) $row['name']);

        if ($entry['shadowed'] && !$shadowed) {
            continue;
        }

        $out[] = $entry;
    }

    return $out;
}
