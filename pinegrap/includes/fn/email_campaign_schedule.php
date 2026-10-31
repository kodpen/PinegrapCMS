<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The schedule label of an e-mail campaign profile on My Campaign Profiles
 * ("Schedule: 1 day after from action, at 10:00"). Each translation is one
 * whole key, so a language moves the pieces with its word order and the
 * time always stays at the end.
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
 * The plain-text schedule label of a campaign profile; the caller escapes it.
 *
 * @param int    $length      schedule_length; 0 sends immediately
 * @param string $unit        schedule_unit: 'days' or 'hours'
 * @param string $period      schedule_period: 'before' or 'after'
 * @param string $base        schedule_base: 'action' or the calendar event start time
 * @param string $time_label  the formatted send time, '' when none is set
 * @return string
 */
function pg_email_campaign_profile_schedule_label($length, $unit, $period, $base, $time_label)
{
    $length = (int) $length;

    if ($length === 0) {
        $amount = lang('immediately');
    } elseif ($unit === 'days') {
        $amount = $length . ' ' . ($length === 1 ? lang('day') : lang('days'));
    } else {
        $amount = $length . ' ' . ($length === 1 ? lang('hour') : lang('hours'));
    }

    $from = ($base === 'action') ? lang('from action') : lang('from calendar event start time');

    $vars = array($amount, lang((string) $period), $from);

    if ((string) $time_label === '') {
        return lang(array('string' => 'Schedule: {var:1} {var:2} {var:3}', 'vars' => $vars));
    }

    $vars[] = (string) $time_label;

    return lang(array('string' => 'Schedule: {var:1} {var:2} {var:3}, at {var:4}', 'vars' => $vars));
}
