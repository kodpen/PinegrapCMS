<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Firewall event log
 *
 * Backend view of the waf_log table written by waf.php. The summary, the
 * possible false positives and the bans are drawn here; the events come one
 * page at a time from the waf_log_list_rows action
 * (includes/fn/waf_log_list.php), sorted by the headings and searched on the
 * server.
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
 * @copyright   2017–2026 Kodpen
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/waf_log_list.php');

include_once('liveform.class.php');
$liveform = new liveform('view_waf_log');

$table_exists = pg_schema_has('waf_log');

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
        db_q(
            "DELETE FROM banned_ip_addresses
             WHERE ip_address = ? AND source = 'auto'",
            array((string) $release_ip)
        ) or output_error(lang('Query failed.'));

        // The pre-database mirror is rewritten at most every five minutes on
        // its own; a release has to reach it now, or the address stays shut
        // out at the door while this screen says it was let back in.
        if (function_exists('waf_ban_shield_refresh')) {
            waf_ip_lists(true);
            waf_ban_shield_refresh(true);
        }

        // The offences that earned the ban are forgotten with it, or the next
        // refusal would place it again before the ten-minute slot is over.
        if (function_exists('waf_offence_clear')) {
            waf_offence_clear($release_ip);
        }

        log_activity(
            lang(array('string' => 'released the automatic firewall ban on {var:1}', 'vars' => $release_ip)),
            $_SESSION['sessionusername']
        );

        $liveform->add_notice(lang(array('string' => 'The ban on {var:1} has been released.', 'vars' => h($release_ip))));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/view_waf_log.php');
}

// Put a flagged address on the allowed list. Written as the same row the
// Settings screen writes (source 'manual'), so it appears in - and survives
// saves of - the Allowed IP Addresses field there. Any automatic ban covering
// the address is released in the same movement: the allow list wins inside
// the firewall, but the pre-database mirror carries block lines only and
// would go on turning the address away.
if (isset($_POST['submit_allow'])) {
    validate_token_field();

    $allow_ip = isset($_POST['allow_ip']) ? trim((string) $_POST['allow_ip']) : '';

    if ($allow_ip !== ''
        && function_exists('waf_is_ip') && waf_is_ip($allow_ip)
        && waf_table_has_column('banned_ip_addresses', 'list_type')
    ) {
        db_q(
            "INSERT INTO banned_ip_addresses
                (ip_address, list_type, source, created_at)
             VALUES
                (?, 'allow', 'manual', ?)
             ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)",
            array((string) $allow_ip, (int) time())
        ) or output_error(lang('Query failed.'));

        $auto_result = mysqli_query(
            db::$con,
            "SELECT ip_address FROM banned_ip_addresses
             WHERE source = 'auto' AND list_type = 'block'"
        );

        if ($auto_result) {
            foreach (mysqli_fetch_items($auto_result) as $auto_row) {
                if (waf_ip_matches($allow_ip, $auto_row['ip_address'])) {
                    db_q(
                        "DELETE FROM banned_ip_addresses
                         WHERE ip_address = ? AND source = 'auto'",
                        array((string) $auto_row['ip_address'])
                    );

                    waf_offence_clear($auto_row['ip_address']);
                }
            }
        }

        waf_offence_clear($allow_ip);
        waf_ip_lists(true);
        waf_ban_shield_refresh(true);

        log_activity(
            lang(array('string' => 'allowed the address {var:1} from the firewall log', 'vars' => $allow_ip)),
            $_SESSION['sessionusername']
        );

        $liveform->add_notice(lang(array('string' => 'The address {var:1} has been added to the allowed list.', 'vars' => $allow_ip)));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/view_waf_log.php');
}

