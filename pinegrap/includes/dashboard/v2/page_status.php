<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's System Status card and its look. The card draws the
 * gauge and the check chips dashboard widget 2 draws (includes/fn/
 * health_gauge.php), from the same cached checks, for the same roles;
 * "Details" opens the Release Notes dialog widget 2's header opens. The
 * look is the person's card style, backdrop, density and lights, the site's
 * Appearance values until they choose their own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * The person's look, cleaned: what they chose where they chose it, the
 * site's Appearance values (pg_dashboard_appearance()) for the rest. An
 * unknown value counts as not chosen.
 *
 * @param string     $look_json the person's row's look column
 * @param array|null $site_row  the dashboard row (widget_theme, panel_backdrop)
 * @return array style, backdrop (the choice, "auto" kept), drawn (the
 *               backdrop the page draws), dense, calm
 */
function pg_dashboard_v2_look($look_json, $site_row)
{
    $choices = pg_dashboard_appearance_choices();
    $site = pg_dashboard_appearance($site_row);

    $look = json_decode((string) $look_json, true);

    if (!is_array($look)) {
        $look = array();
    }

    $style = (isset($look['style']) && in_array($look['style'], $choices['widget_theme'], true))
        ? $look['style']
        : $site['widget_theme'];

    $backdrop = (isset($look['backdrop']) && in_array($look['backdrop'], $choices['panel_backdrop'], true))
        ? $look['backdrop']
        : $site['panel_backdrop'];

    // "auto" is resolved the way the old dashboard resolves it, from the
    // style this person sees.
    $drawn = pg_dashboard_appearance(array('widget_theme' => $style, 'panel_backdrop' => $backdrop));

    return array(
        'style'    => $style,
        'backdrop' => $backdrop,
        'drawn'    => $drawn['backdrop'],
        'dense'    => !empty($look['dense']),
        'calm'     => !empty($look['calm']),
    );
}

/**
 * The attributes of the page's main#content for a look: the card style and
 * the backdrop as the old dashboard's attributes, which the panel stylesheet
 * already reads, and compact and lights off as classes.
 *
 * @param array $look from pg_dashboard_v2_look()
 * @return string HTML attributes, with a leading space
 */
function pg_dashboard_v2_look_attributes($look)
{
    $classes = 'container-fluid pg-dash'
        . (!empty($look['dense']) ? ' pg-dash-compact' : '')
        . (!empty($look['calm']) ? ' pg-dash-calm' : '');

    return ' class="' . $classes . '" data-widget-theme="' . h($look['style']) . '" data-panel-backdrop="' . h($look['drawn']) . '"';
}

/**
 * The checks as chips in widget 2's order. Only the failed ones are always
 * drawn: they are what needs somebody now. Every other check (warnings, the
 * priority checks, the calm rest) folds behind one chip that opens them, so
 * a healthy site's card is its gauge and one quiet chip.
 *
 * That chip reads "+n more" after failed chips and "n checks" when it stands
 * alone. Warnings inside it are counted in words and tint it: the tint alone
 * would not tell them to someone who cannot see the colour.
 *
 * @param array $checks get_system_status_checks()['checks']
 * @param int   $role
 * @return string HTML
 */
function pg_dashboard_v2_status_chips($checks, $role)
{
    $urgent = array();
    $folded = array();
    $warnings = 0;

    foreach (pg_system_status_order($checks) as $check) {

        if ($check['state'] === 'fail') {
            $urgent[] = $check;
            continue;
        }

        $folded[] = $check;

        if ($check['state'] === 'warn') {
            $warnings++;
        }
    }

    $output = pg_system_status_chips($urgent, $role);

    if (empty($folded)) {
        return $output;
    }

    if (!empty($urgent)) {
        $summary = lang(array('string' => '+{var:1} more', 'vars' => count($folded)));
    } elseif (count($folded) === 1) {
        $summary = lang('1 check');
    } else {
        $summary = lang(array('string' => '{var:1} checks', 'vars' => count($folded)));
    }

    if ($warnings === 1) {
        $summary .= ' · ' . lang('1 warning');
    } elseif ($warnings > 1) {
        $summary .= ' · ' . lang(array('string' => '{var:1} warnings', 'vars' => $warnings));
    }

    return $output . '<details class="pg-dash-status-more"><summary' . (($warnings > 0) ? ' data-tone="warn"' : '') . '>'
        . h($summary) . '</summary>'
        . '<div class="pg-dash-status-more-list">' . pg_system_status_chips($folded, $role, 'system_status_more_') . '</div></details>';
}

/**
 * The System Status card, or nothing for a person widget 2 is not drawn for.
 *
 * Every check is a chip here, jobs included: the card has no jobs column,
 * and a job's chip links where its line in widget 2 does.
 *
 * @param array      $user   the row validate_user() returned
 * @param array|null $status get_system_status_checks()'s answer; read when null
 * @param bool       $off    the person hid the card: drawn hidden, so the
 *                           edit mode can show it again
 * @return string HTML
 */
function pg_dashboard_v2_status($user, $status = null, $off = false)
{
    if (!isset($user['role']) || ($user['role'] >= 3)) {
        return '';
    }

    if ($status === null) {
        $status = get_system_status_checks();
    }

    $score = isset($status['score']) ? (int) $status['score'] : 0;
    $checks = (isset($status['checks']) && is_array($status['checks'])) ? $status['checks'] : array();

    $changelog = pg_changelog_dialog();

    // The button opens the Release Notes dialog, so it is named for what the
    // dialog lists, the changes, not for the card's own details.
    $details = ($changelog['dialog'] !== '')
        ? '<button type="button" class="btn btn-link btn-sm pg-dash-status-details" data-bs-toggle="dialog" data-bs-target="#changelog_modal" title="' . h(lang('Release Notes')) . '">' . h(lang('Changes')) . '</button>'
        : '';

    return '
        <section class="card pg-dash-card pg-dash-status" id="pg_dash_status"' . ($off ? ' data-off="1" hidden' : '') . ' aria-labelledby="pg_dash_status_title">
            <div class="pg-dash-status-head">
                <h2 class="pg-dash-status-title" id="pg_dash_status_title"><span class="pg-dash-status-icon" aria-hidden="true"><i class="bi bi-activity"></i></span>' . h(lang('System Status')) . '</h2>
                ' . $details . '
            </div>
            ' . pg_health_gauge($score, 'dash') . '
            <div class="pg-checks pg-dash-status-checks">' . pg_dashboard_v2_status_chips($checks, $user['role']) . '
            </div>
            <script>
                pgUI.ready(function () {
                    pgUI.popover.get(document.getElementById("pg_dash_status"), {
                        selector: ".status-popover",
                        trigger: "hover focus"
                    });
                });
            </script>
        </section>' . $changelog['dialog'];
}
