<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A volunteer's own hours tools on their page (includes/fn/volunteer_page_view.php),
 * drawn for the visitor in the site's look and in look A: the link to the
 * check-in page that reads a place's QR poster, and "Add hours outside a
 * shift" with the browser's own date field, sent for approval.
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
 * The volunteer's hours tools: the check-in page that reads a place's
 * poster, and hours worked outside a shift, sent for approval.
 *
 * @param array $form    action, hidden
 * @param array $c       the look's classes
 * @param array $profile p => the volunteer
 * @return string HTML
 */
function pg_volunteer_page_hours_html(array $form, array $c, array $profile)
{
    $id = 'vo_hours_' . (int) $profile['p'];
    // The site's look (5.3.8) has no keys of its own for a field.
    $label = 'class="' . ($c['label'] ?? 'form-label') . '"';
    $input = $c['input'] ?? 'form-control';

    return '<h3 class="h6 mb-2">' . h(lang('Hours')) . '</h3>'
        . '<p class="' . $c['meta'] . '">' . h(lang('At your shift, scan the QR poster of your place to check in and out.')) . ' <a href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/volunteer_check_in.php') . '">' . h(lang('Scan the poster')) . '</a></p>'
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
