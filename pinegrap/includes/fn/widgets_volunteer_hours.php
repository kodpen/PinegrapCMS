<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A volunteer's own hours tools on their page (includes/fn/volunteer_page_view.php),
 * drawn for the visitor in the site's look and in look A: the link to the
 * check-in page that reads a place's QR poster, the approved hours of this
 * year with "Get my certificate", and "Add hours outside a shift" with the
 * browser's own date field, sent for approval; the certificate page's
 * period form (volunteer_certificate.php), also with the browser's date
 * fields.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_certificate.php');

/**
 * The volunteer's hours tools: the check-in page that reads a place's
 * poster, this year's approved hours with the certificate's link, and
 * hours worked outside a shift, sent for approval.
 *
 * @param array $form    action, hidden
 * @param array $c       the look's classes
 * @param array $profile p => the volunteer
 * @return string HTML
 */
function pg_volunteer_page_hours_html(array $form, array $c, array $profile)
{
    $id = 'vo_hours_' . (int) $profile['p'];
    // Look A's buttons mean look A's fields; else the site's (5.3.8).
    $look_a = (strpos($c['quiet'], 'pg-button') === 0);
    $label = 'class="' . ($look_a ? 'pg-field-label' : 'form-label') . '"';
    $input = $look_a ? 'pg-input' : 'form-control';
    $year = pg_volunteer_approved_minutes((int) $profile['p'], date('Y') . '-01-01', date('Y-m-d'));

    return '<h3 class="h6 mb-2">' . h(lang('Hours')) . '</h3>'
        . '<p class="' . $c['meta'] . '">' . h(lang('At your shift, scan the QR poster of your place to check in and out.')) . ' <a href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/volunteer_check_in.php') . '">' . h(lang('Scan the poster')) . '</a></p>'
        . '<p class="' . $c['meta'] . '">' . h(lang(array('string' => 'Approved hours this year: {var:1}.', 'vars' => array(pg_volunteer_hours_number($year))))) . ' <a href="' . h(pg_volunteer_certificate_url(array('p' => (int) $profile['p']))) . '" target="_blank" rel="noopener">' . h(lang('Get my certificate')) . '</a></p>'
        . '<details class="mb-5"><summary class="' . $c['quiet'] . '">' . h(lang('Add hours outside a shift')) . '</summary>'
        . '<form method="post" action="' . h($form['action']) . '" class="mt-3">' . $form['hidden']
        . '<input type="hidden" name="p" value="' . (int) $profile['p'] . '"><input type="hidden" name="action" value="hours">'
        . '<div class="d-flex flex-wrap gap-3 mb-3">'
        . '<div><label ' . $label . ' for="' . $id . '_day">' . h(lang('Day')) . '</label><input type="date" class="' . $input . '" id="' . $id . '_day" name="day" max="' . date('Y-m-d') . '" min="' . pg_volunteer_add_days(date('Y-m-d'), -PG_VOLUNTEER_OUTSIDE_DAYS) . '" required></div>'
        . '<div><label ' . $label . ' for="' . $id . '_hours">' . h(lang('Hours')) . '</label><input type="text" class="' . $input . '" id="' . $id . '_hours" name="hours" inputmode="decimal" maxlength="5" placeholder="2.5" required></div>'
        . '</div><label ' . $label . ' for="' . $id . '_note">' . h(lang('What did you do?')) . '</label><input type="text" class="' . $input . ' mb-3" id="' . $id . '_note" name="note" maxlength="255">'
        . '<p class="' . $c['meta'] . '">' . h(lang('The coordinator approves the hours before they count.')) . '</p>'
        . '<button type="submit" class="' . $c['fill'] . '">' . h(lang('Send for approval')) . '</button></form></details>';
}

/**
 * What adding hours outside a shift tells the volunteer. Pure.
 *
 * @param string $answer pg_volunteer_outside_add()
 * @return array|null text, ok; null for an answer of another action
 */
function pg_volunteer_hours_answer_text($answer)
{
    $texts = array(
        'hours_added' => array(lang('Your hours were sent. They count once the coordinator approves them.'), true),
        'day'         => array(lang(array('string' => 'Choose a day in the last {var:1} days, not after today.', 'vars' => array(PG_VOLUNTEER_OUTSIDE_DAYS))), false),
        'hours'       => array(lang('Give the hours as a number from 0.25 to 24.'), false),
    );

    return $texts[$answer] ?? null;
}

/**
 * The certificate page's period form: from, to, Show, and "Print or save
 * as PDF" once the period has approved hours.
 *
 * @param string $action       the page's address
 * @param int    $volunteer_id
 * @param array  $period       from, to
 * @param bool   $printable
 * @return string HTML
 */
function pg_volunteer_certificate_period_form_html($action, $volunteer_id, array $period, $printable)
{
    return '<form method="get" action="' . h($action) . '" class="d-flex flex-wrap align-items-end justify-content-end gap-2">'
        . '<input type="hidden" name="p" value="' . (int) $volunteer_id . '">'
        . '<span><label class="form-label mb-1" for="vf_from">' . h(lang('From')) . '</label><input type="date" class="form-control" id="vf_from" name="from" value="' . h($period['from']) . '" max="' . date('Y-m-d') . '" required></span>'
        . '<span><label class="form-label mb-1" for="vf_to">' . h(lang('To')) . '</label><input type="date" class="form-control" id="vf_to" name="to" value="' . h($period['to']) . '" max="' . date('Y-m-d') . '" required></span>'
        . '<button type="submit" class="pg-certificate-button">' . h(lang('Show')) . '</button>'
        . ($printable ? '<button type="button" class="pg-certificate-button" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>' . h(lang('Print or save as PDF')) . '</button>' : '')
        . '</form>';
}
