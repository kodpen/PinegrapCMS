<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the markup of the calendar widgets' sections
// that depend on the request, built from plain values so the designer's
// canvas can draw the same markup (its twins in style_designer.src.js):
//
//   calendar_picker  the calendar view's calendar and view choice
//                    (_render_system_widget_calendar_view());
//   reserve_form     the event view's Reserve button
//                    (_render_system_widget_calendar_event_view()).

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The calendar view's picker: a form that reloads the page with the chosen
 * calendar (only when the widget shows more than one) and view.
 *
 * @param string $action      the page's path
 * @param string $hidden      the visitor's other query parameters as hidden inputs
 * @param array  $calendars   rows with id and name
 * @param int    $calendar_id the calendar chosen, 0 for all
 * @param string $view        monthly or weekly
 * @return string
 */
function pg_sw_calendar_picker_html($action, $hidden, $calendars, $calendar_id, $view)
{
    $opts = '';
    if (count($calendars) > 1) {
        $opts .= '<select name="calendar_id" class="form-select form-select-sm w-auto" aria-label="' . h(lang('Calendar')) . '">'
               . '<option value="">' . h(lang('All Calendars')) . '</option>';
        foreach ($calendars as $c) {
            $opts .= '<option value="' . (int)$c['id'] . '"' . ((int)$c['id'] === (int)$calendar_id ? ' selected' : '') . '>' . h($c['name']) . '</option>';
        }
        $opts .= '</select>';
    }
    $opts .= '<select name="view" class="form-select form-select-sm w-auto" aria-label="' . h(lang('View')) . '">'
           . '<option value="monthly"' . ($view === 'monthly' ? ' selected' : '') . '>' . h(lang('Monthly')) . '</option>'
           . '<option value="weekly"' . ($view === 'weekly' ? ' selected' : '') . '>' . h(lang('Weekly')) . '</option>'
           . '</select>';

    return '<form method="get" action="' . h($action) . '" class="d-flex flex-wrap align-items-center gap-2">'
         . $hidden
         . $opts
         . '<button type="submit" class="btn btn-sm btn-outline-secondary">' . h(lang('Update')) . '</button>'
         . '</form>';
}

/**
 * The event view's Reserve button: a form posting the occurrence to
 * reserve_calendar_event.php, which puts its product in the cart.
 *
 * @param string $action      reserve_calendar_event.php's address
 * @param string $token       the CSRF field (get_token_field())
 * @param string $send_to     the page to come back to
 * @param int    $page_id
 * @param int    $event_id
 * @param int    $recurrence
 * @param string $label       the button's words
 * @return string
 */
function pg_sw_calendar_reserve_form_html($action, $token, $send_to, $page_id, $event_id, $recurrence, $label)
{
    return '<form action="' . h($action) . '" method="post" class="d-inline">'
        . $token
        . '<input type="hidden" name="send_to" value="' . h($send_to) . '">'
        . '<input type="hidden" name="page_id" value="' . (int)$page_id . '">'
        . '<input type="hidden" name="calendar_event_id" value="' . (int)$event_id . '">'
        . '<input type="hidden" name="recurrence_number" value="' . (int)$recurrence . '">'
        . '<input type="hidden" name="require_cookies" value="true">'
        . '<button type="submit" class="btn btn-primary">' . h($label) . '</button>'
        . '</form>';
}
