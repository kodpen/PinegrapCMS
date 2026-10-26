<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - commands typed into a channel.
 *
 *   /task Logo revision @ayse friday ~2h !urgent #contact   (also /görev, /gorev)
 *   /assign T-42 @mehmet                                     (also /ata)
 *   /done T-42                                               (also /bitti, /tamam)
 *   /postpone T-42 +2d                                       (also /ertele)
 *   /schedule tomorrow 09:00 Stand-up in ten minutes         (also /zamanla)
 *   /decision The packaging will be kraft                    (also /karar)
 *   /note Accounts contact: Selin, ext. 204                  (also /not)
 *   /plan @ayse                                              the next working week
 *   /poll Which day? | Tuesday | Wednesday                   (also /oylama, /anket, /sor)
 *   /price #product 149,90                                   (also /fiyat)
 *   /stock #product 12, +5 or -3                             (also /stok)
 *   /product Blue mug 149,90 #group                          (also /ürün, /urun)
 *   /help                                                    (also /yardım, /yardim)
 *
 * A command is a shortcut, never the only way: each has a button somewhere on
 * the screen. Creating a task is always shown back first - "this is the task I
 * would create, with these warnings" - and only an explicit confirmation
 * writes it, so a misread date never becomes a task nobody asked for. A price
 * or a stock is shown back the same way, old value beside the new one, and a
 * new product with every value it will be created with.
 *
 * The date words are a short fixed list (today, tomorrow, weekday names,
 * 26.09, 26.09.2026, +3d, +2w, haftaya or nextweek written as one word) in
 * Turkish and English. Anything else stays in the title, where the author
 * will see it in the preview.
 *
 * One registry (ws_commands()) describes every command: its names, what it
 * does, its usage line and the arguments it takes in order, each typed, so
 * the composer's / picker, the argument popup after "/name ", /help and the
 * command palette read the same list. A slash and a word that is no command
 * ("/gorevx") is not posted: the writer is told the closest names. Two
 * slashes ("//gorevx") post the text with one slash taken off.
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
        'durum' => 'status', 'status' => 'status',
        'zamanla' => 'schedule', 'schedule' => 'schedule',
        'karar' => 'decision', 'decision' => 'decision',
        'not' => 'note', 'note' => 'note',
        'plan' => 'plan',
        'oylama' => 'poll', 'anket' => 'poll', 'sor' => 'poll', 'poll' => 'poll',
        'fiyat' => 'price', 'price' => 'price',
        'stok' => 'stock', 'stock' => 'stock',
        'yardim' => 'help', 'yardım' => 'help', 'help' => 'help',
    );
}

/**
 * The names one command answers to, in the order ws_command_table() lists
 * them.
 *
 * @param string $command the command's own name (ws_command_table() value)
 * @return string[] names without the leading slash
 */
function ws_command_aliases($command)
{
    return array_keys(ws_command_table(), (string) $command, true);
}

/**
 * The names of the commands that make a new record: /product makes a
 * product (includes/workspace/quick_edit.php, ws_quick_create_save()). Kept
 * beside ws_command_names(), the commands that act on what is there; the
 * parser reads both through ws_command_table().
 *
 * @return array name => command
 */
function ws_command_create_names()
{
    return array('urun' => 'product', 'ürün' => 'product', 'product' => 'product');
}

/**
 * Every name the parser answers to: ws_command_names() and
 * ws_command_create_names() together.
 *
 * @return array name => command
 */
function ws_command_table()
{
    return ws_command_names() + ws_command_create_names();
}

/**
 * The words after a slash that ask an assistant rather than run a command.
 * Once the assistant is set up ws_claude_command_body() and
 * ws_ai_command_body() turn them into its tag before the commands are read;
 * before that the text is posted and the assistant's line says how to set it
 * up, so they are never refused as unknown commands.
 *
 * @return string[]
 */
function ws_command_assistant_words()
{
    return array('claude', 'ai');
}

/**
 * Whether "/claude" or "/ai" asks its assistant on this site: the test
 * ws_claude_command_body() and ws_ai_command_body() make before they turn
 * the command into the assistant's tag.
 *
 * @param string $command claude | ai
 * @return bool
 */
function ws_command_assistant_ready($command)
{
    if ($command === 'claude') {
        return function_exists('ws_claude_schema_ready') && function_exists('ws_claude_config')
            && ws_claude_schema_ready() && ((int) ws_claude_config()['app_id'] > 0);
    }

    if ($command === 'ai') {
        return function_exists('ws_ai_schema_ready') && function_exists('ws_ai_config')
            && ws_ai_schema_ready() && ((int) ws_ai_config()['app_id'] > 0);
    }

    return false;
}

/**
 * May this person use a command? /price, /stock and /product write a
 * product, so they are offered only to whoever may change products (the
 * e-commerce right, as add_product.php asks it); /schedule only to whoever
 * may schedule messages (scheduled_messages.php); /claude and /ai only once
 * their assistant is set up on the site; the others are anybody's. Without a
 * person every command the parser answers to is listed, and the assistants
 * are left out: that list is read without asking the database anything.
 *
 * @param array|null $viewer
 * @param string     $command the command's own name (ws_command_table()
 *                            value, or claude | ai)
 * @return bool
 */
function ws_command_offered($viewer, $command)
{
    if (in_array($command, ws_command_assistant_words(), true)) {
        return is_array($viewer) && ws_command_assistant_ready($command);
    }

    if (($command === 'schedule') && is_array($viewer)) {
        return function_exists('ws_can_schedule_messages') && ws_can_schedule_messages($viewer);
    }

    if (!in_array($command, array('price', 'stock', 'product'), true) || !is_array($viewer)) {
        return true;
    }

    return function_exists('ws_change_allowed') && ws_change_allowed($viewer, ($command === 'stock') ? 'stock' : 'product', 0);
}

/**
 * The kinds of argument a command takes, as the registry types them. The
 * composer suggests values for some of them (ws_commands(), suggest).
 *
 * @return string[]
 */
function ws_command_argument_kinds()
{
    return array('person', 'people', 'department', 'task', 'record', 'date', 'time', 'estimate', 'priority', 'status', 'amount', 'quantity', 'text', 'options');
}

/**
 * Whether the commands are shown with their Turkish names and words. The
 * parser reads both languages either way; a screen in any other language is
 * shown the English ones.
 *
 * @param string|null $language a language code, or null for the active one
 * @return bool
 */
function ws_command_turkish($language = null)
{
    if ($language === null) {
        $language = (string) lang(array('info' => true));
    }

    return strtolower((string) $language) === 'tr';
}

/**
 * Every command the person may use, in the order the picker and /help list
 * them. Each entry:
 *   key          the command's own name (ws_command_table() value, or claude | ai)
 *   names        every name it answers to, each with its slash, in
 *                ws_command_table() order
 *   name         the name shown first: the Turkish one on a Turkish screen
 *   label        both names, as /help heads the entry ("/task · /görev")
 *   description  what it does
 *   usage        the name and its arguments: <required> [optional]
 *   example      a line as it would be typed
 *   args         in the order they are typed: name, kind
 *                (ws_command_argument_kinds()), label, optional, suggest
 *                (where the composer finds values for it: people,
 *                people_departments, tasks, records, products, dates,
 *                estimates, priorities, statuses, or '' for none), multiple (takes more
 *                than one), anywhere (read wherever it is written, as /task
 *                reads its people, day, estimate and priority)
 *   live         the composer asks ws_command_suggest() for a line under
 *                the usage strip while it is written
 *
 * @param array|null $viewer leaves out the commands this person may not use
 * @return array[]
 */
