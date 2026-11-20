<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Contacts › Privacy centre › Requests: the request box (P11). The list of
 * a person's requests about their personal data with their 30 days, a
 * request's drawer (Request, Answer, History) and "Prepare the answer" as
 * numbered steps; a request typed in by staff. Drawn by privacy_centre.php
 * after its gate (manager and up); the work is in
 * includes/fn/privacy_requests.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$requests_labels = pg_privacy_request_labels();
$requests_operator = array('role' => (int) $user['role'], 'id' => (int) $user['id']);
$requests_now = time();
$requests_ready = pg_privacy_requests_ready();

// A change: checked, done, and the screen again on the same request.
if ($requests_ready && ($_SERVER['REQUEST_METHOD'] === 'POST')) {
    validate_token_field();

    $requests_action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $requests_request = pg_privacy_request_get((int) ($_POST['request'] ?? 0));
    $requests_back = 'privacy_centre.php?view=requests';

    if ($requests_action === 'add') {
        $requests_added = pg_privacy_request_add($_POST, $requests_operator);

        if ($requests_added['errors']) {
            $liveform->add_error(isset($requests_added['errors']['email']) ? lang('Enter the address of the person who asks.') : lang('The request could not be added.'));
        } else {
            $liveform->add_notice(lang('The request was added. Its 30 days start today.'));
            $requests_back .= '&request=' . $requests_added['id'];
        }
    } elseif ($requests_request !== null) {
        $requests_back .= '&request=' . (int) $requests_request['id'];

        if ($requests_action === 'save') {
            pg_privacy_request_save($requests_request, array('assigned_user_id' => (int) ($_POST['assigned_user_id'] ?? 0), 'verified' => !empty($_POST['verified'])));
            $liveform->add_notice(lang('The request was saved.'));
        } elseif (in_array($requests_action, array('send', 'refuse'), true)) {
            $requests_text = is_string($_POST[($requests_action === 'refuse') ? 'reason' : 'answer'] ?? null) ? $_POST[($requests_action === 'refuse') ? 'reason' : 'answer'] : '';
            $requests_sent = pg_privacy_request_send($requests_request, $requests_text, $requests_operator, $requests_action === 'refuse');
            $requests_errors = array(
                'open'       => lang('This request is already closed.'),
                'empty'      => ($requests_action === 'refuse') ? lang('Write why the request is refused.') : lang('Write the answer.'),
                'unverified' => lang('Check who asks first, in step 1.'),
                'mail'       => lang('The e-mail could not be sent. Nothing was erased; try again.'),
                'refused'    => lang('You may not erase this person: they hold a role equal to or above yours.'),
            );

            if ($requests_sent === 'ok') {
                $liveform->add_notice(($requests_action === 'refuse') ? lang('The request was refused and the reason sent.') : lang('The answer was sent.'));
            } else {
                $liveform->add_error($requests_errors[$requests_sent] ?? lang('The request could not be saved.'));
            }
        }
    }

    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/' . $requests_back);
    exit();
}

$requests_filter = is_string($_GET['filter'] ?? null) && in_array($_GET['filter'], array('open', 'ending', 'answered', 'all'), true) ? $_GET['filter'] : 'open';
$requests_counts = pg_privacy_requests_counts($requests_now);

// The list.
$requests_rows = array();

foreach (pg_privacy_requests_list($requests_filter) as $requests_row) {
    $requests_rows[] = array('id' => (int) $requests_row['id'], 'cells' => pg_privacy_request_list_cells($requests_row, $requests_labels, $requests_now));
}

$requests_list = pg_list_table_printed(array(
    'list'    => 'privacy_requests',
    'columns' => pg_privacy_request_list_columns(),
    'levels'  => array(),
    'rows'    => $requests_rows,
));

$requests_filters = '<nav class="pg-seg" aria-label="' . h(lang('Filter')) . '">';

