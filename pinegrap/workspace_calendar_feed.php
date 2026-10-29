<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - calendar subscription: the signed-in person's own address for
 * their phone's or desktop's calendar (Apple, Google, Outlook), what it
 * carries, when a calendar last read it, and the buttons that make a new one
 * or end it. The address itself is answered by workspace_ical.php; what it
 * carries is decided in includes/workspace/calendar_feed.php.
 *
 * Every person sees and changes only their own address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
include_once('liveform.class.php');

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$viewer = ws_screen_gate($user);

if (!ws_calendar_feed_ready()) {
    output_error(lang('The workspace is not installed yet: the database has to be updated first.'));
}

$liveform = new liveform('workspace_calendar_feed');

$screen_url = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/workspace_calendar_feed.php';

$user_id = (int) $user['id'];

$feed = ws_calendar_feed_of_user($user_id);

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_token_field();

    $action = (string) ($_POST['feed_action'] ?? '');
    $kinds = ws_calendar_feed_clean_kinds(isset($_POST['kinds']) ? (array) $_POST['kinds'] : array());
    $details = !empty($_POST['details']);

    // An address the site cannot show again (it has no encryption key) is
    // shown once, on the next drawing of the screen.
    $show_once = function ($token) use ($user_id) {
        $feed = ws_calendar_feed_of_user($user_id);

        if ($feed && (ws_calendar_feed_token($feed) === '')) {
            $_SESSION['software']['ws_calendar_feed_token'] = $token;
        }
    };

    // The person's own calendars on their Work Calendar.
    if (ws_personal_ical_post($user_id, $action, $liveform)) {

        header('Location: ' . $screen_url . '#ws-own-calendars');

        exit();

    }

    if (($action === 'create') || (($action === 'save') && $feed)) {

        if (empty($kinds)) {

            $liveform->mark_error('kinds', lang('Choose at least one thing for the calendar to carry.'));

        } elseif ($action === 'create') {

            if ($feed) {
                ws_calendar_feed_save($user_id, $kinds, $details);
            } else {
                $show_once(ws_calendar_feed_issue($user_id, $kinds, $details));

                log_activity(lang('made a calendar subscription address for their workspace'), $_SESSION['sessionusername']);
            }

            $liveform->add_notice(lang('Your calendar address is ready. Add it to your calendar with the buttons below.'));

        } else {

            ws_calendar_feed_save($user_id, $kinds, $details);

            $liveform->add_notice(lang('Saved. Your calendars show the change the next time they read the address.'));

        }

    } elseif (($action === 'renew') && $feed) {

        $show_once(ws_calendar_feed_issue($user_id, ws_calendar_feed_clean_kinds($feed['kinds']), ((int) $feed['details'] === 1)));

        log_activity(lang('made a new calendar subscription address for their workspace'), $_SESSION['sessionusername']);

        $liveform->add_notice(lang('A new address was made and the old one no longer works. Add the new one to your calendars again.'));

    } elseif (($action === 'end') && $feed) {

        ws_calendar_feed_end($user_id);

        log_activity(lang('ended the calendar subscription of their workspace'), $_SESSION['sessionusername']);

        $liveform->add_notice(lang('The subscription was ended and the address no longer works. Remove the calendar from your calendar applications too.'));

    }

    header('Location: ' . $screen_url);

    exit();

}

/* ---------------------------------------------------------------------------
   Reads
   --------------------------------------------------------------------------- */

$token = $feed ? ws_calendar_feed_token($feed) : '';

if ($feed && ($token === '') && !empty($_SESSION['software']['ws_calendar_feed_token'])) {
    $token = (string) $_SESSION['software']['ws_calendar_feed_token'];
}

unset($_SESSION['software']['ws_calendar_feed_token']);

$chosen = $feed ? ws_calendar_feed_clean_kinds($feed['kinds']) : array_keys(ws_calendar_feed_kinds());
$details_on = $feed && ((int) $feed['details'] === 1);

// The kinds and the details switch, the same on the first form and on the
// options card.
$options = '';

foreach (ws_calendar_feed_kinds() as $key => $kind) {
    $options .= '
                <div class="form-field mb-3">
                    <input class="check" type="checkbox" name="kinds[]" value="' . h($key) . '" id="ws_ical_kind_' . h($key) . '"' . (in_array($key, $chosen, true) ? ' checked' : '') . '>
                    <label class="" for="ws_ical_kind_' . h($key) . '">
                        <span class="fw-semibold">' . h($kind['label']) . '</span>
                        <span class="d-block small fg-secondary">' . h($kind['hint']) . '</span>
                    </label>
                </div>';
}

