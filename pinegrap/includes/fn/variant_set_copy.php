<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The variant set part of a product group copy: the group columns later
 * versions added (the form settings and variant_price_rules) and the group's
 * form template (form_fields rows with form_type 'product_group', their
 * options and triggers). Duplicate Product Group and the Catalog Manager's
 * copy write the group row with a fixed column list and call
 * pg_variant_set_copy() after it; a product copy's fields generated from the
 * template are linked to the template copy, so the next template change
 * rewrites them instead of adding them a second time.
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
 * The later product_groups columns a copy keeps, each only where the source
 * row (SELECT *) has it.
 *
 * @param array|null $row
 * @return array column => value
 */
function pg_variant_set_copy_columns($row)
{
    $columns = array();

    if (!is_array($row)) {
        return $columns;
    }

    foreach (array('form', 'form_name', 'form_label_column_width', 'form_quantity_type', 'variant_price_rules') as $column) {
        if (array_key_exists($column, $row)) {
            $columns[$column] = $row[$column];
        }
    }

    // A set without rules keeps NULL, the value every reader takes as none.
    if (array_key_exists('variant_price_rules', $columns) && (($columns['variant_price_rules'] === null) || ($columns['variant_price_rules'] === ''))) {
        $columns['variant_price_rules'] = null;
    }

    return $columns;
}

/**
 * Which fields of a product copy were generated from the copied template.
 *
 * Field names are unique within one form, so a copy's field is matched to the
 * original's field by name; each copy field is used once.
 *
 * @param array $original_fields the original product's rows: id, name, template_field_id
 * @param array $copy_fields     the copy's rows: id, name
 * @param array $template_map    old template field id => new template field id
 * @return array copy field id => new template field id
 */
function pg_variant_set_copy_field_links($original_fields, $copy_fields, $template_map)
{
    $links = array();

    if (!$template_map) {
        return $links;
    }

    $free = array();

    foreach ((array) $copy_fields as $field) {
        $free[(string) $field['name']][] = (int) $field['id'];
    }

    foreach ((array) $original_fields as $field) {
        $template_id = (int) $field['template_field_id'];
        $name = (string) $field['name'];

        if (!isset($template_map[$template_id]) || empty($free[$name])) {
            continue;
        }

        $links[array_shift($free[$name])] = (int) $template_map[$template_id];
    }

    return $links;
}

/**
 * Insert a row copied with SELECT *, minus its id.
 *
 * @param string $table
 * @param array  $row
 * @return int the new id
 */
function pg_variant_set_copy_insert($table, $row)
{
    unset($row['id']);

    $columns = array();
    $values = array();

    foreach ($row as $column => $value) {
        $columns[] = '`' . preg_replace('/[^a-z_0-9]/', '', $column) . '`';
        $values[] = ($value === null) ? 'NULL' : "'" . e($value) . "'";
    }

    db("INSERT INTO `" . $table . "` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")");

    return (int) mysqli_insert_id(db::$con);
}

/**
 * Copy a group's variant set part onto its copy.
 *
 * @param int   $group_id     the original group
 * @param int   $new_group_id the copy
 * @param array $product_map  original product id => product copy id, for
 *                            the products duplicated into the copy (empty
 *                            when the copy lists the same products)
 * @return void
 */
function pg_variant_set_copy($group_id, $new_group_id, $product_map = array())
{
    $group_id = (int) $group_id;
    $new_group_id = (int) $new_group_id;

    if (($group_id < 1) || ($new_group_id < 1)) {
        return;
    }

    $columns = pg_variant_set_copy_columns(db_item("SELECT * FROM product_groups WHERE id = '" . $group_id . "'"));

    if ($columns) {
        $assignments = array();

        foreach ($columns as $column => $value) {
            $assignments[] = '`' . $column . '` = ' . (($value === null) ? 'NULL' : "'" . e($value) . "'");
        }

        db("UPDATE product_groups SET " . implode(', ', $assignments) . " WHERE id = '" . $new_group_id . "'");
    }

    if (!pg_schema_has('form_fields', 'product_group_id')) {
        return;
    }

    $template_fields = (array) db_items(
        "SELECT *
        FROM form_fields
        WHERE
            (form_type = 'product_group')
            AND (product_group_id = '" . $group_id . "')
        ORDER BY sort_order, id");

    if (!$template_fields) {
        return;
    }

    // Three passes, as the template is applied: fields, then options (which
    // can point at a field), then triggers (which point at both).
    $field_map = array();
    $option_map = array();

    foreach ($template_fields as $field) {
        $copy = $field;
        $copy['product_group_id'] = $new_group_id;

        if (array_key_exists('user', $copy)) {
            $copy['user'] = defined('USER_ID') ? (int) USER_ID : 0;
        }

        if (array_key_exists('timestamp', $copy)) {
            $copy['timestamp'] = time();
        }

        $field_map[(int) $field['id']] = pg_variant_set_copy_insert('form_fields', $copy);
    }

    foreach ($template_fields as $field) {
        foreach ((array) db_items("SELECT * FROM form_field_options WHERE form_field_id = '" . (int) $field['id'] . "' ORDER BY sort_order, id") as $option) {
            $copy = $option;
            $copy['form_field_id'] = $field_map[(int) $field['id']];

            if (array_key_exists('product_group_id', $copy)) {
                $copy['product_group_id'] = $new_group_id;
            }

            if (array_key_exists('target_form_field_id', $copy) && (int) $copy['target_form_field_id']) {
                $target = (int) $copy['target_form_field_id'];
                $copy['target_form_field_id'] = isset($field_map[$target]) ? $field_map[$target] : 0;
            }

            $option_map[(int) $option['id']] = pg_variant_set_copy_insert('form_field_options', $copy);
        }
    }

    foreach ($template_fields as $field) {
        foreach ((array) db_items("SELECT * FROM target_options WHERE trigger_form_field_id = '" . (int) $field['id'] . "'") as $trigger) {
            if (!isset($option_map[(int) $trigger['trigger_option_id']])) {
                continue;
            }

            $copy = $trigger;
            $copy['trigger_form_field_id'] = $field_map[(int) $field['id']];
            $copy['trigger_option_id'] = $option_map[(int) $trigger['trigger_option_id']];

            if (array_key_exists('product_group_id', $copy)) {
                $copy['product_group_id'] = $new_group_id;
            }

            pg_variant_set_copy_insert('target_options', $copy);
        }
    }

    // A product copy carries the fields generated from the original template
    // as standalone fields (duplicate_product() drops template_field_id).
    // Linking them to the template copy lets the next template change rewrite
    // them rather than add them again.
    foreach ((array) $product_map as $original_id => $copy_id) {
        if (((int) $original_id < 1) || ((int) $copy_id < 1)) {
            continue;
        }

        $links = pg_variant_set_copy_field_links(
            db_items("SELECT id, name, template_field_id FROM form_fields WHERE (form_type = 'product') AND (product_id = '" . (int) $original_id . "') ORDER BY sort_order, id"),
            db_items("SELECT id, name FROM form_fields WHERE (form_type = 'product') AND (product_id = '" . (int) $copy_id . "') AND (template_field_id = '0') ORDER BY sort_order, id"),
            $field_map);

        foreach ($links as $field_id => $template_id) {
            db(
                "UPDATE form_fields
                SET
                    template_field_id = '" . (int) $template_id . "',
                    product_group_id = '" . $new_group_id . "'
                WHERE id = '" . (int) $field_id . "'");
        }
    }
}