function ws_commands($viewer = null)
{
    $turkish = ws_command_turkish();

    $arg = function ($name, $kind, $label, $optional, $suggest = '', $more = array()) {
        return array_merge(array(
            'name'     => $name,
            'kind'     => $kind,
            'label'    => $label,
            'optional' => (bool) $optional,
            'suggest'  => $suggest,
            'multiple' => false,
            'anywhere' => false,
        ), $more);
    };

    $task = $arg('task', 'task', lang('task'), false, 'tasks');
    $product = $arg('product', 'record', lang('#product'), false, 'products');
    $question = $arg('question', 'text', lang('question'), false);

    $list = array(
        array(
            'key' => 'task', 'tr' => 'görev', 'en' => 'task', 'label' => '/task · /görev',
            'description' => lang('Creates a task: people with @, a day, an estimate after ~, a priority after !. Shown to you before it is created.'),
            'example'     => lang('/task Logo revision @ayse friday ~2h !urgent'),
            'live'        => true,
            'args'        => array(
                $arg('title', 'text', lang('title'), false),
                $arg('people', 'people', lang('@people'), true, 'people_departments', array('multiple' => true, 'anywhere' => true)),
                $arg('due', 'date', lang('day'), true, 'dates', array('anywhere' => true)),
                $arg('estimate', 'estimate', lang('~estimate'), true, 'estimates', array('anywhere' => true)),
                $arg('priority', 'priority', lang('!priority'), true, 'priorities', array('anywhere' => true)),
                $arg('records', 'record', lang('#record'), true, 'records', array('multiple' => true, 'anywhere' => true)),
            ),
        ),
        array(
            'key' => 'assign', 'tr' => 'ata', 'en' => 'assign', 'label' => '/assign · /ata',
            'description' => lang('Adds people to a task.'),
            'example'     => lang('/assign T-42 @mehmet'),
            'args'        => array($task, $arg('people', 'people', lang('@people'), false, 'people', array('multiple' => true))),
        ),
        array(
            'key' => 'done', 'tr' => 'bitti', 'en' => 'done', 'label' => '/done · /bitti',
            'description' => lang('Marks a task as done.'),
            'example'     => lang('/done T-42'),
            'args'        => array($task),
        ),
        array(
            'key' => 'postpone', 'tr' => 'ertele', 'en' => 'postpone', 'label' => '/postpone · /ertele',
            'description' => lang('Moves a task\'s due date.'),
            'example'     => lang('/postpone T-42 +2d'),
            'args'        => array($task, $arg('due', 'date', lang('day'), false, 'dates')),
        ),
        array(
            'key' => 'status', 'tr' => 'durum', 'en' => 'status', 'label' => '/status · /durum',
            'description' => lang('Moves a task to another status: to do, in progress, waiting, done or cancelled.'),
            'example'     => lang('/status T-42 waiting'),
            'live'        => true,
            'args'        => array($task, $arg('status', 'status', lang('status'), false, 'statuses')),
        ),
        array(
            'key' => 'schedule', 'tr' => 'zamanla', 'en' => 'schedule', 'label' => '/schedule · /zamanla',
            'description' => lang('Posts a message later: a day, a time such as 14:00 or +30m, then the message. Only you see it until then.'),
            'example'     => lang('/schedule tomorrow 09:00 Stand-up in ten minutes'),
            'live'        => true,
            'args'        => array(
                $arg('day', 'date', lang('day'), true, 'dates'),
                $arg('time', 'time', lang('time'), true),
                $arg('message', 'text', lang('message'), false),
            ),
        ),
        array(
            'key' => 'decision', 'tr' => 'karar', 'en' => 'decision', 'label' => '/decision · /karar',
            'description' => lang('Writes a decision; it is kept on the Decisions tab.'),
            'example'     => lang('/decision The packaging will be kraft'),
            'args'        => array($arg('text', 'text', lang('text'), false)),
        ),
        array(
            'key' => 'note', 'tr' => 'not', 'en' => 'note', 'label' => '/note · /not',
            'description' => lang('Writes a note; it is kept on the Decisions tab.'),
            'example'     => lang('/note Accounts contact: Selin, ext. 204'),
            'args'        => array($arg('text', 'text', lang('text'), false)),
        ),
        array(
            'key' => 'plan', 'tr' => 'plan', 'en' => 'plan', 'label' => '/plan',
            'description' => lang('Shows the next working week of a person, only to you.'),
            'example'     => lang('/plan @ayse'),
            'args'        => array($arg('person', 'person', lang('@person'), true, 'people')),
        ),
        array(
            'key' => 'poll', 'tr' => 'oylama', 'en' => 'poll', 'label' => '/poll · /oylama',
            'description' => lang('Asks the channel to choose; the options go after |. Without options it opens the poll form, where it can also be anonymous, multiple choice or closed at a set time.'),
            'example'     => lang('/poll Which day for the launch? | Tuesday | Wednesday'),
            'args'        => array($question, $arg('options', 'options', lang('| options'), true, '', array('multiple' => true))),
        ),
        array(
            'key' => 'price', 'tr' => 'fiyat', 'en' => 'price', 'label' => '/price · /fiyat',
            'description' => lang('Changes the price of a product. Shown to you, old and new, before it is written.'),
            'example'     => lang('/price #mug 149,90'),
            'args'        => array($product, $arg('amount', 'amount', lang('new price'), false)),
        ),
        array(
            'key' => 'stock', 'tr' => 'stok', 'en' => 'stock', 'label' => '/stock · /stok',
            'description' => lang('Sets the stock of a product, or moves it with +5 or -3. Shown to you, old and new, before it is written.'),
            'example'     => lang('/stock #mug 12'),
            'args'        => array($product, $arg('quantity', 'quantity', lang('quantity'), false)),
        ),
        array(
            'key' => 'product', 'tr' => 'ürün', 'en' => 'product', 'label' => '/product · /ürün',
            'description' => lang('Creates a product, off sale: its name, then a price and a #group if you like. Shown to you before it is created.'),
            'example'     => lang('/product Blue mug 149,90'),
            'args'        => array(
                $arg('name', 'text', lang('product name'), false),
                $arg('price', 'amount', lang('price'), true),
                $arg('group', 'record', lang('#group'), true, 'records', array('anywhere' => true)),
            ),
        ),
        array(
            'key' => 'claude', 'tr' => 'claude', 'en' => 'claude', 'label' => '/claude',
            'description' => lang('Asks Claude, the same as writing @Claude.'),
            'example'     => lang('/claude summarise this week'),
            'args'        => array($question),
        ),
        array(
            'key' => 'ai', 'tr' => 'ai', 'en' => 'ai', 'label' => '/ai',
            'description' => lang('Asks Pinegrap AI, the same as writing @ai.'),
            'example'     => lang('/ai summarise this week'),
            'args'        => array($question),
        ),
        array(
            'key' => 'help', 'tr' => 'yardım', 'en' => 'help', 'label' => '/help · /yardım',
            'description' => lang('Lists the commands and what they do.'),
            'example'     => lang('/help'),
            'args'        => array(),
        ),
    );

    $out = array();

    foreach ($list as $entry) {
        if (!ws_command_offered($viewer, $entry['key'])) {
            continue;
        }

        $aliases = in_array($entry['key'], ws_command_assistant_words(), true) ? array($entry['key']) : ws_command_aliases($entry['key']);
        $name = '/' . ($turkish ? $entry['tr'] : $entry['en']);
        $usage = $name;

        foreach ($entry['args'] as $item) {
            $usage .= ' ' . ($item['optional'] ? '[' . $item['label'] . ']' : '<' . $item['label'] . '>');
        }

        $out[] = array(
            'key'         => $entry['key'],
            'names'       => array_map(function ($alias) {
                return '/' . $alias;
            }, $aliases),
            'name'        => $name,
            'label'       => $entry['label'],
            'description' => $entry['description'],
            'usage'       => $usage,
            'example'     => $entry['example'],
            'args'        => $entry['args'],
            'live'        => !empty($entry['live']),
        );
    }

    return $out;
}

