<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the quick setup of a flow: a flow built by choices instead of
 * boxes on the canvas. The person picks what starts it among the areas of
 * the site, may narrow the start ("Only when"), then picks the steps one
 * after the other, from a ready recipe or one by one, and the choices are
 * turned into a normal draft through the store (ws_flow_save()), with the
 * same graph check and problems as a flow drawn on the canvas. Nothing at
 * run time knows a flow came from here; it is edited on the canvas after.
 *
 * Everything offered is a box or a start event that exists: an area groups
 * the event start's catalogue (ws_events_catalog()) and the start boxes of
 * their own, a step group lists kinds of ws_flow_kinds(), and a kind or an
 * event the site does not have is not offered. A recipe is data: steps in
 * the shape the dialog sends, never code.
 *
 * The choices the dialog sends:
 *   start      the key of a start of the catalogue: event:<event> or the
 *              kind of a start box (schedule, manual, join, row, mention,
 *              no_order, web)
 *   config     the start box's settings (an event start keeps its event)
 *   only_when  up to WS_FLOW_QUICK_ONLY_WHEN_MAX conditions, each field (a
 *              key of the start's fields: trigger.<key> or record.<key>),
 *              op and value as a condition box's value rule keeps them
 *   steps      the steps in order, each kind, config, an optional id and,
 *              for a condition or a For each box, steps of its own
 *   name       the flow's name ('' takes the start's)
 *   channel_id the home channel
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most boxes a quick setup places, its own boxes included. */
define('WS_FLOW_QUICK_BOXES_MAX', 60);

/** The most "Only when" conditions on a start. */
define('WS_FLOW_QUICK_ONLY_WHEN_MAX', 5);

/** How deep steps may hold steps: a step in a condition in a loop is 3. */
define('WS_FLOW_QUICK_DEPTH_MAX', 3);

/**
 * The areas of the site the dialog's rail lists, in order, each with the
 * starts it holds: event:<event> for an event of the event start's
 * catalogue, else the kind of a start box. Every start sits in one area of
 * the site's part; Often used repeats the most common ones. part says where
 * the rail draws its line: top, site, other. Pure.
 *
 * @return array key => label, icon, part, starts
 */
function ws_flow_quick_areas()
{
    $area = function ($label, $icon, $part, $starts) {
        return array('label' => $label, 'icon' => $icon, 'part' => $part, 'starts' => $starts);
    };

    return array(
        'popular'       => $area(lang('Often used'), 'bi-star', 'top', array('event:order.created', 'event:form.submitted', 'event:customer.created',
            'event:booking.created', 'schedule', 'manual')),
        'orders'        => $area(lang('Orders'), 'bi-bag', 'site', array('event:order.created', 'event:order.status_changed', 'event:order.shipped',
            'event:order.delivered', 'event:order.cancelled', 'event:order.return.requested', 'event:order.return.approved', 'event:order.return.received',
            'event:order.return.refunded', 'event:marketplace.order.imported', 'no_order')),
        'carts'         => $area(lang('Cart and quotes'), 'bi-cart3', 'site', array('event:cart_quote.created', 'event:cart_quote.ordered',
            'event:erp.quote.accepted', 'event:erp.quote.rejected')),
        'forms'         => $area(lang('Forms'), 'bi-ui-checks', 'site', array('event:form.submitted', 'event:form.paid')),
        'customers'     => $area(lang('Customers'), 'bi-people', 'site', array('event:customer.created', 'event:customer.updated',
            'event:contact_group.joined', 'event:customer.consent_changed', 'event:user.created', 'event:credit.issued', 'event:credit.expired')),
        'bookings'      => $area(lang('Reservations'), 'bi-calendar-event', 'site', array('event:booking.created', 'event:booking.confirmed',
            'event:booking.rescheduled', 'event:booking.cancelled', 'event:calendar_event.reserved')),
        'products'      => $area(lang('Products and stock'), 'bi-box-seam', 'site', array('event:stock.low', 'event:inventory.changed')),
        'reviews'       => $area(lang('Reviews'), 'bi-star-half', 'site', array('event:product.review.created')),
        'subscriptions' => $area(lang('Subscriptions'), 'bi-arrow-repeat', 'site', array('event:subscription.renewed', 'event:subscription.failed',
            'event:subscription.cancelled')),
        'invoices'      => $area(lang('Invoices'), 'bi-receipt', 'site', array('event:erp.invoice.created', 'event:erp.invoice.paid',
            'event:erp.invoice.overdue', 'event:erp.invoice.edoc_changed', 'event:erp.receipt.created', 'event:erp.edoc.received')),
        'content'       => $area(lang('Content and campaigns'), 'bi-megaphone', 'site', array('event:page.created', 'event:page.updated',
            'event:campaign.sent', 'event:mail.bounced')),
        'workspace'     => $area(lang('Workspace'), 'bi-chat-square-text', 'site', array('event:workspace.task.completed',
            'event:workspace.task.status_changed', 'event:workspace.task.overdue', 'event:workspace.approval.decided', 'event:workspace.guest.message',
            'event:workspace.mail.received', 'event:workspace.flow.run.failed', 'join', 'mention', 'row')),
        'time'          => $area(lang('By time'), 'bi-clock', 'other', array('schedule')),
        'hand'          => $area(lang('By hand'), 'bi-hand-index', 'other', array('manual', 'web')),
    );
}