// Forget the policy reports. They live in a file, not the log table, so the
// Clear Log button above does not reach them; after a policy change an
// operator wants a clean slate to see what the new policy still refuses.
if (isset($_POST['submit_csp_clear'])) {
    validate_token_field();

    if (function_exists('waf_csp_report_clear')) {
        waf_csp_report_clear();
        log_activity(lang('cleared the content security policy reports'), $_SESSION['sessionusername']);
        $liveform->add_notice(lang('The policy reports have been cleared.'));
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
        'heading_description' => lang('Requests the firewall blocked or flagged'),
    ));

    echo '<main id="content" class="container-fluid">
        <div class="alert theme-warning my-7">
            <i class="bi bi-exclamation-triangle me-3"></i>'
            . lang('The firewall tables do not exist yet. Please run the software upgrade to create them.')
        . '</div>
    </main>';

    echo output_footer();
    exit;
}

// ---- Filters ---------------------------------------------------------------

// Each filter is one value; one sent as an array (?range[]=x) counts as not
// given: it ended in an error page (pg_waf_log_list_filters()).
$filters = pg_waf_log_list_filters($_GET);
$range_options = pg_waf_log_list_ranges();
$category_options = pg_waf_log_list_categories();
$range = $filters['range'];
$category = $filters['category'];
$search = $filters['search'];
$now = time();
$start_timestamp = $now - ((int) $range * 86400);
$condition = pg_waf_log_list_condition($filters, $now);

// ---- Summary ---------------------------------------------------------------

$summary_result = db_q(
    // SUM(hit_count), not COUNT(*): one row can stand for thousands of
    // requests now that identical events are folded into a five-minute
    // bucket. Counting rows would report an attack as a handful of events.
    "SELECT
        COALESCE(SUM(hit_count), 0) AS total,
        COALESCE(SUM(CASE WHEN action IN ('block','rate','ban') THEN hit_count ELSE 0 END), 0) AS blocked,
        COALESCE(SUM(CASE WHEN action IN ('would-block','would-rate','would-ban') THEN hit_count ELSE 0 END), 0) AS would_block,
        COUNT(DISTINCT ip_address) AS addresses,
        COUNT(*) AS rows_stored
     FROM waf_log WHERE " . $condition['sql'],
    $condition['params']
);
$summary = ($summary_result ? mysqli_fetch_assoc($summary_result) : null) ?: array('total' => 0, 'blocked' => 0, 'would_block' => 0, 'addresses' => 0, 'rows_stored' => 0);

$mode = function_exists('waf_mode') ? waf_mode() : 'off';

// ---- Possible false positives ----------------------------------------------
//
// A flagged address that also turns out to be a real person is the one thing
// an operator has to know before switching from Monitor to Block, and the one
// thing a list of raw events cannot show. Four signs are checked, cheapest
// first:
//
//   - the firewall event itself carried a signed-in user id;
//   - a "remember me" token was issued to the address (auth_tokens keeps the
//     resolved visitor address);
//   - an activity log entry with a named user came from the address;
//   - a completed order came from the address.
//
// The activity log and orders store REMOTE_ADDR rather than the resolved
// visitor address, so behind a CDN those two signs go quiet rather than
// wrong: the edge address never appears among the flagged ones. Both tables
// can be large and neither is indexed by address, so each is read only from
// its most recent rows, bounded by primary key the way waf_enforce_log_cap()
// bounds its own work.
//
// Filtered by period only, not by the category or search box: a verdict on
// whether blocking is safe has to consider everything that was flagged.

$suspects      = array();
$suspect_count = 0;
$flagged       = array();

$flag_result = db_q(
    "SELECT ip_address,
            SUM(hit_count) AS hits,
            MAX(last_seen) AS last_seen,
            MAX(CASE WHEN user_id > 0 THEN 1 ELSE 0 END) AS signed_in,
            GROUP_CONCAT(DISTINCT rule_id ORDER BY rule_id SEPARATOR ', ') AS rules
     FROM waf_log
     WHERE log_timestamp >= ?
       AND action IN ('block', 'rate', 'would-block', 'would-rate')
     GROUP BY ip_address
     ORDER BY hits DESC
     LIMIT 300",
    array((int) $start_timestamp)
);

if ($flag_result) {
    $flagged = mysqli_fetch_items($flag_result);
}

