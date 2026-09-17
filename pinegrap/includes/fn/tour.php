<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: Guided tours of the control panel.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


/**
 * Render a complete page shell: output_header + a standardized action bar.
 * Additive: output_header() remains the underlying API; this
 * wrapper accepts the same keys plus optional UX-bar fields and renders:
 *   <output_header html>
 *     <div class="pg-page-actions ...">  (primary_button + actions[])
 *
 * The page owns its own <main>: every screen writes the element itself so it can
 * pick .container or .container-fluid, and closes it before output_footer().
 *
 * Accepted keys (passed through to output_header):
 *   title, extra classes, icon, heading, cancel, breadcrumb, head, hide_menu, toolbar
 * UX-bar keys (rendered after the shell):
 *   primary_button: ['label' => '...', 'url' => '...', 'icon' => 'bi-plus-lg', 'variant' => 'primary']
 *   actions: [ same shape as primary_button, rendered as secondary buttons ]
 *   action_bar_class: extra CSS class for the wrapping div (default "mb-3")
 *
 * @param array $properties
 * @return string Page-shell HTML up to the point where the caller opens <main>.
 */
// ── Guided tours ────────────────────────────────────────────────────────
//
// A tour is a short walk over a screen: everything dims, a ring lands on one
// control at a time and a bubble beside it says what that control does.  It
// runs by itself the first time someone opens the screen and after that only
// when they ask for it again.
//
// What has been watched is a comma separated list of keys on the user row
// rather than a column per tour.  validate_user() reads that row whole on
// every request already, so asking the question costs no query, and the next
// tour is then a new string in a screen instead of another migration.  The
// number after the dot in a key ('explorer_files.1') is the tour's own
// version: raise it in the screen and everyone watches that tour once more,
// which is what has to happen when the screen has changed underneath it.
//
// The steps themselves are not here.  They belong to the screen being
// explained, because a step needs that screen's language strings and, when it
// has to make something happen before it can point at it, its own functions.
// This file carries the parts every tour shares: the asset, the answer to
// "has this person watched it", and the note that they now have.

// Whether the 2026.4.4 column is in place.  Before that upgrade there is
// nowhere to record a watched tour, and a tour that cannot be recorded would
// open itself on every single visit -- so it is treated as already watched and
// stays reachable from the menu instead.
// The tour of the frame, and its version.  Raising the number shows it to
// everybody once more, which is what has to happen when the frame itself
// changes underneath them.
if (!defined('PG_TOUR_SHELL_KEY')) {
    define('PG_TOUR_SHELL_KEY', 'shell.3');
}

function pg_tour_ready()
{
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    // validate_user() has usually answered this already, without a query.
    if ((isset($GLOBALS['user'])) && (is_array($GLOBALS['user'])) && (array_key_exists('tours_ready', $GLOBALS['user']))) {
        $ready = ($GLOBALS['user']['tours_ready'] == true);
        return $ready;
    }

    $ready = (db_item("SHOW COLUMNS FROM user LIKE 'tours_seen'")) ? true : false;

    return $ready;
}

// The stored list, as an array of keys.
function pg_tour_list($user)
{
    $raw = (is_array($user) && isset($user['tours_seen'])) ? (string) $user['tours_seen'] : '';

    if (trim($raw) === '') {
        return array();
    }

    $keys = array();

    foreach (explode(',', $raw) as $key) {

        $key = trim($key);

        if ($key !== '') {
            $keys[] = $key;
        }
    }

    return $keys;
}

function pg_tour_seen($tour_key, $user = null)
{
    if ($user === null) {
        $user = (isset($GLOBALS['user']) && is_array($GLOBALS['user'])) ? $GLOBALS['user'] : array();
    }

    if (pg_tour_ready() == false) {
        return true;
    }

    return in_array((string) $tour_key, pg_tour_list($user), true);
}

// Writes the key down.  An older version of the same tour is dropped rather
// than kept beside the new one, so the list stays as long as the number of
// tours and no longer.
function pg_tour_mark($user_id, $tour_key)
{
    $user_id = (int) $user_id;
    $tour_key = trim((string) $tour_key);

    if (($user_id <= 0) || ($tour_key === '') || (pg_tour_ready() == false)) {
        return false;
    }

    // The key is written into a row, so it may only be what a key can be.
    if (!preg_match('/^[a-z0-9_]{1,60}\.[0-9]{1,3}$/', $tour_key)) {
        return false;
    }

    $name = substr($tour_key, 0, strrpos($tour_key, '.'));

    $current = pg_tour_list(array('tours_seen' => (string) db_value("SELECT tours_seen FROM user WHERE user_id = '" . e($user_id) . "'")));

    $keep = array();

    foreach ($current as $key) {

        if (strpos($key, $name . '.') === 0) {
            continue;
        }

        $keep[] = $key;
    }

    $keep[] = $tour_key;

    db("UPDATE user SET tours_seen = '" . e(implode(',', $keep)) . "' WHERE user_id = '" . e($user_id) . "'");

    return true;
}