$options .= '
                <div class="form-field mt-5">
                    <div class="switch"><input class="" type="checkbox" role="switch" name="details" value="1" id="ws_ical_details"' . ($details_on ? ' checked' : '') . '></div>
                    <label class="" for="ws_ical_details">
                        <span class="fw-semibold">' . h(lang('Include descriptions and notes')) . '</span>
                        <span class="d-block small fg-secondary">' . h(lang('Off: only titles, dates and a link go out. The calendar service (Google, Microsoft, Apple) keeps a copy of whatever the calendar carries.')) . '</span>
                    </label>
                </div>';

$address_card = '';
$manage_card = '';

if (!$feed) {

    $address_card = '
        <div class="card mb-5">
            <div class="card-body">
                <h2 class="h6 mb-3"><i class="bi bi-calendar-plus me-3" aria-hidden="true"></i>' . h(lang('Your work in your own calendar')) . '</h2>
                <p class="small fg-secondary">' . h(lang('Make a private address and add it to the calendar on your phone or computer. Your work appears there next to your other appointments and stays up to date by itself. The calendar only reads: changes are made here.')) . '</p>
                <form method="post" action="workspace_calendar_feed.php" class="disable_shortcut">
                    ' . get_token_field() . '
                    <input type="hidden" name="feed_action" value="create">
                    ' . $options . '
                    <button type="submit" class="btn btn-solid theme-primary mt-5"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' . h(lang('Make my calendar address')) . '</button>
                </form>
            </div>
        </div>';

} else {

    $reader = ws_calendar_feed_reader_name($feed['fetched_agent']);

    if ((int) $feed['fetched_at'] > 0) {
        $seen = lang(array(
            'string' => 'Last read {var:1} by {var:2}, from {var:3}',
            'vars'   => array(
                strip_tags(get_relative_time(array('timestamp' => (int) $feed['fetched_at']))),
                ($reader !== '') ? $reader : lang('a calendar application'),
                ((string) $feed['fetched_ip'] !== '') ? (string) $feed['fetched_ip'] : '-',
            ),
        ));
    } else {
        $seen = lang('No calendar has read this address yet.');
    }

    if ($token !== '') {

        $google = ws_calendar_feed_google_url($token);

        $address = '
                <label class="form-label small fw-semibold" for="ws_ical_url">' . h(lang('Your calendar address')) . '</label>
                <div class="input-group mb-3">
                    <input type="text" class="form-control font-monospace" id="ws_ical_url" value="' . h(ws_calendar_feed_url($token)) . '" readonly>
                    <button type="button" class="btn btn-outline theme-secondary" id="ws_ical_copy" data-done="' . h(lang('Copied')) . '"><i class="bi bi-clipboard me-1" aria-hidden="true"></i>' . h(lang('Copy')) . '</button>
                </div>
                <p class="small fg-secondary mb-5"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>' . h(lang('Keep it like a password: anyone who has it can read what it carries. If it got out, make a new address below.')) . '</p>
                <div class="d-flex flex-wrap gap-3 mb-5">
                    ' . (($google !== '')
                        ? '<a class="btn btn-sm btn-outline theme-primary" href="' . h($google) . '" target="_blank" rel="noopener noreferrer"><i class="bi bi-google me-1" aria-hidden="true"></i>' . h(lang('Add to Google Calendar')) . '</a>'
                        : '') . '
                    <a class="btn btn-sm btn-outline theme-primary" href="' . h(ws_calendar_feed_webcal_url($token)) . '"><i class="bi bi-apple me-1" aria-hidden="true"></i>' . h(lang('Open in Apple Calendar or Outlook')) . '</a>
                </div>';

    } else {

        $address = '
                <div class="alert theme-warning small">' . h(lang('This site cannot show your address again because it has no encryption key. Make a new address below and copy it while it is on screen.')) . '</div>';

    }

    $address_card = '
        <div class="card mb-5">
            <div class="card-body">
                <h2 class="h6 mb-5"><i class="bi bi-calendar-check me-3" aria-hidden="true"></i>' . h(lang('Your calendar subscription')) . '</h2>
                ' . $address . '
                <p class="small fg-secondary mb-0"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h($seen) . '</p>
            </div>
        </div>
        <div class="card mb-5">
            <div class="card-body">
                <h2 class="h6 mb-5">' . h(lang('What the calendar carries')) . '</h2>
                <form method="post" action="workspace_calendar_feed.php" class="disable_shortcut">
                    ' . get_token_field() . '
                    <input type="hidden" name="feed_action" value="save">
                    ' . $options . '
                    <button type="submit" class="btn btn-solid theme-primary mt-5">' . h(lang('Save')) . '</button>
                </form>
            </div>
        </div>';

    $manage_card = '
        <div class="card mb-5">
            <div class="card-body">
                <h2 class="h6 mb-3">' . h(lang('The address')) . '</h2>
                <p class="small fg-secondary">' . h(lang('A new address ends the old one at once: every calendar that reads it stops updating until the new one is added.')) . '</p>
                <div class="d-flex flex-wrap gap-3">
                    <button type="button" class="btn btn-sm btn-outline theme-secondary ws-ical-action" data-action="renew"
                        data-confirm="' . h(lang('The current address will stop working. Make a new one?')) . '">
                        <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>' . h(lang('Make a new address')) . '</button>
                    <button type="button" class="btn btn-sm btn-outline theme-danger ws-ical-action" data-action="end"
                        data-confirm="' . h(lang('The address will stop working and your calendars will no longer be updated. End the subscription?')) . '">
                        <i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('End the subscription')) . '</button>
                </div>
            </div>
        </div>';

}

