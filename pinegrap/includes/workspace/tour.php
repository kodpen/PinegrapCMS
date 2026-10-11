<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the guided tour of the channel screen.
 *
 * The panel's tour engine (assets/js/pg_tour.js, includes/fn/tour.php) draws
 * it and remembers who has watched it, in user.tours_seen like every other
 * tour. What is here is what belongs to the workspace: the steps, their words
 * and the part of the screen each one is about. assets/js/workspace_tour.js
 * puts a small drawing on every step and registers the tour once the screen
 * has drawn itself; the signpost button above the channel list plays it
 * again.
 *
 * A step whose part of the screen is not there (no channel open, a phone) is
 * shown in the middle with its drawing; one marked optional is left out
 * instead. The steps about the assistants, the notes and the scheduled
 * actions are there only where those are.
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
 * The tour's key in user.tours_seen. Raise the number after the dot and
 * everybody is shown the tour once more: what has to happen when the screen
 * changes underneath it.
 */
define('WS_TOUR_KEY', 'workspace.2');

/**
 * The key the channel screen names in its header.
 *
 * @return string
 */
function ws_tour_key()
{
    return WS_TOUR_KEY;
}

/**
 * The steps, in order: what each is about (target: selectors, the first one
 * on the screen is rung; "last:" rings the last match), the side the bubble
 * prefers, the words and the drawing (art: a key of workspace_tour.js).
 *
 * @param array $viewer
 * @return array[]
 */