// The words on the tour's own buttons.  Shared by every tour, so they are
// written once here rather than in each screen.
//
// The forward button says "next step", not the shared "Next" a paged list
// uses: the tour is walking somebody through a screen, and the two want
// different words in a language that distinguishes them.
function pg_tour_labels()
{
    return array(
        'next' => lang('Next step'),
        'back' => lang('Back'),
        'skip' => lang('Skip'),
        'done' => lang('Finish'),
        'of' => lang('{var:1} of {var:2}'));
}

// The engine and its styles.  Emitted once, from the header, because the frame
// of the software carries a tour of its own on every screen -- so by the time
// a screen adds its own tour the engine is already there.
function pg_tour_assets()
{
    return '<link rel="stylesheet" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/css/pg_tour.css?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/css/pg_tour.css') . '">
        <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_tour.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_tour.js') . '"></script>';
}

// What a screen with its own tour needs to know: which tour, and whether this
// person has watched it.  The steps are put together by the screen itself.
function pg_tour_head($tour_key)
{
    global $user;

    $tour_key = trim((string) $tour_key);

    if ($tour_key === '') {
        return '';
    }

    $boot = array(
        'key' => $tour_key,
        'seen' => (pg_tour_seen($tour_key, $user) == true),
        'token' => (isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : ''),
        'labels' => pg_tour_labels());

    return '
        <script type="text/javascript">var PG_TOUR = ' . json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
}

// ── The tour of the frame ────────────────────────────────────────────────
//
// Every screen in the software sits in the same furniture: the menu down the
// left, the bar across the top, the chat in the corner.  None of it belongs to
// any one screen, so its tour does not either -- it is registered from the
// header and is therefore on every screen at once, and whichever one somebody
// happens to open first is where they are shown around.
//
// It is registered before any screen's own tour, and the engine runs the first
// tour nobody has watched, so someone seeing the software for the first time
// is told what the left menu is before being walked around a file manager.
//
// Targets are plain selectors: nothing here has to be opened or built first,
// the way a right-click menu does.  A control that is not on this screen, or
// switched off for this site, simply fails to be found and its step is passed
// over -- which is how the chat step behaves where chat is off.
function pg_tour_shell_steps()
{
    return array(

        // No target: the opening step is centred and dims the whole screen.
        array(
            'at' => 'welcome',
            'title' => lang('A look around'),
            'text' => lang('Every screen sits in the same frame: the menu down the left, the bar across the top, and the chat in the corner. A few short steps and you will know where things are. You can leave at any point, and start this again whenever you like from your own menu at the top right.')),

        // The menu is in two different places depending on how wide the
        // window is, so it is explained twice and each version stands down
        // where the other one is right.  On a wide screen it is simply there
        // down the left; below the large breakpoint it is pushed off screen
        // and lives behind a button in the bar, which is a different thing to
        // learn and cannot be taught by ringing a menu nobody can see.
        array(
            'at' => 'menu',
            'when' => 'wide',
            'target' => '#menu',
            'placement' => 'right',
            'title' => lang('The menu on the left'),
            'text' => lang('Everything the software can do is gathered here: the pages and files of your site, the store, visitors and reports. The arrow at the foot of it narrows the menu down to its icons when you want the room back, and widens it again.')),

        array(
            'at' => 'menu_drawer',
            'when' => 'narrow',
            'placement' => 'right',
            'title' => lang('The menu, on a small screen'),
            'text' => lang('There is no room for the menu beside the screen here, so it waits behind this button and slides in over the top -- as it has just done. Everything the software can do is in it: pages and files, the store, visitors and reports. Tapping anywhere else puts it away again.')),

        array(
            'at' => 'bar',
            'target' => '#header',
            'placement' => 'bottom',
            'padding' => 2,
            'title' => lang('The bar across the top'),
            'text' => lang('The name of the screen you are on sits at the left of it, and the things that belong to you at the right: search, the site settings, notifications and your own menu. It stays where it is while the screen underneath scrolls.')),

        array(
            // Wide screens carry the search as a field in the middle of the
            // bar, narrow ones as a single button on the right.  Only one of
            // the two is ever on screen, so both are named and the one that is
            // there is the one that gets rung.
            'at' => 'search',
            'target' => array('.pg-searchbar-field', '#EnableSearch'),
            'placement' => 'bottom',
            'title' => lang('Search'),
            'text' => lang('One search covers pages, files, products and settings at once. Ctrl+K opens it from anywhere without reaching for the mouse, and the results can be walked through with the arrow keys.')),

        array(
            'at' => 'notifications',
            'target' => '#notification_toggle',
            'placement' => 'bottom',
            'title' => lang('Notifications'),
            'text' => lang('What happened while you were elsewhere: form submissions, orders, comments, and the jobs the software ran on its own. Ctrl+Q opens the list.')),

        // Role 3 has no gear in the bar, and the step is passed over for
        // them the same way the chat step is where chat is off.
        array(
            'at' => 'settings',
            'target' => '#pg_settings_open',
            'placement' => 'bottom',
            'title' => lang('Site Settings'),
            'text' => lang('The settings of the whole site open here, over whatever you are doing: pick a heading on the left, change what you came for, save, and close. You stay on the screen you were on, so nothing is left half done and there is nothing to find your way back from.')),

        array(
            'at' => 'chat',
            'target' => '#pg-chat-root .pg-chat-launcher',
            'placement' => 'left',
            'title' => lang('Chat'),
            'text' => lang('Everyone else signed in to this site is listed here, and you can write to them without leaving the screen you are on. Files can be attached to a message, and staff find an AI assistant in the same list.')),

        array(
            'at' => 'account',
            'target' => '.pg-acctbtn',
            'placement' => 'bottom',
            'title' => lang('Your own menu'),
            'text' => lang('Your account, the light and dark appearance switch, the keyboard shortcuts, help and the way out. This tour lives here too, under Help & Support, so you can watch it again at any time.')));
}