if ($flagged) {
    $quoted = array();
    $quoted_v4 = array();

    foreach ($flagged as $row) {
        $quoted[] = "'" . escape($row['ip_address']) . "'";

        if (strpos($row['ip_address'], ':') === false) {
            $quoted_v4[] = "INET_ATON('" . escape($row['ip_address']) . "')";
        }
    }

    $in_list = implode(',', $quoted);
    $seen    = array('token' => array(), 'activity' => array(), 'order' => array());

    // Silenced: the table arrived in 2026.4.4 and an install that has not run
    // the upgrade simply contributes no sign.
    $token_result = @mysqli_query(
        db::$con,
        "SELECT DISTINCT ip_address FROM auth_tokens
         WHERE created_at >= " . (int) $start_timestamp . " AND ip_address IN (" . $in_list . ")"
    );

    if ($token_result) {
        foreach (mysqli_fetch_items($token_result) as $row) {
            $seen['token'][$row['ip_address']] = true;
        }
    }

    // Anonymous activity is recorded under the translated word for unknown,
    // so both the source string and its current translation are excluded.
    $top_row = mysqli_fetch_assoc(mysqli_query(db::$con, "SELECT MAX(log_id) AS top FROM log"));
    $floor   = max(0, (int) $top_row['top'] - 20000);

    $activity_result = mysqli_query(
        db::$con,
        "SELECT DISTINCT log_ip FROM log
         WHERE log_id > " . $floor . "
           AND log_timestamp >= " . (int) $start_timestamp . "
           AND log_user NOT IN ('', 'UNKNOWN', '" . escape(lang('UNKNOWN')) . "')
           AND log_ip IN (" . $in_list . ")"
    );

    if ($activity_result) {
        foreach (mysqli_fetch_items($activity_result) as $row) {
            $seen['activity'][$row['log_ip']] = true;
        }
    }

    // orders.ip_address is INET_ATON() of the address, so IPv4 only.
    if (defined('ECOMMERCE') && ECOMMERCE && $quoted_v4) {
        $top_row = mysqli_fetch_assoc(mysqli_query(db::$con, "SELECT MAX(id) AS top FROM orders"));
        $floor   = max(0, (int) $top_row['top'] - 20000);

        $order_result = @mysqli_query(
            db::$con,
            "SELECT DISTINCT INET_NTOA(ip_address) AS ip FROM orders
             WHERE id > " . $floor . "
               AND order_date >= " . (int) $start_timestamp . "
               AND status IN ('complete', 'exported')
               AND ip_address IN (" . implode(',', $quoted_v4) . ")"
        );

        if ($order_result) {
            foreach (mysqli_fetch_items($order_result) as $row) {
                $seen['order'][$row['ip']] = true;
            }
        }
    }

    foreach ($flagged as $row) {
        // An address already on the allow list is a case the operator has
        // settled - usually from this very table. Listing it again is noise.
        if (function_exists('waf_ip_is_allowed') && waf_ip_is_allowed($row['ip_address'])) {
            continue;
        }

        $signs = array();

        if ((int) $row['signed_in']) {
            $signs[] = 'user';
        }

        foreach (array('token', 'activity', 'order') as $sign) {
            if (isset($seen[$sign][$row['ip_address']])) {
                $signs[] = $sign;
            }
        }

        if ($signs) {
            $row['signs'] = $signs;
            $suspects[]   = $row;
        }
    }

    $suspect_count = count($suspects);
    $suspects      = array_slice($suspects, 0, 50);
}

$verdict = '';

if ($suspect_count > 0) {
    $verdict = ($mode === 'block')
        ? lang(array('string' => '{var:1} blocked addresses also show signed-in activity or a completed order. See Possible False Positives below.', 'vars' => pg_format_number($suspect_count, 0)))
        : lang(array('string' => '{var:1} of the flagged addresses also show signed-in activity or a completed order. Review them below before switching to Block.', 'vars' => pg_format_number($suspect_count, 0)));
} elseif ($flagged && $mode === 'monitor') {
    $verdict = lang(array('string' => 'None of the {var:1} flagged addresses in this period shows signed-in activity or a completed order.', 'vars' => pg_format_number(count($flagged), 0)));
}