/**
 * The groups of steps the dialog lists, in order, each with its kinds of
 * box in the order shown. A new kind of step box gets its place here
 * (tests/workspace_flow_quick_test.php fails until it has one or is in
 * ws_flow_quick_kinds_left_out()). Pure.
 *
 * @return array key => label, kinds
 */
function ws_flow_quick_groups()
{
    return array(
        'message' => array('label' => lang('Message'), 'kinds' => array('email', 'notify', 'post', 'report')),
        'people'  => array('label' => lang('People'), 'kinds' => array('task_open', 'task', 'approval', 'lead', 'checkin', 'task_digest')),
        'records' => array('label' => lang('Records'), 'kinds' => array('change', 'record_get', 'row_add', 'row_set', 'row_get', 'rows_get')),
        'logic'   => array('label' => lang('Logic'), 'kinds' => array('delay', 'condition', 'for_each')),
        'system'  => array('label' => lang('System'), 'kinds' => array('ai', 'agent', 'webhook', 'web_check', 'start_flow', 'mcp')),
    );
}

/**
 * The kinds of box that are never a step of their own here: the End for
 * each box, placed after a loop's steps; End, since a chain ends where its
 * steps end; the value boxes, which no arrow reaches. Pure.
 *
 * @return string[]
 */
function ws_flow_quick_kinds_left_out()
{
    return array('for_each_end', 'end', 'constants', 'value');
}

/**
 * The record a start's run is about and how the box that reads it is set:
 * an event's record (ws_events_catalog()) read by its id, a customer who
 * stopped ordering by theirs; null when Read a record does not read it.
 * Pure.
 *
 * @param string $kind   the start box's kind
 * @param string $record the event's record kind
 * @return array|null record, id
 */
function ws_flow_quick_read($kind, $record)
{
    $readable = function_exists('ws_flow_record_get_fields') ? ws_flow_record_get_fields() : array();

    if ($kind === 'no_order') {
        return isset($readable['contact']) ? array('record' => 'contact', 'id' => '{{trigger.contact_id}}') : null;
    }

    return (($kind === 'event') && isset($readable[$record])) ? array('record' => $record, 'id' => '{{trigger.record_id}}') : null;
}

/**
 * One start as the dialog shows it: key, kind, event, label (the event's
 * "when" sentence or the start box's label), name (the event's short
 * label), record (the event's record kind, '' for none), read
 * (ws_flow_quick_read()) and the values "Only when" may look at, each key
 * trigger.<key> or record.<key>, type, label. A row or web start's values
 * come from the table or the fields set on it, so it offers none here.
 * Pure.
 *
 * @param string $key    event:<event> or a start box's kind
 * @param array  $events event => label, when, record: those the person may wait for
 * @param array  $kinds  ws_flow_kinds()
 * @return array|null null when the start is not there for the person
 */