function ws_tour_steps($viewer)
{
    $claude = function_exists('ws_claude_ready') && ws_claude_ready();
    $ai = function_exists('ws_ai_ready') && ws_ai_ready();

    $steps = array(
        array(
            'art'   => 'welcome',
            'title' => lang('Welcome to the Workspace'),
            'text'  => lang('Your team\'s conversations, tasks, plans and decisions, in one place. This short tour shows you around. You can leave at any point and watch it again from the signpost button above the channel list.'),
        ),
        array(
            'art'       => 'channels',
            'target'    => array('#ws-root .ws-side-scroll'),
            'placement' => 'right',
            'padding'   => 0,
            'title'     => lang('Channels'),
            'text'      => lang('Every conversation has its channel. A public channel is open to the whole team, a private one only to the people invited. Channels can be coloured, pinned to the top and gathered in groups; a channel in bold has something you have not read yet.'),
        ),
        array(
            'art'       => 'search',
            'target'    => array('#ws-root .ws-side-head'),
            'placement' => 'right',
            'title'     => lang('Search and a new channel'),
            'text'      => lang('Search every conversation you can read from the box. The + button opens a new channel: give it a name, make it public or private, invite people and, when it is about a customer, tie it to them.'),
        ),
        array(
            'art'       => 'head',
            'target'    => array('#ws-root .ws-head'),
            'placement' => 'bottom',
            'title'     => lang('The channel'),
            'text'      => lang('The name of the channel, the people in it and its menu sit at the top. The tabs under them hold the channel\'s tasks, its summary and decisions, its files and notes. The Board tab lays its tasks, decisions and files out as cards in columns, by status, kind, person or date; a task moves to another column by dragging it.'),
        ),
        array(
            'art'       => 'composer',
            'target'    => array('#ws-root .ws-composer'),
            'placement' => 'top',
            'title'     => lang('The writing box'),
            'text'      => lang('Write here and press Enter to send. @ mentions a person, # tags an order, a product, a customer or a task, and / starts a command such as /gorev for a new task. Tables, checklists, polls, formulas and files go in from the buttons.'),
        ),
        array(
            'art'       => 'actions',
            'target'    => array('last:#ws-root .ws-pane-messages .ws-msg:not(.ws-msg-deleted)'),
            'placement' => 'top',
            'title'     => lang('What a message can become'),
            'text'      => lang('Point at a message to see what it can do: answer it, react to it, pin it to the top, forward it, mark it as a decision or turn it into a task. Every decision is kept in the Decision Timeline.'),
        ),
        array(
            'art'       => 'tasks',
            'target'    => array('#ws-root [data-ws-nav="tasks"]'),
            'placement' => 'right',
            'title'     => lang('My Tasks'),
            'text'      => lang('The tasks given to you, from every channel, with their dates and urgency. A task keeps its notes, a checklist and a repeat, and the people on it are told when it changes.'),
        ),
        array(
            'art'       => 'board',
            'target'    => array('#ws-root [data-ws-nav="board"]'),
            'placement' => 'right',
            'title'     => lang('Planning Board'),
            'text'      => lang('Who does what on which day, for the whole company or for one person. Work is handed over by dragging it, and clashes with leave, holidays and other work are pointed out.'),
        ),
        array(
            'art'       => 'calendar',
            'target'    => array('#ws-root [data-ws-nav="calendar"]'),
            'placement' => 'right',
            'title'     => lang('Work Calendar'),
            'text'      => lang('Tasks, meetings, visits and leave on one calendar, laid over the company\'s working days and holidays.'),
        ),
    );

    if (function_exists('ws_notes_ready') && ws_notes_ready()) {
        $steps[] = array(
            'art'       => 'notes',
            'target'    => array('#ws-root [data-ws-nav="notes"]'),
            'placement' => 'right',
            'title'     => lang('My Notes'),
            'text'      => lang('Your own notes, with tags, checklists and figures that add themselves up. A note can be shared with a colleague or copied into a channel.'),
        );
    }

    $steps[] = array(
        'art'       => 'timeline',
        'target'    => array('#ws-root [data-ws-nav="timeline"]'),
        'placement' => 'right',
        'title'     => lang('Decision Timeline'),
        'text'      => lang('Every decision of every channel you can read, day by day. Each one opens the conversation it was taken in.'),
    );

    $steps[] = array(
        'art'       => 'inbox',
        'target'    => array('#ws-root [data-ws-nav="inbox"]'),
        'placement' => 'right',
        'title'     => lang('Inbox'),
        'text'      => lang('Mentions, tasks given to you, notes shared with you and the answers to your questions wait here until you have read them.'),
    );

    // Staff only, and only where the list shows them.
    if (function_exists('ws_can_schedule') && ws_can_schedule($viewer)) {
        $steps[] = array(
            'art'       => 'scheduled',
            'target'    => array('#ws-root [data-ws-nav="scheduled"]'),
            'placement' => 'right',
            'optional'  => true,
            'title'     => lang('Scheduled actions'),
            'text'      => lang('Work the workspace does by itself at the times you set: checking a website, a reminder, a summary, a task, a webhook, one after another if need be.'),
        );
    }

    if ($claude || $ai) {
        if ($claude && $ai) {
            $text = lang('Write @Claude or @ai in a channel and the assistant answers under your message, having read the conversation. What it proposes, tasks or changes to records, waits under its answer until somebody applies it.');
        } elseif ($ai) {
            $text = lang('Write @ai in a channel and Pinegrap AI answers under your message, having read the conversation. What it proposes, tasks or changes to records, waits under its answer until somebody applies it.');
        } else {
            $text = lang('Write @Claude in a channel and Claude answers under your message, having read the conversation. What it proposes, tasks or changes to records, waits under its answer until somebody applies it.');
        }

        $steps[] = array(
            'art'       => 'assistants',
            'target'    => array('#ws-root .ws-composer'),
            'placement' => 'top',
            'title'     => lang('Assistants in the channels'),
            'text'      => $text,
        );
    }

    $steps[] = array(
        'art'       => 'finish',
        'target'    => array('#ws-root [data-ws-tour]'),
        'placement' => 'right',
        'title'     => lang('That is all'),
        'text'      => lang('Start a channel of your own, or write in one you are in. This tour is always here: the signpost button above the channel list plays it again.'),
    );

    return $steps;
}

/**
 * What the channel screen's script is handed.
 *
 * @param array $viewer
 * @return array key, steps
 */
function ws_tour_js_config($viewer)
{
    return array(
        'key'   => WS_TOUR_KEY,
        'steps' => ws_tour_steps($viewer),
    );
}

/**
 * The texts of the tour's parts on the channel screen itself.
 *
 * @return array key => text
 */
function ws_tour_js_strings()
{
    return array(
        'tour_start' => lang('Tour of the workspace'),
    );
}