if ($verdict !== '') {
    $verdict = ' <strong class="ms-1">' . $verdict . '</strong>';
}

$mode_banner = '';

if ($mode === 'off') {
    $mode_banner = '<div class="alert theme-secondary d-flex align-items-center">'
        . '<i class="bi bi-shield-slash me-3"></i>'
        . lang('The firewall is currently off. Nothing new is being recorded.')
        . ' <a class="ms-3" href="' . pg_settings_link('firewall', 'pgset-waf') . '">' . lang('Site Settings') . '</a></div>';
} elseif ($mode === 'monitor') {
    $mode_banner = '<div class="alert theme-info d-flex align-items-center">'
        . '<i class="bi bi-eye me-3"></i>'
        . '<span>' . lang('Monitor mode: these requests were recorded but allowed through, except addresses on the ban list, which are refused in every mode. Review the rest, and when nothing legitimate appears here, switch the firewall to Block.') . $verdict . '</span>'
        . '</div>';
} else {
    $mode_banner = '<div class="alert theme-' . ($suspect_count > 0 ? 'warning' : 'success') . ' d-flex align-items-center">'
        . '<i class="bi bi-shield-check me-3"></i>'
        . '<span>' . lang('Blocking mode: attacking requests are being rejected.') . $verdict . '</span>'
        . '</div>';
}

// ---- Active automatic bans -------------------------------------------------

$auto_bans = array();

if (waf_table_has_column('banned_ip_addresses', 'source')) {
    $ban_result = db_q(
        "SELECT ip_address, note, expires_at, hit_count
         FROM banned_ip_addresses
         WHERE source = 'auto' AND (expires_at = 0 OR expires_at > ?)
         ORDER BY expires_at DESC
         LIMIT 100",
        array((int) time())
    );

    if ($ban_result) {
        $auto_bans = mysqli_fetch_items($ban_result);
    }
}

// ---- Content Security Policy reports -------------------------------------
//
// Read from the file the report endpoint aggregates into; see
// waf_csp_report_store(). Shown whatever the period filter says: the file
// keeps fourteen days and an operator deciding whether to enforce wants all
// of it.

$csp_entries = function_exists('waf_csp_report_read') ? waf_csp_report_read() : array();
$csp_mode    = function_exists('waf_setting') ? (string) waf_setting('security_csp_mode', 'report') : 'off';

// ---- Events ----------------------------------------------------------------
//
// The events come from the waf_log_list_rows action one page at a time
// (includes/fn/waf_log_list.php), sorted by the headings' kept sort.

$list_sort = pg_waf_log_list_sort();
$list_query = array_filter(array('range' => $range, 'category' => $category, 'search' => $search), 'strlen');
$list_heading_query = h('&' . http_build_query($list_query));

$list_heading = function ($code) use ($list_sort, $list_heading_query) {
    return get_column_heading($list_sort['sort_columns'][$code], $list_sort['sort_levels'], '', $list_heading_query, $code);
};

// ---- Output ----------------------------------------------------------------

echo pg_page_shell(array(
    'title'         => lang('Firewall Log'),
    'extra classes' => 'setting',
    'icon'          => 'setting',
    'heading'       => lang('Firewall Log'),
));

echo '<main id="content" class="container-fluid">';

echo $liveform->output_errors();
echo $liveform->output_notices();
echo $mode_banner;

// Filter bar.
echo '<form method="get" action="view_waf_log.php" class="row g-3 align-items-end mb-7">
    <div class="col-12 md:col-3">
        <label for="range" class="form-label">' . lang('Period') . '</label>
        <select name="range" id="range" class="form-control">';

foreach ($range_options as $value => $label) {
    echo '<option value="' . h($value) . '"' . ($range === (string) $value ? ' selected="selected"' : '') . '>' . h($label) . '</option>';
}