function ws_flow_quick_start($key, $events, $kinds)
{
    $event = (strpos($key, 'event:') === 0) ? substr($key, 6) : '';
    $kind = ($event !== '') ? 'event' : $key;

    if (empty($kinds[$kind]['trigger']) || (($event !== '') && !is_array($events[$event] ?? null)) || ($kind === 'event' && $event === '')) {
        return null;
    }

    $info = ($event !== '') ? $events[$event] : array('label' => $kinds[$kind]['label'], 'when' => $kinds[$kind]['label'], 'record' => '');
    $record = (string) ($info['record'] ?? '');
    $read = ws_flow_quick_read($kind, $record);
    $fields = array();
    $outputs = (in_array($kind, array('row', 'web'), true) || !function_exists('ws_flow_trigger_outputs')) ? array() : ws_flow_trigger_outputs($kind, array('event' => $event));

    foreach ($outputs as $output) {
        if ($output['key'] !== 'event') {
            $fields[] = array('key' => 'trigger.' . $output['key'], 'type' => $output['type'], 'label' => $output['label']);
        }
    }

    foreach (($read && function_exists('ws_flow_record_get_outputs')) ? ws_flow_record_get_outputs($read['record']) : array() as $output) {
        $fields[] = array('key' => 'record.' . $output['key'], 'type' => $output['type'], 'label' => $output['label']);
    }

    return array(
        'key'    => $key,
        'kind'   => $kind,
        'event'  => $event,
        'label'  => (string) ($info['when'] ?? $info['label']),
        'name'   => (string) $info['label'],
        'record' => $record,
        'read'   => $read,
        'fields' => $fields,
    );
}

/**
 * The ready recipes: steps that fit a start, in the shape the dialog sends
 * them, which the person changes like steps chosen one by one. fits lists
 * the starts' keys a recipe fits, or record for every start whose run is
 * about a record (its texts name it through {{record_title}}). Only boxes
 * that serve every scenario; a recipe the person cannot build (a kind not
 * open to them) is left out by ws_flow_quick_catalogue(). Pure.
 *
 * @param array $fill me (the person's id: the recipes' tasks and notices
 *                    are theirs until changed)
 * @return array[] key, title, icon, fits, steps
 */
function ws_flow_quick_recipes($fill)
{
    $me = (int) ($fill['me'] ?? 0);
    $mail = function ($subject, $body, $purpose = 'info') {
        return array('kind' => 'email', 'config' => array('to' => '', 'recipient' => 'record_person', 'purpose' => $purpose, 'page_id' => 0,
            'subject' => $subject, 'body' => $body));
    };
    $task = function ($title, $due_in, $priority) use ($me) {
        return array('kind' => 'task_open', 'config' => array('title' => $title, 'description' => '{{record}}' . "\n" . '{{record_link}}',
            'assignees' => array($me), 'due_in' => $due_in, 'priority' => $priority));
    };
    $tell = function ($text) use ($me) {
        return array('kind' => 'notify', 'config' => array('user_ids' => array($me), 'text' => $text));
    };
    $wait = function ($days) {
        return array('kind' => 'delay', 'config' => array('mode' => 'for', 'amount' => $days, 'unit' => 'days'));
    };
    $recipe = function ($key, $title, $icon, $fits, $steps) {
        return array('key' => $key, 'title' => $title, 'icon' => $icon, 'fits' => $fits, 'steps' => $steps);
    };

    return array(
        // Asking for a review is commercial mail: it goes only to a customer
        // who agreed to it (ws_flow_mail_marketing_refusal()).
        $recipe('thank_then_review', lang('Thank, then ask for a review'), 'bi-envelope-heart', array('event:order.created'), array(
            $mail(lang('Thank you for your order'), lang('Hello {{customer}},') . "\n\n" . lang('Thank you for your order {{record_title}}. We will let you know when it is on its way.')),
            $wait(7),
            $mail(lang('How was your order?'), lang('Hello {{customer}},') . "\n\n" . lang('We hope you are happy with your order {{record_title}}. We would be glad if you told others about it in a review.'), 'marketing'),
        )),
        $recipe('task_and_tell', lang('Open a task, let me know'), 'bi-check2-square', array('record'), array(
            $task(lang('Handle {{record_title}}'), 1, 'normal'),
            $tell('{{event}}: {{record}}'),
        )),
        // The approval needs a channel to be asked in; the dialog asks it
        // with the step's own settings.
        $recipe('approve_first', lang('Ask for approval, then open a task'), 'bi-person-check', array('record'), array(
            array('kind' => 'approval', 'config' => array('channel_id' => 0, 'title' => lang('Go ahead with {{record_title}}?'), 'text' => '{{record}}',
                'approvers' => array($me), 'rule' => 'any', 'closes_in' => 48)),
            $task(lang('Go ahead with {{record_title}}'), 1, 'high'),
        )),
        $recipe('form_reply', lang('Confirm to the sender, open a task'), 'bi-ui-checks', array('event:form.submitted'), array(
            $mail(lang('We received your message'), lang('Hello,') . "\n\n" . lang('Thank you for writing to us. We received your message and will answer soon.')),
            $task(lang('Answer {{record_title}}'), 1, 'normal'),
        )),
        $recipe('booking_tell', lang('Confirm to the guest, let me know'), 'bi-calendar-check', array('event:booking.created', 'event:booking.confirmed'), array(
            $mail(lang('Your booking'), lang('Hello,') . "\n\n" . lang('Thank you for your booking {{record_title}}. We look forward to seeing you.')),
            $tell(lang('New booking: {{record}}')),
        )),
        $recipe('welcome_contact', lang('Welcome, then get in touch'), 'bi-person-plus', array('event:customer.created'), array(
            $mail(lang('Welcome'), lang('Hello {{customer}},') . "\n\n" . lang('Thank you for your interest. We are glad to have you with us.')),
            $wait(3),
            $task(lang('Get in touch with {{record_title}}'), 2, 'normal'),
        )),
        $recipe('restock', lang('Restock, let me know'), 'bi-box-seam', array('event:stock.low'), array(
            $task(lang('Restock {{record_title}}'), 2, 'high'),
            $tell(lang('Running low: {{record}}')),
        )),
        $recipe('win_back', lang('Ask the customer back'), 'bi-person-heart', array('no_order'), array(
            $mail(lang('We miss you'), lang('Hello {{trigger.name}},') . "\n\n" . lang('It has been a while since your last order. Have a look at what is new with us.'), 'marketing'),
            $tell(lang('{{trigger.name}} was asked back after {{trigger.days_since}} days without an order.')),
        )),
    );
}

