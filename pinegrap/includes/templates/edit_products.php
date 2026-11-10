<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// This file is a view partial: the Modify Selected dialog of the products
// list and the product group screen, printed by pg_product_modify_dialog()
// (includes/fn/product_list.php), which sets the variables it reads. Its
// fields belong to the page's form by their form attribute ($form_id), so
// they post with it to edit_products.php.
// Requested directly over the web it runs with none of them and dies on the
// first constant it touches, so it refuses the request instead.
if (!defined('PG_INIT_LOADED')) {
    http_response_code(403);
    exit;
}

$owner = ' form="' . h($form_id) . '"';
$select_options = function ($options) {
    $html = '';
    foreach ($options as $label => $value) {
        $html .= '<option value="' . h($value) . '">' . h($label) . '</option>';
    }
    return $html;
};
?>
<dialog class="dialog dialog-lg dialog-scrollable" id="product_modify_dialog" aria-labelledby="product_modify_dialog_title" data-pg-modify-form="<?=h($form_id)?>">
    <div class="dialog-header">
        <h5 class="dialog-title" id="product_modify_dialog_title"><i class="bi bi-pencil me-5" aria-hidden="true"></i><?=lang(array('string'=>'Modify Products' ))?></h5>
        <button type="button" class="btn-close no-submit" data-bs-dismiss="dialog" aria-label="<?=h(lang('Close'))?>"></button>
    </div>
    <div class="dialog-body">
        <p class="small fg-secondary"><?=lang(array('string'=>'You may update the selected Products via the form below.' ))?></p>
        <?php if ($warning !== ''): ?>
        <div class="alert theme-warning small" role="alert"><?=h($warning)?></div>
        <?php endif; ?>
        <div class="row g-7">
            <div class="col-12 md:col-6">
                <label for="enabled" class="form-label"><?=lang(array('string'=>'Status' ))?></label>
                <select id="enabled" name="edit_enabled" class="form-control"<?=$owner?>><?=$select_options($enabled_options)?></select>
            </div>
            <div class="col-12 md:col-6">
                <label for="change_price_method" class="form-label"><?=lang(array('string'=>'Price Change Method' ))?></label>
                <select id="change_price_method" name="edit_change_price_method" class="form-control collapse-if-selected" data-bs-target="#increase_price_row"<?=$owner?>><?=$select_options($price_change_method_options)?></select>
                <div class="collapse" id="increase_price_row">
                    <div class="border rounded-4 p-5 mt-5">
                        <label for="price_value" id="price_value_label" class="form-label"><?=h(lang('Amount'))?></label>
                        <div class="input-group">
                            <span id="value_type" class="input-group-text"><?=h(BASE_CURRENCY_CODE)?></span>
                            <input type="text" id="price_value" name="edit_price_value" inputmode="decimal" class="form-control"<?=pg_money_mask_html()?><?=$owner?>>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 md:col-6">
                <label for="tax_rate_method" class="form-label"><?=lang(array('string'=>'Tax Rate' ))?></label>
                <select id="tax_rate_method" name="edit_tax_rate_method" class="form-control"<?=$owner?>><?=$select_options($tax_rate_method_options)?></select>
            </div>
            <div class="col-12 md:col-6">
                <label for="tax_rate_value" class="form-label"><?=h(lang('New Tax Rate'))?></label>
                <div class="input-group">
                    <input type="number" id="tax_rate_value" name="edit_tax_rate_value" min="0" max="100" step="0.001" placeholder="20" aria-describedby="tax_rate_value_hint" class="form-control"<?=$owner?>>
                    <span class="input-group-text">%</span>
                </div>
                <div class="form-text" id="tax_rate_value_hint"><?=lang(array('string'=>'Used only when the rate is being set.' ))?></div>
            </div>
            <?php if (pg_product_customs_ready()): ?>
            <?php
            // Customs data (products.hs_code, origin_country): left as it is,
            // set to the value beside, or cleared to the shop default.
            $customs_method_options = function ($label) {
                return array(
                    lang(array('string' => 'Select to change {var:1}', 'vars' => array($label))) => '',
                    lang('Set to the value beside') => 'set',
                    lang('Clear (use the shop default)') => 'clear',
                );
            };
            ?>
            <div class="col-12 md:col-6">
                <label for="hs_code_method" class="form-label"><?=h(lang('HS Code'))?></label>
                <select id="hs_code_method" name="edit_hs_code_method" class="form-control" aria-describedby="customs_hint"<?=$owner?>><?=$select_options($customs_method_options(lang('HS Code')))?></select>
            </div>
            <div class="col-12 md:col-6">
                <label for="hs_code" class="form-label"><?=h(lang('New HS Code'))?></label>
                <input type="text" id="hs_code" name="edit_hs_code" maxlength="<?=PG_PRODUCT_HS_CODE_MAX?>" inputmode="decimal" pattern="[0-9]+(\.[0-9]+)*" placeholder="6109.10" aria-describedby="customs_hint" class="form-control"<?=$owner?>>
            </div>
            <div class="col-12 md:col-6">
                <label for="origin_country_method" class="form-label"><?=h(lang('Country of Origin'))?></label>
                <select id="origin_country_method" name="edit_origin_country_method" class="form-control" aria-describedby="customs_hint"<?=$owner?>><?=$select_options($customs_method_options(lang('Country of Origin')))?></select>
            </div>
            <div class="col-12 md:col-6">
                <label for="origin_country" class="form-label"><?=h(lang('New Country of Origin'))?></label>
                <select id="origin_country" name="edit_origin_country" aria-describedby="customs_hint" class="form-control"<?=$owner?>><?=pg_product_origin_country_options('')?></select>
            </div>
            <div class="col-12">
                <div class="form-text mt-0" id="customs_hint"><?=lang(array('string' => 'Used only when the value is being set. Empty uses the shop default of the label service.'))?></div>
            </div>
            <?php endif; ?>
            <div class="col-12 md:col-6">
                <label for="inventory" class="form-label"><?=lang(array('string'=>'Track Inventory' ))?></label>
                <select id="inventory" name="edit_inventory" class="form-control"<?=$owner?>><?=$select_options($inventory_options)?></select>
            </div>
            <div class="col-12 md:col-6">
                <label for="inventory_quantity_process" class="form-label"><?=lang(array('string'=>'Inventory Quantity Process' ))?></label>
                <select id="inventory_quantity_process" name="edit_inventory_quantity_process" class="form-control collapse-if-selected" data-bs-target="#increase_quantity_row"<?=$owner?>><?=$select_options($inventory_quantity_process_options)?></select>
                <div class="collapse" id="increase_quantity_row">
                    <div class="border rounded-4 p-5 mt-5">
                        <label for="inventory_quantity" class="form-label"><?=h(lang('Quantity'))?></label>
                        <div class="input-group number-controls">
                            <button class="btn material-icons minus border border-end-0 no-submit" type="button" aria-label="<?=h(lang('Decrease'))?>">remove</button>
                            <input type="text" id="inventory_quantity" name="edit_inventory_quantity" class="form-control text-center border-start-0 border-end-0" maxlength="9" inputmode="numeric" data-inputmask-alias="integer" data-inputmask-allowminus="false" data-inputmask-placeholder="0"<?=$owner?>>
                            <button class="btn material-icons plus border border-start-0 no-submit" type="button" aria-label="<?=h(lang('Increase'))?>">add</button>
                        </div>
                        <?php if ($stock_locations): ?>
                        <label for="inventory_location" class="form-label mt-5"><?=h(lang('Location'))?></label>
                        <select id="inventory_location" name="edit_inventory_location" class="form-control"<?=$owner?>>
                            <?php foreach ($stock_locations as $stock_location): ?>
                            <option value="<?=(int) $stock_location['id']?>"><?=h($stock_location['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($zones !== null): ?>
            <?php foreach (array('allowed' => lang('Allow Shipping Zones'), 'disallowed' => lang('Disallow Shipping Zones')) as $zone_list => $zone_title): ?>
            <div class="col-12 md:col-6">
                <fieldset class="border rounded-4 p-5 h-100">
                    <legend class="form-label fs-md float-none w-auto px-1 mb-3"><?=$zone_title?></legend>
                    <?php foreach ($zones as $zone): ?>
                    <div class="form-field">
                        <input type="checkbox" id="<?=$zone_list?>_zone_<?=(int) $zone['id']?>" name="<?=$zone_list?>_zones" value="<?=(int) $zone['id']?>" class="checkbox check">
                        <label for="<?=$zone_list?>_zone_<?=(int) $zone['id']?>"><?=h($zone['name'])?></label>
                    </div>
                    <?php endforeach; ?>
                </fieldset>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <input type="hidden" name="edit_allowed_zones"<?=$owner?>>
        <input type="hidden" name="edit_disallowed_zones"<?=$owner?>>
    </div>
    <div class="dialog-footer">
        <?php if (defined('BARCODE_ENABLED') && BARCODE_ENABLED): ?>
        <button type="button" class="btn btn-outline theme-secondary no-submit me-auto" data-pg-modify-barcodes><i class="bi bi-upc-scan me-5" aria-hidden="true"></i><?=lang('Assign Barcodes')?></button>
        <?php endif ?>
        <button type="button" class="btn btn-solid theme-secondary no-submit" data-bs-dismiss="dialog"><?=lang('Cancel')?></button>
        <button type="button" class="btn btn-solid theme-primary no-submit" data-pg-modify-save><i class="bi bi-check-lg me-5" aria-hidden="true"></i><?=lang(array('string'=>'Modify Products') )?></button>
    </div>
</dialog>
<script>
    // Modify Selected: the dialog opens empty, so nothing left from an earlier
    // opening is posted; Save checks the boxes the post needs, writes the zone
    // lists and submits the page form only once the dialog has closed.
    (function () {
        var dialog = document.getElementById("product_modify_dialog");
        var form = dialog ? document.getElementById(dialog.getAttribute("data-pg-modify-form")) : null;

        if (!dialog || !form) {
            return;
        }

        function field(id) {
            return document.getElementById(id);
        }

        function ticked(name) {
            return Array.prototype.map.call(
                dialog.querySelectorAll('input[name="' + name + '"]:checked'),
                function (box) { return box.value; }
            ).join(",");
        }

        function send(action) {
            form.elements.action.value = action;
            dialog.querySelectorAll("[data-pg-modify-save], [data-pg-modify-barcodes]").forEach(function (button) {
                button.disabled = true;
            });
            pgUI.dialog.hide(dialog).then(function () {
                form.submit();
            });
        }

        // The box of the price change names an amount in the base currency
        // or a percent, as the method says.
        function change_price_methodfunc() {
            var method = field("change_price_method").value;
            var price_value_label = field("price_value_label");

            if ((method == "1") || (method == "2")) {
                field("value_type").textContent = <?=json_encode(BASE_CURRENCY_CODE)?>;
                price_value_label.textContent = <?=json_encode(lang('Amount'))?>;
            } else if ((method == "3") || (method == "4")) {
                field("value_type").textContent = "%";
                price_value_label.textContent = <?=json_encode(lang('Percent'))?>;
            }
        }

        // Opens the dialog empty.
        function open() {
            dialog.querySelectorAll("select").forEach(function (select) {
                select.selectedIndex = 0;
                select.dispatchEvent(new Event("change", { bubbles: true }));
            });
            dialog.querySelectorAll("input[type=text], input[type=number]").forEach(function (box) {
                box.value = "";
            });
            dialog.querySelectorAll("input[type=checkbox]").forEach(function (box) {
                box.checked = false;
            });
            dialog.querySelectorAll("[data-pg-modify-save], [data-pg-modify-barcodes]").forEach(function (button) {
                button.disabled = false;
            });
            pgUI.dialog.show(dialog);
        }

        document.addEventListener("click", function (event) {
            if (event.target.closest("[data-pg-modify-open]")) {
                open();
            }
        });

        dialog.addEventListener("change", function (event) {
            var box = event.target;

            if (box.id == "change_price_method") {
                change_price_methodfunc();
            }

            // A zone is either allowed or disallowed: ticking it in one list
            // clears it in the other.
            if (box.checked && ((box.name == "allowed_zones") || (box.name == "disallowed_zones"))) {
                var other = field(((box.name == "allowed_zones") ? "disallowed_zone_" : "allowed_zone_") + box.value);
                if (other) {
                    other.checked = false;
                }
            }
        });

        dialog.querySelector("[data-pg-modify-save]").addEventListener("click", function () {
            // The boxes' own limits are asked here: the tax rate box (0..100)
            // when a rate is being set, and a stock process needs a figure.
            if (field("tax_rate_method").value == "set") {
                var tax_rate_box = field("tax_rate_value");
                tax_rate_box.required = true;
                var tax_rate_valid = tax_rate_box.reportValidity();
                tax_rate_box.required = false;
                if (!tax_rate_valid) {
                    return;
                }
            }
            // An HS code or a country of origin being set needs a valid
            // value; Clear stands for the shop default.
            var customs = [
                ["hs_code_method", "hs_code", <?=json_encode(lang('Enter an HS code, or choose to clear it.'))?>],
                ["origin_country_method", "origin_country", <?=json_encode(lang('Choose a country of origin, or choose to clear it.'))?>]
            ];
            for (var i = 0; i < customs.length; i++) {
                var customs_method = field(customs[i][0]);
                var customs_box = field(customs[i][1]);
                if (customs_method && (customs_method.value == "set")) {
                    customs_box.setCustomValidity(customs_box.value.trim() == "" ? customs[i][2] : "");
                    var customs_valid = customs_box.reportValidity();
                    customs_box.setCustomValidity("");
                    if (!customs_valid) {
                        return;
                    }
                }
            }
            if ((field("inventory_quantity_process").value != "") && (field("inventory_quantity").value == "")) {
                var quantity_box = field("inventory_quantity");
                quantity_box.setCustomValidity(<?=json_encode(lang('Enter an inventory quantity.'))?>);
                quantity_box.reportValidity();
                quantity_box.setCustomValidity("");
                return;
            }

            form.elements.edit_allowed_zones.value = ticked("allowed_zones");
            form.elements.edit_disallowed_zones.value = ticked("disallowed_zones");
            send("edit");
        });

        var barcodes = dialog.querySelector("[data-pg-modify-barcodes]");
        if (barcodes) {
            barcodes.addEventListener("click", function () {
                send("assign_barcodes");
            });
        }
    }());
</script>