// The inline script that registers the tour of the frame, wires the menu entry
// that plays it again, and asks the engine to run whichever tour this person
// has not watched.  Called once, from the header.
function pg_tour_shell_head($toolbar = false)
{
    global $user;

    // A guest has no row to record a watched tour in, and no software frame to
    // be shown around either.
    if ((!is_array($user)) || (empty($user['id']))) {
        return '';
    }

    // Not on a toolbar screen.  The page designer takes the sidebar away and
    // gives the middle of the bar to its own controls, so there is no frame
    // left to be shown around -- and someone who has opened the designer is in
    // the middle of something and does not want a tour of anything.
    if ($toolbar == true) {
        return '';
    }

    // saveToken, not token: this object is handed to the engine as the tour
    // itself, and the engine posts with the field of that name.  Under the
    // other name the post was never made, so the tour was never written down
    // and opened itself again on every single visit.
    $boot = array(
        'key' => PG_TOUR_SHELL_KEY,
        'seen' => (pg_tour_seen(PG_TOUR_SHELL_KEY, $user) == true),
        'saveToken' => (isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : ''),
        'labels' => pg_tour_labels(),
        'steps' => pg_tour_shell_steps());

    return '
        <script type="text/javascript">
        (function () {

            if (!window.PgTour) { return; }

            var shell = ' . json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';

            // The breakpoint the sidebar itself uses.  Below it the menu is a
            // drawer, above it a column, and the two are different enough to
            // be worth different words.
            function narrow() {
                return (window.matchMedia) ? window.matchMedia("(max-width: 991.98px)").matches : (window.innerWidth < 992);
            }

            function drawer() { return document.getElementById("menu_drawer_toggle"); }

            function drawerOpen() {
                var container = document.querySelector(".software-container");
                return (container) ? container.classList.contains("drawer-open") : false;
            }

            shell.steps.forEach(function (step) {

                if (step.when === "wide") { step.skipIf = narrow; }
                if (step.when === "narrow") { step.skipIf = function () { return !narrow(); }; }

                if (step.at !== "menu_drawer") { return; }

                // Shown rather than described: the drawer is pulled open so the
                // menu is actually on the screen while it is being explained,
                // and the ring goes round the button and the menu together so
                // the one is plainly the way to the other.  The drawer is the
                // screen own control, driven by its own click handler rather
                // than by reaching into its classes from here.
                step.target = function () { return [drawer(), ".software-sidebar"]; };

                step.before = function (done) {

                    var button = drawer();

                    if ((!button) || (drawerOpen())) { done(); return; }

                    button.click();

                    // The drawer slides in over 0.3s and must have arrived
                    // before anything is measured against it.
                    window.setTimeout(done, 360);
                };

                step.after = function () {

                    var button = drawer();

                    if ((button) && (drawerOpen())) { button.click(); }
                };
            });

            PgTour.register(shell);

            document.addEventListener("DOMContentLoaded", function () {

                var link = document.getElementById("tour_link");

                if (link) {

                    link.addEventListener("click", function (event) {

                        event.preventDefault();

                        // The menu this entry lives in stays open on a click
                        // inside it, and would then sit on top of the very bar
                        // the last step rings.
                        if ((window.bootstrap) && (window.bootstrap.Dropdown)) {

                            var toggle = document.querySelector(".pg-acctbtn");
                            var open = toggle ? window.bootstrap.Dropdown.getInstance(toggle) : null;

                            if (open) { open.hide(); }
                        }

                        PgTour.start(shell.key);
                    });
                }

                // Asked for here as well as from any screen that carries its
                // own tour: both are asking the same question, and the engine
                // answers it once.
                //
                // Only where the frame is actually on screen.  A screen may ask
                // for the sidebar to be left out, and half a tour of furniture
                // that is not there teaches nobody anything.  The entry in the
                // menu still plays it on demand.
                if (document.getElementById("menu")) { PgTour.autoStart(700); }
            });
        })();
        </script>';
}