/**
 * What the quick setup offers a person (pure): the areas with the starts
 * they may use, each start (ws_flow_quick_start()), the step groups with
 * the kinds they may add, the recipes made only of those kinds, and the
 * limits. The dialog also leaves out what the canvas palette leaves out
 * (a box with nothing to work on, such as an MCP box with no server).
 *
 * @param array $fill me, events (event => label, when, record: those the
 *                    person may wait for), starts (the kinds of start box
 *                    offered beside the event start), kinds (the kinds of
 *                    box the person may add, null for every kind)
 * @return array areas, starts, groups, recipes, limits
 */
function ws_flow_quick_catalogue($fill)
{
    $kinds = ws_flow_kinds();
    $allowed = is_array($fill['kinds'] ?? null) ? $fill['kinds'] : null;
    $may = function ($kind) use ($kinds, $allowed) {
        return isset($kinds[$kind]) && !in_array($kind, ws_flow_kinds_not_offered(), true) && (($allowed === null) || in_array($kind, $allowed, true));
    };
    $start_kinds = array_merge(array('event'), (array) ($fill['starts'] ?? array()));
    $events = (array) ($fill['events'] ?? array());
    $areas = array();
    $starts = array();

    foreach (ws_flow_quick_areas() as $key => $area) {
        $keys = array();

        foreach ($area['starts'] as $start_key) {
            $kind = (strpos($start_key, 'event:') === 0) ? 'event' : $start_key;

            if (!isset($starts[$start_key]) && $may($kind) && in_array($kind, $start_kinds, true)) {
                $start = ws_flow_quick_start($start_key, $events, $kinds);

                if ($start !== null) {
                    $starts[$start_key] = $start;
                }
            }

            if (isset($starts[$start_key])) {
                $keys[] = $start_key;
            }
        }

        if (!empty($keys)) {
            $areas[] = array('key' => $key, 'label' => $area['label'], 'icon' => $area['icon'], 'part' => $area['part'], 'starts' => $keys);
        }
    }

    $groups = array();
    $steps = array();

    foreach (ws_flow_quick_groups() as $key => $group) {
        $list = array_values(array_filter($group['kinds'], $may));

        if (!empty($list)) {
            $groups[] = array('key' => $key, 'label' => $group['label'], 'kinds' => $list);
            $steps = array_merge($steps, $list);
        }
    }

    $recipes = array();

    foreach (ws_flow_quick_recipes($fill) as $recipe) {
        $used = array();

        array_walk_recursive($recipe['steps'], function ($value, $key) use (&$used) {
            if ($key === 'kind') {
                $used[] = $value;
            }
        });

        if (empty(array_diff($used, $steps))) {
            $recipes[] = $recipe;
        }
    }

    return array(
        'areas'   => $areas,
        'starts'  => $starts,
        'groups'  => $groups,
        'recipes' => $recipes,
        'limits'  => array('boxes' => WS_FLOW_QUICK_BOXES_MAX, 'only_when' => WS_FLOW_QUICK_ONLY_WHEN_MAX, 'depth' => WS_FLOW_QUICK_DEPTH_MAX),
    );
}

