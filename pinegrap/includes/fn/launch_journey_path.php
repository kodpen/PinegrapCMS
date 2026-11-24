<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * After the set-up: the "First week" card's rows (the first order or form
 * entry, visitors, the first e-mail sent, operations alerts), the "Start
 * here" card of a new team member (three to five screens for their role and
 * rights) and the hint lines drawn under a screen's title on its first
 * visits, marked as `hint:<screen>` keys in user.tours_seen.
 *
 * Everything here is pure but the two readers at the end
 * (pg_launch_journey_newcomer(), pg_launch_journey_hint_html()).
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
 * How long an account counts as new: the "Start here" card and the hint
 * lines are drawn for it this long after it was made, in seconds.
 */
define('PG_LAUNCH_JOURNEY_NEWCOMER', 30 * 86400);

/**
 * The tour key "Hide" on the "Start here" card writes (pg_tour_mark()).
 */
define('PG_LAUNCH_JOURNEY_PATH_KEY', 'start_here.1');

/**
 * The "First week" card: its head ("Day 3 of 7") and four rows, each with a
 * pill Waiting, Seen or Problem. A missing fact leaves its row waiting with
 * "Could not be checked". Pure.
 *
 * The facts array (every key optional):
 * - shop          bool, the shop is on (orders are counted)
 * - orders        int, orders since going live
 * - entries       int, form entries since going live
 * - visits        int, visits since going live
 * - mail_sent     int, e-mails the queue sent since going live
 * - mail_failed   int, e-mails the queue gave up since going live
 * - alerts        int, operations alerts sent since going live
 *
 * @param array $week  pg_launch_journey_first_week()
 * @param array $facts
 * @return array head, rows (key, name, icon, tone, state, pill, pill_tone, line)
 */
function pg_launch_journey_week_card($week, $facts)
{
    $pills = array(
        'waiting' => array(lang('Waiting'), 'gray'),
        'seen'    => array(lang('Seen'), 'teal'),
        'problem' => array(lang('Problem'), 'rose'),
    );
    $count = function ($key) use ($facts) {
        return isset($facts[$key]) ? max(0, (int) $facts[$key]) : null;
    };
    $unknown = lang('Could not be checked');
    $rows = array();

    // The first order or form entry; a site without the shop counts entries.
    $shop = !empty($facts['shop']);
    $orders = $shop ? $count('orders') : 0;
    $entries = $count('entries');
    if (($orders === null) || ($entries === null)) {
        $rows['first'] = array('waiting', $unknown);
    } elseif ($orders + $entries > 0) {
        $rows['first'] = array('seen', $shop
            ? lang(array('string' => 'Orders: {var:1}, form entries: {var:2}', 'vars' => array($orders, $entries)))
            : lang(array('string' => 'Form entries: {var:1}', 'vars' => array($entries))));
    } else {
        $rows['first'] = array('waiting', $shop ? lang('No order or form entry yet') : lang('No form entry yet'));
    }

    $visits = $count('visits');
    if ($visits === null) {
        $rows['visits'] = array('waiting', $unknown);
    } elseif ($visits > 0) {
        $rows['visits'] = array('seen', lang(array('string' => 'Visits since going live: {var:1}', 'vars' => array($visits))));
    } else {
        $rows['visits'] = array('waiting', lang('No visit counted yet'));
    }

    // Mail that only failed is a problem; one sent is enough to be seen.
    $sent = $count('mail_sent');
    $failed = $count('mail_failed');
    if (($sent === null) || ($failed === null)) {
        $rows['mail'] = array('waiting', $unknown);
    } elseif ($sent > 0) {
        $rows['mail'] = array('seen', ($failed > 0)
            ? lang(array('string' => 'E-mails sent: {var:1}, failed: {var:2}', 'vars' => array($sent, $failed)))
            : lang(array('string' => 'E-mails sent: {var:1}', 'vars' => array($sent))));
    } elseif ($failed > 0) {
        $rows['mail'] = array('problem', lang(array('string' => 'E-mails failed: {var:1}, none sent', 'vars' => array($failed))));
    } else {
        $rows['mail'] = array('waiting', lang('No e-mail sent yet'));
    }

    $alerts = $count('alerts');
    if ($alerts === null) {
        $rows['alerts'] = array('waiting', $unknown);
    } elseif ($alerts > 0) {
        $rows['alerts'] = array('problem', lang(array('string' => 'Operations alerts since going live: {var:1}', 'vars' => array($alerts))));
    } else {
        $rows['alerts'] = array('seen', lang('No alert since going live'));
    }

    $faces = array(
        'first'  => array($shop ? lang('First order or form entry') : lang('First form entry'), 'bi-inbox', 'shop'),
        'visits' => array(lang('Visitors'), 'bi-people', 'teal'),
        'mail'   => array(lang('First e-mail delivered'), 'bi-envelope-check', 'blue'),
        'alerts' => array(lang('Alerts'), 'bi-bell', 'warning'),
    );
    $drawn = array();

    foreach ($faces as $key => $face) {
        $state = $rows[$key][0];
        $drawn[] = array(
            'key'       => $key,
            'name'      => $face[0],
            'icon'      => $face[1],
            'tone'      => $face[2],
            'state'     => $state,
            'pill'      => $pills[$state][0],
            'pill_tone' => $pills[$state][1],
            'line'      => $rows[$key][1],
        );
    }

    return array(
        'head' => lang(array('string' => 'Day {var:1} of {var:2}', 'vars' => array(max(1, min(7, (int) $week['day'])), 7))),
        'rows' => $drawn,
    );
}