/**
 * The help text the /help command, the composer's / picker and the command
 * palette show: the registry (ws_commands()) in the shape they read.
 *
 * @param array|null $viewer leaves out the commands this person may not use
 * @return array[] command (the label), names (every name it answers to, each
 *                 with its slash, for the composer's / picker), example,
 *                 description; and from the registry key, name, usage, args,
 *                 live for the composer's argument popup and live line
 */
function ws_command_help($viewer = null)
{
    $help = array();

    foreach (ws_commands($viewer) as $entry) {
        $help[] = array(
            'command'     => $entry['label'],
            'names'       => $entry['names'],
            'example'     => $entry['example'],
            'description' => $entry['description'],
            'key'         => $entry['key'],
            'name'        => $entry['name'],
            'usage'       => $entry['usage'],
            'args'        => $entry['args'],
            'live'        => $entry['live'],
        );
    }

    return $help;
}

/**
 * The fixed values the composer's argument popup offers after a command:
 * days, estimates, priorities and task statuses, each written as the parser
 * reads it (ws_parse_date_word(), ws_parse_estimate(), ws_parse_priority(),
 * ws_parse_status_word()), in the
 * screen's language. The first days are "top": the ones shown before
 * anything is typed; the others are found as they are typed.
 *
 * @param string|null $language a language code, or null for the active one
 * @return array dates, estimates, priorities, statuses: each a list of value,
 *               meta, top
 */
