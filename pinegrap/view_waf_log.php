<?php
/**
 * PineGrap - Firewall event log
 *
 * Backend view of the waf_log table written by waf.php.
 *
 * This screen is what makes Monitor mode useful: it shows exactly which
 * requests blocking would have rejected, so an operator can confirm on their
 * own traffic that nothing legitimate is caught before switching the firewall
 * from Monitor to Block.
 *
 * Staff only (administrator, manager, designer). Contributors are excluded:
 * firewall events expose raw attack payloads and visitor addresses.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
// Administrator, manager and designer. Contributors are excluded.
//
// 'manager' maps to role number 2 in validate_area_access(), so the check is
// role <= 2. The names in that function do not line up with the role table —
// its 'designer' case is number 1, which is Manager — so read the number, not
// the word.
//
// These screens were administrator-only, which was inconsistent: a manager can
// open Settings and already sees this same data summarised in the dashboard
// widgets, but was refused the screen that explains it.
validate_area_access($user, 'manager');

include_once('liveform.class.php');
$liveform = new liveform('view_waf_log');

$table_exists = mysqli_num_rows(mysqli_query(db::$con, "SHOW TABLES LIKE 'waf_log'")) > 0;

// ---- Actions ---------------------------------------------------------------

// Clear the log. Same CSRF-token + POST pattern as the performance log, so a
// stray GET URL cannot wipe the evidence of an attack in progress.
if (isset($_POST['submit_clear'])) {
    validate_token_field();

    if ($table_exists) {
        mysqli_query(db::$con, "TRUNCATE waf_log") or output_error(lang('Query failed.'));
        log_activity(lang('cleared all firewall log data'), $_SESSION['sessionusername']);
        $liveform->add_notice(lang('Firewall log data has been cleared.'));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/view_waf_log.php');
}

// Release an automatic ban. Manual entries are untouched: those belong to the
// Settings screen, and silently deleting one from here would be a surprise.
if (isset($_POST['submit_release'])) {
    validate_token_field();

    $release_ip = isset($_POST['release_ip']) ? trim((string) $_POST['release_ip']) : '';

    if ($release_ip !== '' && waf_table_has_column('banned_ip_addresses', 'source')) {
        mysqli_query(
            db::$con,
            "DELETE FROM banned_ip_addresses
             WHERE ip_address = '" . escape($release_ip) . "' AND source = 'auto'"
        ) or output_error(lang('Query failed.'));

        log_activity(
            lang(array('string' => 'released the automatic firewall ban on {var:1}', 'vars' => $release_ip)),
            $_SESSION['sessionusername']
        );

        $liveform->add_notice(lang(array('string' => 'The ban on {var:1} has been released.', 'vars' => $release_ip)));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/view_waf_log.php');
}

// ---- Missing table ---------------------------------------------------------

if (!$table_exists) {
    echo pg_page_shell(array(
        'title'         => lang('Firewall Log'),
        'extra classes' => 'setting',
        'icon'          => 'setting',
        'heading'       => lang('Firewall Log'),
        'cancel'        => array(
            'enable' => 'true',
            'title'  => lang('Return to Settings'),
            'url'    => 'settings.php',
        ),
        'auto_main' => false,
    ));

    echo '<main id="content" class="container">
        <div class="alert alert-warning my-4">
            <i class="bi bi-exclamation-triangle me-2"></i>'
            . lang('The firewall tables do not exist yet. Please run the software upgrade to create them.')
        . '</div>
    </main>';

    echo output_footer();
    exit;
}

// ---- Filters ---------------------------------------------------------------

$range_options = array(
    '1'  => lang('Last 24 hours'),
    '7'  => lang('Last 7 days'),
    '30' => lang('Last 30 days'),
);

$range = (isset($_GET['range']) && isset($range_options[$_GET['range']])) ? $_GET['range'] : '7';
$start_timestamp = time() - ((int) $range * 86400);

$category_options = array(
    ''         => lang('All'),
    'sqli'     => lang('SQL Injection'),
    'xss'      => lang('Cross-site Scripting'),
    'lfi'      => lang('Path Traversal'),
    'rce'      => lang('Command Injection'),
    'protocol' => lang('Protocol Abuse'),
    'bot'      => lang('Bots'),
    'tool'     => lang('Scanners'),
    'rate'     => lang('Rate Limit'),
    'iplist'   => lang('IP List'),
    'ban'      => lang('Bans'),
);

$category = (isset($_GET['category']) && isset($category_options[$_GET['category']])) ? $_GET['category'] : '';

$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

if (strlen($search) > 200) {
    $search = substr($search, 0, 200);
}

$where = "WHERE log_timestamp >= " . (int) $start_timestamp;

if ($category !== '') {
    $where .= " AND category = '" . escape($category) . "'";
}

if ($search !== '') {
    $like = '%' . escape_like($search) . '%';
    $where .= " AND (ip_address LIKE '" . escape($like) . "'
                  OR request_url LIKE '" . escape($like) . "'
                  OR rule_id LIKE '" . escape($like) . "'
                  OR reference LIKE '" . escape($like) . "'
                  OR user_agent LIKE '" . escape($like) . "')";
}

// ---- Summary ---------------------------------------------------------------

$summary = mysqli_fetch_assoc(mysqli_query(
    db::$con,
    // SUM(hit_count), not COUNT(*): one row can stand for thousands of
    // requests now that identical events are folded into a five-minute
    // bucket. Counting rows would report an attack as a handful of events.
    "SELECT
        COALESCE(SUM(hit_count), 0) AS total,
        COALESCE(SUM(CASE WHEN action IN ('block','rate','ban') THEN hit_count ELSE 0 END), 0) AS blocked,
        COALESCE(SUM(CASE WHEN action IN ('would-block','would-rate') THEN hit_count ELSE 0 END), 0) AS would_block,
        COUNT(DISTINCT ip_address) AS addresses,
        COUNT(*) AS rows_stored
     FROM waf_log $where"
));

$mode = function_exists('waf_mode') ? waf_mode() : 'off';

$mode_banner = '';

if ($mode === 'off') {
    $mode_banner = '<div class="alert alert-secondary d-flex align-items-center">'
        . '<i class="bi bi-shield-slash me-2"></i>'
        . lang('The firewall is currently off. Nothing new is being recorded.')
        . ' <a class="ms-2" href="settings.php">' . lang('Site Settings') . '</a></div>';
} elseif ($mode === 'monitor') {
    $mode_banner = '<div class="alert alert-info d-flex align-items-center">'
        . '<i class="bi bi-eye me-2"></i>'
        . lang('Monitor mode: these requests were recorded but allowed through. Review them, and when nothing legitimate appears here, switch the firewall to Block.')
        . '</div>';
} else {
    $mode_banner = '<div class="alert alert-success d-flex align-items-center">'
        . '<i class="bi bi-shield-check me-2"></i>'
        . lang('Blocking mode: attacking requests are being rejected.')
        . '</div>';
}

// ---- Active automatic bans -------------------------------------------------

$auto_bans = array();

if (waf_table_has_column('banned_ip_addresses', 'source')) {
    $ban_result = mysqli_query(
        db::$con,
        "SELECT ip_address, note, expires_at, hit_count
         FROM banned_ip_addresses
         WHERE source = 'auto' AND (expires_at = 0 OR expires_at > " . time() . ")
         ORDER BY expires_at DESC
         LIMIT 100"
    );

    if ($ban_result) {
        $auto_bans = mysqli_fetch_items($ban_result);
    }
}

// ---- Events ----------------------------------------------------------------

$events = mysqli_fetch_items(mysqli_query(
    db::$con,
    "SELECT * FROM waf_log $where ORDER BY last_seen DESC, id DESC LIMIT 500"
));

// ---- Output ----------------------------------------------------------------

$action_badges = array(
    'block'       => array('bg-danger', lang('Blocked')),
    'rate'        => array('bg-danger', lang('Rate limited')),
    'ban'         => array('bg-dark', lang('Banned')),
    'would-block' => array('bg-warning text-dark', lang('Would block')),
    'would-rate'  => array('bg-warning text-dark', lang('Would rate limit')),
    'log'         => array('bg-secondary', lang('Recorded')),
);

echo pg_page_shell(array(
    'title'         => lang('Firewall Log'),
    'extra classes' => 'setting',
    'icon'          => 'setting',
    'heading'       => lang('Firewall Log'),
    'cancel'        => array(
        'enable' => 'true',
        'title'  => lang('Return to Settings'),
        'url'    => 'settings.php',
    ),
    'auto_main' => false,
));

echo '<main id="content" class="container">';

echo $liveform->output_errors();
echo $liveform->output_notices();
echo $mode_banner;

// Filter bar.
echo '<form method="get" action="view_waf_log.php" class="row g-2 align-items-end mb-4">
    <div class="col-12 col-md-3">
        <label for="range" class="form-label">' . lang('Period') . '</label>
        <select name="range" id="range" class="form-select">';

foreach ($range_options as $value => $label) {
    echo '<option value="' . h($value) . '"' . ($range === $value ? ' selected="selected"' : '') . '>' . h($label) . '</option>';
}

echo '</select>
    </div>
    <div class="col-12 col-md-3">
        <label for="category" class="form-label">' . lang('Category') . '</label>
        <select name="category" id="category" class="form-select">';

foreach ($category_options as $value => $label) {
    echo '<option value="' . h($value) . '"' . ($category === $value ? ' selected="selected"' : '') . '>' . h($label) . '</option>';
}

echo '</select>
    </div>
    <div class="col-12 col-md-4">
        <label for="search" class="form-label">' . lang('Search') . '</label>
        <input type="text" name="search" id="search" class="form-control" value="' . h($search) . '" placeholder="' . lang('IP address, URL, rule, reference') . '"/>
    </div>
    <div class="col-12 col-md-2">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>' . lang('Filter') . '</button>
    </div>
</form>';

// Summary cards.
echo '<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">' . lang('Events') . '</div>
            <div class="h3 mb-0">' . number_format((int) $summary['total']) . '</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">' . lang('Blocked') . '</div>
            <div class="h3 mb-0 text-danger">' . number_format((int) $summary['blocked']) . '</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">' . lang('Would block') . '</div>
            <div class="h3 mb-0 text-warning">' . number_format((int) $summary['would_block']) . '</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">' . lang('Distinct Addresses') . '</div>
            <div class="h3 mb-0">' . number_format((int) $summary['addresses']) . '</div>
        </div></div>
    </div>
</div>
<p class="text-muted small mb-4">' . lang(array(
    'string' => 'Identical events within five minutes share one row. {var:1} requests are stored as {var:2} rows.',
    'vars'   => array(number_format((int) $summary['total']), number_format((int) $summary['rows_stored'])),
)) . '</p>';

// Active automatic bans.
if ($auto_bans) {
    echo '<div class="card mb-4">
        <div class="card-header bg-reset border-0 text-uppercase h6 text-primary fw-bold">'
        . lang('Active Automatic Bans') . '</div>
        <div class="card-body">
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr>
                    <th>' . lang('IP Address') . '</th>
                    <th>' . lang('Reason') . '</th>
                    <th>' . lang('Expires') . '</th>
                    <th class="text-end">' . lang('Action') . '</th>
                </tr></thead><tbody>';

    foreach ($auto_bans as $ban) {
        $remaining = max(0, (int) $ban['expires_at'] - time());

        echo '<tr>
            <td class="font-monospace">' . h($ban['ip_address']) . '</td>
            <td class="small text-muted">' . h($ban['note']) . '</td>
            <td class="small">' . ceil($remaining / 60) . ' ' . lang('minute(s)') . '</td>
            <td class="text-end">
                <form method="post" action="view_waf_log.php" class="d-inline">'
                    . get_token_field() . '
                    <input type="hidden" name="release_ip" value="' . h($ban['ip_address']) . '"/>
                    <button type="submit" name="submit_release" value="1" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-unlock me-1"></i>' . lang('Release') . '
                    </button>
                </form>
            </td>
        </tr>';
    }

    echo '</tbody></table></div></div></div>';
}

// Event table.
echo '<div class="card mb-4">
    <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
        <span class="text-uppercase h6 text-primary fw-bold mb-0">' . lang('Recent Events') . '</span>
        <form method="post" action="view_waf_log.php" onsubmit="return confirm(\'' . lang('Clear all firewall log data?') . '\');">'
            . get_token_field() . '
            <button type="submit" name="submit_clear" value="1" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-trash me-1"></i>' . lang('Clear Log') . '
            </button>
        </form>
    </div>
    <div class="card-body">';

if (!$events) {
    echo '<p class="text-muted mb-0">' . lang('No firewall events were recorded in this period.') . '</p>';
} else {
    echo '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead><tr>
            <th>' . lang('Time') . '</th>
            <th>' . lang('Hits') . '</th>
            <th>' . lang('Action') . '</th>
            <th>' . lang('IP Address') . '</th>
            <th>' . lang('Rule') . '</th>
            <th>' . lang('Score') . '</th>
            <th>' . lang('Request') . '</th>
            <th>' . lang('Match') . '</th>
        </tr></thead><tbody>';

    foreach ($events as $event) {
        $badge = isset($action_badges[$event['action']])
            ? $action_badges[$event['action']]
            : array('bg-secondary', $event['action']);

        echo '<tr>
            <td class="small text-nowrap">' . h(date('Y-m-d H:i', (int) $event['log_timestamp']))
                . ($event['last_seen'] > $event['log_timestamp']
                    ? '<div class="text-muted" style="font-size:.75rem;">&rarr; ' . h(date('H:i', (int) $event['last_seen'])) . '</div>'
                    : '')
                // Relative age of the LAST hit. Without it a row that stopped
                // firing an hour ago is indistinguishable from one still
                // firing right now — both just show a clock time — and an
                // operator who has just fixed the cause cannot tell whether
                // the entry is live or a historical record of the problem
                // they already solved.
                . '<div class="' . ($event['last_seen'] > (time() - 300) ? 'text-danger fw-semibold' : 'text-muted')
                . '" style="font-size:.75rem;">'
                . get_relative_time(array('timestamp' => (int) $event['last_seen']))
                . '</div></td>
            <td class="small">' . ((int) $event['hit_count'] > 1
                ? '<span class="badge bg-secondary-subtle text-secondary-emphasis border">&times;' . number_format((int) $event['hit_count']) . '</span>'
                : '1') . '</td>
            <td><span class="badge ' . h($badge[0]) . '">' . h($badge[1]) . '</span></td>
            <td class="font-monospace small text-nowrap">' . h($event['ip_address']) . '</td>
            <td class="small"><code>' . h($event['rule_id']) . '</code>'
                . (isset($event['reference']) && $event['reference'] !== ''
                    ? '<div class="text-muted" style="font-size:.7rem;" title="' . lang('Reference') . '">' . h($event['reference']) . '</div>'
                    : '') . '</td>
            <td class="small">' . (int) $event['score'] . '</td>
            <td class="small text-break" style="max-width:22rem;">
                <span class="text-muted">' . h($event['method']) . '</span> ' . h($event['request_url']) . '
                <div class="text-muted" style="font-size:.75rem;">' . h(mb_substr($event['user_agent'], 0, 90)) . '</div>
            </td>
            <td class="small text-break" style="max-width:16rem;">
                <span class="text-muted">' . h($event['target']) . '</span>
                <div><code>' . h($event['matched']) . '</code></div>
            </td>
        </tr>';
    }

    echo '</tbody></table></div>';
}

echo '</div></div></main>';

echo output_footer();

// Notices and errors live in the session and output_notices() only READS
// them — it does not consume them. Without this they survive every redirect
// and pile up, so the banner grows by one line on each action and never
// empties. Every other list screen clears here, after the footer, for the
// same reason (view_currencies.php, view_menu_items.php).
$liveform->unmark_errors();
$liveform->clear_notices();