/**
 * Does an account still count as new: made at most
 * PG_LAUNCH_JOURNEY_NEWCOMER ago. An account with no creation time (made
 * before the time was kept) is not new. Pure.
 *
 * @param int $created user.user_created
 * @param int $now
 * @return bool
 */
function pg_launch_journey_is_new($created, $now)
{
    $created = (int) $created;

    return ($created > 0) && ((int) $now - $created < PG_LAUNCH_JOURNEY_NEWCOMER);
}

/**
 * The screens of the journey's catalogue a team member's path and the hint
 * lines name: the screen's key (its script name without .php), its title,
 * the line of what it is for, its icon tile and the line of its hint. Only
 * these screens carry a hint, so the hint keys in user.tours_seen stay
 * bounded. Pure.
 *
 * @return array key => name, line, icon, tone, hint
 */
function pg_launch_journey_screens()
{
    $screen = function ($name, $line, $icon, $tone, $hint) {
        return array('name' => $name, 'line' => $line, 'icon' => $icon, 'tone' => $tone, 'hint' => $hint);
    };

    return array(
        'view_pages'           => $screen(lang('Pages'), lang('The site\'s pages: edit their content and publish them'), 'bi-file-earmark-text', 'content',
            lang('Open a page to edit its content; a change shows on the site once the page is published.')),
        'view_styles'          => $screen(lang('Page Styles'), lang('The layouts pages are built on, opened in the designer'), 'bi-palette', 'violet',
            lang('A page style is the layout its pages share: change it once and every page on it follows.')),
        'view_menus'           => $screen(lang('Menus'), lang('The site\'s navigation and its links'), 'bi-list', 'blue',
            lang('A menu is drawn wherever a page style places it; its items can link to pages or addresses.')),
        'view_folders'         => $screen(lang('Files'), lang('Images and documents the pages use'), 'bi-folder2-open', 'amber',
            lang('Files sit in folders; a folder\'s access decides who may see and change what is in it.')),
        'view_orders'          => $screen(lang('Orders'), lang('Orders as they come in, and their shipping'), 'bi-cart4', 'shop',
            lang('New orders come first; open one to see its items, its payment and what is left to ship.')),
        'view_submitted_forms' => $screen(lang('Form entries'), lang('What visitors sent through the site\'s forms'), 'bi-inbox', 'teal',
            lang('Each line is one entry sent through a form; open it to read and answer it.')),
        'view_contacts'        => $screen(lang('Contacts'), lang('The people the site knows, and their consent'), 'bi-person-lines-fill', 'blue',
            lang('Contacts are added by forms, orders and sign-ups; their consent decides what may be sent to them.')),
        'view_calendars'       => $screen(lang('Calendars'), lang('Events and their dates'), 'bi-calendar3', 'teal',
            lang('An event is shown on the site once it is published in a calendar a page lists.')),
        'view_comments'        => $screen(lang('Comments'), lang('Comments waiting to be published'), 'bi-chat-square-quote', 'content',
            lang('A new comment waits here until it is published or deleted.')),
        'connections'          => $screen(lang('Connections'), lang('Google, payment and other accounts the site uses'), 'bi-plug', 'reports',
            lang('Connect Search Console first: it tells how the site is found and what keeps pages out of search.')),
        'add_user'             => $screen(lang('Add User'), lang('A new person for the team'), 'bi-person-plus', 'team',
            lang('The role decides what the person may open; a User gets only the areas ticked below.')),
    );
}

/**
 * The rows of a team member's "Start here" card: three to five screens for
 * their role, each one they may open. Designer: the pages and the design;
 * Manager: orders and contents; User: their own areas. An administrator
 * (role 0) has the set-up card instead and gets none. Pure.
 *
 * @param int   $role
 * @param array $may  screen key => bool, the screens the person may open
 * @return array rows (key, name, line, icon, tone, url), at most five
 */