/**
 * The recipes that fit a start of the catalogue: those that name it, and
 * those for any start about a record when its run is about one. Pure.
 *
 * @param array  $catalogue ws_flow_quick_catalogue()
 * @param string $key       the start's key
 * @return array[]
 */
function ws_flow_quick_recipes_for($catalogue, $key)
{
    $start = $catalogue['starts'][(string) $key] ?? null;

    if (!is_array($start)) {
        return array();
    }

    return array_values(array_filter($catalogue['recipes'], function ($recipe) use ($start) {
        return in_array($start['key'], $recipe['fits'], true) || (in_array('record', $recipe['fits'], true) && ($start['record'] !== ''));
    }));
}

/**
 * The steps the dialog sent, checked and in order (pure): each a kind of
 * the catalogue's groups, its settings, its id (the one sent when it is a
 * box id nothing else holds, else '' for one to be given) and the steps it
 * holds, which only a condition (on yes) or a For each box (its loop) may.
 *
 * @param mixed    $steps
 * @param string[] $kinds the kinds the catalogue offers as steps
 * @param int      $depth how deep these steps are, 1 at the top
 * @param bool     $loop  inside a For each box
 * @param array    $seen  ids taken so far, id => true
 * @param int      $count boxes placed so far
 * @return array ok, error, steps
 */
function ws_flow_quick_steps_read($steps, $kinds, $depth, $loop, &$seen, &$count)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'steps' => array());
    };

    if (!is_array($steps)) {
        return $fail(lang('The steps could not be read.'));
    }

    if ($depth > WS_FLOW_QUICK_DEPTH_MAX) {
        return $fail(lang(array('string' => 'Steps may be held at most {var:1} deep.', 'vars' => WS_FLOW_QUICK_DEPTH_MAX)));
    }

    $out = array();

    foreach (array_values($steps) as $step) {
        $kind = is_array($step) && is_string($step['kind'] ?? null) ? $step['kind'] : '';

        if (!in_array($kind, $kinds, true)) {
            return $fail(lang('That step is not offered to you.'));
        }

        $inner = $step['steps'] ?? array();
        $holds = in_array($kind, array('condition', 'for_each'), true);

        if (!$holds && !empty($inner)) {
            return $fail(lang('Only a condition or a loop holds steps of its own.'));
        }

        if (($kind === 'for_each') && $loop) {
            return $fail(lang('A loop cannot hold another loop.'));
        }

        // A loop's End for each box is a box too.
        $count += ($kind === 'for_each') ? 2 : 1;

        if ($count > WS_FLOW_QUICK_BOXES_MAX) {
            return $fail(lang(array('string' => 'A quick setup places at most {var:1} boxes; add more on the canvas.', 'vars' => WS_FLOW_QUICK_BOXES_MAX)));
        }

        $id = is_string($step['id'] ?? null) ? $step['id'] : '';
        $id = (ws_flow_node_id_valid($id) && !isset($seen[$id])) ? $id : '';

        if ($id !== '') {
            $seen[$id] = true;
        }

        $read = array('ok' => true, 'steps' => array());

        if ($holds) {
            $read = ws_flow_quick_steps_read($inner, $kinds, $depth + 1, $loop || ($kind === 'for_each'), $seen, $count);

            if (!$read['ok']) {
                return $read;
            }

            if (($kind === 'for_each') && empty($read['steps'])) {
                return $fail(lang('A loop needs at least one step inside it.'));
            }
        }

        $out[] = array('id' => $id, 'kind' => $kind, 'config' => is_array($step['config'] ?? null) ? $step['config'] : array(), 'steps' => $read['steps']);
    }

    return array('ok' => true, 'error' => '', 'steps' => $out);
}

/**
 * The arrow a box leaves on to the step after it in a chain: its first
 * outcome (a task done, an approval approved, a wait over), or any
 * outcome for a Pinegrap AI box that sorts, none of whose classes is the
 * one to go on from. Pure.
 *
 * @param array $kinds ws_flow_kinds()
 * @param array $node  id, kind, config
 * @return string
 */
