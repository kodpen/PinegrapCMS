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
 *   /timer T-42, or /timer stop                              (also /süre, /sure)
 *   /schedule tomorrow 09:00 Stand-up in ten minutes         (also /zamanla)
 *   /away friday monday Family visit                         (also /izin)
 *   /meeting tomorrow 14:00 ~1h Kick-off @ayse               (also /toplantı, /toplanti)
 *   /decision The packaging will be kraft                    (also /karar)
 *   /note Accounts contact: Selin, ext. 204                  (also /not)
 *   /plan @ayse                                              the next working week
 *   /poll Which day? | Tuesday | Wednesday                   (also /oylama, /anket, /sor)
 *   /price #product 149,90                                   (also /fiyat)
 *   /stock #product 12, +5 or -3                             (also /stok)
 *   /create product Blue mug 149,90 #group                   (also /oluştur, /olustur)
 *   /wake @ci every 30m Look at the queue                (also /uyandır, /uyandir)
 *   /agent-channel on, off or nothing for the state      (also /ajan-kanalı, /ajan-kanali)
 *   /create group Mugs                                       (grup, group)
 *   /product Blue mug 149,90 #group                          (also /ürün, /urun:
 *                                                            /create product)
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/command_registry.php');

/**
 * The commands, each under the names it answers to.
 *
 * @return array name => command
 */