foreach (array('open' => lang('Open'), 'answered' => lang('Answered'), 'all' => lang('All')) as $requests_key => $requests_label) {
    $requests_active = ($requests_key === $requests_filter);
    $requests_filters .= '<a class="pg-seg-item' . ($requests_active ? ' is-active' : '') . '" href="privacy_centre.php?view=requests&amp;filter=' . $requests_key . '"' . ($requests_active ? ' aria-current="true"' : '') . '>' . h($requests_label) . '</a>';
}

$requests_filters .= '</nav>';

$requests_ending = ($requests_counts['ending'] > 0)
    ? '<p class="pg-text-2"><a href="privacy_centre.php?view=requests&amp;filter=ending">' . h(lang(array('string' => '{var:1} requests end within 7 days', 'vars' => array(pg_format_number($requests_counts['ending'], 0))))) . '</a></p>'
    : '';

// The drawer of the request the address names, and the drawer that adds one.
$requests_drawers = pg_privacy_request_add_drawer(isset($_GET['add']));
$requests_open = $requests_ready ? pg_privacy_request_get((int) ($_GET['request'] ?? 0)) : null;

if ($requests_open !== null) {
    $requests_drawers .= pg_privacy_request_drawer($requests_open, $requests_labels, $requests_operator, $requests_now);
}

echo
pg_page_shell(array(
        'title'               => lang('Privacy centre'),
        'extra classes'       => 'contact pg-measure-list',
        'icon'                => 'contact',
        'heading'             => lang('Privacy centre'),
        'heading_description' => lang('The personal data the site keeps, why and for how long, and the outside services it goes to, for your KVKK or GDPR records.'),
        'cancel'              => true,
    )) . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    ' . pg_privacy_centre_views('requests', $requests_counts['open']) . '
    ' . pg_screen_toolbar(array(
        'primary' => $requests_ready ? '<button type="button" class="pg-button pg-button-fill" data-privacy-drawer="privacy-request-add"><i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('Add a request')) . '</button>' : '',
        'search'  => $requests_filters,
    )) . '
    ' . ($requests_ready ? $requests_ending . pg_list_table_html($requests_list['source'], $requests_list['page'], lang('Requests')) : '<p class="pg-text-2">' . h(lang('The request box comes with the software update that adds its table.')) . '</p>') . '
    ' . $requests_drawers . '
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
<script>
(function () {
    // A drawer opened by its button, or with the screen when it carries
    // data-open; its tabs and its numbered steps show one part at a time.
    var open = function (drawer) {
        if (drawer && window.pgUI) { pgUI.drawer.show(drawer); }
    };

    document.addEventListener("click", function (event) {
        var opener = event.target.closest("[data-privacy-drawer]");
        var tab = event.target.closest("[data-privacy-tab]");

        if (opener) {
            open(document.getElementById(opener.getAttribute("data-privacy-drawer")));
        }

        if (tab) {
            var drawer = tab.closest("dialog");
            drawer.querySelectorAll("[data-privacy-tab]").forEach(function (item) {
                item.setAttribute("aria-pressed", item === tab ? "true" : "false");
            });
            drawer.querySelectorAll("[data-privacy-pane]").forEach(function (pane) {
                pane.hidden = pane.getAttribute("data-privacy-pane") !== tab.getAttribute("data-privacy-tab");
            });
            drawer.querySelectorAll("[data-privacy-foot]").forEach(function (foot) {
                foot.hidden = tab.getAttribute("data-privacy-tab") === "answer";
            });
        }
    });

    // A step of the answer opens alone.
    document.addEventListener("toggle", function (event) {
        var step = event.target;
        if (step.matches && step.matches("details[data-privacy-step]") && step.open) {
            step.parentNode.querySelectorAll("details[data-privacy-step]").forEach(function (other) {
                if (other !== step) { other.open = false; }
            });
        }
    }, true);

    document.querySelectorAll("dialog[data-open]").forEach(open);
})();
</script>
' .
output_footer();

$liveform->remove_form();
