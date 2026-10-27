<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - per-placement content of a system widget. A widget's
 * look and texts are shared by every page placing it; a placement (the
 * shared_ref node in a page tree, props.placement) may only narrow what the
 * widget shows: its records filter, product group, calendars, menu or ad
 * region, how many records and in which order. Nothing a placement sets can show a record the
 * widget's own settings keep out.
 *
 * The values travel from the stored page body to the widget's render as a
 * marker written just before the placement's own marker:
 * `<!--pg-placement:BASE64URL--><!--pg-shared-ref:ID-->`. A placement
 * without values writes no marker, so its page body stays as it was.
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
 * The settings a placement of a widget kind may set. The form list's legacy
 * kind names are form lists.
 *
 * @param string $kind  regionType
 * @return string[]
 */
function pg_sw_placement_keys($kind)
{
    $kind = (string)$kind;
    if (in_array($kind, array('form_list_view', 'submitted_forms_list', 'form_list', 'blog_list'), true)) {
        return array('filters', 'max_results', 'order_by_field', 'order_by_direction');
    }
    $keys = array(
        'catalog_listing' => array('product_group_id', 'max_results', 'order_by_field', 'order_by_direction'),
        'calendar_view'   => array('calendar_ids', 'number_of_upcoming_events'),
        'menu_region'     => array('menu_name'),
        'ad_region'       => array('ad_region_name'),
    );
    return isset($keys[$kind]) ? $keys[$kind] : array();
}

/**
 * A placement's values as stored, kept only where they are of a known key
 * and of its type. Empty values mean "as the widget has it" and are dropped.
 *
 * @param mixed $values  props.placement of a shared_ref node
 * @return array
 */
function pg_sw_placement_clean($values)
{
    $out = array();
    if (!is_array($values)) return $out;
    foreach (array('product_group_id', 'max_results', 'number_of_upcoming_events') as $k) {
        if (isset($values[$k]) && is_numeric($values[$k]) && (int)$values[$k] > 0) {
            $out[$k] = min(100000, (int)$values[$k]);
        }
    }
    if (isset($values['order_by_field']) && is_string($values['order_by_field'])
        && preg_match('/^[A-Za-z0-9_]{1,64}$/', $values['order_by_field'])) {
        $out['order_by_field'] = $values['order_by_field'];
    }
    if (isset($values['order_by_direction']) && in_array($values['order_by_direction'], array('ASC', 'DESC'), true)) {
        $out['order_by_direction'] = $values['order_by_direction'];
    }
    if (isset($values['calendar_ids']) && is_array($values['calendar_ids'])) {
        $ids = array();
        foreach ($values['calendar_ids'] as $id) {
            if (is_numeric($id) && (int)$id > 0) $ids[(int)$id] = (int)$id;
        }
        if ($ids) $out['calendar_ids'] = array_values($ids);
    }
    // Filter rows as the widget's own are written; their fields, operators
    // and dynamic values are checked where the SQL is built
    // (pg_sw_form_list_filter_sql()). At most ten.
    if (isset($values['filters']) && is_array($values['filters'])) {
        $rows = array();
        foreach (array_values($values['filters']) as $f) {
            if (count($rows) >= 10) break;
            if (!is_array($f) || !isset($f['field']) || !is_string($f['field']) || trim($f['field']) === '') continue;
            $attr = isset($f['dynamic_value_attribute']) && is_scalar($f['dynamic_value_attribute']) ? $f['dynamic_value_attribute'] : '';
            $rows[] = array(
                'field'                   => substr($f['field'], 0, 100),
                'operator'                => (isset($f['operator']) && is_string($f['operator'])) ? substr($f['operator'], 0, 40) : '',
                'value'                   => (isset($f['value']) && is_scalar($f['value'])) ? substr((string)$f['value'], 0, 255) : '',
                'dynamic_value'           => (isset($f['dynamic_value']) && is_string($f['dynamic_value'])) ? substr($f['dynamic_value'], 0, 40) : '',
                'dynamic_value_attribute' => is_string($attr) ? substr($attr, 0, 32) : (int)$attr,
            );
        }
        if ($rows) $out['filters'] = $rows;
    }
    foreach (array('menu_name', 'ad_region_name') as $k) {
        if (isset($values[$k]) && is_string($values[$k]) && trim($values[$k]) !== '' && strlen($values[$k]) <= 100) {
            $out[$k] = $values[$k];
        }
    }
    return $out;
}