function pg_launch_journey_path($role, $may)
{
    $orders = array(
        1 => array('view_pages', 'view_styles', 'view_menus', 'view_folders', 'view_comments'),
        2 => array('view_orders', 'view_pages', 'view_submitted_forms', 'view_contacts', 'view_folders'),
        3 => array('view_pages', 'view_orders', 'view_submitted_forms', 'view_contacts', 'view_calendars', 'view_folders'),
    );
    $role = (int) $role;

    if (!isset($orders[$role])) {
        return array();
    }

    $screens = pg_launch_journey_screens();
    $rows = array();

    foreach ($orders[$role] as $key) {
        if (empty($may[$key])) {
            continue;
        }
        $rows[] = array(
            'key'  => $key,
            'name' => $screens[$key]['name'],
            'line' => $screens[$key]['line'],
            'icon' => $screens[$key]['icon'],
            'tone' => $screens[$key]['tone'],
            'url'  => $key . '.php',
        );
        if (count($rows) === 5) {
            break;
        }
    }

    return $rows;
}

/**
 * The tour key a screen's hint line is marked with, '' for a screen outside
 * the catalogue. Pure.
 *
 * @param string $screen a script name without .php
 * @return string
 */
function pg_launch_journey_hint_key($screen)
{
    $screen = (string) $screen;

    return isset(pg_launch_journey_screens()[$screen]) ? 'hint:' . $screen : '';
}

/**
 * The hint line's markup: `.alert.theme-info.small` with one quiet "Don't
 * show again", which posts the panel action tour_seen with the hint's key
 * and takes the line away. '' for a screen outside the catalogue. Pure.
 *
 * @param string $screen a script name without .php
 * @return string
 */
function pg_launch_journey_hint_markup($screen)
{
    $key = pg_launch_journey_hint_key($screen);

    if ($key === '') {
        return '';
    }

    $hint = pg_launch_journey_screens()[$screen]['hint'];

    return '<div class="container-fluid pg-hint-line"><div class="alert theme-info small d-flex flex-wrap align-items-center gap-2 mb-0" role="note" data-pg-hint="' . h($key) . '">'
        . '<i class="bi bi-lightbulb" aria-hidden="true"></i><span class="flex-grow-1">' . h($hint) . '</span>'
        . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-hint-off>' . h(lang('Don\'t show again')) . '</button>'
        . '</div></div>'
        . '<script>(function () {'
        . 'var line = document.querySelector(\'[data-pg-hint="' . h($key) . '"]\');'
        . 'var off = line && line.querySelector("[data-pg-hint-off]");'
        . 'if (!off) { return; }'
        . 'off.addEventListener("click", function () {'
        . 'off.disabled = true;'
        . 'fetch("api.php", {method: "POST", credentials: "same-origin", headers: {"Content-Type": "application/json"},'
        . ' body: JSON.stringify({action: "tour_seen", key: line.getAttribute("data-pg-hint"), token: (typeof software_token !== "undefined") ? software_token : ""})})'
        . '.then(function (answer) { return answer.json(); })'
        . '.then(function (answer) { if (answer && answer.status === "success") { line.parentNode.remove(); } else { off.disabled = false; } })'
        . '.catch(function () { off.disabled = false; });'
        . '});'
        . '}());</script>';
}

/**
 * Is the person new: their account was made at most
 * PG_LAUNCH_JOURNEY_NEWCOMER ago. Read once per request.
 *
 * @param array $user the row validate_user() returned
 * @return bool
 */
function pg_launch_journey_newcomer($user)
{
    static $answers = array();

    $id = (is_array($user) && isset($user['id'])) ? (int) $user['id'] : 0;

    if ($id < 1) {
        return false;
    }

    if (!isset($answers[$id])) {
        $answers[$id] = pg_launch_journey_is_new((int) db_value("SELECT user_created FROM user WHERE user_id = '" . $id . "'"), time());
    }

    return $answers[$id];
}

/**
 * The hint line of the screen being drawn, for a new person who has not
 * marked it; '' otherwise.
 *
 * @param string $script the script's path ($_SERVER['SCRIPT_NAME'])
 * @param array  $user
 * @return string
 */
function pg_launch_journey_hint_html($script, $user)
{
    $screen = basename((string) $script, '.php');
    $key = pg_launch_journey_hint_key($screen);

    if (($key === '') || !is_array($user) || pg_tour_seen($key, $user) || !pg_launch_journey_newcomer($user)) {
        return '';
    }

    return pg_launch_journey_hint_markup($screen);
}