echo '</select>
    </div>
    <div class="col-12 md:col-3">
        <label for="category" class="form-label">' . lang('Category') . '</label>
        <select name="category" id="category" class="form-control">';

foreach ($category_options as $value => $label) {
    echo '<option value="' . h($value) . '"' . ($category === $value ? ' selected="selected"' : '') . '>' . h($label) . '</option>';
}

echo '</select>
    </div>
    <div class="col-12 md:col-4">
        <label for="search" class="form-label">' . lang('Search') . '</label>
        <input type="text" name="search" id="search" class="form-control" value="' . h($search) . '" placeholder="' . lang('IP address, URL, rule, reference') . '"/>
    </div>
    <div class="col-12 md:col-2">
        <button type="submit" class="btn btn-solid theme-primary w-100"><i class="bi bi-funnel me-1"></i>' . lang('Filter') . '</button>
    </div>
</form>';

// Summary cards.
echo '<div class="row g-5 mb-7">
    <div class="col-6 lg:col-3">
        <div class="card h-100"><div class="card-body">
            <div class="fg-secondary small text-uppercase">' . lang('Events') . '</div>
            <div class="h3 mb-0">' . pg_format_number((int) $summary['total'], 0) . '</div>
        </div></div>
    </div>
    <div class="col-6 lg:col-3">
        <div class="card h-100"><div class="card-body">
            <div class="fg-secondary small text-uppercase">' . lang('Blocked') . '</div>
            <div class="h3 mb-0 fg-danger">' . pg_format_number((int) $summary['blocked'], 0) . '</div>
        </div></div>
    </div>
    <div class="col-6 lg:col-3">
        <div class="card h-100"><div class="card-body">
            <div class="fg-secondary small text-uppercase">' . lang('Would block') . '</div>
            <div class="h3 mb-0 fg-warning">' . pg_format_number((int) $summary['would_block'], 0) . '</div>
        </div></div>
    </div>
    <div class="col-6 lg:col-3">
        <div class="card h-100"><div class="card-body">
            <div class="fg-secondary small text-uppercase">' . lang('Distinct Addresses') . '</div>
            <div class="h3 mb-0">' . pg_format_number((int) $summary['addresses'], 0) . '</div>
        </div></div>
    </div>
</div>
<p class="fg-secondary small mb-7">' . lang(array(
    'string' => 'Identical events within five minutes share one row. {var:1} requests are stored as {var:2} rows.',
    'vars'   => array(pg_format_number((int) $summary['total'], 0), pg_format_number((int) $summary['rows_stored'], 0)),
)) . '</p>';