// How to add the address, application by application.
$help = '
        <div class="card mb-5">
            <div class="card-body small">
                <h2 class="h6 mb-5"><i class="bi bi-info-circle me-3" aria-hidden="true"></i>' . h(lang('How to add it')) . '</h2>
                <p class="fw-semibold mb-1">Google Calendar</p>
                <p class="fg-secondary">' . h(lang('On the web, next to Other calendars choose +, then From URL, and paste the address. Google adds it to your phone as well; the Android app cannot add it by itself.')) . '</p>
                <p class="fw-semibold mb-1">Outlook</p>
                <p class="fg-secondary">' . h(lang('In Outlook on the web choose Add calendar, then Subscribe from web, and paste the address. In the Windows app: Add calendar, From internet.')) . '</p>
                <p class="fw-semibold mb-1">iPhone, iPad, Mac</p>
                <p class="fg-secondary">' . h(lang('On iPhone and iPad: Settings, Calendar, Accounts, Add Account, Other, Add Subscribed Calendar. On a Mac: in Calendar, File, New Calendar Subscription.')) . '</p>
                <p class="mb-0 fg-secondary"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>' . h(lang('Calendars read the address at their own pace: Apple and Outlook every hour or so, Google a few times a day. A change made here appears there after the next read.')) . '</p>
            </div>
        </div>';

/* ---------------------------------------------------------------------------
   The screen
   --------------------------------------------------------------------------- */

$liveform_messages = $liveform->output_errors() . $liveform->output_notices();

$liveform->remove_form();

echo pg_page_shell(array(
    'title'               => lang('Calendar subscription'),
    'icon'                => 'workspace',
    'heading'             => lang('Calendar subscription'),
    'heading_description' => lang('Your tasks, plan items, days off and approval deadlines in the calendar on your phone or computer.'),
    'actions'             => array(
        array('label' => lang('Work Calendar'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace_calendar.php', 'icon' => 'bi-calendar3'),
    ),
)) . '

<main id="content" class="container-fluid">
    <div class="row"><div class="col-12">
        ' . $liveform_messages . '
    </div></div>
    <div class="row g-5">
        <div class="col-12 xl:col-7">
            ' . $address_card . '
            ' . $manage_card . '
            ' . ws_personal_ical_card($user_id) . '
        </div>
        <div class="col-12 xl:col-5">
            ' . $help . '
        </div>
    </div>

    <form method="post" action="workspace_calendar_feed.php" id="ws_ical_form" class="disable_shortcut d-none">
        ' . get_token_field() . '
        <input type="hidden" name="feed_action" value="" id="ws_ical_action">
    </form>
</main>

<script>
(function () {

    var copy = document.getElementById("ws_ical_copy");

    if (copy) {
        copy.addEventListener("click", function () {
            var field = document.getElementById("ws_ical_url");
            var done = function () {
                copy.innerHTML = "<i class=\"bi bi-check2 me-1\" aria-hidden=\"true\"></i>";
                copy.appendChild(document.createTextNode(copy.dataset.done));
            };
            field.select();
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(field.value).then(done, function () {
                    document.execCommand("copy");
                    done();
                });
            } else {
                document.execCommand("copy");
                done();
            }
        });
    }

    // Making a new address and ending the subscription post through one form
    // outside the cards, each after a question.
    document.addEventListener("click", function (e) {
        var button = e.target.closest(".ws-ical-action");
        if (!button) { return; }
        if (!window.confirm(button.dataset.confirm)) { return; }
        document.getElementById("ws_ical_action").value = button.dataset.action;
        document.getElementById("ws_ical_form").submit();
    });

})();
</script>' . output_footer();
