<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Advanced Filters panel of the list screens (`.advanced-filter-bar`):
 * its head (icon, "Filters", the count of filters in use, Close), its foot
 * ("Clear" and "Update"), the count itself and the address "Clear" opens.
 *
 * The panel is a column of three parts: the head and the foot keep their
 * place and only the body between them scrolls, so nothing floats over the
 * fields. "Clear" sends the screen's own filter fields with their empty
 * values, the same request an emptied form would send: no new parameter.
 *
 * Loaded where it is needed (require_once), not by functions.php.
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
 * The number of filters in use: a field counts when the state holds a value
 * for it that differs from its empty value. A field missing from the state
 * is not in use, so a filter never touched does not count.
 *
 * @param array $state  The screen's kept filter values (its session array).
 * @param array $empty  Field name => the value that means "no filter".
 * @return int
 */
function pg_advanced_filters_count(array $state, array $empty)
{
    $count = 0;

    foreach ($empty as $name => $neutral) {
        if (!array_key_exists($name, $state) || !is_scalar($state[$name])) {
            continue;
        }

        if (trim((string) $state[$name]) !== (string) $neutral) {
            $count++;
        }
    }

    return $count;
}

/**
 * The address of "Clear": the screen's form address with every filter field
 * at its empty value and the form's own submit value, so the screen stores
 * them as it stores any submitted filter. Extra pairs (a screen's view, such
 * as Contacts' filter) are kept.
 *
 * @param string $action  The form's action, such as 'view_orders.php'.
 * @param array  $empty   Field name => the value that means "no filter".
 * @param array  $keep    Extra name => value pairs sent with it.
 * @return string  An unescaped URL; the caller escapes it with h().
 */
function pg_advanced_filters_clear_url($action, array $empty, array $keep = array())
{
    $query = array();

    foreach ($keep as $name => $value) {
        $query[$name] = (string) $value;
    }

    foreach ($empty as $name => $neutral) {
        $query[$name] = (string) $neutral;
    }

    $query['submit_data'] = 'Update';

    return $action . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

/**
 * The panel's head, 48 px: the funnel icon, "Filters", the count of filters
 * in use as a count pill when there is one, and Close as an icon button (the
 * link that turns the panel off).
 *
 * @param string $close_url    The address that turns the panel off.
 * @param string $close_label  The Close link's name ("Remove Advanced Filters").
 * @param int    $count        Filters in use; 0 draws no pill.
 * @return string
 */
function pg_advanced_filters_head($close_url, $close_label, $count)
{
    $count = (int) $count;

    $pill = '';

    if ($count > 0) {
        $in_use = lang(array('string' => '{var:1} filters in use', 'vars' => array($count)));
        $pill = '<span class="pg-count" title="' . h($in_use) . '"><span class="visually-hidden">' . h($in_use) . '</span><span aria-hidden="true">' . $count . '</span></span>';
    }

    return '<div class="advanced-filter-head">'
        . '<p class="advanced-filter-title"><i class="bi bi-funnel" aria-hidden="true"></i>' . h(lang('Filters')) . $pill . '</p>'
        . '<a class="pg-icon-button pg-icon-button-sm" href="' . h($close_url) . '" title="' . h($close_label) . '" aria-label="' . h($close_label) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></a>'
        . '</div>';
}

/**
 * The panel's foot, fixed under the body: "Clear" (quiet, at the left) and
 * "Update" (fill, last). Update keeps today's name and value, so the screens
 * read the submit as before.
 *
 * @param string $clear_url  The address from pg_advanced_filters_clear_url().
 * @return string
 */
function pg_advanced_filters_foot($clear_url)
{
    return '<div class="advanced-filter-foot">'
        . '<a class="pg-button pg-button-quiet" href="' . h($clear_url) . '">' . h(lang('Clear')) . '</a>'
        . '<button type="submit" name="submit_data" value="Update" class="pg-button pg-button-fill"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . h(lang('Update')) . '</button>'
        . '</div>';
}