// Possible false positives.
if ($suspects) {
    $sign_badges = array(
        'user'     => array('bg-subtle-danger fg-emphasis-danger border-subtle-danger', lang('Signed-in user')),
        'token'    => array('bg-subtle-primary fg-emphasis-primary border-subtle-primary', lang('Remember-me sign-in')),
        'activity' => array('bg-subtle-primary fg-emphasis-primary border-subtle-primary', lang('Activity log')),
        'order'    => array('bg-subtle-success fg-emphasis-success border-subtle-success', lang('Completed order')),
    );

    echo '<div class="card mb-7 border-subtle-warning">
        <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
            <span class="text-uppercase h6 fg-emphasis-warning fw-bold mb-0"><i class="bi bi-person-exclamation me-1"></i>' . lang('Possible False Positives') . '</span>
            <span class="badge bg-subtle-warning fg-emphasis-warning border border-subtle-warning">' . pg_format_number($suspect_count, 0) . '</span>
        </div>
        <div class="card-body">
            <p class="fg-secondary small">' . lang('These addresses were flagged by the firewall in this period and also look like real people: a signed-in user, a "remember me" sign-in, a named entry in the activity log or a completed order came from the same address. Check them before switching the firewall to Block. Allowing an address puts it on the allowed list in Site Settings and releases any automatic ban on it.') . '</p>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr>
                    <th>' . lang('IP Address') . '</th>
                    <th>' . lang('Flagged') . '</th>
                    <th>' . lang('Rules') . '</th>
                    <th>' . lang('Evidence') . '</th>
                    <th>' . lang('Last seen') . '</th>
                    <th class="text-end">' . lang('Action') . '</th>
                </tr></thead><tbody>';

    foreach ($suspects as $suspect) {
        $badges = '';

        foreach ($suspect['signs'] as $sign) {
            $badges .= '<span class="badge border ' . h($sign_badges[$sign][0]) . ' me-1">' . h($sign_badges[$sign][1]) . '</span>';
        }

        echo '<tr>
            <td class="font-monospace small text-nowrap">' . h($suspect['ip_address']) . '</td>
            <td class="small"><span class="badge bg-subtle-warning fg-emphasis-warning border border-subtle-warning">&times;' . pg_format_number((int) $suspect['hits'], 0) . '</span></td>
            <td class="small"><code>' . h($suspect['rules']) . '</code></td>
            <td class="small">' . $badges . '</td>
            <td class="small text-nowrap">' . get_relative_time(array('timestamp' => (int) $suspect['last_seen'])) . '</td>
            <td class="text-end">
                <form method="post" action="view_waf_log.php" class="d-inline">'
                    . get_token_field() . '
                    <input type="hidden" name="allow_ip" value="' . h($suspect['ip_address']) . '"/>
                    <button type="submit" name="submit_allow" value="1" class="btn btn-sm btn-outline theme-success">
                        <i class="bi bi-check-circle me-1"></i>' . lang('Allow') . '
                    </button>
                </form>
            </td>
        </tr>';
    }

    echo '</tbody></table></div></div></div>';
}

// Active automatic bans.
if ($auto_bans) {
    echo '<div class="card mb-7">
        <div class="card-header bg-reset border-0 text-uppercase h6 fg-primary fw-bold">'
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
            <td class="font-monospace"><a href="' . h(pg_waf_ban_log_url($ban['ip_address'])) . '" title="' . h(lang('Open the firewall log for this address')) . '">' . h($ban['ip_address']) . '</a></td>
            <td class="small fg-secondary">' . h($ban['note']) . '</td>
            <td class="small">' . ceil($remaining / 60) . ' ' . lang('minute(s)') . '</td>
            <td class="text-end">
                <form method="post" action="view_waf_log.php" class="d-inline">'
                    . get_token_field() . '
                    <input type="hidden" name="release_ip" value="' . h($ban['ip_address']) . '"/>
                    <button type="submit" name="submit_release" value="1" class="btn btn-sm btn-outline theme-secondary">
                        <i class="bi bi-unlock me-1"></i>' . lang('Release') . '
                    </button>
                </form>
            </td>
        </tr>';
    }

    echo '</tbody></table></div></div></div>';
}