function ws_command_suggestions($language = null)
{
    $turkish = ws_command_turkish($language);
    $today = date('Y-m-d');

    $days = $turkish
        ? array('bugün', 'yarın', 'cuma', '+2g', 'haftaya', 'pazartesi', 'salı', 'çarşamba', 'perşembe', 'cumartesi', 'pazar', '+1g', '+3g', '+1hafta')
        : array('today', 'tomorrow', 'friday', '+2d', 'nextweek', 'monday', 'tuesday', 'wednesday', 'thursday', 'saturday', 'sunday', '+1d', '+3d', '+1w');

    $estimates = $turkish ? array('~30dk', '~1sa', '~2sa', '~4sa') : array('~30m', '~1h', '~2h', '~4h');

    $priorities = $turkish
        ? array('urgent' => '!acil', 'high' => '!yüksek', 'normal' => '!normal', 'low' => '!düşük')
        : array('urgent' => '!urgent', 'high' => '!high', 'normal' => '!normal', 'low' => '!low');

    $statuses = $turkish
        ? array('todo' => 'yapılacak', 'doing' => 'sürüyor', 'waiting' => 'bekliyor', 'done' => 'bitti', 'cancelled' => 'iptal')
        : array('todo' => 'todo', 'doing' => 'doing', 'waiting' => 'waiting', 'done' => 'done', 'cancelled' => 'cancelled');

    $labels = function_exists('ws_task_priorities') ? ws_task_priorities() : array();
    $status_labels = function_exists('ws_task_statuses') ? ws_task_statuses() : array();
    $out = array('dates' => array(), 'estimates' => array(), 'priorities' => array(), 'statuses' => array());

    foreach ($days as $index => $word) {
        $date = ws_parse_date_word($word, $today);

        $out['dates'][] = array('value' => $word, 'meta' => function_exists('ws_day_label') ? ws_day_label($date) : $date, 'top' => ($index < 5));
    }

    foreach ($estimates as $word) {
        $out['estimates'][] = array('value' => $word, 'meta' => '', 'top' => true);
    }

    foreach ($priorities as $key => $word) {
        $out['priorities'][] = array('value' => $word, 'meta' => (string) ($labels[$key] ?? ''), 'top' => true);
    }

    foreach ($statuses as $key => $word) {
        $out['statuses'][] = array('value' => $word, 'meta' => (string) ($status_labels[$key] ?? ''), 'top' => true);
    }

    return $out;
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

    $names = ws_command_table();
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
 * Lower case with the Turkish letters folded to plain ones (ç → c, ğ → g,
 * ı → i, ö → o, ş → s, ü → u), so a name typed without them, or in
 * capitals, still meets it.
 *
 * @param string $text
 * @return string
 */
function ws_command_fold($text)
{
    return strtr(ws_lower($text), array('ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'â' => 'a', 'î' => 'i', 'û' => 'u'));
}

/**
 * The word a text tries to run as a command: a slash and letters only at its
 * very start, then a space or its end ("/todo", "/gorevx yarın"). A path
 * ("/var/www"), an address, two slashes, digits or a slash and a space are
 * text, not an attempt.
 *
 * @param string $body
 * @return string the word without its slash, as typed; '' when there is none
 */
function ws_command_attempt($body)
{
    if (!preg_match('/^\/(\p{L}{1,20})(?=\s|$)/u', ltrim((string) $body), $match)) {
        return '';
    }

    return $match[1];
}

/**
 * Is the word one of the assistants' ("/claude", "/ai")? Matched the way
 * their own patterns match it, ASCII case-insensitive, and in Turkish lower
 * case as well.
 *
 * @param string $word without its slash
 * @return bool
 */
function ws_command_assistant_word($word)
{
    return in_array(strtolower((string) $word), ws_command_assistant_words(), true)
        || in_array(ws_lower($word), ws_command_assistant_words(), true);
}

/**
 * A command that does not exist: the text starts like one
 * (ws_command_attempt()) but no command answers to the word, and it is not an
 * assistant's word.
 *
 * @param string $body
 * @return string the word as typed, without its slash; '' when the text is a
 *                command or no attempt at one
 */
function ws_command_unknown($body)
{
    $word = ws_command_attempt($body);

    if (($word === '') || ws_command_assistant_word($word) || (ws_command_split($body) !== null)) {
        return '';
    }

    return $word;
}

/**
 * The command names nearest to a word that is no command, for the hint that
 * answers it: names the word starts, names that start the word, and names
 * one or two letters away (one for a word of up to three letters), Turkish
 * letters folded. One name per command, the closest first; of two names
 * equally near, the one with its Turkish letters, as the help writes it.
 * The one-letter shortcuts (/g, /t) are never suggested.
 *
 * @param string     $word   without its slash
 * @param array|null $viewer only the commands this person may use
 * @param int        $limit
 * @return string[] names with their slash
 */
function ws_command_closest($word, $viewer = null, $limit = 3)
{
    $typed = ws_command_fold($word);
    $length = strlen($typed);
    $best = array();

    if ($typed === '') {
        return array();
    }

    foreach (ws_commands($viewer) as $order => $entry) {
        foreach ($entry['names'] as $name) {
            $plain = ltrim($name, '/');
            $folded = ws_command_fold($plain);

            if (mb_strlen($plain, 'UTF-8') < 2) {
                continue;
            }

            $distance = levenshtein($typed, $folded);
            $prefix = (($length >= 2) && (strpos($folded, $typed) === 0)) || ((strlen($folded) >= 3) && (strpos($typed, $folded) === 0));

            if (!$prefix && ($distance > (($length <= 3) ? 1 : 2))) {
                continue;
            }

            $score = array($prefix ? 0 : 1, $distance, ($plain !== $folded) ? 0 : 1, $order);

            if (!isset($best[$entry['key']]) || ($score < $best[$entry['key']]['score'])) {
                $best[$entry['key']] = array('score' => $score, 'name' => $name);
            }
        }
    }

    usort($best, function ($a, $b) {
        return ($a['score'] < $b['score']) ? -1 : (($a['score'] > $b['score']) ? 1 : 0);
    });

    return array_slice(array_map(function ($item) {
        return $item['name'];
    }, $best), 0, max(1, (int) $limit));
}

/**
 * The answer to a command that does not exist: shown to the writer only,
 * nothing posted, the text left in the box to be put right. It names the
 * closest commands and says how to post the line as a message.
 *
 * @param array|null $viewer
 * @param string     $word as typed, without its slash
 * @return array type ephemeral, keep (the box keeps the text), html, unknown,
 *               closest
 */
function ws_command_unknown_answer($viewer, $word)
{
    $closest = ws_command_closest($word, $viewer);
    $html = '<div class="ws-ephemeral-title">' . h(lang(array('string' => '{var:1} is not a command.', 'vars' => '/' . $word))) . '</div>';

    if (!empty($closest)) {
        $html .= '<div>' . h(lang('Did you mean:')) . ' ' . implode(' ', array_map(function ($name) {
            return '<code>' . h($name) . '</code>';
        }, $closest)) . '</div>';
    }

    $html .= '<div class="text-body-secondary">' . h(lang(array(
        'string' => 'Nothing was sent. To post it as a message, start it with two slashes: {var:1}. {var:2} lists every command.',
        'vars'   => array('//' . $word, lang('/help')),
    ))) . '</div>';

    return array('type' => 'ephemeral', 'keep' => true, 'html' => $html, 'unknown' => '/' . $word, 'closest' => $closest);
}

/**
 * Two slashes before a word ("//gorevx", "//not bir komut") post the text as
 * a message, one slash taken off. Only where one slash would read as a
 * command (ws_command_attempt()): "// TODO" or "///x" stay as they are, and
 * so does "//claude", which would otherwise ask the assistant once posted.
 *
 * @param string $body
 * @return string
 */
function ws_command_unescape($body)
{
    $body = (string) $body;
    $trimmed = ltrim($body);

    if ((strpos($trimmed, '//') !== 0) || !preg_match('/^\/\/(\p{L}{1,20})(?=\s|$)/u', $trimmed, $match) || ws_command_assistant_word($match[1])) {
        return $body;
    }

    return substr($trimmed, 1);
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
        'pazartesi' => 1, 'pazartesiye' => 1, 'pzt' => 1, 'monday' => 1, 'mon' => 1,
        'salı' => 2, 'sali' => 2, 'salıya' => 2, 'saliya' => 2, 'tuesday' => 2, 'tue' => 2,
        'çarşamba' => 3, 'carsamba' => 3, 'çarşambaya' => 3, 'carsambaya' => 3, 'wednesday' => 3, 'wed' => 3,
        'perşembe' => 4, 'persembe' => 4, 'perşembeye' => 4, 'persembeye' => 4, 'thursday' => 4, 'thu' => 4,
        'cuma' => 5, 'cumaya' => 5, 'friday' => 5, 'fri' => 5,
        'cumartesi' => 6, 'cumartesiye' => 6, 'saturday' => 6, 'sat' => 6,
        'pazar' => 7, 'pazara' => 7, 'sunday' => 7, 'sun' => 7,
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

    // A suffix after an apostrophe ("25.10'a", "cuma'ya") is the same day.
    if (preg_match('/^(.+?)[\'’]\p{L}{1,4}$/u', $word, $match)) {
        $plain = preg_replace('/[\'’]\p{L}{1,4}$/u', '', $plain);
        $word = $match[1];
    }

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

    if (in_array($word, array('bugün', 'bugun', 'bugüne', 'bugune', 'today'), true)) {
        return $today;
    }

    if (in_array($word, array('yarın', 'yarin', 'yarına', 'yarina', 'tomorrow'), true)) {
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
 * A time of day written as hour:minute ("14:00", "9:30", "14:00'te"), as
 * H:i, or ''.
 *
 * @param string $word
 * @return string
 */
function ws_parse_time_word($word)
{
    if (!preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])(?:[\'’]\p{L}{1,4})?[.,;]?$/u', (string) $word, $match)) {
        return '';
    }

    return sprintf('%02d:%02d', (int) $match[1], (int) $match[2]);
}

/**
 * The estimate a number and an hour or minute word make ("1.5 saat",
 * "45 dk", "2 hours"), in minutes, or 0.
 *
 * @param string $number
 * @param string $unit
 * @return int
 */
function ws_parse_estimate_words($number, $unit)
{
    if (!preg_match('/^[0-9]{1,4}(?:[.,][0-9]{1,2})?$/', (string) $number)) {
        return 0;
    }

    $units = array(
        'saat' => 'sa', 'sa' => 'sa', 'hour' => 'h', 'hours' => 'h', 'hr' => 'h', 'hrs' => 'h', 'h' => 'h',
        'dk' => 'dk', 'dakika' => 'dk', 'min' => 'min', 'mins' => 'min', 'minute' => 'min', 'minutes' => 'min',
    );
    $unit = ws_command_fold(rtrim((string) $unit, '.,;'));

    if (!isset($units[$unit])) {
        return 0;
    }

    return ws_parse_estimate('~' . $number . $units[$unit]);
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
 * A task status as /status reads it, in either language, in any case, with
 * or without the Turkish letters: "bekliyor", "waiting", "sürüyor",
 * "doing", "iptal"...
 *
 * @param string $word
 * @return string a ws_task_statuses() key, or '' when the word is none
 */
function ws_parse_status_word($word)
{
    $words = array(
        'yapilacak' => 'todo', 'todo' => 'todo', 'to-do' => 'todo', 'acik' => 'todo', 'open' => 'todo',
        'suruyor' => 'doing', 'devam' => 'doing', 'basladi' => 'doing', 'doing' => 'doing', 'inprogress' => 'doing', 'started' => 'doing',
        'bekliyor' => 'waiting', 'beklemede' => 'waiting', 'waiting' => 'waiting',
        'bitti' => 'done', 'tamam' => 'done', 'done' => 'done',
        'iptal' => 'cancelled', 'cancelled' => 'cancelled', 'canceled' => 'cancelled', 'cancel' => 'cancelled',
    );

    return $words[ws_command_fold(trim((string) $word))] ?? '';
}

/**
 * Reads a task out of the words after /task.
 *
 * @param string      $text
 * @param string|null $today Y-m-d, the server's day when not given
 * @return array title, assignees, department_id, refs, priority, estimate_minutes,
 *               start_date, due_date, due_time (H:i), typed_people (the names
 *               written after an @ that is no tag, kept in the title until
 *               ws_command_typed_assignees() reads them)
 */
function ws_command_parse_task($text, $today = null)
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
        'due_time'         => null,
        'typed_people'     => array(),
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
    $today = ($today === null) ? date('Y-m-d') : (string) $today;
    $dates = array();
    $title = array();
    $words = array_values(array_filter(preg_split('/\s+/u', trim($text)), 'strlen'));
    $count = count($words);

    for ($i = 0; $i < $count; $i++) {
        $word = $words[$i];
        $next = ($i + 1 < $count) ? $words[$i + 1] : '';
        $folded = ws_command_fold($word);

        if (preg_match('/^@([\p{L}\p{N}_.\-]{1,40})[,;:!?]*$/u', $word, $match)) {
            $out['typed_people'][] = rtrim($match[1], '.-');
            $title[] = $word;
            continue;
        }

        // "next week", "gelecek hafta", "önümüzdeki hafta": seven days on,
        // like "haftaya".
        if (($next !== '') && in_array($folded, array('next', 'gelecek', 'onumuzdeki'), true) && in_array(ws_command_fold(rtrim($next, '.,;')), array('week', 'hafta'), true)) {
            $dates[] = ws_parse_date_word('haftaya', $today);
            $i++;
            continue;
        }

        // A number before an hour or minute word is how long ("1.5 saat"),
        // never a day and a month.
        $estimate = ($next !== '') ? ws_parse_estimate_words($word, $next) : 0;

        if ($estimate > 0) {
            $out['estimate_minutes'] = $estimate;
            $i++;
            continue;
        }

        // "saat 14:00", "at 14:00": the word before a time is not the title's.
        if (in_array($folded, array('saat', 'at'), true) && ($next !== '') && (ws_parse_time_word($next) !== '')) {
            continue;
        }

        $time = ws_parse_time_word($word);

        if ($time !== '') {
            $out['due_time'] = $time;
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

    // A time without a day is today's.
    if (($out['due_time'] !== null) && ($out['due_date'] === null)) {
        $out['due_date'] = $today;
    }

    $out['title'] = trim(implode(' ', $title), " \t-–—:,");

    return $out;
}

/**
 * Gives a parsed task the people whose usernames were typed after an @
 * instead of picked: a name that is exactly one team member's username, in
 * any case and with or without the Turkish letters, assigns that person and
 * leaves the title; one nobody has, or several share, assigns nobody and
 * stays in the title for the live line's warning.
 *
 * @param array   $parsed ws_command_parse_task()
 * @param array[] $team   briefs with id and username (ws_team_members())
 * @return array $parsed with assignees and title changed, typed_unknown and
 *               typed_ambiguous (names as typed)
 */
function ws_command_typed_assignees($parsed, $team)
{
    $parsed['typed_unknown'] = array();
    $parsed['typed_ambiguous'] = array();

    $usernames = array();

    foreach ((array) $team as $person) {
        $usernames[ws_command_fold($person['username'] ?? '')][] = (int) $person['id'];
    }

    $title = (string) $parsed['title'];

    foreach (array_unique((array) ($parsed['typed_people'] ?? array())) as $name) {
        $ids = $usernames[ws_command_fold($name)] ?? array();

        if (count($ids) > 1) {
            $parsed['typed_ambiguous'][] = $name;
            continue;
        }

        if (empty($ids)) {
            $parsed['typed_unknown'][] = $name;
            continue;
        }

        if (!in_array($ids[0], $parsed['assignees'], true)) {
            $parsed['assignees'][] = $ids[0];
        }

        $title = preg_replace('/(^|\s)@' . preg_quote($name, '/') . '[.\-]*[,;:!?]*(?=\s|$)/u', '$1', $title);
    }

    $parsed['title'] = trim(preg_replace('/\s+/u', ' ', $title), " \t-–—:,");

    return $parsed;
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
 *                        the parsed ones - the "give it to somebody lighter" button),
 *                        parent_id (the message a /schedule answers),
 *                        from (the value a /price or /stock preview showed),
 *                        decision (bool, keep that change in the channel),
 *                        on_sale (bool, a /product card's tick)
 * @return array|null null when the text is not a command nor an attempt at
 *                    one; otherwise type = preview | record_preview |
 *                    create_preview | poll_form | done | ephemeral | error
 *                    and what goes with it (a slash and a word that is no
 *                    command: ws_command_unknown_answer())
 */
function ws_command_run($viewer, $channel, $body, $options = array())
{
    $split = ws_command_split($body);

    if ($split === null) {
        $unknown = ws_command_unknown($body);

        return ($unknown !== '') ? ws_command_unknown_answer($viewer, $unknown) : null;
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

        case 'status':
            return ws_command_status($viewer, $channel, $rest);

        case 'schedule':
            return ws_command_schedule($viewer, $channel, $rest, $options);

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

        case 'price':
        case 'stock':
            return ws_command_record_change($viewer, $channel, $split['command'], $rest, $options);

        case 'product':
            return ws_command_product($viewer, $channel, $rest, $options);

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

            foreach (ws_command_help($viewer) as $item) {
                $html .= '<dt><code>' . h($item['command']) . '</code></dt><dd>' . h($item['description'])
                    . '<br><code class="ws-help-usage">' . h($item['usage']) . '</code>'
                    . '<br><span class="text-body-secondary">' . h($item['example']) . '</span></dd>';
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
    $parsed = ws_command_typed_assignees(ws_command_parse_task($text), ws_team_members());

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
                'due'        => $parsed['due_date'] ? ws_task_due_label(array('due_date' => $parsed['due_date'], 'due_time' => $parsed['due_time'], 'status' => 'todo')) : lang('No due date'),
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

    $data = array(
        'title'            => $parsed['title'],
        'priority'         => $parsed['priority'],
        'start_date'       => $parsed['start_date'],
        'due_date'         => $parsed['due_date'],
        'estimate_minutes' => $parsed['estimate_minutes'],
        'department_id'    => $parsed['department_id'],
        'channel_id'       => $channel['id'],
        'assignees'        => $parsed['assignees'],
        'refs'             => $parsed['refs'],
    );

    // Checked and written by ws_task_reminder_validate() when the site has
    // the column.
    if ($parsed['due_time'] !== null) {
        $data['due_time'] = $parsed['due_time'];
    }

    $result = ws_task_create($viewer, $data);

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
 * The live line under a command being written: what sending it now would
 * do, read by the same parser that runs it, and what it cannot read: /task,
 * /status (ws_command_status_suggest()) and /schedule
 * (ws_command_schedule_suggest()) have one; any other command answers with
 * an empty line.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $body the line as written so far
 * @return array line (text, '' for none), warnings (texts)
 */
function ws_command_suggest($viewer, $channel, $body)
{
    $out = array('line' => '', 'warnings' => array());
    $split = ws_command_split($body);

    if ($split && ($split['command'] === 'status')) {
        return ws_command_status_suggest($viewer, $split['rest']);
    }

    if ($split && ($split['command'] === 'schedule')) {
        return ws_command_schedule_suggest($viewer, $channel, $split['rest']);
    }

    if (!$split || ($split['command'] !== 'task') || ($split['rest'] === '')) {
        return $out;
    }

    $parsed = ws_command_typed_assignees(ws_command_parse_task($split['rest']), ws_team_members());

    foreach ($parsed['typed_unknown'] as $name) {
        $out['warnings'][] = lang(array('string' => 'Nobody in the team has the username {var:1}, so nobody is assigned by it: pick the person from the @ list.', 'vars' => '@' . $name));
    }

    foreach ($parsed['typed_ambiguous'] as $name) {
        $out['warnings'][] = lang(array('string' => 'Several people have the username {var:1}, so nobody is assigned by it: pick the person from the @ list.', 'vars' => '@' . $name));
    }

    if ($parsed['title'] === '') {
        $out['warnings'][] = lang('Write what the task is after the command.');

        return $out;
    }

    $parts = array('“' . $parsed['title'] . '”');

    if (!empty($parsed['assignees'])) {
        $names = array();

        foreach (ws_people($parsed['assignees']) as $person) {
            $names[] = $person['name'];
        }

        $parts[] = implode(', ', $names);
    } elseif ($parsed['department_id'] > 0) {
        $department = ws_department($parsed['department_id']);
        $parts[] = $department ? $department['name'] : '';
    } else {
        $parts[] = lang('Yours');
    }

    if ($parsed['start_date']) {
        $parts[] = lang(array('string' => 'from {var:1}', 'vars' => ws_day_label($parsed['start_date'])));
    }

    $parts[] = $parsed['due_date'] ? ws_task_due_label(array('due_date' => $parsed['due_date'], 'due_time' => $parsed['due_time'], 'status' => 'todo')) : lang('No due date');

    if ($parsed['estimate_minutes'] > 0) {
        $parts[] = ws_minutes_label($parsed['estimate_minutes']);
    }

    if ($parsed['priority'] !== 'normal') {
        $parts[] = ws_task_priorities()[$parsed['priority']];
    }

    $out['line'] = lang(array('string' => 'Will create: {var:1}', 'vars' => implode(' · ', array_filter($parts, 'strlen'))));

    if (!empty($parsed['assignees']) && !ws_can_assign_to($viewer, $parsed['assignees'])) {
        $out['warnings'][] = lang('You may only give tasks to yourself or to the people in the departments you lead.');
    }

    return $out;
}

/**
 * What /status is given: the task it points at and the status word after
 * it.
 *
 * @param string $text the line after the command's name
 * @return array task_id, word (as typed, '' for none), status (a
 *               ws_task_statuses() key, '' when the word is none)
 */
function ws_command_parse_status($text)
{
    list($task_id, $rest) = ws_command_task_ref($text);
    $words = preg_split('/\s+/u', trim($rest), 2);
    $word = (string) ($words[0] ?? '');

    return array('task_id' => $task_id, 'word' => $word, 'status' => ($word === '') ? '' : ws_parse_status_word($word));
}

/**
 * The status words /status reads, as the argument popup offers them, for a
 * refusal to name.
 *
 * @return string
 */
function ws_command_status_words()
{
    return implode(', ', array_map(function ($item) {
        return $item['value'];
    }, ws_command_suggestions()['statuses']));
}

/**
 * /status: moves a task to another status through ws_task_set_status(),
 * which tells the creator and the task's channel when it is done; the
 * channel the command was written in gets its own line otherwise. Without a
 * status word it only shows the task's status, to the writer.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $text
 * @return array
 */
function ws_command_status($viewer, $channel, $text)
{
    $parsed = ws_command_parse_status($text);
    $task = $parsed['task_id'] ? ws_task($parsed['task_id']) : null;

    if (!$task || !ws_can_see_task($viewer, $task)) {
        return array('type' => 'error', 'error' => lang('That task could not be found.'));
    }

    $name = ws_task_number($task['id']) . ' “' . $task['title'] . '”';

    if ($parsed['word'] === '') {
        return array('type' => 'ephemeral', 'html' => h(lang(array('string' => '{var:1} is {var:2}.', 'vars' => array($name, ws_task_status_label($task['status']))))));
    }

    if ($parsed['status'] === '') {
        return array('type' => 'error', 'error' => lang(array('string' => '{var:1} is not a task status. Write one of: {var:2}.', 'vars' => array($parsed['word'], ws_command_status_words()))));
    }

    if ($parsed['status'] === $task['status']) {
        return array('type' => 'ephemeral', 'html' => h(lang(array('string' => '{var:1} is already {var:2}.', 'vars' => array($name, ws_task_status_label($task['status']))))));
    }

    $result = ws_task_set_status($viewer, $task, $parsed['status']);

    if (!$result['ok']) {
        return array('type' => 'error', 'error' => $result['error']);
    }

    // A finished task is said in its own channel by ws_task_set_status();
    // here only when that is another channel, as /done does.
    if (($parsed['status'] !== 'done') || ((int) $task['channel_id'] !== (int) $channel['id'])) {
        ws_message_system($channel['id'], lang(array(
            'string' => '{var:1} set {var:2} to {var:3}',
            'vars'   => array('<@user:' . (int) $viewer['id'] . '>', '<#task:' . (int) $task['id'] . '>', ws_task_status_label($parsed['status'])),
        )), $task['id']);
    }

    return array('type' => 'done');
}

/**
 * The live line under /status: the task, its status now and the one the
 * line would move it to; a warning when the task cannot be found, the word
 * is no status or the writer may not change the task.
 *
 * @param array  $viewer
 * @param string $text the line after the command's name
 * @return array line, warnings
 */
function ws_command_status_suggest($viewer, $text)
{
    $out = array('line' => '', 'warnings' => array());
    $parsed = ws_command_parse_status($text);

    if ($parsed['task_id'] <= 0) {
        return $out;
    }

    $task = ws_task($parsed['task_id']);

    if (!$task || !ws_can_see_task($viewer, $task)) {
        $out['warnings'][] = lang('That task could not be found.');

        return $out;
    }

    $name = ws_task_number($task['id']) . ' “' . $task['title'] . '”';
    $now = ws_task_status_label($task['status']);

    if ($parsed['status'] === '') {
        $out['line'] = $name . ' · ' . $now;

        if ($parsed['word'] !== '') {
            $out['warnings'][] = lang(array('string' => '{var:1} is not a task status. Write one of: {var:2}.', 'vars' => array($parsed['word'], ws_command_status_words())));
        }

        return $out;
    }

    $out['line'] = lang(array('string' => 'Will move: {var:1}', 'vars' => $name . ' · ' . $now . ' → ' . ws_task_status_label($parsed['status'])));
    $check = ws_task_set_status_check($viewer, $task, $parsed['status']);

    if (!$check['ok']) {
        $out['warnings'][] = $check['error'];
    }

    return $out;
}

/**
 * A time from now written after + for /schedule ("+30dk", "+2sa", "+45m",
 * "+1h"), in minutes, or 0. Here "h" is an hour: a day word reads "+1h" as a
 * week (ws_parse_date_word()), which a message posted later never means.
 *
 * @param string $word
 * @return int
 */
function ws_parse_schedule_offset($word)
{
    if (!preg_match('/^\+([0-9]{1,4})(dk|dakika|m|min|mins|sa|saat|h|hr|hrs|hour|hours)$/u', ws_lower(rtrim((string) $word, '.,;')), $match)) {
        return 0;
    }

    $minutes = in_array($match[2], array('dk', 'dakika', 'm', 'min', 'mins'), true) ? (int) $match[1] : (int) $match[1] * 60;

    return ($minutes > 0) ? $minutes : 0;
}

/**
 * What /schedule is given: when, then the message. When is a time from now
 * ("+30dk"), or a day word, a time of day ("14:00", "saat 14:00", "at
 * 14:00") or both, in that order, at the start of the line. A day alone is
 * at 09:00; a time alone is today's, or tomorrow's once it has gone by.
 *
 * @param string   $text the line after the command's name
 * @param int|null $now  the moment it is read at, for tests
 * @return array date (Y-m-d, '' when no time was read), time (H:i), at
 *               (timestamp, 0 when no time was read), body (the message)
 */
function ws_command_parse_schedule($text, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $today = date('Y-m-d', $now);
    $words = preg_split('/\s+/u', trim((string) $text));
    $date = '';
    $clock = '';
    $minutes = 0;
    $taken = 0;

    foreach ($words as $index => $word) {
        if ($index > 2) {
            break;
        }

        $offset = ws_parse_schedule_offset($word);

        if (($offset > 0) && ($taken === 0)) {
            $minutes = $offset;
            $taken = $index + 1;
            break;
        }

        if (($date === '') && ($clock === '') && (ws_parse_date_word($word, $today) !== '')) {
            $date = ws_parse_date_word($word, $today);
            $taken = $index + 1;
            continue;
        }

        // "saat 14:00" / "at 14:00": the word before the time is no message.
        if (($clock === '') && in_array(ws_lower($word), array('saat', 'at'), true) && (ws_parse_time_word($words[$index + 1] ?? '') !== '')) {
            continue;
        }

        if (($clock === '') && (ws_parse_time_word($word) !== '')) {
            $clock = ws_parse_time_word($word);
            $taken = $index + 1;
            continue;
        }

        break;
    }

    $body = trim(implode(' ', array_slice($words, $taken)));

    if ($minutes > 0) {
        $at = $now + $minutes * 60;

        return array('date' => date('Y-m-d', $at), 'time' => date('H:i', $at), 'at' => strtotime(date('Y-m-d H:i', $at) . ':00'), 'body' => $body);
    }

    if (($date === '') && ($clock === '')) {
        return array('date' => '', 'time' => '', 'at' => 0, 'body' => trim((string) $text));
    }

    if ($date === '') {
        $date = $today;

        if (strtotime($date . ' ' . $clock . ':00') <= $now) {
            $date = date('Y-m-d', strtotime('+1 day', strtotime($today . ' 12:00:00')));
        }
    }

    if ($clock === '') {
        $clock = '09:00';
    }

    return array('date' => $date, 'time' => $clock, 'at' => (int) strtotime($date . ' ' . $clock . ':00'), 'body' => $body);
}

/**
 * /schedule: the message is saved as the writer's scheduled message
 * (ws_scheduled_message_save()), shown to the writer alone until it is
 * posted, the same as the clock beside the send button.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $text the line after the command's name
 * @param array  $options parent_id
 * @return array
 */
function ws_command_schedule($viewer, $channel, $text, $options)
{
    $parsed = ws_command_parse_schedule($text);

    if ($parsed['at'] <= 0) {
        return array('type' => 'error', 'error' => lang('Say when first: a day, a time such as 14:00 or +30m, then the message.'));
    }

    if ($parsed['body'] === '') {
        return array('type' => 'error', 'error' => lang('The message is empty.'));
    }

    $result = ws_scheduled_message_save($viewer, array(
        'id'         => 0,
        'channel_id' => (int) $channel['id'],
        'body'       => $parsed['body'],
        'parent_id'  => (int) ($options['parent_id'] ?? 0),
        'date'       => $parsed['date'],
        'time'       => $parsed['time'],
    ));

    if (!$result['ok']) {
        return array('type' => 'error', 'error' => $result['error']);
    }

    return array('type' => 'ephemeral', 'html' => h(lang(array(
        'string' => 'Scheduled for {var:1}: “{var:2}”. Only you see it until it is posted.',
        'vars'   => array(ws_scheduled_moment($parsed['at']), ws_plain_excerpt($viewer, $parsed['body'], 120)),
    ))));
}

/**
 * The live line under /schedule: when the message would be posted and its
 * start; a warning when no time is read, the message is empty or the time
 * is one ws_scheduled_message_save() refuses.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $text the line after the command's name
 * @return array line, warnings
 */
function ws_command_schedule_suggest($viewer, $channel, $text)
{
    $out = array('line' => '', 'warnings' => array());

    if (trim((string) $text) === '') {
        return $out;
    }

    $parsed = ws_command_parse_schedule($text);

    if ($parsed['at'] <= 0) {
        $out['warnings'][] = lang('Say when first: a day, a time such as 14:00 or +30m, then the message.');

        return $out;
    }

    $parts = array(ws_scheduled_moment($parsed['at']));

    if ($parsed['body'] !== '') {
        $parts[] = '“' . ws_plain_excerpt($viewer, $parsed['body'], 80) . '”';
    }

    $out['line'] = lang(array('string' => 'Will post: {var:1}', 'vars' => implode(' · ', $parts)));

    if ($parsed['body'] === '') {
        $out['warnings'][] = lang('The message is empty.');
    } elseif (preg_match('#^/[a-z]#i', $parsed['body'])) {
        $out['warnings'][] = lang('A command cannot be scheduled. Write it as a message, or send it now.');
    }

    if ($parsed['at'] <= time()) {
        $out['warnings'][] = lang('That time has already gone by.');
    } elseif ($parsed['at'] > strtotime('+' . WS_SCHEDULED_MESSAGES_AHEAD . ' days')) {
        $out['warnings'][] = lang('A message can be scheduled at most a year ahead.');
    }

    return $out;
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

/**
 * An amount as a person types it after /price, in minor units: 149,90 and
 * 149.90 alike, thousands grouped either way (1.249,90 or 1,249.90), a
 * currency sign or code beside it ignored. The last separator is the decimal
 * point when one or two digits follow it; a separator followed by three
 * digits groups thousands. The preview shows the amount back before anything
 * is written, so a misread is seen.
 *
 * @param string $text
 * @return int|null minor units, or null when the text is not an amount
 */
function ws_command_parse_amount($text)
{
    // A space inside the number would join two numbers into one.
    $text = trim(preg_replace('/(?:₺|\$|€|£|\b(?:tl|try|usd|eur|gbp)\b)/iu', '', (string) $text));

    if (!preg_match('/^[0-9][0-9.,]*$/', $text) || preg_match('/[.,][.,]|[.,]$/', $text)) {
        return null;
    }

    $whole = $text;
    $point = '';
    $fraction = '';

    if (preg_match('/^(.*)([.,])([0-9]{1,2})$/', $text, $match)) {
        $whole = $match[1];
        $point = $match[2];
        $fraction = $match[3];
    }

    $marks = preg_replace('/[0-9]/', '', $whole);

    // One thousands mark, used throughout, every group three digits wide.
    if ($marks !== '') {
        $mark = $marks[0];

        if (($mark === $point) || (str_replace($mark, '', $marks) !== '')
            || !preg_match('/^[0-9]{1,3}(?:' . preg_quote($mark, '/') . '[0-9]{3})+$/', $whole)) {
            return null;
        }
    }

    $digits = ltrim(str_replace(array('.', ','), '', $whole), '0');

    // Larger than any price column holds; refused before the arithmetic.
    if (strlen($digits) > 12) {
        return null;
    }

    return ((int) $digits * 100) + (int) str_pad($fraction, 2, '0');
}

/**
 * A quantity as a person types it after /stock: 12 sets the stock, +5 and
 * -3 move it from what it is now.
 *
 * @param string $text
 * @return array|null relative (bool), value (int, signed when relative)
 */
function ws_command_parse_quantity($text)
{
    if (!preg_match('/^([+-]?)\s*([0-9]{1,7})$/u', trim((string) $text), $match)) {
        return null;
    }

    return array('relative' => ($match[1] !== ''), 'value' => (($match[1] === '-') ? -1 : 1) * (int) $match[2]);
}

/**
 * The first record of a kind tagged in a command's text, and the text left
 * without that tag.
 *
 * @param string $text
 * @param string $tag the record's tag type (product)
 * @return array [id or 0, the rest]
 */
function ws_command_record_ref($text, $tag)
{
    $id = 0;

    $rest = preg_replace_callback('/<#' . preg_quote($tag, '/') . ':([0-9]{1,10})>/', function ($match) use (&$id) {
        if ($id > 0) {
            return $match[0];
        }

        $id = (int) $match[1];

        return ' ';
    }, (string) $text);

    return array($id, trim(preg_replace('/\s+/u', ' ', $rest)));
}

/**
 * /price and /stock: a product's price or quantity, shown back with what it
 * holds now, written on confirmation through the drawer's save
 * (ws_quick_edit_save()), so the rights, the Recycle Bin refusal, the check
 * that nobody changed it meanwhile, the log line and the decision in the
 * channel are those of an edit in the record drawer.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $command price | stock
 * @param string $text    what follows the command
 * @param array  $options confirm, from, decision
 * @return array
 */
function ws_command_record_change($viewer, $channel, $command, $text, $options)
{
    $error = function ($text) {
        return array('type' => 'error', 'error' => $text);
    };

    if (!function_exists('ws_quick_edit_check')) {
        return $error(lang('Invalid request.'));
    }

    list($id, $value_text) = ws_command_record_ref($text, 'product');

    if ($id <= 0) {
        return $error(lang('Name the product with #.'));
    }

    $check = ws_quick_edit_check($viewer, 'product', $id);

    if (!$check['ok']) {
        return $error($check['error']);
    }

    $types = ws_change_types();
    $record = $check['record'];

    if ($command === 'stock') {
        if (!isset($types['stock']) || !ws_change_allowed($viewer, 'stock', $id)) {
            return $error(lang('You may not change this record.'));
        }

        $kind = 'stock';
        $name = WS_QUICK_EDIT_STOCK_FIELD;
        $field = $types['stock']['fields'][$name];
        $current = ws_change_current_value($field, $record);
        $parsed = ws_command_parse_quantity($value_text);

        if ($parsed === null) {
            return $error(lang('Write the new quantity after the product, like 12, +5 or -3.'));
        }

        $value = $parsed['relative'] ? ((int) $current + $parsed['value']) : $parsed['value'];
    } else {
        $kind = 'product';
        $name = 'price';
        $field = ws_change_field($types['product'], $name);
        $current = ws_change_current_value($field, $record);
        $value = ws_command_parse_amount($value_text);

        if ($value === null) {
            return $error(lang('Write the new price after the product, like 149,90.'));
        }
    }

    if (ws_change_value($field, $value) === null) {
        return $error(lang(array('string' => '{var:1}: the value is out of range.', 'vars' => (string) $field[2])));
    }

    if (ws_change_value($field, $value) === ws_change_value($field, $current)) {
        return $error(lang('Nothing in it differs from what the record holds now.'));
    }

    if (empty($options['confirm'])) {
        $chips = '';

        foreach (ws_refs_resolve($viewer, ws_tokens('<#product:' . $id . '>')) as $ref) {
            $chips .= ws_chip_html($ref);
        }

        return array(
            'type'    => 'record_preview',
            'preview' => array(
                'record_html' => $chips,
                'label'       => (string) $field[2],
                'from'        => (string) ws_change_show($kind, $name, $current, 120),
                'to'          => (string) ws_change_show($kind, $name, $value, 120),
                'note'        => (($kind === 'stock') && ((int) ($record['inventory'] ?? 0) !== 1)) ? lang('Stock is not tracked for this product; saving a quantity turns tracking on.') : '',
            ),
            // Sent back with the confirmation: what the record held when the
            // preview was drawn.
            'from'    => $current,
        );
    }

    $from = (isset($options['from']) && is_scalar($options['from'])) ? array($name => $options['from']) : array();
    $saved = ws_quick_edit_save($viewer, 'product', $id, array($name => $value), $from, (int) $channel['id'], !empty($options['decision']));

    if (!$saved['ok']) {
        return $error($saved['stale'] ? lang('The record changed meanwhile, so nothing was written. Send the command again to see what it holds now.') : $saved['error']);
    }

    return array('type' => 'done', 'saved' => true, 'lines' => $saved['lines'], 'message_id' => $saved['decision_id']);
}

/**
 * The words after /product: a product group's tag wherever it is written,
 * a price as the last word (its currency may follow it as a word of its
 * own: "149,90 TL"), the rest the name. One word is always the name, even a
 * number; the card shows every part back before anything is written.
 *
 * @param string $text
 * @return array name, price (minor units or null), group_id (0 for none)
 */
function ws_command_parse_product($text)
{
    list($group_id, $rest) = ws_command_record_ref($text, 'product_group');
    $words = ($rest === '') ? array() : preg_split('/\s+/u', $rest);
    $count = count($words);
    $price = null;

    if (($count >= 3) && (ws_command_parse_amount($words[$count - 1]) === null)) {
        $price = ws_command_parse_amount($words[$count - 2] . ' ' . $words[$count - 1]);

        if ($price !== null) {
            array_splice($words, -2);
        }
    }

    if (($price === null) && ($count >= 2)) {
        $price = ws_command_parse_amount($words[$count - 1]);

        if ($price !== null) {
            array_pop($words);
        }
    }

    return array('name' => trim(implode(' ', $words)), 'price' => $price, 'group_id' => (int) $group_id);
}

/**
 * What a /product card creates: the name as the product's name (its code)
 * and as its short description (the words the store shows), the price and
 * the group when they were written.
 *
 * @param array $parsed ws_command_parse_product()
 * @return array field => value, as ws_quick_create_save() takes them
 */
function ws_command_product_fields($parsed)
{
    $fields = array('name' => (string) $parsed['name'], 'short_description' => (string) $parsed['name']);

    if ($parsed['price'] !== null) {
        $fields['price'] = (int) $parsed['price'];
    }

    if ((int) $parsed['group_id'] > 0) {
        $fields['group_ids'] = array((int) $parsed['group_id']);
    }

    return $fields;
}

/**
 * /product: a new product, shown back with every value it will be created
 * with, made on confirmation through the drawer's create
 * (ws_quick_create_save()): the same rights, checks, writer, ws_ai_changes
 * row, activity log line and decision in the channel. Off sale unless the
 * card's "On sale" is ticked.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $text    what follows the command
 * @param array  $options confirm, decision, on_sale
 * @return array
 */
function ws_command_product($viewer, $channel, $text, $options)
{
    $error = function ($text) {
        return array('type' => 'error', 'error' => $text);
    };

    if (!function_exists('ws_quick_create_input')) {
        return $error(lang('Invalid request.'));
    }

    $parsed = ws_command_parse_product($text);

    if ($parsed['name'] === '') {
        return $error(lang('Write the product\'s name after the command, like /product Blue mug 149,90.'));
    }

    // Another tag left in the name (a second group, a product) would be
    // written into it as its markup.
    if (preg_match('/<[#@][a-z_]+:[0-9]+>/', $parsed['name'])) {
        return $error(lang('Write the name as plain text: only one #group may follow it.'));
    }

    $fields = ws_command_product_fields($parsed);

    if (!empty($options['confirm'])) {
        $fields['enabled'] = !empty($options['on_sale']);
        $saved = ws_quick_create_save($viewer, 'product', $fields, (int) $channel['id'], !empty($options['decision']));

        if (!$saved['ok']) {
            return $error($saved['error']);
        }

        return array(
            'type'       => 'done',
            'saved'      => true,
            'created'    => true,
            'lines'      => $saved['lines'],
            'message_id' => $saved['decision_id'],
            'record'     => array('type' => 'product', 'id' => $saved['id'], 'name' => $saved['name']),
        );
    }

    // Checked as the save will check it (a name already taken, a group
    // that is gone), so the card never offers what cannot be made.
    $input = ws_quick_create_input($viewer, 'product', $fields, (int) $channel['id']);

    if (!$input['ok']) {
        return $error($input['error']);
    }

    $types = ws_change_types();
    $rows = array();
    $labels = array();

    foreach ($input['row']['fields'] as $item) {
        $field = ws_change_field($types['product'], (string) $item['name']);

        // The card has a tick of its own for it.
        if (($field === null) || ($item['name'] === 'enabled')) {
            continue;
        }

        if ($field[0] === 'ids') {
            $chips = '';

            foreach (ws_refs_resolve($viewer, ws_tokens(ws_change_ids_tokens($field, $item['to']))) as $ref) {
                $chips .= ws_chip_html($ref);
                $labels[$item['name']] = $ref['label'];
            }

            $rows[] = array('label' => (string) $field[2], 'text' => '', 'html' => $chips);
            continue;
        }

        $rows[] = array('label' => (string) $field[2], 'text' => (string) ws_change_show('product', $item['name'], $item['to'], 120), 'html' => '');
    }

    return array(
        'type'    => 'create_preview',
        'preview' => array(
            'kind'    => 'product',
            'rows'    => $rows,
            'on_sale' => (string) $types['product']['fields']['enabled'][2],
        ),
        // The drawer's new mode, filled with the same values ("All fields"),
        // and the names of the records they pick.
        'values'  => $fields,
        'labels'  => $labels,
    );
}