function ws_flow_quick_on($kinds, $node)
{
    $outcomes = ws_flow_node_arrow_outcomes($kinds[$node['kind']], array('kind' => $node['kind'], 'config' => ws_flow_config_normalize($node['kind'], $node['config'])));
    $first = (string) ($outcomes[0] ?? 'always');

    return (strpos($first, 'class_') === 0) ? 'always' : $first;
}

/**
 * Lays a chain of steps on the graph (pure): each step a box in the next
 * column, a held step a row lower per depth; every arrow of $into leads
 * into the first step and each step into the next. A condition's steps
 * follow it on yes and what comes after it follows its steps or its no; a
 * condition with no steps lets what comes after it follow on yes only. A
 * For each box's steps follow it on each and end in its End for each box.
 *
 * @param array    $steps ws_flow_quick_steps_read() steps, ids given
 * @param array[]  $into  arrows waiting for the first step: [from, on]
 * @param int      $depth
 * @param array    $build nodes, edges, column, fresh (callable giving a free id)
 * @param array    $kinds ws_flow_kinds()
 * @return array[] the arrows waiting for what comes after the chain
 */
function ws_flow_quick_chain($steps, $into, $depth, &$build, $kinds)
{
    foreach ($steps as $step) {
        $build['nodes'][] = array('id' => $step['id'], 'kind' => $step['kind'], 'label' => '', 'x' => $build['column'] * 256, 'y' => ($depth - 1) * 112, 'config' => $step['config']);
        $build['column']++;

        foreach ($into as $arrow) {
            $build['edges'][] = array('from' => $arrow[0], 'to' => $step['id'], 'on' => $arrow[1]);
        }

        if ($step['kind'] === 'condition') {
            $into = empty($step['steps'])
                ? array(array($step['id'], 'yes'))
                : array_merge(ws_flow_quick_chain($step['steps'], array(array($step['id'], 'yes')), $depth + 1, $build, $kinds), array(array($step['id'], 'no')));
        } elseif ($step['kind'] === 'for_each') {
            $inside = ws_flow_quick_chain($step['steps'], array(array($step['id'], 'each')), $depth + 1, $build, $kinds);
            $end = call_user_func($build['fresh'], 'loop_end');
            $build['nodes'][] = array('id' => $end, 'kind' => 'for_each_end', 'label' => '', 'x' => $build['column'] * 256, 'y' => ($depth - 1) * 112,
                'config' => array('for_each' => $step['id'], 'collect' => ''));
            $build['column']++;

            foreach ($inside as $arrow) {
                $build['edges'][] = array('from' => $arrow[0], 'to' => $end, 'on' => $arrow[1]);
            }

            $into = array(array($end, 'done'));
        } else {
            $into = array(array($step['id'], ws_flow_quick_on($kinds, $step)));
        }
    }

    return $into;
}

/**
 * Gives every step without an id a free one, step1, step2 and on, in
 * order, held steps after their box. Pure.
 *
 * @param array    $steps ws_flow_quick_steps_read() steps
 * @param callable $fresh gives a free id for a stem
 * @return array
 */
function ws_flow_quick_ids($steps, $fresh)
{
    foreach ($steps as $n => $step) {
        $steps[$n]['id'] = ($step['id'] !== '') ? $step['id'] : call_user_func($fresh, 'step');
        $steps[$n]['steps'] = ws_flow_quick_ids($step['steps'], $fresh);
    }

    return $steps;
}

/**
 * The choices as a draft graph (pure): the start box, a Read a record box
 * when a condition or a step reads the start's record ({{node.read.…}}),
 * one condition box per "Only when" condition on yes, then the steps
 * (ws_flow_quick_chain()). What is wrong with a choice is refused here;
 * what a box still lacks is a problem of the draft, as on the canvas.
 *
 * @param array $catalogue ws_flow_quick_catalogue()
 * @param array $choices   see the file's docblock
 * @return array ok, error, field, name, graph
 */