function ws_command_names()
{
    return ws_command_registry_names(false);
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
 * The names of the commands that make a new record: /create makes the kind
 * named after it, /product is /create product
 * (includes/workspace/quick_edit.php, ws_quick_create_save()). Kept beside
 * ws_command_names(), the commands that act on what is there; the parser
 * reads both through ws_command_table().
 *
 * @return array name => command
 */
function ws_command_create_names()
{
    return ws_command_registry_names(true);
}

/**
 * The registry's names, in its order: of the commands that make a record
 * (/create, /product) or of the others.
 *
 * @param bool $create
 * @return array name => command
 */
function ws_command_registry_names($create)
{
    $out = array();

    foreach (ws_command_registry() as $key => $entry) {
        if (in_array($key, array('create', 'product'), true) !== $create) {
            continue;
        }

        foreach ($entry['names'] as $name) {
            $out[$name] = $key;
        }
    }

    return $out;
}

/**
 * The kinds /create makes, under the words that name them after the
 * command, read folded (ws_command_fold()): "ürün", "URUN" and "product"
 * are the same kind.
 *
 * @return array word => ws_quick_create_types() value
 */
function ws_command_create_kinds()
{
    return array(
        'urun' => 'product', 'product' => 'product',
        'grup' => 'product_group', 'group' => 'product_group',
        'kisi' => 'contact', 'contact' => 'contact',
        'satir' => 'wtable_row', 'row' => 'wtable_row',
    );
}

/**
 * The kinds /create makes that this person may make, each once, in the
 * order of ws_command_create_kinds(): a kind is the right of its record's
 * screen (ws_change_allowed(): products the e-commerce right, contacts the
 * contacts right), so the product right never opens a contact.
 *
 * @param array    $viewer
 * @param callable $may    ($viewer, $type, $record_id) => bool
 * @return string[]
 */
function ws_command_create_types_for($viewer, $may = 'ws_command_create_may')
{
    $out = array();

    foreach (array_unique(array_values(ws_command_create_kinds())) as $type) {
        if (is_callable($may) && call_user_func($may, $viewer, $type, 0)) {
            $out[] = $type;
        }
    }

    return $out;
}

/**
 * May this person make a record of this kind of /create? A row: when they
 * may write one workflow table at least (ws_command_row_tables()); the
 * others: the right of their record's screen (ws_change_allowed()).
 *
 * @param array  $viewer
 * @param string $type
 * @param int    $record_id
 * @return bool
 */
function ws_command_create_may($viewer, $type, $record_id = 0)
{
    if ($type === 'wtable_row') {
        return ws_command_row_tables($viewer) !== array();
    }

    return function_exists('ws_change_allowed') && ws_change_allowed($viewer, $type, $record_id);
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
 * May this person use a command? /price, /stock, /create and /product write
 * a product or a product group, so they are offered only to whoever may change products (the
 * e-commerce right, as add_product.php asks it); /schedule only to whoever
 * may schedule messages (scheduled_messages.php); /timer once task time is
 * kept (task_time.php); /claude and /ai only once
 * their assistant is set up on the site; the others are anybody's. Without a
 * person every command the parser answers to is listed, and the assistants
 * are left out: that list is read without asking the database anything.
 *
 * A command that runs only in the channel itself is not offered in one of
 * its discussions (ws_command_runs_here()).
 *
 * @param array|null $viewer
 * @param string     $command the command's own name (ws_command_table()
 *                            value, or claude | ai)
 * @param array|null $channel where it would run; null for anywhere
 * @return bool
 */
function ws_command_offered($viewer, $command, $channel = null)
{
    $entry = ws_command_registry()[$command] ?? null;

    if ($entry === null) {
        return true;
    }

    if (!ws_command_runs_here($entry['where'], $channel)) {
        return false;
    }

    if ($entry['gate'] === '') {
        return true;
    }

    // A gate may read the channel too (/recap), when one is known.
    return (bool) call_user_func($entry['gate'], $viewer, $command, $channel);
}

/**
 * /claude and /ai: only with a person, once their assistant is set up.
 *
 * @param array|null $viewer
 * @param string     $command claude | ai
 * @return bool
 */
function ws_command_gate_assistant($viewer, $command)
{
    return is_array($viewer) && ws_command_assistant_ready($command);
}

/**
 * /schedule: whoever may schedule messages.
 *
 * @param array|null $viewer
 * @return bool
 */
function ws_command_gate_schedule($viewer)
{
    if (!is_array($viewer)) {
        return true;
    }

    return function_exists('ws_can_schedule_messages') && ws_can_schedule_messages($viewer);
}

/**
 * /wake: once agents are set up.
 *
 * @param array|null $viewer
 * @return bool
 */
function ws_command_gate_wake($viewer)
{
    if (!is_array($viewer)) {
        return true;
    }

    return function_exists('ws_agents_ready') && ws_agents_ready();
}

/**
 * /agent-channel: once agent channels are set up.
 *
 * @param array|null $viewer
 * @return bool
 */
function ws_command_gate_agent_channel($viewer)
{
    if (!is_array($viewer)) {
        return true;
    }

    return function_exists('ws_agent_channel_ready') && ws_agent_channel_ready();
}

/**
 * /remind: a team member, once their own plans are kept (the New plan
 * button of My calendar asks the same).
 *
 * @param array|null $viewer
 * @return bool
 */
function ws_command_gate_remind($viewer)
{
    if (!is_array($viewer)) {
        return true;
    }

    return function_exists('ws_personal_plans_ready') && ws_personal_plans_ready() && !empty($viewer['member']);
}

/**
 * /recap: where the catch-up line offers "Sum up what I missed", that is
 * where Pinegrap AI may read the channel (ws_channel_briefing()'s offer).
 * Without a channel it is listed like the rest.
 *
 * @param array|null $viewer
 * @param string     $command
 * @param array|null $channel
 * @return bool
 */
function ws_command_gate_recap($viewer, $command = 'recap', $channel = null)
{
    if (!is_array($channel)) {
        return true;
    }

    $state = function_exists('ws_ai_channel_state') ? ws_ai_channel_state($channel) : null;

    return is_array($state) && !empty($state['available']);
}

/**
 * /approval: once approval requests are kept (the "Ask for approval" item
 * of the box's Insert menu asks the same).
 *
 * @param array|null $viewer
 * @return bool
 */
function ws_command_gate_approval($viewer)
{
    if (!is_array($viewer)) {
        return true;
    }

    return function_exists('ws_approvals_ready') && ws_approvals_ready();
}

/**
 * /update and /checkin: once status updates and check-ins are kept (the
 * "Status update" and "Ask for a check-in" items ask the same).
 *
 * @param array|null $viewer
 * @return bool
 */
function ws_command_gate_status($viewer)
{
    if (!is_array($viewer)) {
        return true;
    }

    return function_exists('ws_status_ready') && ws_status_ready();
}

/**
 * /notify and /leave: offered to a member of the channel, as the channel
 * menu offers its notification choices and "Leave the channel" only to
 * one. Without a channel, listed like the rest.
 *
 * @param array|null $viewer
 * @param string     $command
 * @param array|null $channel
 * @return bool
 */
function ws_command_gate_member($viewer, $command = '', $channel = null)
{
    if (!is_array($viewer) || !is_array($channel)) {
        return true;
    }

    return (bool) ws_channel_membership($channel['id'], $viewer['id']);
}

/**
 * /invite: "Add people"'s rule, anybody in a public channel and only a
 * member in any other.
 *
 * @param array|null $viewer
 * @param string     $command
 * @param array|null $channel
 * @return bool
 */
function ws_command_gate_invite($viewer, $command = '', $channel = null)
{
    if (!is_array($viewer) || !is_array($channel) || ((string) $channel['kind'] === 'public')) {
        return true;
    }

    return (bool) ws_channel_membership($channel['id'], $viewer['id']);
}

/**
 * /timer: once task time is kept.
 *
 * @param array|null $viewer
 * @return bool
 */
function ws_command_gate_timer($viewer)
{
    if (!is_array($viewer)) {
        return true;
    }

    return function_exists('ws_task_time_ready') && ws_task_time_ready();
}

/**
 * /price, /stock, /create and /product: whoever may change products (the
 * stock for /stock).
 *
 * @param array|null $viewer
 * @param string     $command
 * @return bool
 */
function ws_command_gate_product($viewer, $command)
{
    if (!is_array($viewer)) {
        return true;
    }

    return function_exists('ws_change_allowed') && ws_change_allowed($viewer, ($command === 'stock') ? 'stock' : 'product', 0);
}

/**
 * /create: whoever may make one of its kinds (ws_command_create_types_for());
 * the kind typed is checked again when it runs.
 *
 * @param array|null $viewer
 * @param string     $command
 * @return bool
 */
function ws_command_gate_create($viewer, $command)
{
    if (!is_array($viewer)) {
        return true;
    }

    return function_exists('ws_change_allowed') && (ws_command_create_types_for($viewer) !== array());
}

/**
 * The kinds of argument a command takes, as the registry types them. The
 * composer suggests values for some of them (ws_commands(), suggest).
 *
 * @return string[]
 */
function ws_command_argument_kinds()
{
    return array('person', 'people', 'department', 'task', 'record', 'date', 'time', 'estimate', 'priority', 'status', 'amount', 'quantity', 'text', 'options', 'record_kind', 'health', 'notify');
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
 *                estimates, priorities, statuses, kinds, healths, notify, or '' for none), multiple (takes more
 *                than one), anywhere (read wherever it is written, as /task
 *                reads its people, day, estimate and priority)
 *   live         the composer asks ws_command_suggest() for a line under
 *                the usage strip while it is written
 *   app_id, app  the application that registered it and its name; 0 and ''
 *                for a built-in command. An application's command
 *                (ws_app_command_entry(), key app:<name>) comes after the
 *                built-in ones, listed for a person only
 *   shadowed     an application's command whose name a built-in one took
 *                (ws_command_shadowed()); listed only when asked for
 *
 * The built-in entries come from ws_command_registry().
 *
 *   group        ws_command_groups() value; agents for an application's
 *   where        where it runs (ws_command_runs_here()): any | channel
 *
 * @param array|null $viewer   leaves out the commands this person may not use
 * @param bool       $shadowed lists the shadowed application commands too
 * @param array|null $channel  leaves out the commands that do not run there
 * @return array[]
 */
function ws_commands($viewer = null, $shadowed = false, $channel = null)
{
    $turkish = ws_command_turkish();
    $out = array();

    foreach (ws_command_registry() as $entry) {
        if (!ws_command_offered($viewer, $entry['key'], $channel)) {
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
            'variants'    => $entry['variants'],
            'live'        => ($entry['live'] !== ''),
            'app_id'      => 0,
            'app'         => '',
            'shadowed'    => false,
            'group'       => $entry['group'],
            'where'       => $entry['where'],
        );
    }

    // The commands applications registered, after the built-in ones; only for
    // a person, so the registry without one touches no table.
    if (is_array($viewer) && function_exists('ws_app_commands_live')) {
        foreach (ws_command_app_entries(ws_app_commands_live(), $shadowed) as $app_entry) {
            $out[] = $app_entry + array('group' => 'agents', 'where' => 'any');
        }
    }

    return $out;
}

/**
 * The help text the /help command, the composer's / picker and the command
 * palette show: the registry (ws_commands()) in the shape they read.
 *
 * @param array|null $viewer  leaves out the commands this person may not use
 * @param array|null $channel leaves out the commands that do not run there
 * @return array[] command (the label), names (every name it answers to, each
 *                 with its slash, for the composer's / picker), example,
 *                 description; and from the registry key, name, usage, args,
 *                 live for the composer's argument popup and live line;
 *                 group, group_label and where for the list's headers
 */
function ws_command_help($viewer = null, $channel = null)
{
    $help = array();
    $labels = ws_command_group_labels();

    foreach (ws_commands($viewer, false, $channel) as $entry) {
        $help[] = array(
            'command'     => $entry['label'],
            'names'       => $entry['names'],
            'example'     => $entry['example'],
            'description' => $entry['description'],
            'key'         => $entry['key'],
            'name'        => $entry['name'],
            'usage'       => $entry['usage'],
            'args'        => $entry['args'],
            'variants'    => $entry['variants'] ?? array(),
            'live'        => $entry['live'],
            'group'       => $entry['group'],
            'group_label' => $labels[$entry['group']] ?? '',
            'where'       => $entry['where'],
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
 * @param array|null  $viewer   with a person, only the kinds they may make
 * @return array dates, estimates, priorities, statuses, kinds (what /create
 *               makes), healths (the states /update reads), notify (the
 *               choices /notify reads): each a list of value, meta, top
 */
function ws_command_suggestions($language = null, $viewer = null)
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
    $kinds = $turkish
        ? array('product' => 'ürün', 'product_group' => 'grup', 'contact' => 'kişi', 'wtable_row' => 'satır')
        : array('product' => 'product', 'product_group' => 'group', 'contact' => 'contact', 'wtable_row' => 'row');
    $kind_labels = array('product' => lang('Product'), 'product_group' => lang('Product group'), 'contact' => lang('Contact'), 'wtable_row' => lang('Workflow table row'));

    if (is_array($viewer) && function_exists('ws_change_allowed')) {
        $kinds = array_intersect_key($kinds, array_flip(ws_command_create_types_for($viewer)));
    }

    $out = array('dates' => array(), 'estimates' => array(), 'priorities' => array(), 'statuses' => array(), 'kinds' => array());

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

    foreach ($kinds as $key => $word) {
        $out['kinds'][] = array('value' => $word, 'meta' => $kind_labels[$key], 'top' => true);
    }

    // The states /update reads.
    $out['healths'] = ws_command_health_values($turkish);

    // The choices /notify reads.
    $out['notify'] = ws_command_notify_values($turkish);

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
 * Folds a text for a case-insensitive search: İ, I, ı and i all become i,
 * the rest is lower cased. mb_strtolower() alone turns İ into i + U+0307 and
 * I into i, so "YILDIZ" never met "Yıldız". Every character maps to exactly
 * one, so a position in the folded text is the same in the original.
 *
 * @param string $text
 * @return string
 */
function ws_fold($text)
{
    $text = str_replace(array('İ', 'I', 'ı'), 'i', (string) $text);

    return mb_strtolower($text, 'UTF-8');
}

/**
 * A search phrase as an SQL condition over some columns, with the fold of
 * ws_fold(): one of the columns holds the phrase as typed, or under Turkish
 * case rules, or with İ, I, ı and i as one letter (pg_list_search_where()'s
 * three alternatives, so "plani" and "PLANI" find "Planı"). The values are
 * escaped into the text, % and _ stand for themselves. '' for an empty
 * phrase. Pure but for the escaping.
 *
 * @param string   $search
 * @param string[] $columns SQL expressions
 * @param string   $charset the connection charset CONVERT() names
 *                          (pg_list_search_connection_charset()), '' for the
 *                          plain match only
 * @return string "(… OR …)" or ''
 */
function ws_search_like_sql($search, $columns, $charset)
{
    $search = trim((string) $search);

    if ($search === '') {
        return '';
    }

    $where = pg_list_search_where(array($search), $columns, array(), (string) $charset);
    $sql = pg_db_bind($where['sql'], $where['params'], 'escape');

    return ($sql === null) ? '(1 = 0)' : $sql;
}

/**
 * A LIKE '%…%' condition on a column that finds the words the way ws_fold()
 * does: utf8mb4_unicode_ci folds I with i but never with ı, so "YANIT" and
 * "yanit" missed "yanıt". The plain LIKE stays (it keeps the collation's ş/s
 * folding); a word with an i letter adds the column with İ and ı replaced by
 * i (REPLACE is case-sensitive, I already equals i) against the folded word.
 * The scan is the same, a '%…%' pattern uses no index either way.
 *
 * @param string      $column  a column name the caller wrote, never input
 * @param string      $query
 * @param string|null $charset connection charset for CONVERT; null reads it
 *                             (pg_list_search_connection_charset())
 * @return string parenthesised SQL condition
 */
function ws_text_like_sql($column, $query, $charset = null)
{
    $query = (string) $query;

    if ($charset === null) {
        $charset = function_exists('pg_list_search_connection_charset') ? pg_list_search_connection_charset() : '';
    }

    $sql = $column . " LIKE '%" . e(escape_like($query)) . "%'";

    if (in_array($charset, array('utf8', 'utf8mb4'), true) && preg_match('/[iIıİ]/u', $query)) {
        $sql .= " OR REPLACE(REPLACE(CONVERT(" . $column . ' USING ' . $charset . "), 'İ', 'i'), 'ı', 'i') LIKE '%" . e(escape_like(ws_fold($query))) . "%'";
    }

    return '(' . $sql . ')';
}

/**
 * Where a needle first stands in a text, case and dotted or dotless i
 * ignored (ws_fold()).
 *
 * @param string $haystack
 * @param string $needle
 * @return int|false character position in the original text
 */
function ws_text_position($haystack, $needle)
{
    if ((string) $needle === '') {
        return 0;
    }

    return mb_strpos(ws_fold($haystack), ws_fold($needle), 0, 'UTF-8');
}

/**
 * Whether a text holds a needle, case and dotted or dotless i ignored.
 *
 * @param string $haystack
 * @param string $needle
 * @return bool
 */
function ws_text_contains($haystack, $needle)
{
    return ws_text_position($haystack, $needle) !== false;
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

    $html .= '<div class="fg-secondary">' . h(lang(array(
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

        if ($unknown === '') {
            return null;
        }

        // An application's command goes to that application alone, where it
        // may read the channel; anywhere else it is unknown.
        $app_command = function_exists('ws_app_command_find') ? ws_app_command_find($unknown) : null;

        if ($app_command !== null) {
            $rest = trim((string) mb_substr(ltrim((string) $body), mb_strlen($unknown, 'UTF-8') + 1, null, 'UTF-8'));
            $sent = ws_app_command_send($viewer, $channel, $app_command, $rest, (int) ($options['parent_id'] ?? 0));

            if ($sent !== null) {
                return $sent;
            }
        }

        return ws_command_unknown_answer($viewer, $unknown);
    }

    $entry = ws_command_registry()[$split['command']] ?? null;
    $runner = ($entry !== null) ? $entry['runner'] : 'ws_command_run_help';

    if (($entry !== null) && !ws_command_runs_here($entry['where'], $channel)) {
        return array('type' => 'error', 'error' => lang('This command runs in the channel itself, not in one of its discussions.'));
    }

    return call_user_func($runner, $viewer, $channel, $split['rest'], $options, $body, $split['command']);
}

/*
 * The runners: each one is a command's registry runner, called by
 * ws_command_run() as ($viewer, $channel, $rest, $options, $body, $key),
 * $rest the line after the command's name, $body the text as typed.
 */

/**
 * /assign: adds people to a task.
 *
 * @return array
 */
function ws_command_run_assign($viewer, $channel, $rest)
{
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
}

/**
 * /done: marks a task as done.
 *
 * @return array
 */
function ws_command_run_done($viewer, $channel, $rest)
{
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
}

/**
 * /postpone: moves a task's due date.
 *
 * @return array
 */
function ws_command_run_postpone($viewer, $channel, $rest)
{
    list($task_id, $rest_text) = ws_command_task_ref($rest);
    $task = ws_task($task_id);

    if (!$task || !ws_can_see_task($viewer, $task)) {
        return array('type' => 'error', 'error' => lang('That task could not be found.'));
    }

    $new_due = '';

    foreach (preg_split('/\s+/u', $rest_text) as $word) {
        $new_due = ws_parse_date_word($word, (ws_task_date_value($task['due_date']) !== '') ? $task['due_date'] : date('Y-m-d'));

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

    // The start moves with the due date, so the task keeps its length;
    // a start without a due date stays where it is.
    $data = ws_task_move_dates($task, $new_due);

    if (array_key_exists('start_date', $data) && ($data['start_date'] === null)) {
        unset($data['start_date']);
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
}

/**
 * /timer.
 *
 * @return array
 */
function ws_command_run_timer($viewer, $channel, $rest)
{
    return ws_command_timer($viewer, $rest);
}

/**
 * /away.
 *
 * @return array
 */
function ws_command_run_away($viewer, $channel, $rest)
{
    return ws_command_away($viewer, $rest);
}

/**
 * /remind: a plan of the writer's own on My calendar, saved through
 * ws_personal_plan_save() as the New plan form saves one, with a reminder
 * at its start. Typed as a reply, the plan's note carries the answered
 * message's link (and its line is the title when nothing else is written).
 * Without a time the New plan form opens with what was typed.
 *
 * @return array
 */
function ws_command_run_remind($viewer, $channel, $rest, $options)
{
    $fill = ws_command_remind_fill($viewer, $channel, $rest, $options);

    if (isset($fill['error'])) {
        return array('type' => 'error', 'error' => $fill['error']);
    }

    if ($fill['start_time'] === '') {
        return array('type' => 'plan_form', 'fill' => $fill, 'strings' => ws_personal_plan_js_strings());
    }

    $saved = ws_personal_plan_save($viewer, $fill);

    if (empty($saved['ok'])) {
        return array('type' => 'error', 'error' => (string) ($saved['error'] ?? lang('Access denied')));
    }

    return ws_command_remind_answer($fill, (int) ($saved['plan']['id'] ?? 0));
}

/**
 * /remind's answer once the plan is saved, to the writer alone: what will
 * be reminded and when, and a link to the plan on My calendar. Pure.
 *
 * @param array $fill    ws_command_remind_fill()
 * @param int   $plan_id
 * @return array
 */
function ws_command_remind_answer($fill, $plan_id)
{
    $url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace_my_calendar.php?plan=' . (int) $plan_id;
    $html = '<div class="ws-ephemeral-title">' . h(lang('Reminder set')) . '</div>'
        . '<div>' . h(ws_command_remind_line($fill)) . '</div>'
        . '<div class="fg-secondary">' . h(lang('Only you see this plan.')) . ' <a href="' . h($url) . '">' . h(lang('Open the plan')) . '</a></div>';

    return array('type' => 'ephemeral', 'html' => $html, 'plan_id' => (int) $plan_id);
}

/**
 * /recap: "Sum up what I missed" (ws_recap_ai()), to the writer alone, with
 * the button's refusals. since_id is the last message read before this
 * visit, as the catch-up line hands it; without one, the writer's own
 * read mark.
 *
 * @return array
 */
function ws_command_run_recap($viewer, $channel, $rest, $options)
{
    if (!ws_command_gate_recap($viewer, 'recap', $channel)) {
        return array('type' => 'error', 'error' => lang('Pinegrap AI cannot be asked in this channel.'));
    }

    $since_id = (int) ($options['since_id'] ?? 0);

    if ($since_id <= 0) {
        $membership = ws_channel_membership((int) $channel['id'], (int) $viewer['id']);
        $since_id = $membership ? (int) $membership['last_read_id'] : 0;
    }

    if (function_exists('set_time_limit') && defined('WS_AI_CALL_TIMEOUT')) {
        @set_time_limit(WS_AI_CALL_TIMEOUT + 30);
    }

    $recap = ws_recap_ai($viewer, $channel, $since_id);

    if (empty($recap['ok'])) {
        return array('type' => 'error', 'error' => (string) $recap['error']);
    }

    return array('type' => 'ephemeral', 'html' => ws_command_recap_html($recap['text']));
}

/**
 * /recap's answer: the summary and who wrote it, as the catch-up line
 * shows them under itself. Pure.
 *
 * @param string $text
 * @return string
 */
function ws_command_recap_html($text)
{
    return '<div class="ws-ephemeral-title">' . h(lang('Sum up what I missed')) . '</div>'
        . '<div class="ws-catchup-recap-text">' . nl2br(h((string) $text)) . '</div>'
        . '<div class="ws-catchup-recap-by fg-secondary">' . h(lang('Summed up by Pinegrap AI')) . '</div>';
}

/**
 * Does a command act on the message it answers? Only when it is typed as a
 * reply and nothing follows its name: with a text it does as it always did.
 *
 * @param string $rest    the line after the command's name
 * @param array  $options ws_command_run() options (parent_id)
 * @return bool
 */
function ws_command_acts_on_reply($rest, $options)
{
    return (trim((string) $rest) === '') && ((int) ($options['parent_id'] ?? 0) > 0);
}

/**
 * The message a command answers: in this channel, not deleted, and read by
 * the writer.
 *
 * @param array $viewer
 * @param array $channel
 * @param array $options ws_command_run() options (parent_id)
 * @return array|null ws_messages row
 */
function ws_command_answered_message($viewer, $channel, $options)
{
    $message = ws_message((int) ($options['parent_id'] ?? 0));

    if (!$message || ((int) $message['channel_id'] !== (int) $channel['id']) || ((int) $message['deleted_at'] > 0)
        || !ws_can_read_channel($viewer, $channel)) {
        return null;
    }

    return $message;
}

/**
 * /decision and /note: the text posted as that kind of message; typed as a
 * reply without a text, the answered message marked.
 *
 * @return array
 */
function ws_command_run_noted($viewer, $channel, $rest, $options, $body, $command)
{
    // Typed as a reply without a text: the answered message is marked, as
    // its menu's "Mark as a decision" or "Mark as a channel note" does.
    if (ws_command_acts_on_reply($rest, $options)) {
        $message = ws_command_answered_message($viewer, $channel, $options);

        if ($message === null) {
            return array('type' => 'error', 'error' => lang('That message could not be found.'));
        }

        $marked = ws_message_mark($viewer, $message, $command);

        return $marked['ok']
            ? array('type' => 'done', 'message_id' => (int) $message['id'])
            : array('type' => 'error', 'error' => $marked['error']);
    }

    if ($rest === '') {
        return array('type' => 'error', 'error' => lang('Write the text after the command.'));
    }

    $result = ws_message_send($viewer, $channel, $rest, array('kind' => $command));

    // A tag in a note or a decision asks as in a message.
    if ($result['ok']) {
        ws_message_ask_noted($viewer, $channel, $result['message_id'], $rest);
    }

    return $result['ok']
        ? array('type' => 'done', 'message_id' => $result['message_id'])
        : array('type' => 'error', 'error' => $result['error']);
}

/**
 * /plan.
 *
 * @return array
 */
function ws_command_run_plan($viewer, $channel, $rest)
{
    return ws_command_plan($viewer, $rest);
}

/**
 * /price and /stock.
 *
 * @return array
 */
function ws_command_run_record($viewer, $channel, $rest, $options, $body, $command)
{
    return ws_command_record_change($viewer, $channel, $command, $rest, $options);
}

/**
 * /create and /product. The text as typed goes along: several lines become
 * the message a bulk card hangs under.
 *
 * @return array
 */
function ws_command_run_create($viewer, $channel, $rest, $options, $body, $command)
{
    if ($command === 'product') {
        return ws_command_product($viewer, $channel, $rest, $options + array('typed' => (string) $body));
    }

    return ws_command_create($viewer, $channel, $rest, $options + array('typed' => (string) $body));
}

/**
 * /poll: posted with two options or more, the form below that.
 *
 * @return array
 */
function ws_command_run_poll($viewer, $channel, $rest)
{
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
}

/**
 * /wake, once agents are there.
 *
 * @return array
 */
function ws_command_run_wake($viewer, $channel, $rest)
{
    return function_exists('ws_command_wake') ? ws_command_wake($viewer, $channel, $rest) : array('type' => 'error', 'error' => lang('Agents are not set up on this site yet.'));
}

/**
 * /help: the commands this person may use, to them alone.
 *
 * @return array
 */
function ws_command_run_help($viewer, $channel = null, $rest = '')
{
    $word = ltrim((string) preg_split('/\s+/u', trim((string) $rest))[0], '/');

    if ($word !== '') {
        return ws_command_help_one($viewer, $channel, $word);
    }

    $html = '<div class="ws-ephemeral-title">' . h(lang('Slash commands')) . '</div>';
    $groups = array();

    foreach (ws_command_help($viewer, $channel) as $item) {
        $groups[$item['group']][] = $item;
    }

    foreach (ws_command_group_labels() as $group => $label) {
        if (empty($groups[$group])) {
            continue;
        }

        $html .= '<div class="ws-help-group fw-semibold fg-secondary mt-2">' . h($label) . '</div><dl class="ws-help">';

        foreach ($groups[$group] as $item) {
            $html .= '<dt><code>' . h($item['command']) . '</code></dt><dd>' . h($item['description'])
                . '<br><code class="ws-help-usage">' . ws_command_usage_html($item) . '</code>'
                . '<br><span class="fg-secondary">' . h($item['example']) . '</span></dd>';
        }

        $html .= '</dl>';
    }

    return array('type' => 'ephemeral', 'html' => $html);
}

/**
 * A command's usage line as /help shows it: each argument kept whole on a
 * line (text-nowrap), so a narrow screen wraps between arguments only. An
 * application's command, whose usage is its own free text, is shown as
 * written.
 *
 * @param array $item ws_command_help() entry
 * @return string HTML
 */
function ws_command_usage_html($item)
{
    if (empty($item['args'])) {
        return h($item['usage']);
    }

    $html = h($item['name']);

    foreach ($item['args'] as $arg) {
        $html .= ' <span class="text-nowrap">' . h($arg['optional'] ? '[' . $arg['label'] . ']' : '<' . $arg['label'] . '>') . '</span>';
    }

    return $html;
}

/**
 * /help <command>: that command alone, with every name it answers to, its
 * usage and an example; a name that is no command here is answered with
 * the closest ones.
 *
 * @param array|null $viewer
 * @param array|null $channel
 * @param string     $word the name asked about, without its slash
 * @return array an ephemeral answer
 */
function ws_command_help_one($viewer, $channel, $word)
{
    $folded = ws_command_fold($word);

    foreach (ws_command_help($viewer, $channel) as $item) {
        foreach ($item['names'] as $name) {
            if (ws_command_fold(ltrim($name, '/')) !== $folded) {
                continue;
            }

            $html = '<div class="ws-ephemeral-title">' . h($item['command']) . '</div>'
                . '<div>' . h($item['description']) . '</div>'
                . '<code class="ws-help-usage">' . ws_command_usage_html($item) . '</code>'
                . '<div>' . h(lang(array('string' => 'Names: {var:1}', 'vars' => implode(' ', $item['names'])))) . '</div>'
                . '<div class="fg-secondary">' . h(lang(array('string' => 'Example: {var:1}', 'vars' => $item['example']))) . '</div>';

            return array('type' => 'ephemeral', 'html' => $html);
        }
    }

    $closest = ws_command_closest($word, $viewer);
    $html = '<div class="ws-ephemeral-title">' . h(lang(array('string' => 'There is no {var:1} command.', 'vars' => '/' . $word))) . '</div>';

    if (!empty($closest)) {
        $html .= '<div>' . h(lang('Did you mean:')) . ' ' . implode(' ', array_map(function ($name) {
            return '<code>' . h($name) . '</code>';
        }, $closest)) . '</div>';
    }

    return array('type' => 'ephemeral', 'html' => $html, 'closest' => $closest);
}

/**
 * /task: shown back first, created on confirmation.
 *
 * @return array
 */
function ws_command_task($viewer, $channel, $text, $options)
{
    $parsed = ws_command_typed_assignees(ws_command_parse_task($text), ws_team_members());
    $description = null;

    // Typed as a reply without a text: the task is made of the answered
    // message, as its menu's "Make a task of it" does.
    if (ws_command_acts_on_reply($text, $options)) {
        $message = ws_command_answered_message($viewer, $channel, $options);

        if ($message === null) {
            return array('type' => 'error', 'error' => lang('That message could not be found.'));
        }

        $parsed['title'] = mb_substr(ws_plain_line($viewer, (string) $message['body']), 0, 200, 'UTF-8');

        // The menu fills the description only from the writer's own message.
        if (((string) $message['sender_kind'] === 'user') && ((int) $message['sender_id'] === (int) $viewer['id'])) {
            $description = (string) $message['body'];
        }
    }

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

    if ($description !== null) {
        $data['description'] = $description;
    }

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
        $made = ws_task($result['task_id']);
        ws_task_ask_tags($viewer, $made, '', ws_task_tag_text($made));
    }

    return array('type' => 'done', 'task_id' => $result['task_id'], 'message_id' => $sent['message_id'] ?? 0);
}

/**
 * The live line under a command being written: what sending it now would
 * do, read by the same parser that runs it, and what it cannot read: /task,
 * /status, /timer, /schedule, /away and /meeting (their
 * ws_command_*_suggest()) have one; any other command answers with an empty
 * line.
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
    $entry = $split ? (ws_command_registry()[$split['command']] ?? null) : null;

    if (($entry === null) || ($entry['live'] === '')) {
        return $out;
    }

    return call_user_func($entry['live'], $viewer, $channel, $split['rest']);
}

/**
 * /task's live line: "Will create: …" and its warnings.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $rest the line after the command's name
 * @return array line, warnings
 */
function ws_command_task_suggest($viewer, $channel, $rest)
{
    $out = array('line' => '', 'warnings' => array());

    if ($rest === '') {
        return $out;
    }

    $parsed = ws_command_typed_assignees(ws_command_parse_task($rest), ws_team_members());

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
 * /status's live line.
 *
 * @return array line, warnings
 */
function ws_command_live_status($viewer, $channel, $rest)
{
    return ws_command_status_suggest($viewer, $rest);
}

/**
 * /timer's live line.
 *
 * @return array line, warnings
 */
function ws_command_live_timer($viewer, $channel, $rest)
{
    return ws_command_timer_suggest($viewer, $rest);
}

/**
 * /away's live line.
 *
 * @return array line, warnings
 */
function ws_command_live_away($viewer, $channel, $rest)
{
    return ws_command_event_suggest($viewer, ws_command_away_data($rest));
}

/**
 * /remind's live line: what will be reminded and when, or that the New
 * plan form opens when no time is given.
 *
 * @return array line, warnings
 */
function ws_command_live_remind($viewer, $channel, $rest, $options = array(), $now = null)
{
    $out = array('line' => '', 'warnings' => array());

    if (trim((string) $rest) === '') {
        return $out;
    }

    $fill = ws_command_remind_fill($viewer, $channel, $rest, $options, $now);

    if (isset($fill['error'])) {
        $out['warnings'][] = $fill['error'];

        return $out;
    }

    $out['line'] = ws_command_remind_line($fill);

    if ($fill['title'] === '') {
        $out['warnings'][] = lang('Write what to remind after the time.');
    }

    if (($fill['start_time'] !== '') && (strtotime($fill['start'] . ' ' . $fill['start_time'] . ':00') <= (($now === null) ? time() : (int) $now))) {
        $out['warnings'][] = lang('That time has already gone by.');
    }

    return $out;
}

/**
 * The words of /remind: a day word and a time ("yarın 10:00", "10:00",
 * "cuma saat 14:30"), in either order, at the start of the line, read as
 * /task reads them (ws_parse_date_word(), ws_parse_time_word()); the rest
 * is what to remind, as written. A time with no day is today's. Pure.
 *
 * @param string   $text the line after the command's name
 * @param int|null $now  for tests
 * @return array date (Y-m-d, '' when none was read), time (H:i, '' when
 *               none), title
 */
function ws_command_remind_data($text, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $today = date('Y-m-d', $now);
    $text = trim((string) $text);
    $words = ($text === '') ? array() : preg_split('/\s+/u', $text);
    $date = '';
    $clock = '';
    $taken = 0;

    foreach ($words as $index => $word) {
        if ($index > 3) {
            break;
        }

        if (($date === '') && (ws_parse_date_word($word, $today) !== '')) {
            $date = ws_parse_date_word($word, $today);
            $taken = $index + 1;
            continue;
        }

        // "saat 14:00" / "at 14:00": the word before the time is no title.
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

    $rest = ($taken > 0) ? preg_split('/\s+/u', $text, $taken + 1) : array($text);
    $title = trim((string) (($taken > 0) ? ($rest[$taken] ?? '') : $text));

    if (($clock !== '') && ($date === '')) {
        $date = $today;
    }

    return array('date' => $date, 'time' => $clock, 'title' => $title);
}

/**
 * What /remind hands to ws_personal_plan_save(), or to the New plan form
 * when no time was given: the day (today when none), the time, the title,
 * the reminder at the start, and as a reply the answered message's link in
 * the note.
 *
 * @param array    $viewer
 * @param array    $channel
 * @param string   $text
 * @param array    $options parent_id
 * @param int|null $now
 * @return array title, note, start, end, all_day, start_time, end_time,
 *               remind, or error
 */
function ws_command_remind_fill($viewer, $channel, $text, $options = array(), $now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $data = ws_command_remind_data($text, $now);
    $note = '';
    $title = $data['title'];

    if ((int) ($options['parent_id'] ?? 0) > 0) {
        $message = ws_command_answered_message($viewer, $channel, $options);

        if ($message === null) {
            return array('error' => lang('That message could not be found.'));
        }

        $note = ws_command_message_link($channel, $message);

        if ($title === '') {
            $title = (string) mb_substr(ws_plain_line($viewer, (string) $message['body']), 0, 200);
        }
    }

    return ws_command_remind_plan($data, $title, $note, $now);
}

/**
 * The plan's values from what /remind read. Pure.
 *
 * @param array  $data  ws_command_remind_data()
 * @param string $title
 * @param string $note
 * @param int    $now
 * @return array
 */
function ws_command_remind_plan($data, $title, $note, $now)
{
    $day = ($data['date'] !== '') ? $data['date'] : date('Y-m-d', (int) $now);

    return array(
        'title'      => trim((string) $title),
        'note'       => (string) $note,
        'start'      => $day,
        'end'        => $day,
        'all_day'    => 0,
        'start_time' => $data['time'],
        'end_time'   => '',
        // A reminder command reminds: the New plan form's first reminder,
        // at the start ("No reminder" is the form's empty choice).
        'remind'     => '0',
    );
}

/**
 * A message's own address, written into a plan's note as plain text.
 *
 * @param array $channel
 * @param array $message
 * @return string
 */
function ws_command_message_link($channel, $message)
{
    $base = (defined('URL_SCHEME') && defined('HOSTNAME_SETTING')) ? URL_SCHEME . HOSTNAME_SETTING : '';

    return $base . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace.php?channel=' . (int) $channel['id'] . '&message=' . (int) $message['id'];
}

/**
 * The line /remind's live line and answer say: "Will remind you: “title” ·
 * Tomorrow 10:00", or the New plan form opening on a day.
 *
 * @param array $fill ws_command_remind_fill()
 * @return string
 */
function ws_command_remind_line($fill)
{
    $title = ($fill['title'] !== '') ? '“' . mb_substr($fill['title'], 0, 80) . '”' : '';

    if ($fill['start_time'] === '') {
        return lang(array('string' => 'Will open New plan: {var:1}', 'vars' => implode(' · ', array_filter(array($title, ws_day_label($fill['start'])), 'strlen'))));
    }

    $at = strtotime($fill['start'] . ' ' . $fill['start_time'] . ':00');
    $when = function_exists('ws_scheduled_moment') ? ws_scheduled_moment($at) : ws_day_label($fill['start']) . ' ' . $fill['start_time'];

    return lang(array('string' => 'Will remind you: {var:1}', 'vars' => implode(' · ', array_filter(array($title, $when), 'strlen'))));
}

/**
 * /approval: an approval request posted through ws_approval_create(), as
 * the "Ask for approval" form posts one (its checks in ws_approval_input()),
 * asking the people named with @ on its first line and approved by any one
 * of them. Without a
 * title or without anybody named, that form opens filled with what was
 * typed.
 *
 * @return array
 */
function ws_command_run_approval($viewer, $channel, $rest)
{
    $data = ws_command_approval_read($rest);

    if (($data['title'] === '') || empty($data['people'])) {
        return array('type' => 'approval_form', 'fill' => ws_command_approval_fill($data));
    }

    $result = ws_approval_create($viewer, $channel, array(
        'title'     => $data['title'],
        'text'      => $data['text'],
        'approvers' => $data['people'],
        'rule'      => 'any',
        'closes_at' => 0,
        // Written by a person, as the form is.
        'may_ask_agents' => true,
    ));

    return $result['ok']
        ? array('type' => 'done', 'message_id' => (int) $result['message_id'])
        : array('type' => 'error', 'error' => $result['error']);
}

/**
 * What /approval reads from the line typed, the team read only when a
 * username is typed after an @.
 *
 * @param string $text the line after the command's name
 * @return array ws_command_approval_data()
 */
function ws_command_approval_read($text)
{
    $typed = preg_match('/(?:^|\s)@[\p{L}\p{N}_]/u', preg_replace('/<@user:[0-9]{1,10}>/', ' ', (string) $text));

    return ws_command_approval_data($text, $typed ? ws_team_members() : array());
}

/**
 * What /approval reads: the first line is the request, the people on it
 * tagged or typed after an @ (a team member's exact username, as /task
 * reads them) and the rest of it the title; the lines under it are the
 * details, as written, where a tag only tells. Pure.
 *
 * @param string  $text the line after the command's name
 * @param array[] $team briefs with id and username (ws_team_members())
 * @return array title, text, people (user ids), typed_unknown, typed_ambiguous
 */
function ws_command_approval_data($text, $team)
{
    $lines = explode("\n", str_replace("\r\n", "\n", trim((string) $text)), 2);
    $first = $lines[0];
    $people = array();

    foreach (ws_tokens($first) as $token) {
        if ($token['type'] === 'user') {
            $people[] = (int) $token['id'];
        }
    }

    $first = preg_replace('/<@user:[0-9]{1,10}>/', ' ', $first);
    $typed = array();

    if (preg_match_all('/(?:^|\s)@([\p{L}\p{N}_.\-]{1,40})[,;:!?]*(?=\s|$)/u', $first, $matches)) {
        foreach ($matches[1] as $name) {
            $typed[] = rtrim($name, '.-');
        }
    }

    $parsed = ws_command_typed_assignees(array('title' => $first, 'assignees' => $people, 'typed_people' => $typed), $team);

    return array(
        'title'           => (string) $parsed['title'],
        'text'            => trim((string) ($lines[1] ?? '')),
        'people'          => array_values(array_unique(array_map('intval', $parsed['assignees']))),
        'typed_unknown'   => $parsed['typed_unknown'],
        'typed_ambiguous' => $parsed['typed_ambiguous'],
    );
}

/**
 * What the "Ask for approval" form is opened with. Pure.
 *
 * @param array $data ws_command_approval_data()
 * @return array title, text, approvers
 */
function ws_command_approval_fill($data)
{
    return array('title' => $data['title'], 'text' => $data['text'], 'approvers' => $data['people']);
}

/**
 * /approval's live line: who will be asked to approve what, or the form
 * opening, with the refusals of ws_approval_input().
 *
 * @return array line, warnings
 */
function ws_command_live_approval($viewer, $channel, $rest)
{
    $out = array('line' => '', 'warnings' => array());

    if (trim((string) $rest) === '') {
        return $out;
    }

    $data = ws_command_approval_read($rest);
    $names = array();

    foreach (empty($data['people']) ? array() : ws_people($data['people']) as $person) {
        $names[] = $person['name'];
    }

    $said = implode(' · ', array_filter(array(
        ($data['title'] !== '') ? '“' . mb_substr($data['title'], 0, 80) . '”' : '',
        implode(', ', $names),
    ), 'strlen'));

    foreach ($data['typed_unknown'] as $name) {
        $out['warnings'][] = lang(array('string' => 'No team member is called @{var:1}.', 'vars' => $name));
    }

    if (($data['title'] === '') || empty($data['people'])) {
        $out['line'] = lang(array('string' => 'Will open Ask for approval: {var:1}', 'vars' => $said));
        $out['warnings'][] = ($data['title'] === '') ? lang('An approval request needs a title.') : lang('Choose who is to approve it.');

        return $out;
    }

    $out['line'] = lang(array('string' => 'Will ask for approval: {var:1}', 'vars' => $said));
    $checked = ws_approval_input($viewer, $channel, array('title' => $data['title'], 'text' => $data['text'], 'approvers' => $data['people']));

    if (!$checked['ok']) {
        $out['warnings'][] = $checked['error'];
    }

    return $out;
}

/**
 * /update: a status update of the channel through ws_status_post(), as the
 * "Status update" form posts one. Without a state the form opens, the text
 * typed in place of its draft.
 *
 * @return array
 */
function ws_command_run_update($viewer, $channel, $rest)
{
    $data = ws_command_update_data($rest);

    if ($data['health'] === '') {
        return array('type' => 'status_form', 'fill' => $data);
    }

    $result = ws_status_post($viewer, $channel, $data['health'], $data['text']);

    return $result['ok']
        ? array('type' => 'done', 'message_id' => (int) $result['message_id'])
        : array('type' => 'error', 'error' => $result['error']);
}

/**
 * What /update reads: a state word first (ws_command_health_word()), then
 * what to say, as written. A first word that is no state stays in the
 * text. Pure.
 *
 * @param string $text the line after the command's name
 * @return array health (a ws_status_healths() key, '' for none), text
 */
function ws_command_update_data($text)
{
    $text = trim((string) $text);
    $words = ($text === '') ? array() : preg_split('/\s+/u', $text, 3);

    // "on track", "at risk", "off track" are read as one word.
    if (count($words) > 1) {
        $health = ws_command_health_word($words[0] . '-' . $words[1]);

        if ($health !== '') {
            return array('health' => $health, 'text' => trim((string) ($words[2] ?? '')));
        }
    }

    $health = ws_command_health_word($words[0] ?? '');

    if ($health === '') {
        return array('health' => '', 'text' => $text);
    }

    $rest = preg_split('/\s+/u', $text, 2);

    return array('health' => $health, 'text' => trim((string) ($rest[1] ?? '')));
}

/**
 * A state of the work typed after /update, in Turkish or English, with or
 * without the Turkish letters. Pure.
 *
 * @param string $word
 * @return string a ws_status_healths() key, '' when it is none
 */
function ws_command_health_word($word)
{
    $words = array(
        'yolunda' => 'on_track', 'iyi' => 'on_track', 'on-track' => 'on_track', 'ontrack' => 'on_track', 'on_track' => 'on_track',
        'riskte' => 'at_risk', 'riskli' => 'at_risk', 'risk' => 'at_risk', 'at-risk' => 'at_risk', 'atrisk' => 'at_risk', 'at_risk' => 'at_risk',
        'gecikti' => 'off_track', 'gecikmede' => 'off_track', 'off-track' => 'off_track', 'offtrack' => 'off_track', 'off_track' => 'off_track',
    );

    return $words[ws_command_fold(trim((string) $word, " \t.,;:!"))] ?? '';
}

/**
 * The state words the argument popup offers, in the screen's language.
 * The word is the state's own name, so no label repeats it. Pure.
 *
 * @param bool $turkish
 * @return array[] value, meta, top
 */
function ws_command_health_values($turkish)
{
    $words = $turkish
        ? array('on_track' => 'yolunda', 'at_risk' => 'riskte', 'off_track' => 'gecikti')
        : array('on_track' => 'on-track', 'at_risk' => 'at-risk', 'off_track' => 'off-track');
    $out = array();

    foreach ($words as $word) {
        $out[] = array('value' => $word, 'meta' => '', 'top' => true);
    }

    return $out;
}

/**
 * /update's live line: the state and the text that will be posted, or the
 * form opening, with the refusals of ws_status_post_check().
 *
 * @return array line, warnings
 */
function ws_command_live_update($viewer, $channel, $rest)
{
    $out = array('line' => '', 'warnings' => array());

    if (trim((string) $rest) === '') {
        return $out;
    }

    $data = ws_command_update_data($rest);
    $said = ($data['text'] !== '') ? '“' . mb_substr($data['text'], 0, 80) . '”' : '';

    if ($data['health'] === '') {
        $out['line'] = lang(array('string' => 'Will open Status update: {var:1}', 'vars' => $said));
        $out['warnings'][] = lang('Choose how the work stands.');

        return $out;
    }

    $out['line'] = lang(array('string' => 'Will post a status update: {var:1}', 'vars' => implode(' · ', array_filter(array(ws_status_healths()[$data['health']], $said), 'strlen'))));
    $checked = ws_status_post_check($viewer, $channel, $data['health'], $data['text']);

    if (!$checked['ok']) {
        $out['warnings'][] = $checked['error'];
    }

    return $out;
}

/**
 * /checkin: the question asked through ws_checkin_start(), as "Ask for a
 * check-in" asks it. Without a question that form opens with the usual
 * one written in.
 *
 * @return array
 */
function ws_command_run_checkin($viewer, $channel, $rest)
{
    $question = ws_checkin_question($rest);

    if ($question === '') {
        return array('type' => 'checkin_form', 'fill' => array('question' => ws_checkin_default_question()));
    }

    $result = ws_checkin_start($viewer, $channel, $question);

    return $result['ok']
        ? array('type' => 'done', 'message_id' => (int) $result['message_id'])
        : array('type' => 'error', 'error' => $result['error']);
}

/**
 * /checkin's live line: the question that will be asked, with the
 * refusals of ws_checkin_start_check().
 *
 * @return array line, warnings
 */
function ws_command_live_checkin($viewer, $channel, $rest)
{
    $out = array('line' => '', 'warnings' => array());
    $question = ws_checkin_question($rest);

    if ($question === '') {
        return $out;
    }

    $out['line'] = lang(array('string' => 'Will ask for a check-in: {var:1}', 'vars' => '“' . mb_substr($question, 0, 80) . '”'));
    $checked = ws_checkin_start_check($viewer, $channel, $question);

    if (!$checked['ok']) {
        $out['warnings'][] = $checked['error'];
    }

    return $out;
}

/**
 * /notify: the writer's notifications of this channel through
 * ws_channel_set_notify(), as the channel menu's three choices set them.
 * Without a word, the choice in force and the words that change it; both
 * to the writer alone.
 *
 * @return array
 */
function ws_command_run_notify($viewer, $channel, $rest)
{
    $word = trim((string) $rest);
    $notify = ws_command_notify_word($word);

    if ($word === '') {
        $membership = ws_channel_membership($channel['id'], $viewer['id']);

        return array('type' => 'ephemeral', 'html' => ws_command_notify_html($membership ? (string) $membership['notify'] : '', false));
    }

    if ($notify === '') {
        return array('type' => 'error', 'error' => ws_command_notify_unknown());
    }

    $result = ws_channel_set_notify($viewer, $channel, $notify);

    if (!$result['ok']) {
        return array('type' => 'error', 'error' => $result['error']);
    }

    return array('type' => 'ephemeral', 'html' => ws_command_notify_html($notify, true));
}

/**
 * A notification choice typed after /notify, in Turkish or English, with
 * or without the Turkish letters. Pure.
 *
 * @param string $word
 * @return string all | mentions | none, '' when it is none of them
 */
function ws_command_notify_word($word)
{
    $words = array(
        'hepsi' => 'all', 'tumu' => 'all', 'all' => 'all', 'every' => 'all',
        'anma' => 'mentions', 'anmalar' => 'mentions', 'mentions' => 'mentions', 'mention' => 'mentions',
        'hic' => 'none', 'hicbiri' => 'none', 'never' => 'none', 'none' => 'none',
    );

    return $words[ws_command_fold(trim((string) $word, " \t.,;:!"))] ?? '';
}

/**
 * The three choices as the channel menu names them.
 *
 * @return array all | mentions | none => label
 */
function ws_command_notify_labels()
{
    return array(
        'all'      => lang('Tell me about every message'),
        'mentions' => lang('Only when I am mentioned'),
        'none'     => lang('Never'),
    );
}

/**
 * The words /notify reads, as the argument popup offers them, in the
 * screen's language. Pure.
 *
 * @param bool $turkish
 * @return array[] value, meta, top
 */
function ws_command_notify_values($turkish)
{
    $words = $turkish ? array('all' => 'hepsi', 'mentions' => 'anma', 'none' => 'hiç') : array('all' => 'all', 'mentions' => 'mentions', 'none' => 'never');
    $labels = ws_command_notify_labels();
    $out = array();

    foreach ($words as $key => $word) {
        $out[] = array('value' => $word, 'meta' => $labels[$key], 'top' => true);
    }

    return $out;
}

/**
 * The refusal for a word /notify does not read, naming the three.
 *
 * @return string
 */
function ws_command_notify_unknown()
{
    return lang(array('string' => 'Write one of: {var:1}.', 'vars' => implode(', ', array_column(ws_command_notify_values(ws_command_turkish()), 'value'))));
}

/**
 * /notify's answer: the choice in force (or just set) and the words that
 * change it. Pure.
 *
 * @param string $notify all | mentions | none, '' when not in the channel
 * @param bool   $set    just changed
 * @return string
 */
function ws_command_notify_html($notify, $set)
{
    $labels = ws_command_notify_labels();
    $now = $labels[$notify] ?? lang('You are not in this channel.');
    $words = array();

    foreach (ws_command_notify_values(ws_command_turkish()) as $item) {
        // A word and its meaning hold together; the line breaks only
        // between them.
        $words[] = '<span class="text-nowrap"><code>' . h($item['value']) . '</code> ' . h($item['meta']) . '</span>';
    }

    return '<div class="ws-ephemeral-title">' . h(lang('Notifications')) . '</div>'
        . '<div>' . h($set ? lang(array('string' => 'Now set: {var:1}', 'vars' => $now)) : lang(array('string' => 'Now: {var:1}', 'vars' => $now))) . '</div>'
        . '<div class="fg-secondary">' . h(lang('Change with one of:')) . ' ' . implode(' · ', $words) . '</div>';
}

/**
 * /notify's live line: the choice Enter will set, or the one in force.
 *
 * @return array line, warnings
 */
function ws_command_live_notify($viewer, $channel, $rest)
{
    $out = array('line' => '', 'warnings' => array());
    $word = trim((string) $rest);

    if ($word === '') {
        return $out;
    }

    $notify = ws_command_notify_word($word);

    if ($notify === '') {
        $out['warnings'][] = ws_command_notify_unknown();

        return $out;
    }

    $out['line'] = lang(array('string' => 'Will set your notifications here: {var:1}', 'vars' => ws_command_notify_labels()[$notify]));

    return $out;
}

/**
 * /invite: the people named with @ brought into the channel through
 * ws_channel_add_members(), as "Add people" brings them, with its rule: in
 * a channel that is not public only a member may bring somebody in. The
 * answer, to the writer alone, names who was added, who was in already and
 * who could not be.
 *
 * @return array
 */
function ws_command_run_invite($viewer, $channel, $rest)
{
    $people = ws_command_approval_read($rest)['people'];

    if (empty($people)) {
        return array('type' => 'error', 'error' => lang('Name the people to add with @.'));
    }

    if (!ws_command_gate_invite($viewer, 'invite', $channel)) {
        return array('type' => 'error', 'error' => lang('Access denied.'));
    }

    $already = array_values(array_filter($people, function ($user_id) use ($channel) {
        return (bool) ws_channel_membership($channel['id'], $user_id);
    }));
    $split = ws_command_invite_split($people, $already, ws_channel_add_members($viewer, $channel, array_values(array_diff($people, $already))));

    return array('type' => 'ephemeral', 'html' => ws_command_invite_html($split['added'], $split['already'], $split['refused']));
}

/**
 * The people /invite named, told apart: added, in already, and refused by
 * ws_channel_add_members() (not on the team, or not one a guest room or a
 * discussion takes). Pure.
 *
 * @param int[] $people  named
 * @param int[] $already in the channel before
 * @param int[] $added   what ws_channel_add_members() added
 * @return array added, already, refused
 */
function ws_command_invite_split($people, $already, $added)
{
    $added = array_values(array_map('intval', (array) $added));
    $already = array_values(array_map('intval', (array) $already));

    return array(
        'added'   => $added,
        'already' => $already,
        'refused' => array_values(array_diff(array_map('intval', (array) $people), $already, $added)),
    );
}

/**
 * /invite's answer. Pure but for the names.
 *
 * @param int[] $added
 * @param int[] $already
 * @param int[] $refused
 * @return string
 */
function ws_command_invite_html($added, $already, $refused)
{
    $names = function ($ids) {
        return implode(', ', array_map('ws_person_name', $ids));
    };

    $html = '<div class="ws-ephemeral-title">' . h(lang('Add people')) . '</div>';

    if (!empty($added)) {
        $html .= '<div>' . h(lang(array('string' => 'Added: {var:1}', 'vars' => $names($added)))) . '</div>';
    }

    if (!empty($already)) {
        $html .= '<div>' . h(lang(array('string' => 'Already in the channel: {var:1}', 'vars' => $names($already)))) . '</div>';
    }

    if (!empty($refused)) {
        $html .= '<div>' . h(lang(array('string' => 'Could not be added: {var:1}', 'vars' => $names($refused)))) . '</div>';
    }

    return $html;
}

/**
 * /invite's live line: who Enter will add, and who is in already.
 *
 * @return array line, warnings
 */
function ws_command_live_invite($viewer, $channel, $rest)
{
    $out = array('line' => '', 'warnings' => array());

    if (trim((string) $rest) === '') {
        return $out;
    }

    $data = ws_command_approval_read($rest);
    $new = array();

    foreach ($data['people'] as $user_id) {
        if (ws_channel_membership($channel['id'], $user_id)) {
            $out['warnings'][] = lang(array('string' => 'Already in the channel: {var:1}', 'vars' => ws_person_name($user_id)));
        } else {
            $new[] = ws_person_name($user_id);
        }
    }

    foreach ($data['typed_unknown'] as $name) {
        $out['warnings'][] = lang(array('string' => 'No team member is called @{var:1}.', 'vars' => $name));
    }

    if (!empty($new)) {
        $out['line'] = lang(array('string' => 'Will add: {var:1}', 'vars' => implode(', ', $new)));
    }

    return $out;
}

/**
 * /leave: a card to the writer alone asks first, as the channel menu's
 * "Leave the channel" does; only its confirm leaves, through
 * ws_channel_leave() with that function's refusals.
 *
 * @return array
 */
function ws_command_run_leave($viewer, $channel, $rest, $options)
{
    $step = ws_command_leave_step((bool) ws_channel_membership($channel['id'], $viewer['id']), !empty($options['confirm']), (string) $channel['kind']);

    if ($step['type'] !== 'leave') {
        return $step;
    }

    $result = ws_channel_leave($viewer, $channel);

    return $result['ok']
        ? array('type' => 'left')
        : array('type' => 'error', 'error' => $result['error']);
}

/**
 * What /leave does now: refused to somebody not in the channel, the card
 * before a confirm, the leave only after it. Pure.
 *
 * @param bool   $member  the writer is in the channel
 * @param bool   $confirm sent from the card's button
 * @param string $kind    the channel's kind
 * @return array type error | leave_card | leave
 */
function ws_command_leave_step($member, $confirm, $kind)
{
    if (!$member) {
        return array('type' => 'error', 'error' => lang('You are not in this channel.'));
    }

    if (!$confirm) {
        return array('type' => 'leave_card', 'private' => ($kind !== 'public'));
    }

    return array('type' => 'leave');
}

/**
 * /leave's live line: the channel it leaves once the card is confirmed.
 *
 * @return array line, warnings
 */
function ws_command_live_leave($viewer, $channel, $rest)
{
    return array('line' => lang(array('string' => 'Will ask before you leave #{var:1}', 'vars' => (string) ($channel['name'] ?? ''))), 'warnings' => array());
}

/**
 * /topic: what the channel is about, written through ws_channel_update()
 * as the channel settings write it, with its rights and refusal. Without
 * a text, the one in force to the writer alone.
 *
 * @return array
 */
function ws_command_run_topic($viewer, $channel, $rest)
{
    $topic = trim((string) $rest);

    if ($topic === '') {
        return array('type' => 'ephemeral', 'html' => ws_command_topic_html((string) ($channel['topic'] ?? ''), false));
    }

    $result = ws_channel_update($viewer, $channel, array('topic' => $topic));

    if (!$result['ok']) {
        return array('type' => 'error', 'error' => $result['error']);
    }

    // The header and the info panel show the new one (refreshInfo()).
    return array('type' => 'ephemeral', 'html' => ws_command_topic_html(mb_substr($topic, 0, 255), true), 'refresh' => true);
}

/**
 * /topic's answer: the text in force (or just set), or that there is none
 * and how to set it. Pure but for the language.
 *
 * @param string $topic
 * @param bool   $set   just changed
 * @return string
 */
function ws_command_topic_html($topic, $set)
{
    $html = '<div class="ws-ephemeral-title">' . h(lang('What it is about')) . '</div>';

    if ($topic === '') {
        return $html . '<div>' . h(lang('This channel does not say what it is about yet.')) . '</div>'
            . '<div class="fg-secondary">' . h(lang(array('string' => 'Set it with {var:1} and the text.', 'vars' => ws_command_turkish() ? '/konu' : '/topic'))) . '</div>';
    }

    $quoted = '“' . $topic . '”';

    return $html . '<div>' . h($set ? lang(array('string' => 'Now set: {var:1}', 'vars' => $quoted)) : lang(array('string' => 'Now: {var:1}', 'vars' => $quoted))) . '</div>';
}

/**
 * /topic's live line: the text Enter will set, refused ahead as the
 * settings refuse it; without a text, that it only shows the one in force.
 *
 * @return array line, warnings
 */
function ws_command_live_topic($viewer, $channel, $rest)
{
    $topic = trim((string) $rest);

    if ($topic === '') {
        return array('line' => lang('Will show what it is about, to you alone'), 'warnings' => array());
    }

    $out = array('line' => lang(array('string' => 'Will set what it is about: {var:1}', 'vars' => '“' . mb_substr($topic, 0, 80) . '”')), 'warnings' => array());

    if (is_array($viewer) && is_array($channel) && !ws_can_manage_channel($viewer, $channel)) {
        $out['warnings'][] = lang('Only the owner of the channel can change it.');
    }

    return $out;
}

/**
 * /search: the composer opens the search palette on the channel with the
 * words itself and never sends the line (commandSearchWords(),
 * assets/js/workspace.js). Reached only over the API, it writes nothing and
 * answers what the palette would be opened with.
 *
 * @return array
 */
function ws_command_run_search($viewer, $channel, $rest)
{
    return array('type' => 'search', 'query' => trim((string) $rest), 'channel_id' => (int) $channel['id']);
}

/**
 * /search's live line: the channel and the words the palette opens with.
 *
 * @return array line, warnings
 */
function ws_command_live_search($viewer, $channel, $rest)
{
    $words = trim((string) $rest);
    $name = (string) ($channel['name'] ?? '');

    if ($words === '') {
        return array('line' => lang(array('string' => 'Will open the search on #{var:1}', 'vars' => $name)), 'warnings' => array());
    }

    return array('line' => lang(array('string' => 'Will search #{var:1} for: {var:2}', 'vars' => array($name, '“' . mb_substr($words, 0, 80) . '”'))), 'warnings' => array());
}

/**
 * /memory: whoever may write the channel's memory, as its New entry
 * offers (ws_memory_check()); only where the memory table is there.
 * Without a person or a channel, listed like the rest.
 *
 * @param array|null $viewer
 * @param string     $command
 * @param array|null $channel
 * @return bool
 */
function ws_command_gate_memory($viewer, $command = '', $channel = null)
{
    if (!is_array($viewer) || !is_array($channel)) {
        return true;
    }

    return function_exists('ws_memory_ready') && ws_memory_ready() && ws_can_post_channel($viewer, $channel);
}

/**
 * /memory: an entry written through ws_memory_save(), checked by
 * ws_memory_check() as the Memory tab's New entry is, with its refusals.
 * Typed as a reply, the entry's text carries the answered message's link.
 * Without a text, the New entry form opens, filled with the link if any.
 *
 * @return array
 */
function ws_command_run_memory($viewer, $channel, $rest, $options)
{
    $link = '';

    if ((int) ($options['parent_id'] ?? 0) > 0) {
        $message = ws_command_answered_message($viewer, $channel, $options);

        if ($message === null) {
            return array('type' => 'error', 'error' => lang('That message could not be found.'));
        }

        $link = ws_command_message_link($channel, $message);
    }

    $entry = ws_command_memory_entry($rest, $link);

    if ($entry === null) {
        return array('type' => 'memory_form', 'fill' => array('title' => '', 'text' => $link));
    }

    $check = ws_memory_check($viewer, $channel, $entry);

    if (!$check['ok']) {
        return array('type' => 'error', 'error' => $check['error']);
    }

    $id = ws_memory_save($channel['id'], $check['values'], 'user', $viewer['id']);

    return array('type' => 'ephemeral', 'html' => ws_command_memory_html($check['values']['title'], $link !== ''), 'memory_id' => (int) $id);
}

/**
 * The entry /memory writes of what was typed: the first line its title
 * (cut to the title's limit, the whole text then kept as its text), the
 * lines after it its text, and the answered message's link under them.
 * Pure.
 *
 * @param string $typed the line after the command's name
 * @param string $link  the answered message's address, '' for none
 * @return array|null title, text; null when nothing was typed
 */
function ws_command_memory_entry($typed, $link)
{
    $typed = trim(str_replace("\r\n", "\n", (string) $typed));

    if ($typed === '') {
        return null;
    }

    $max = defined('WS_MEMORY_TITLE_MAX') ? WS_MEMORY_TITLE_MAX : 150;
    $lines = explode("\n", $typed, 2);
    $title = trim($lines[0]);
    $text = trim((string) ($lines[1] ?? ''));

    if (mb_strlen($title) > $max) {
        $title = rtrim(mb_substr($title, 0, $max - 1)) . '…';
        $text = $typed;
    }

    if ($link !== '') {
        $text = trim($text . "\n\n" . $link);
    }

    return array('title' => $title, 'text' => $text);
}

/**
 * /memory's answer to the writer alone. Pure but for the language.
 *
 * @param string $title
 * @param bool   $linked the entry carries an answered message's link
 * @return string
 */
function ws_command_memory_html($title, $linked)
{
    $html = '<div class="ws-ephemeral-title">' . h(lang('Saved in the channel\'s memory')) . '</div>'
        . '<div>' . h('“' . $title . '”') . '</div>';

    if ($linked) {
        $html .= '<div class="fg-secondary">' . h(lang('With the link of the message you answered.')) . '</div>';
    }

    return $html;
}

/**
 * /memory's live line: the entry's title, and a warning when the first
 * line is longer than a title holds; without a text, the form it opens.
 *
 * @return array line, warnings
 */
function ws_command_live_memory($viewer, $channel, $rest)
{
    $entry = ws_command_memory_entry($rest, '');

    if ($entry === null) {
        return array('line' => lang('Will open New entry of the channel\'s memory'), 'warnings' => array());
    }

    $max = defined('WS_MEMORY_TITLE_MAX') ? WS_MEMORY_TITLE_MAX : 150;
    $first = trim(explode("\n", trim(str_replace("\r\n", "\n", (string) $rest)))[0]);
    $out = array('line' => lang(array('string' => 'Will remember: {var:1}', 'vars' => '“' . mb_substr($entry['title'], 0, 80) . '”')), 'warnings' => array());

    if (mb_strlen($first) > $max) {
        $out['warnings'][] = lang(array('string' => 'The first line is cut to {var:1} characters for the title; the whole text is kept.', 'vars' => $max));
    }

    return $out;
}

/**
 * /meeting's live line.
 *
 * @return array line, warnings
 */
function ws_command_live_meeting($viewer, $channel, $rest)
{
    return ws_command_event_suggest($viewer, ws_command_meeting_data($rest));
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
 * at 09:00; a time alone is today's, or tomorrow's once it has gone by. The
 * message is the rest as it was written, its line breaks included.
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

    // Only the words read as the time come off the start: the message keeps
    // its own line breaks and spaces (paragraphs, lists, quotes, code).
    $rest = preg_split('/\s+/u', trim((string) $text), $taken + 1);
    $body = trim((string) ($rest[$taken] ?? ''));

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
 * Whether a word asks /timer to stop rather than to start.
 *
 * @param string $word
 * @return bool
 */
function ws_command_timer_stop_word($word)
{
    return in_array(ws_command_fold(rtrim((string) $word, '.,;!')), array('dur', 'durdur', 'bitir', 'stop', 'end'), true);
}

/**
 * /timer: starts the writer's timer on a task (ws_task_time_start(), which
 * stops and keeps one running elsewhere), stops it with a stop word, or
 * says what runs when nothing follows the name. Only the writer is told;
 * a stopped timer is announced the way the drawer's is.
 *
 * @param array  $viewer
 * @param string $text the line after the command's name
 * @return array
 */
function ws_command_timer($viewer, $text)
{
    $text = trim((string) $text);

    if (ws_command_timer_stop_word($text)) {
        $result = ws_task_time_stop($viewer);

        if (!$result['ok']) {
            return array('type' => 'error', 'error' => $result['error']);
        }

        return array('type' => 'ephemeral', 'html' => h(lang(array(
            'string' => 'Timer stopped: {var:1} kept on {var:2}.',
            'vars'   => array(ws_task_time_label($result['minutes']), ws_task_number($result['task_id'])),
        ))));
    }

    if ($text === '') {
        $running = ws_task_time_ready() ? ws_task_time_running($viewer['id']) : null;

        return array('type' => 'ephemeral', 'html' => h($running
            ? lang(array('string' => 'Your timer runs on {var:1}: {var:2} so far.', 'vars' => array(ws_task_number($running['task_id']), ws_task_time_label(ws_task_time_elapsed($running['started_at'], time())))))
            : lang('No timer of yours is running.')));
    }

    list($task_id) = ws_command_task_ref($text);
    $task = ws_task($task_id);

    if (!$task || !ws_can_see_task($viewer, $task)) {
        return array('type' => 'error', 'error' => lang('That task could not be found.'));
    }

    $result = ws_task_time_start($viewer, $task);

    if (!$result['ok']) {
        return array('type' => 'error', 'error' => $result['error']);
    }

    $said = lang(array('string' => 'Timer started on {var:1}.', 'vars' => ws_task_number($task['id']) . ' “' . $task['title'] . '”'));

    if ($result['stopped'] !== '') {
        $said .= ' ' . lang(array('string' => 'The timer on {var:1} was stopped and its time kept.', 'vars' => $result['stopped']));
    }

    return array('type' => 'ephemeral', 'html' => h($said));
}

/**
 * The live line under /timer: the task the timer would start on, the one
 * it would stop, or the one a stop word stops.
 *
 * @param array  $viewer
 * @param string $text the line after the command's name
 * @return array line, warnings
 */
function ws_command_timer_suggest($viewer, $text)
{
    $out = array('line' => '', 'warnings' => array());
    $text = trim((string) $text);
    $running = ws_task_time_ready() ? ws_task_time_running($viewer['id']) : null;

    if (ws_command_timer_stop_word($text)) {
        if ($running) {
            $out['line'] = lang(array('string' => 'Will stop the timer on {var:1}', 'vars' => ws_task_number($running['task_id']) . ' · ' . ws_task_time_label(ws_task_time_elapsed($running['started_at'], time()))));
        } else {
            $out['warnings'][] = lang('No timer of yours is running.');
        }

        return $out;
    }

    list($task_id) = ws_command_task_ref($text);

    if ($task_id <= 0) {
        return $out;
    }

    $task = ws_task($task_id);

    if (!$task || !ws_can_see_task($viewer, $task)) {
        $out['warnings'][] = lang('That task could not be found.');

        return $out;
    }

    $out['line'] = lang(array('string' => 'Will start the timer on {var:1}', 'vars' => ws_task_number($task['id']) . ' “' . $task['title'] . '”'));

    if (!ws_task_time_may_log($viewer, $task, ws_task_assignee_ids($task['id']))) {
        $out['warnings'][] = lang('Only the people on a task, the one who created it and staff can write time on it.');
    } elseif ($running && ((int) $running['task_id'] !== (int) $task['id'])) {
        $out['warnings'][] = lang(array('string' => 'The timer on {var:1} will be stopped and its time kept.', 'vars' => ws_task_number($running['task_id'])));
    }

    return $out;
}

/**
 * What /away is given, as ws_event_save() takes it: the first day, the last
 * day when a second day word follows, the rest as the title (Leave when
 * there is none). The first day is read from today and the last from the
 * first, so "friday monday" ends on the Monday after that Friday whatever
 * day it is typed on.
 *
 * @param string      $text the line after the command's name
 * @param string|null $today Y-m-d, for tests
 * @return array kind, scope, start, end ('' when no day was read), title
 */
function ws_command_away_data($text, $today = null)
{
    $today = ($today === null) ? date('Y-m-d') : $today;
    $words = preg_split('/\s+/u', trim((string) $text));
    $start = ws_parse_date_word($words[0] ?? '', $today);
    $end = ($start !== '') ? ws_parse_date_word($words[1] ?? '', $start) : '';
    $taken = ($start === '') ? 0 : (($end === '') ? 1 : 2);

    return array(
        'kind'  => 'leave',
        'scope' => 'people',
        'start' => $start,
        'end'   => ($end !== '') ? $end : $start,
        'title' => trim(implode(' ', array_slice($words, $taken))),
    );
}

/**
 * What /meeting is given, as ws_event_save() takes it: when it starts as
 * /schedule reads it (ws_command_parse_schedule(): a day, a time or both,
 * or +30m), then the end as a time or a length after ~ (an hour when there
 * is neither), the people tagged with @ (the writer among them) and the
 * rest as the title.
 *
 * @param string   $text the line after the command's name
 * @param int|null $now  for tests
 * @return array kind, scope, people, title, start, end, start_time, end_time
 *               ('' dates when no start was read)
 */
function ws_command_meeting_data($text, $now = null)
{
    $when = ws_command_parse_schedule($text, $now);
    $words = preg_split('/\s+/u', $when['body']);
    $minutes = 60;
    $end_time = '';

    if (ws_parse_time_word($words[0] ?? '') !== '') {
        $end_time = ws_parse_time_word(array_shift($words));
    } elseif (ws_parse_estimate($words[0] ?? '') > 0) {
        $minutes = ws_parse_estimate(array_shift($words));
    }

    $body = implode(' ', $words);
    $people = array();

    foreach (ws_tokens($body) as $token) {
        if ($token['type'] === 'user') {
            $people[] = (int) $token['id'];
        }
    }

    $data = array(
        'kind'   => 'meeting',
        'scope'  => 'people',
        'people' => array_values(array_unique($people)),
        'title'  => trim(preg_replace('/\s+/u', ' ', preg_replace('/<@user:[0-9]+>/', ' ', $body))),
        'start'  => '', 'end' => '', 'start_time' => '', 'end_time' => '',
    );

    if ($when['at'] <= 0) {
        return $data;
    }

    // An end before the start is the next day's; a length may run past midnight.
    $ends = ($end_time !== '') ? strtotime($when['date'] . ' ' . $end_time . ':00') : $when['at'] + $minutes * 60;

    if ($ends <= $when['at']) {
        $ends = strtotime('+1 day', $ends);
    }

    return array_merge($data, array(
        'start'      => $when['date'],
        'start_time' => $when['time'],
        'end'        => date('Y-m-d', $ends),
        'end_time'   => date('H:i', $ends),
    ));
}

/**
 * The writer is on a meeting they call, with the people they tag.
 *
 * @param array $viewer
 * @param array $data ws_command_meeting_data()
 * @return array
 */
function ws_command_meeting_people($viewer, $data)
{
    if ($data['kind'] === 'meeting') {
        $data['people'] = array_values(array_unique(array_merge(array((int) $viewer['id']), $data['people'])));
    }

    return $data;
}

/**
 * /away: the writer's leave on the plan through ws_event_save(); only the
 * writer is told.
 *
 * @param array  $viewer
 * @param string $text the line after the command's name
 * @return array
 */
function ws_command_away($viewer, $text)
{
    $data = ws_command_away_data($text);

    if ($data['start'] === '') {
        return array('type' => 'error', 'error' => lang('Say the first day of your leave: a day, a date or +2d.'));
    }

    $result = ws_event_save($viewer, $data);

    if (!$result['ok']) {
        return array('type' => 'error', 'error' => $result['error']);
    }

    return array('type' => 'ephemeral', 'html' => h(lang(array('string' => 'Leave written on the plan: {var:1}.', 'vars' => ws_command_event_span($data)))));
}

/**
 * /meeting: a meeting on the plan through ws_event_save(), and a line in
 * the channel naming it and its people.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $text the line after the command's name
 * @return array
 */
function ws_command_meeting($viewer, $channel, $text)
{
    $data = ws_command_meeting_people($viewer, ws_command_meeting_data($text));

    if ($data['start'] === '') {
        return array('type' => 'error', 'error' => lang('Say when the meeting starts: a day and a time such as 14:00.'));
    }

    if ($data['title'] === '') {
        return array('type' => 'error', 'error' => lang('Write what the meeting is about.'));
    }

    $result = ws_event_save($viewer, $data);

    if (!$result['ok']) {
        return array('type' => 'error', 'error' => $result['error']);
    }

    ws_message_system($channel['id'], lang(array(
        'string' => '{var:1} planned a meeting: {var:2}, {var:3} with {var:4}',
        'vars'   => array('<@user:' . (int) $viewer['id'] . '>', $data['title'], ws_command_event_span($data), implode(', ', array_map(function ($id) {
            return '<@user:' . (int) $id . '>';
        }, $data['people']))),
    )));

    return array('type' => 'done');
}

/**
 * When a plan item from /away or /meeting runs: its days, or its day and
 * hours.
 *
 * @param array $data ws_command_away_data() or ws_command_meeting_data()
 * @return string
 */
function ws_command_event_span($data)
{
    if ($data['kind'] === 'leave') {
        return ws_day_label($data['start']) . (($data['end'] !== $data['start']) ? ' – ' . ws_day_label($data['end']) : '');
    }

    return ws_day_label($data['start']) . ' ' . $data['start_time'] . '–' . $data['end_time']
        . (($data['end'] !== $data['start']) ? ' (' . ws_day_label($data['end']) . ')' : '');
}

/**
 * The live line under /away and /meeting: the plan item ws_event_save()
 * would write, with the refusals of ws_event_save_check().
 *
 * @param array $viewer
 * @param array $data ws_command_away_data() or ws_command_meeting_data()
 * @return array line, warnings
 */
function ws_command_event_suggest($viewer, $data)
{
    $out = array('line' => '', 'warnings' => array());

    if ($data['start'] === '') {
        return $out;
    }

    $data = ws_command_meeting_people($viewer, $data);
    $parts = array(ws_command_event_span($data));

    if ($data['title'] !== '') {
        $parts[] = '“' . $data['title'] . '”';
    }

    if ($data['kind'] === 'meeting') {
        $names = array();

        foreach (ws_people($data['people']) as $person) {
            $names[] = $person['name'];
        }

        $parts[] = implode(', ', $names);
    }

    $said = implode(' · ', array_filter($parts, 'strlen'));
    $out['line'] = ($data['kind'] === 'leave')
        ? lang(array('string' => 'Will write leave: {var:1}', 'vars' => $said))
        : lang(array('string' => 'Will plan: {var:1}', 'vars' => $said));

    if (($data['kind'] === 'meeting') && ($data['title'] === '')) {
        $out['warnings'][] = lang('Write what the meeting is about.');
    }

    $check = ws_event_save_check($viewer, $data);

    if (!$check['ok']) {
        $out['warnings'][] = $check['error'];
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

        $day_state = ws_plan_day_state($cell);
        $words = array('away' => lang('Away'), 'holiday' => lang('Holiday'), 'off' => lang('Not a working day'));
        $classes = array('away' => 'ws-load-away', 'holiday' => 'ws-load-away', 'off' => 'ws-load-away', 'over' => 'ws-load-over', 'full' => 'ws-load-full', 'ok' => 'ws-load-ok');
        $state = $words[$day_state] ?? lang(array('string' => '{var:1}%', 'vars' => $cell['load']));
        $class = $classes[$day_state];
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
 * The kind /create is asked for and the words after it: the first word
 * names the kind (ws_command_create_kinds()), the rest is that kind's line.
 *
 * @param string $text what follows /create
 * @return array kind (product | product_group, '' when the first word names
 *               none), rest
 */
function ws_command_parse_create($text)
{
    $parts = preg_split('/\s+/u', trim((string) $text), 2);
    $kinds = ws_command_create_kinds();
    $word = ws_command_fold($parts[0]);

    if (!isset($kinds[$word])) {
        return array('kind' => '', 'rest' => trim((string) $text));
    }

    return array('kind' => $kinds[$word], 'rest' => trim((string) ($parts[1] ?? '')));
}

/**
 * /create: the kind named after it, made through its own card - a product
 * as /product makes it, a group by ws_command_group(), a contact by
 * ws_command_contact(). A kind the person may not make is refused before
 * anything is read, as its screen refuses it.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $text    what follows the command
 * @param array  $options confirm, decision, on_sale
 * @return array
 */
function ws_command_create($viewer, $channel, $text, $options)
{
    $parsed = ws_command_parse_create($text);

    if (is_array($viewer) && ($parsed['kind'] !== '') && !in_array($parsed['kind'], ws_command_create_types_for($viewer), true)) {
        return array('type' => 'error', 'error' => lang('You may not create this kind of record.'));
    }

    if ($parsed['kind'] === 'contact') {
        return ws_command_contact($viewer, $channel, $parsed['rest'], $options);
    }

    if ($parsed['kind'] === 'wtable_row') {
        return ws_command_row($viewer, $channel, $parsed['rest'], $options);
    }

    if ($parsed['kind'] === 'product') {
        return ws_command_product($viewer, $channel, $parsed['rest'], $options);
    }

    if ($parsed['kind'] === 'product_group') {
        return ws_command_group($viewer, $channel, $parsed['rest'], $options);
    }

    return array('type' => 'error', 'error' => lang('Say what to create, a product, a group, a contact or a row, like /create product Blue mug 149,90 or /create row Leads.'));
}

/**
 * The workflow tables this person may write (ws_wtables_reached(), edit
 * access or more), the latest changed first.
 *
 * @param array $viewer
 * @return array[] id, name
 */
function ws_command_row_tables($viewer)
{
    if (!is_array($viewer) || !function_exists('ws_wtables_reached')) {
        return array();
    }

    $out = array();

    foreach (ws_wtables_reached($viewer) as $table) {
        if (ws_wtable_may((string) $table['access'], 'write')) {
            $out[] = array('id' => (int) $table['id'], 'name' => (string) $table['name']);
        }
    }

    return $out;
}

/**
 * The table the words after /create row name: the one whose whole name they
 * are (case and Turkish letters folded), else the one table whose name
 * starts with them. Several or none: 0, and the card asks. Pure.
 *
 * @param array[] $tables id, name (ws_command_row_tables())
 * @param string  $text
 * @return int table id, or 0
 */
function ws_command_row_table_match($tables, $text)
{
    $typed = ws_command_fold(trim((string) preg_replace('/\s+/u', ' ', (string) $text)));

    if ($typed === '') {
        return 0;
    }

    $whole = array();
    $starts = array();

    foreach ((array) $tables as $table) {
        $name = ws_command_fold(trim((string) preg_replace('/\s+/u', ' ', (string) $table['name'])));

        if ($name === $typed) {
            $whole[] = (int) $table['id'];
        } elseif (strpos($name, $typed) === 0) {
            $starts[] = (int) $table['id'];
        }
    }

    if (count($whole) === 1) {
        return $whole[0];
    }

    return (($whole === array()) && (count($starts) === 1)) ? $starts[0] : 0;
}

/**
 * A table's columns as the /create row card draws them: the ones a row
 * stores (a formula, a lookup or a rollup is worked out), with what each
 * offers - a status its choices, a person the team, a row link the rows of
 * a table the person reads (none: left out, as the table screen leaves it).
 *
 * @param array $viewer
 * @param int   $table_id
 * @return array[] id, name, type, choices ([key, label]), time, hint
 */
function ws_command_row_columns($viewer, $table_id)
{
    $out = array();
    $team = null;

    foreach (ws_wtable_columns((int) $table_id) as $id => $column) {
        if (!ws_wtable_type_stores($column['type'])) {
            continue;
        }

        $options = (array) $column['options'];
        $choices = array();
        $hint = '';

        if ($column['type'] === 'status') {
            foreach ((array) ($options['choices'] ?? array()) as $choice) {
                $choices[] = array((string) $choice['key'], (string) $choice['label']);
            }
        } elseif ($column['type'] === 'person') {
            $team = ($team === null) ? ws_team_members() : $team;

            foreach ($team as $person) {
                $choices[] = array((string) $person['id'], (string) $person['name']);
            }
        } elseif ($column['type'] === 'row') {
            $linked = ws_wtable_link_choices($viewer, $column);

            if ($linked === null) {
                continue;
            }

            foreach ($linked as $key => $label) {
                $choices[] = array((string) $key, (string) $label);
            }
        } elseif ($column['type'] === 'money') {
            $hint = (string) ($options['currency'] ?? '');
        } elseif ($column['type'] === 'link') {
            $hint = lang('kind:id, such as contact:12');
        } elseif ($column['type'] === 'list') {
            $hint = lang('One item a line.');
        }

        $out[] = array(
            'id'      => (int) $id,
            'name'    => (string) $column['name'],
            'type'    => (string) $column['type'],
            'choices' => $choices,
            'time'    => !empty($options['time']),
            'hint'    => $hint,
        );
    }

    return $out;
}

/**
 * A row refusal in words: each refused cell by its column, else the store's
 * refusal as the table screen words it.
 *
 * @param array $result  ws_wtable_row_create()
 * @param array $columns ws_wtable_columns()
 * @return string
 */
function ws_command_row_refusal($result, $columns)
{
    $lines = array();

    foreach ((array) ($result['errors'] ?? array()) as $id => $code) {
        $lines[] = (isset($columns[$id]) ? $columns[$id]['name'] . ': ' : '') . ws_wtable_screen_error_text((string) $code);
    }

    return ($lines !== array()) ? implode(' ', $lines) : ws_wtable_screen_refusal_text($result);
}

/**
 * /create row (/oluştur satır): a row of a workflow table the person may
 * write, the table named after the command (ws_command_row_table_match())
 * or picked on the card. The card shows the table's fields, empty; Create
 * sends what was filled in, read as the table screen reads its form
 * (ws_wtable_screen_cell_from_form()), and ws_wtable_row_create() writes it
 * with the table's rights, key, limits and events. Nothing is written
 * before Create.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $text    what follows /create row
 * @param array  $options confirm, table_id (the card's table), cells
 *                        (column id => as typed)
 * @return array
 */
function ws_command_row($viewer, $channel, $text, $options)
{
    $tables = ws_command_row_tables($viewer);

    if ($tables === array()) {
        return array('type' => 'error', 'error' => lang('You may not create this kind of record.'));
    }

    $ids = array_column($tables, 'id');
    $chosen = (int) ($options['table_id'] ?? 0);
    $table_id = ($chosen > 0) ? $chosen : ws_command_row_table_match($tables, $text);

    // A table outside the person's list is refused as one that is not there.
    if (($table_id > 0) && !in_array($table_id, $ids, true)) {
        return array('type' => 'error', 'error' => ws_wtable_screen_error_text('not_found'));
    }

    if (!empty($options['confirm'])) {
        $table = ($table_id > 0) ? ws_wtable($table_id) : null;

        if (!$table) {
            return array('type' => 'error', 'error' => lang('Choose the table.'));
        }

        $columns = ws_wtable_columns($table_id);
        $typed = is_array($options['cells'] ?? null) ? $options['cells'] : array();
        $cells = array();

        // What the card sent, column by column, read as the table screen
        // reads its form; a column it did not draw is not sent.
        foreach ($columns as $id => $column) {
            if (ws_wtable_type_stores($column['type']) && array_key_exists($id, $typed)) {
                $cells[$id] = ws_wtable_screen_cell_from_form($column, is_scalar($typed[$id]) ? (string) $typed[$id] : '');
            }
        }

        $result = ws_wtable_row_create($viewer, $table, array('cells' => $cells));

        if (empty($result['ok'])) {
            return array('type' => 'error', 'error' => ws_command_row_refusal($result, $columns));
        }

        return array(
            'type'    => 'done',
            'saved'   => true,
            'created' => true,
            'record'  => array('type' => 'wtable_row', 'id' => (int) $result['row_id'], 'name' => (string) $table['name'], 'table_id' => $table_id),
        );
    }

    return array(
        'type'    => 'create_preview',
        'preview' => array(
            'kind'     => 'wtable_row',
            'rows'     => array(),
            'on_sale'  => '',
            'missing'  => array(),
            'tables'   => $tables,
            'table_id' => $table_id,
            'columns'  => ($table_id > 0) ? ws_command_row_columns($viewer, $table_id) : array(),
        ),
        'values'  => array(),
        'labels'  => array(),
    );
}

/**
 * What follows /create contact, read into the contact's first fields: an
 * e-mail address anywhere, then the parts between commas - the person's
 * name (its first word the first name, the rest the last name), then the
 * company. "Ada Lovelace, Acme Ltd, ada@example.com" and
 * "ada@example.com Ada Lovelace, Acme Ltd" read alike. Pure.
 *
 * @param string $text
 * @return array first_name, last_name, company, email (each '' when not written)
 */
function ws_command_parse_contact($text)
{
    $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));
    $email = '';

    if (preg_match('/(?<![^\s,;])[^\s,;<>@]+@[^\s,;<>@]+\.[^\s,;<>@]+(?![^\s,;])/u', $text, $match)) {
        $email = $match[0];
        $text = str_replace($match[0], '', $text);
    }

    $parts = array_values(array_filter(array_map('trim', explode(',', $text)), 'strlen'));
    $name = preg_split('/ /u', (string) ($parts[0] ?? ''), 2);

    return array(
        'first_name' => trim((string) $name[0]),
        'last_name'  => trim((string) ($name[1] ?? '')),
        'company'    => trim(implode(', ', array_slice($parts, 1))),
        'email'      => $email,
    );
}

/**
 * /create contact: a new contact through the contacts catalogue
 * (ws_command_create_card()): its card shows the first name, last name,
 * company and e-mail it was given, and nothing is written before Create.
 * One of the four is enough, as on the contact screen; a line with none
 * answers with what is missing, and the composer opens the drawer's new
 * contact.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $text    what follows /create contact
 * @param array  $options confirm, decision
 * @return array
 */
function ws_command_contact($viewer, $channel, $text, $options)
{
    if (function_exists('ws_bulk_text_is_list') && ws_bulk_text_is_list($text)) {
        return array('type' => 'error', 'error' => lang('/create contact makes one contact: write it on one line.'));
    }

    // A tag would be written into a name as its markup.
    if (preg_match('/<[#@][a-z_]+:[0-9]+>/', (string) $text)) {
        return array('type' => 'error', 'error' => lang('Write the contact as plain text.'));
    }

    $fields = array_filter(ws_command_parse_contact($text), 'strlen');

    return ws_command_create_card($viewer, $channel, 'contact', $fields, $options);
}

/**
 * What a /create group card creates: the rest of the line as the group's
 * name and as its short description (the words the store shows).
 *
 * @param string $text what follows /create group
 * @return array field => value, as ws_quick_create_save() takes them
 */
function ws_command_group_fields($text)
{
    $name = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

    return array('name' => $name, 'short_description' => $name);
}

/**
 * /create group: a new product group, switched off, shown back and made on
 * confirmation as /product makes a product (ws_command_create_card()).
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $text    what follows /create group
 * @param array  $options confirm, decision, on_sale
 * @return array
 */
function ws_command_group($viewer, $channel, $text, $options)
{
    // One group a command: several lines are not read as several groups.
    if (function_exists('ws_bulk_text_is_list') && ws_bulk_text_is_list($text)) {
        return array('type' => 'error', 'error' => lang('/create group makes one group: write its name on one line.'));
    }

    $fields = ws_command_group_fields($text);

    if ($fields['name'] === '') {
        return array('type' => 'error', 'error' => lang('Write the group\'s name after the command, like /create group Mugs.'));
    }

    // A tag would be written into the name as its markup.
    if (preg_match('/<[#@][a-z_]+:[0-9]+>/', $fields['name'])) {
        return array('type' => 'error', 'error' => lang('Write the name as plain text.'));
    }

    return ws_command_create_card($viewer, $channel, 'product_group', $fields, $options);
}

/**
 * /product (and /create product): a new product, shown back with every
 * value it will be created with, made on confirmation
 * (ws_command_create_card()). Off sale unless the card's "On sale" is
 * ticked. Several lines make one bulk card of product drafts instead
 * (ws_command_product_bulk()), and so does nothing after the command in an
 * answer to a CSV or spreadsheet file (ws_command_product_file()).
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $text    what follows the command
 * @param array  $options confirm, decision, on_sale, typed (the whole text),
 *                        parent_id (the message the command answers)
 * @return array
 */
function ws_command_product($viewer, $channel, $text, $options)
{
    if (function_exists('ws_bulk_text_is_list') && ws_bulk_text_is_list($text)) {
        return ws_command_product_bulk($viewer, $channel, $text, $options);
    }

    // Nothing after the command in an answer: the answered message's file.
    if ((trim((string) $text) === '') && ((int) ($options['parent_id'] ?? 0) > 0)) {
        return ws_command_product_file($viewer, $channel, (int) $options['parent_id']);
    }

    $parsed = ws_command_parse_product($text);

    if ($parsed['name'] === '') {
        return array('type' => 'error', 'error' => lang('Write the product\'s name after the command, like /product Blue mug 149,90.'));
    }

    // Another tag left in the name (a second group, a product) would be
    // written into it as its markup.
    if (preg_match('/<[#@][a-z_]+:[0-9]+>/', $parsed['name'])) {
        return array('type' => 'error', 'error' => lang('Write the name as plain text: only one #group may follow it.'));
    }

    return ws_command_create_card($viewer, $channel, 'product', ws_command_product_fields($parsed), $options);
}

/**
 * /product (and /create product) with several lines: a product per line,
 * read as a list (ws_bulk_text_table(), ws_bulk_table_rows(): the name, then
 * its price; or a first line of column names), made into one bulk card of
 * drafts under the command's own message (ws_bulk_create_card()). The
 * person applies the card; nothing is written before. More rows than one
 * card takes are refused with the product import named.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $text    what follows the command
 * @param array  $options typed (the whole text, posted as the message)
 * @return array
 */
function ws_command_product_bulk($viewer, $channel, $text, $options)
{
    // Laid out before the database is asked anything.
    $laid = ws_bulk_table_rows(ws_bulk_text_table($text));

    if (!$laid['ok']) {
        return array('type' => 'error', 'error' => $laid['error']);
    }

    $made = ws_bulk_create_card($viewer, (int) $channel['id'], $laid, (string) ($options['typed'] ?? $text));

    if (!$made['ok']) {
        return array('type' => 'error', 'error' => $made['error']);
    }

    return array(
        'type'       => 'done',
        'message_id' => $made['message_id'],
        'bulk'       => array('count' => $made['count'], 'ignored' => $made['ignored']),
    );
}

/**
 * /product (and /create product) answering a CSV or spreadsheet file of the
 * channel, or sent with one: one bulk card of drafts from the file
 * (ws_bulk_create_propose()), under a message of the person's own naming
 * it. Anyone who may read the file, post here and make products may do it;
 * the drafts are theirs and every row is checked again when applied.
 *
 * @param array $viewer
 * @param array $channel
 * @param int   $message_id the message carrying the file
 * @return array
 */
function ws_command_product_file($viewer, $channel, $message_id)
{
    $message = ws_message((int) $message_id);

    // A file of this channel only: the card names it here.
    if (!$message || ((int) $message['channel_id'] !== (int) $channel['id'])) {
        return array('type' => 'error', 'error' => lang('That message could not be found.'));
    }

    $made = ws_bulk_create_propose($viewer, (int) $channel['id'], array('message_id' => (int) $message_id));

    if (!$made['ok']) {
        return array('type' => 'error', 'error' => $made['error']);
    }

    return array(
        'type'       => 'done',
        'message_id' => $made['message_id'],
        'bulk'       => array('count' => $made['count'], 'ignored' => $made['ignored']),
    );
}

/**
 * Is a text /product (or /create product) with nothing after it, the
 * command that, sent with a file, makes product drafts from that file?
 *
 * @param string $body
 * @return bool
 */
function ws_command_is_product_file($body)
{
    $split = ws_command_split($body);

    if ($split === null) {
        return false;
    }

    if ($split['command'] === 'product') {
        return $split['rest'] === '';
    }

    if ($split['command'] !== 'create') {
        return false;
    }

    $parsed = ws_command_parse_create($split['rest']);

    return ($parsed['kind'] === 'product') && ($parsed['rest'] === '');
}

/**
 * The card a create command answers with, or the record it makes once
 * confirmed, through the drawer's create (ws_quick_create_save()): the same
 * rights, checks, writer, ws_ai_changes row, activity log line and decision
 * in the channel. Off sale (a group switched off) unless the card's tick
 * says otherwise.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $type    product | product_group | contact
 * @param array  $fields  field => value
 * @param array  $options confirm, decision, on_sale
 * @return array
 */
function ws_command_create_card($viewer, $channel, $type, $fields, $options)
{
    $error = function ($text) {
        return array('type' => 'error', 'error' => $text);
    };

    if (!function_exists('ws_quick_create_input')) {
        return $error(lang('Invalid request.'));
    }

    $switch = ws_quick_create_has_switch($type);

    if (!empty($options['confirm'])) {
        if ($switch) {
            $fields['enabled'] = !empty($options['on_sale']);
        }

        $saved = ws_quick_create_save($viewer, $type, $fields, (int) $channel['id'], !empty($options['decision']));

        if (!$saved['ok']) {
            return $error($saved['error']);
        }

        return array(
            'type'       => 'done',
            'saved'      => true,
            'created'    => true,
            'lines'      => $saved['lines'],
            'message_id' => $saved['decision_id'],
            'record'     => array('type' => $type, 'id' => $saved['id'], 'name' => $saved['name']),
        );
    }

    $types = ws_change_types();

    // None of the kind's required list written: nothing to check yet. The
    // composer opens the drawer's new mode with what was typed, as the
    // kind's button does.
    $missing = ws_quick_create_missing((array) ($types[$type]['required'] ?? array()), ws_quick_create_change($type, $fields)['fields']);

    if ($missing !== array()) {
        $check = ws_quick_create_check($viewer, $type);

        if (!$check['ok']) {
            return $error($check['error']);
        }

        $names = array();

        foreach ($missing as $name) {
            $field = ws_change_field($types[$type], $name);
            $names[] = ($field !== null) ? (string) $field[2] : $name;
        }

        return array(
            'type'    => 'create_preview',
            'preview' => array('kind' => $type, 'rows' => array(), 'on_sale' => '', 'missing' => $names),
            'values'  => $fields,
            'labels'  => array(),
        );
    }

    // Checked as the save will check it (a name already taken, a group
    // that is gone), so the card never offers what cannot be made.
    $input = ws_quick_create_input($viewer, $type, $fields, (int) $channel['id']);

    if (!$input['ok']) {
        return $error($input['error']);
    }

    $rows = array();
    $labels = array();

    foreach ($input['row']['fields'] as $item) {
        $field = ws_change_field($types[$type], (string) $item['name']);

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

        $rows[] = array('label' => (string) $field[2], 'text' => (string) ws_change_show($type, $item['name'], $item['to'], 120), 'html' => '');
    }

    return array(
        'type'    => 'create_preview',
        'preview' => array(
            'kind'    => $type,
            'rows'    => $rows,
            // A kind with no on-sale switch (a contact) draws no tick.
            'on_sale' => $switch ? (string) $types[$type]['fields']['enabled'][2] : '',
            'missing' => array(),
        ),
        // The drawer's new mode, filled with the same values ("All fields"),
        // and the names of the records they pick.
        'values'  => $fields,
        'labels'  => $labels,
    );
}
