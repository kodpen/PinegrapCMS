<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard appearance and refresh: the card styles and panel backdrops the
 * dashboard row may hold, how "auto" is resolved, the widget refresh time and
 * who may change them. Shared by the dashboard (welcome.php), its Site
 * Settings card (Appearance, includes/settings/appearance.php and its save)
 * and api.php's update_dashboard_appearance, so the three accept the same
 * values.
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
 * The values the two appearance columns of the dashboard row accept, the
 * default first.
 *
 * A whitelist rather than validation: one of the keys becomes a column name
 * and each value a stored slug, and neither has a legitimate free-text form.
 *
 * @return array column => list of accepted values
 */
function pg_dashboard_appearance_choices()
{
    return array(
        'widget_theme'   => array('flat', 'neon', 'glass', 'aurora'),
        'panel_backdrop' => array('auto', 'none', 'mesh', 'dusk', 'ember'));
}

/**
 * The dashboard's appearance as stored, cleaned, and the backdrop it draws.
 *
 * An installation that has not run 2026.4.4 has neither column and gets the
 * pair it has always looked at (flat, no backdrop); a value edited by hand in
 * the database falls back the same way.
 *
 * "auto" means "whatever the card style needs", a mapping CSS cannot express
 * (an attribute selector cannot read a second attribute), so it is resolved
 * here. Flat maps to none: a dashboard nobody has restyled must look exactly
 * as it did, and flat is the style it is already on. Glass maps to mesh,
 * because glass over a flat colour is a tint; the wash is what it refracts.
 *
 * @param array|null $row the dashboard row (SELECT * FROM dashboard)
 * @return array widget_theme, panel_backdrop (the stored choice, "auto"
 *               kept) and backdrop (what #content is drawn with)
 */
function pg_dashboard_appearance($row)
{
    $choices = pg_dashboard_appearance_choices();

    $widget_theme   = (is_array($row) && isset($row['widget_theme'])) ? (string) $row['widget_theme'] : '';
    $panel_backdrop = (is_array($row) && isset($row['panel_backdrop'])) ? (string) $row['panel_backdrop'] : '';

    if (!in_array($widget_theme, $choices['widget_theme'], true)) {
        $widget_theme = $choices['widget_theme'][0];
    }

    if (!in_array($panel_backdrop, $choices['panel_backdrop'], true)) {
        $panel_backdrop = $choices['panel_backdrop'][0];
    }

    $backdrop = $panel_backdrop;

    if ($backdrop == 'auto') {

        $backdrop_for_theme = array(
            'flat'   => 'none',
            'neon'   => 'dusk',
            'glass'  => 'mesh',
            'aurora' => 'mesh');

        $backdrop = $backdrop_for_theme[$widget_theme];
    }

    return array(
        'widget_theme'   => $widget_theme,
        'panel_backdrop' => $panel_backdrop,
        'backdrop'       => $backdrop);
}

/**
 * The widget refresh time, in whole seconds, from what a form or a query
 * string sent.
 *
 * At least 10 seconds: every refresh asks the server for the moving cards
 * again, and a shorter cycle is load on the server and on the browser for
 * figures that do not change that fast. At most a day: the dashboard turns it
 * into a timer in milliseconds, and a browser fires a timer longer than
 * 2^31 - 1 ms (about 24.8 days) at once, which would refresh without pause.
 *
 * @param mixed $value    the value sent
 * @param int   $fallback what to keep when the value is not a number
 * @return int
 */
function pg_dashboard_refresh_seconds($value, $fallback = 60)
{
    if ((!is_string($value) && !is_int($value) && !is_float($value)) || !is_numeric($value)) {
        return (int) $fallback;
    }

    return (int) max(10, min(86400, round((float) $value)));
}

/**
 * Whether a role may change the dashboard's appearance, refresh time and
 * widget order from Site Settings: designers and administrators (role 0 and
 * 1). A manager opens the settings too, and neither sees nor saves them.
 *
 * @param mixed $role the user's role (0 administrator ... 3 user)
 * @return bool
 */
function pg_dashboard_appearance_allowed($role)
{
    return (is_numeric($role) && ((int) $role < 2));
}