function ws_flow_quick_graph($catalogue, $choices)
{
    $choices = is_array($choices) ? $choices : array();
    $fail = function ($error, $field) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'name' => '', 'graph' => null);
    };
    $start = $catalogue['starts'][is_string($choices['start'] ?? null) ? $choices['start'] : ''] ?? null;

    if (!is_array($start)) {
        return $fail(lang('Choose what starts the flow.'), 'start');
    }

    $kinds = ws_flow_kinds();
    $offered = array();

    foreach ($catalogue['groups'] as $group) {
        $offered = array_merge($offered, $group['kinds']);
    }

    $seen = array('start' => true, 'read' => true);
    $count = 1;
    $steps = ws_flow_quick_steps_read($choices['steps'] ?? null, $offered, 1, false, $seen, $count);

    if (!$steps['ok']) {
        return $fail($steps['error'], 'steps');
    }

    if (empty($steps['steps'])) {
        return $fail(lang('Choose at least one step.'), 'steps');
    }

    $fields = array();

    foreach ($start['fields'] as $field) {
        $fields[$field['key']] = $field;
    }

    $when = is_array($choices['only_when'] ?? null) ? array_values($choices['only_when']) : array();
    $rules = array();
    $read = (strpos((string) json_encode($choices['steps']), '{{node.read.') !== false);

    if (count($when) > WS_FLOW_QUICK_ONLY_WHEN_MAX) {
        return $fail(lang(array('string' => 'A start may be narrowed by at most {var:1} conditions.', 'vars' => WS_FLOW_QUICK_ONLY_WHEN_MAX)), 'only_when');
    }

    foreach ($when as $row) {
        $key = is_array($row) && is_string($row['field'] ?? null) ? $row['field'] : '';

        if (!isset($fields[$key])) {
            return $fail(lang('Choose a value of the start for each condition.'), 'only_when');
        }

        list($source, $name) = explode('.', $key, 2);
        $read = $read || ($source === 'record');
        $rules[] = array('type' => 'value', 'ref' => ($source === 'record') ? '{{node.read.' . $name . '}}' : '{{trigger.' . $name . '}}',
            'op' => $row['op'] ?? 'eq', 'value' => $row['value'] ?? '');
    }

    if ($read && !is_array($start['read'])) {
        return $fail(lang('The start is not about a record a flow can read.'), 'only_when');
    }

    if ($count + count($rules) + ($read ? 1 : 0) > WS_FLOW_QUICK_BOXES_MAX) {
        return $fail(lang(array('string' => 'A quick setup places at most {var:1} boxes; add more on the canvas.', 'vars' => WS_FLOW_QUICK_BOXES_MAX)), 'steps');
    }

    $fresh = function ($stem) use (&$seen) {
        for ($n = 1; isset($seen[$stem . $n]); $n++) {
        }

        $seen[$stem . $n] = true;

        return $stem . $n;
    };

    $config = is_array($choices['config'] ?? null) ? $choices['config'] : array();

    if ($start['kind'] === 'event') {
        $config['event'] = $start['event'];
    }

    $build = array('nodes' => array(array('id' => 'start', 'kind' => $start['kind'], 'label' => '', 'x' => 0, 'y' => 0, 'config' => $config)),
        'edges' => array(), 'column' => 1, 'fresh' => $fresh);
    $into = array(array('start', 'next'));
    $before = array();

    if ($read) {
        $before[] = array('id' => 'read', 'kind' => 'record_get', 'config' => $start['read'], 'steps' => array());
    }

    foreach ($rules as $rule) {
        $before[] = array('id' => $fresh('only_when'), 'kind' => 'condition', 'config' => array('rule' => $rule), 'steps' => array());
    }

    $into = ws_flow_quick_chain($before, $into, 1, $build, $kinds);
    ws_flow_quick_chain(ws_flow_quick_ids($steps['steps'], $fresh), $into, 1, $build, $kinds);

    $name = ws_flow_line($choices['name'] ?? '', 160);

    return array(
        'ok'    => true,
        'error' => '',
        'field' => '',
        'name'  => ($name !== '') ? $name : ws_flow_line($start['label'], 160),
        'graph' => ws_flow_graph_normalize(array('v' => 1, 'nodes' => $build['nodes'], 'edges' => $build['edges'], 'notes' => array())),
    );
}

/**
 * What ws_flow_quick_catalogue() is filled with for a person: the events
 * they may wait for, the start boxes open to them and, for a role-3
 * member, the kinds of box their own flows hold (ws_flow_member_kinds()).
 * null for whoever does not build flows.
 *
 * @param array      $viewer
 * @param array|null $scheduled ws_scheduled_js_config($viewer, true), read when null
 * @param array|null $lists     tables (a row start has a table to start on), no_order (the start is open to them); read when null
 * @return array|null
 */