/**
 * The settings a widget renders with on one placement: its own, narrowed by
 * the placement's values its kind accepts. A placement's filters are added
 * to the widget's, and every filter must match. A count is never raised above
 * the widget's; a product group must be the widget's or one under it; the
 * calendars must be among the widget's. A value that would widen is
 * ignored and the widget's own stands.
 *
 * @param string        $kind          regionType
 * @param array         $cfg           the widget's system_region_config
 * @param mixed         $values        the placement's values
 * @param callable|null $group_within  function ($group_id, $ancestor_id): bool,
 *                                     true when the group is the ancestor or under it
 * @return array
 */
function pg_sw_placement_narrow($kind, array $cfg, $values, $group_within = null)
{
    $values = pg_sw_placement_clean($values);
    foreach (pg_sw_placement_keys($kind) as $k) {
        if (!isset($values[$k])) continue;
        $v = $values[$k];
        switch ($k) {
            case 'max_results':
                $own = isset($cfg[$k]) ? (int)$cfg[$k] : 0;
                $cfg[$k] = ($own > 0) ? min($own, $v) : $v;
                break;
            case 'number_of_upcoming_events':
                $own = isset($cfg[$k]) ? (int)$cfg[$k] : 0;
                $cfg[$k] = min(($own > 0) ? $own : 10, $v);
                break;
            case 'product_group_id':
                $own = isset($cfg[$k]) ? (int)$cfg[$k] : 0;
                if ($own <= 0 || $own === $v || (is_callable($group_within) && call_user_func($group_within, $v, $own))) {
                    $cfg[$k] = $v;
                }
                break;
            case 'filters':
                $own = (isset($cfg[$k]) && is_array($cfg[$k])) ? array_values($cfg[$k]) : array();
                $cfg[$k] = array_merge($own, $v);
                break;
            case 'calendar_ids':
                $own = array();
                if (isset($cfg[$k]) && is_array($cfg[$k])) {
                    foreach ($cfg[$k] as $id) if ((int)$id > 0) $own[] = (int)$id;
                }
                $ids = $own ? array_values(array_intersect($v, $own)) : $v;
                if ($ids) $cfg[$k] = $ids;
                break;
            default:
                $cfg[$k] = $v;
        }
    }
    return $cfg;
}

/**
 * The marker a placement with values writes before its own marker; '' when
 * none of its values is usable.
 *
 * @param mixed $values  props.placement of a shared_ref node
 * @return string
 */
function pg_sw_placement_marker($values)
{
    $values = pg_sw_placement_clean($values);
    if (!$values) return '';
    $json = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return '';
    return '<!--pg-placement:' . rtrim(strtr(base64_encode($json), '+/', '-_'), '=') . '-->';
}

/**
 * A placement marker's payload back as values; array() when it does not read.
 *
 * @param string $payload  the BASE64URL part of the marker
 * @return array
 */
function pg_sw_placement_decode($payload)
{
    $json = base64_decode(strtr((string)$payload, '-_', '+/'), true);
    if ($json === false) return array();
    return pg_sw_placement_clean(json_decode($json, true));
}

/**
 * True when product group $group_id is $ancestor_id or one under it.
 *
 * @param int $group_id
 * @param int $ancestor_id
 * @return bool
 */
function pg_sw_placement_group_within($group_id, $ancestor_id)
{
    static $paths = array();
    $ancestor_id = (int)$ancestor_id;
    if (!isset($paths[$ancestor_id])) $paths[$ancestor_id] = _pg_catalog_group_paths($ancestor_id);
    return isset($paths[$ancestor_id][(int)$group_id]);
}