// Content Security Policy reports.
if ($csp_entries || $csp_mode !== 'off') {
    $csp_mode_badges = array(
        'off'     => array('bg-subtle-secondary fg-emphasis-secondary border-subtle-secondary', lang('Off')),
        'report'  => array('bg-subtle-info fg-emphasis-info border-subtle-info', lang('Report Only')),
        'enforce' => array('bg-subtle-success fg-emphasis-success border-subtle-success', lang('Enforce')),
    );

    $csp_mode_badge = isset($csp_mode_badges[$csp_mode]) ? $csp_mode_badges[$csp_mode] : $csp_mode_badges['report'];

    echo '<div class="card mb-7">
        <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <span class="d-flex align-items-center gap-3">
                <span class="text-uppercase h6 fg-primary fw-bold mb-0">' . lang('Content Security Policy Reports') . '</span>
                <span class="badge border fw-normal ' . h($csp_mode_badge[0]) . '">' . h($csp_mode_badge[1]) . '</span>
            </span>
            <span class="d-flex flex-wrap align-items-center gap-3 row-gap-2">
                <a href="' . pg_settings_link('firewall', 'pgset-headers') . '" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-sliders me-1"></i>' . lang('Policy Settings') . '</a>'
                . ($csp_entries ? '<form method="post" action="view_waf_log.php" class="d-inline disable_shortcut">'
                    . get_token_field() . '
                    <button type="submit" name="submit_csp_clear" value="1" class="btn btn-sm btn-outline theme-danger" data-confirm-content="' . h(lang(array('string' => 'Delete all {var:1} Content Security Policy reports? This cannot be undone.', 'vars' => count($csp_entries)))) . '">
                        <i class="bi bi-trash me-1"></i>' . lang('Clear Reports') . '
                    </button>
                </form>' : '') . '
            </span>
        </div>
        <div class="card-body">';

    if (!$csp_entries) {
        echo '<p class="fg-secondary mb-0">' . lang('No policy violations have been reported in the last fourteen days. Browsers report a violation when a page loads something the policy does not allow; an empty list after real visits means the policy fits the site.') . '</p>';
    } else {
        echo '<p class="fg-secondary small">' . lang('What browsers reported the policy would refuse, grouped by directive and source and kept for fourteen days. A source the site needs belongs in the policy; one you do not recognise is worth a look. Counts are reports, not visitors.') . '</p>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr>
                    <th>' . lang('Directive') . '</th>
                    <th>' . lang('Blocked Source') . '</th>
                    <th>' . lang('Page') . '</th>
                    <th>' . lang('Reports') . '</th>
                    <th>' . lang('Last seen') . '</th>
                </tr></thead><tbody>';

        foreach (array_slice($csp_entries, 0, 100) as $entry) {
            echo '<tr>
                <td class="small"><code>' . h($entry['d']) . '</code></td>
                <td class="font-monospace small text-break" style="max-width:20rem;">' . h($entry['b']) . '</td>
                <td class="small text-break" style="max-width:18rem;">' . h($entry['p']) . '</td>
                <td class="small"><span class="badge bg-subtle-secondary fg-emphasis-secondary border">&times;' . pg_format_number((int) $entry['n'], 0) . '</span></td>
                <td class="small text-nowrap">' . get_relative_time(array('timestamp' => (int) $entry['l'])) . '</td>
            </tr>';
        }

        echo '</tbody></table></div>';
    }

    echo '</div></div>';
}

// Event table.
echo '<div class="card mb-7">
    <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
        <span class="text-uppercase h6 fg-primary fw-bold mb-0">' . lang('Recent Events') . '</span>
        <form method="post" action="view_waf_log.php" onsubmit="return confirm(\'' . lang('Clear all firewall log data?') . '\');">'
            . get_token_field() . '
            <button type="submit" name="submit_clear" value="1" class="btn btn-sm btn-outline theme-danger">
                <i class="bi bi-trash me-1"></i>' . lang('Clear Log') . '
            </button>
        </form>
    </div>
    <div class="card-body">';

if ((int) $summary['rows_stored'] === 0) {
    echo '<p class="fg-secondary mb-0">' . lang('No firewall events were recorded in this period.') . '</p>';
} else {
    echo '<div class="pg-toolbar mb-3">
            <div class="pg-toolbar-grow"></div>
            ' . pg_list_sort_menu('view_waf_log.php', $list_sort['sort_columns'], $list_sort['sort_levels'], $list_sort['sort_default'], $list_query) . '
        </div>
        <div class="position-relative">
        <table class="chart table table-sm table-hover align-middle mb-0" style="width:100%;display:none" data-pg-list-action="waf_log_list_rows" data-pg-list-params="' . h(encode_json(array('filters' => array('range' => $range, 'category' => $category, 'search' => $search)))) . '">
        <thead><tr>
            <th>' . $list_heading('time') . '</th>
            <th>' . $list_heading('hits') . '</th>
            <th>' . $list_heading('action') . '</th>
            <th>' . $list_heading('ip') . '</th>
            <th>' . $list_heading('rule') . '</th>
            <th>' . $list_heading('score') . '</th>
            <th>' . lang('Request') . '</th>
            <th>' . lang('Match') . '</th>
        </tr></thead><tbody></tbody></table></div>';
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