function ws_flow_quick_fill($viewer, $scheduled = null, $lists = null)
{
    if (!ws_flows_ready() || !ws_flow_builder($viewer)) {
        return null;
    }

    if ($scheduled === null) {
        $scheduled = function_exists('ws_scheduled_js_config') ? ws_scheduled_js_config($viewer, true) : null;
    }

    if (!is_array($scheduled)) {
        return null;
    }

    if ($lists === null) {
        $lists = array(
            'tables'   => function_exists('ws_flow_row_tables') && !empty(ws_flow_row_tables($viewer)),
            'no_order' => function_exists('ws_flow_no_order_refusal') && (ws_flow_no_order_refusal($viewer) === ''),
        );
    }

    $catalog = function_exists('ws_events_catalog') ? ws_events_catalog() : array();
    $events = array();

    foreach (array_keys((array) ($scheduled['events']['list'] ?? array())) as $event) {
        if (isset($catalog[$event])) {
            $events[$event] = array('label' => (string) $catalog[$event]['label'], 'when' => (string) $catalog[$event]['when'], 'record' => (string) $catalog[$event]['record']);
        }
    }

    $starts = array('manual', 'schedule', 'mention', 'web');

    if (!empty($scheduled['join'])) {
        $starts[] = 'join';
    }

    if (!empty($lists['tables'])) {
        $starts[] = 'row';
    }

    if (!empty($lists['no_order'])) {
        $starts[] = 'no_order';
    }

    $member = (ws_flow_builder_kind($viewer) === 'member');

    return array(
        'me'     => (int) $viewer['id'],
        'events' => $events,
        'starts' => $starts,
        'kinds'  => $member ? ws_flow_member_kinds() : null,
    );
}

/**
 * A flow made from the choices: the catalogue filled for the person, the
 * choices made a graph (ws_flow_quick_graph()), saved as a new draft
 * through the store with its home and, with publish, published through
 * Publish's own checks. A refused publish keeps the draft and comes back
 * as publish_error, as a template's setup does.
 *
 * @param array $viewer
 * @param array $data   the choices, channel_id (home) and publish (bool)
 * @return array ok, error, status, field, flow_id, draft_rev, problems,
 *               published, publish_error
 */
function ws_flow_quick_setup($viewer, $data)
{
    $none = array('flow_id' => 0, 'draft_rev' => 0, 'problems' => array(), 'published' => false, 'publish_error' => '');
    $fill = ws_flow_quick_fill($viewer);

    if ($fill === null) {
        return ws_flow_refusal(lang('Only people in the team can build flows.'), 403) + $none;
    }

    $built = ws_flow_quick_graph(ws_flow_quick_catalogue($fill), $data);

    if (!$built['ok']) {
        return ws_flow_refusal($built['error'], 422, $built['field']) + $none;
    }

    $saved = ws_flow_save($viewer, array('name' => $built['name'], 'channel_id' => max(0, (int) ($data['channel_id'] ?? 0)), 'graph' => $built['graph']));

    if (!$saved['ok']) {
        return $saved + $none;
    }

    $out = array('ok' => true, 'error' => '', 'status' => 201, 'field' => '', 'flow_id' => (int) $saved['flow_id'], 'draft_rev' => (int) $saved['draft_rev'],
        'problems' => $saved['problems'], 'published' => false, 'publish_error' => '');

    if (empty($data['publish'])) {
        return $out;
    }

    $flow = ws_flow_for($viewer, (int) $saved['flow_id'], 'edit');
    $published = $flow ? ws_flow_publish($viewer, $flow, array('draft_rev' => (int) $saved['draft_rev'])) : ws_flow_refusal(lang('The flow could not be published.'));

    $out['published'] = !empty($published['ok']);
    $out['publish_error'] = $out['published'] ? '' : (string) $published['error'];

    return $out;
}

/**
 * The catalogue the quick setup dialog reads, filled for the person; null
 * for whoever does not build flows.
 *
 * @param array      $viewer
 * @param array|null $scheduled see ws_flow_quick_fill()
 * @param array|null $lists     see ws_flow_quick_fill()
 * @return array|null
 */
function ws_flow_quick_for($viewer, $scheduled = null, $lists = null)
{
    $fill = ws_flow_quick_fill($viewer, $scheduled, $lists);

    return ($fill === null) ? null : ws_flow_quick_catalogue($fill);
}
