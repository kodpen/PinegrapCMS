<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.4. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_4() {
    // ── Variant sets can own a product form ──────────────────────────────
    //
    // A product form is a set of form_fields rows keyed by product_id. That
    // model assumes one product per form, which breaks down the moment a
    // product comes in nine colour/size combinations: the operator would have
    // to draw the same form nine times.
    //
    // The v2 screens keep the runtime model exactly as it is — every product
    // still owns its own rows, so the catalog, the cart and submit_order.php
    // are untouched — and add a template above it:
    //
    //   form_type = 'product_group', product_group_id = <group>, product_id = 0
    //       the template, edited once
    //   form_type = 'product', product_id = <product>, template_field_id > 0
    //       a copy generated from that template
    //   form_type = 'product', product_id = <product>, template_field_id = 0
    //       a field added to one variant by hand, and left alone when the
    //       template is re-applied
    //
    // Existing rows all fall in the last category, which is why both new
    // columns default to 0 and nothing needs backfilling.
    $columns = array(
        'product_group_id'  => "ALTER TABLE form_fields ADD COLUMN product_group_id INT UNSIGNED NOT NULL DEFAULT 0",
        'template_field_id' => "ALTER TABLE form_fields ADD COLUMN template_field_id INT UNSIGNED NOT NULL DEFAULT 0",
    );

    foreach ($columns as $column => $sql) {
        $exists = db_item("SHOW COLUMNS FROM form_fields LIKE '" . $column . "'");
        if (!$exists) {
            db($sql);
        }
    }

    // Both indexes serve a query that runs on every template edit: "the
    // template rows of this group" and "the copies made from this template
    // row". Without them each apply is a full scan of a table that grows with
    // every product in the catalog.
    $indexes = db_items("SHOW INDEX FROM form_fields");
    $existing_indexes = array();

    foreach ($indexes as $index) {
        $existing_indexes[$index['Key_name']] = TRUE;
    }

    if (!isset($existing_indexes['idx_product_group'])) {
        db("ALTER TABLE form_fields ADD INDEX idx_product_group (product_group_id)");
    }

    if (!isset($existing_indexes['idx_template_field'])) {
        db("ALTER TABLE form_fields ADD INDEX idx_template_field (template_field_id)");
    }

    // form_field_options and target_options carry a denormalised owner column
    // so bulk deletes can find their rows without a join, and add_field.php /
    // edit_field.php write it generically from $form_type_identifier_id. Both
    // tables need the new column or a template field with options cannot be
    // saved at all.
    $xref_columns = array(
        'form_field_options' => "ALTER TABLE form_field_options ADD COLUMN product_group_id INT UNSIGNED NOT NULL DEFAULT 0",
        'target_options'     => "ALTER TABLE target_options ADD COLUMN product_group_id INT UNSIGNED NOT NULL DEFAULT 0",
    );

    foreach ($xref_columns as $table => $sql) {
        $exists = db_item("SHOW COLUMNS FROM " . $table . " LIKE 'product_group_id'");
        if (!$exists) {
            db($sql);
        }
    }

    // The form's own settings live on the group, mirroring the four columns a
    // product carries. Storing them on one "owner" variant instead would lose
    // them the day that variant is deleted.
    $group_columns = array(
        'form'                    => "ALTER TABLE product_groups ADD COLUMN form TINYINT(1) NOT NULL DEFAULT 0",
        'form_name'               => "ALTER TABLE product_groups ADD COLUMN form_name VARCHAR(100) NOT NULL DEFAULT ''",
        'form_label_column_width' => "ALTER TABLE product_groups ADD COLUMN form_label_column_width VARCHAR(3) NOT NULL DEFAULT ''",
        'form_quantity_type'      => "ALTER TABLE product_groups ADD COLUMN form_quantity_type VARCHAR(30) NOT NULL DEFAULT ''",
    );

    foreach ($group_columns as $column => $sql) {
        $exists = db_item("SHOW COLUMNS FROM product_groups LIKE '" . $column . "'");
        if (!$exists) {
            db($sql);
        }
    }
}
