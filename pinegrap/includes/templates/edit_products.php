<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// This file is a view partial. It is required from a page that has already run
// init.php and it depends on the constants and variables that bootstrap sets up.
// Requested directly over the web it runs with none of them and dies on the first
// constant it touches -- which is exactly how it turned up in the error log.
if (!defined('PG_INIT_LOADED')) {
    http_response_code(403);
    exit;
}
?>
<?=output_header_secure(array('title'=>lang('Modify Products'),'icon'=>'store'))?>
<script type="text/javascript">
	function assignBarcodes() {
		opener.document.form.action.value = 'assign_barcodes';
		opener.document.form.submit();
		window.close();
	}

	function edit_products() {
		// If the user selected an option for the enabled field then update field in the form.
		if(document.getElementById("enabled").value != "") {
			opener.document.form.edit_enabled.value = document.getElementById("enabled").value;
		}
		// If the user selected an option for the enabled field then update field in the form.
		if(document.getElementById("change_price_method").value != "") {
			var $change_price_value = document.getElementById("change_price_method").value;
			opener.document.form.edit_change_price_method.value = $change_price_value;
			var $price_value = document.getElementById("price_value").value;
			opener.document.form.edit_price_value.value = $price_value;
		}

		// If the user selected an option for the inventory field then update field in the form.
		if(document.getElementById("inventory").value != "") {
			opener.document.form.edit_inventory.value = document.getElementById("inventory").value;
		}
		// If the user selected an option for the inventory_quantity_process field then update field in the form.
		if(document.getElementById("inventory_quantity_process").value != "") {
			opener.document.form.edit_inventory_quantity_process.value = document.getElementById("inventory_quantity_process").value;
		}
		// If the user selected an option for the inventory_quantity field then update field in the form.
		if(document.getElementById("inventory_quantity").value != "") {
			opener.document.form.edit_inventory_quantity.value = document.getElementById("inventory_quantity").value;
		}
		// The tax rate carries two fields: what to do, and the rate it needs when
		// the answer is "set". Clearing needs no rate, so the value only travels
		// with the method that uses it.
		if(document.getElementById("tax_rate_method").value != "") {
			opener.document.form.edit_tax_rate_method.value = document.getElementById("tax_rate_method").value;
			opener.document.form.edit_tax_rate_value.value = document.getElementById("tax_rate_value").value;
		}
		
		// If shipping is enabled, then deal with allowed/disallowed zones.
		if(document.form.allowed_zones) { 
			opener.document.form.edit_allowed_zones.value = "";
			// Loop through all allowed zone checkboxes.
			for(i = 0; i < document.form.allowed_zones.length; i++) {
				// If zone checkbox is checked, then add zone to hidden form field on opener.
				if(document.form.allowed_zones[i].checked == true) {
					// If there is already zones in the list of zones, then add a comma first.
					if(opener.document.form.edit_allowed_zones.value) {
						opener.document.form.edit_allowed_zones.value += ",";
					}
					opener.document.form.edit_allowed_zones.value += document.form.allowed_zones[i].value;
				}
			}
			opener.document.form.edit_disallowed_zones.value = "";
			// Loop through all disallowed zone checkboxes.
			for(i = 0; i < document.form.disallowed_zones.length; i++) {
				// If zone checkbox is checked, then add zone to hidden form field on opener.
				if(document.form.disallowed_zones[i].checked == true) {
					// If there is already zones in the list of zones, then add a comma first.
					if(opener.document.form.edit_disallowed_zones.value) {
						opener.document.form.edit_disallowed_zones.value += ",";
					}
					opener.document.form.edit_disallowed_zones.value += document.form.disallowed_zones[i].value;
				}
			}
		}
		opener.document.form.submit();
		window.close();
	}
</script>

<nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body">
    <div class="container-fluid">
		<span class="navbar-text me-auto" title="<?=lang(array('string'=>'You may update the selected Products via the form below.' ))?>">
			<?=lang(array('string'=>'Modify Products' ))?>
		</span>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown no-popover"  title="' . lang('Software Theme') . '">
                <button class="nav-link nav-link-sm position-relative dropdown-toggle dropdown-menu-right d-none" data-bs-toggle="dropdown" id="bd-theme" type="button"><span class="bi bi-circle-half"></span></button>
                <ul aria-labelledby="bd-theme" class="dropdown-menu shadow dropdown-menu-end p-1 bg-body backdrop mt-nav-link-sm border-dropdown-menu" data-bs-popper="static" style="--bs-dropdown-min-width: 8rem;">
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="light" type="button"><i class="bi bi-sun-fill m-2"></i><?=lang('Light')?></button></li>
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center active" data-bs-theme-value="dark" type="button"><i class="bi bi-moon-stars-fill m-2"></i><?=lang('Dark')?></button></li>
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="auto" type="button"><i class="bi bi-circle-half m-2"></i><?=lang('Auto')?></button></li>
                </ul>
            </li>
            <li class="nav-item">
                <button title="<?=lang(array('string'=>'Close' ))?>" type="button" class="nav-link nav-link-sm position-relative no-popover" onclick="window.close()" aria-label="Close">
                    <span class="bi bi-x-lg"></span>
                </button>
            </li>
        </ul>
    </div>
</nav>
<main id="content" class="container-fluid">
	<form name="form">
		<div class="row">
			<div class="col-xs-12 col-sm-6 col-md-4 col-lg-3">
				<div class="form-floating mt-1 mb-2">
					<?=$liveform->output_field(array(
						'type' => 'select',
						'id' => 'enabled',
						'name' => 'enabled',
						'class'=>'form-select',
						'options' => $enabled_options))?>
					<label for="enabled" class="form-label"><?=lang(array('string'=>'Status' ))?></label>
				</div>
			</div>
		</div>
		<div class="row mt-1 mb-2">
			<div class="col-xs-12 col-sm-6 col-md-4 col-lg-3">
				<div class="form-floating  mt-1 mb-2">
					<?=$liveform->output_field(array(
						'type' => 'select',
						'id' => 'change_price_method',
						'name' => 'change_price_method',
						'class'=>'form-select collapse-if-selected',
						'data-bs-target'=>'#increase_price_row',
						'options' => $price_change_method_options,
						'onchange'=>'change_price_methodfunc()'))?>
					<label for="enabled" class="form-label"><?=lang(array('string'=>'Price Change Method' ))?></label>
				</div>
				<div class="popover fade bs-popover-bottom p-0 mb-2 w-100 collapse" id="increase_price_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
							<div class="input-group my-1">
								<span id="value_type" class="input-group-text"><?=BASE_CURRENCY_CODE?></span>
								<?=$liveform->output_field(array(
									'type' => 'number',
									'placeholder' =>'12,30',
									'id' => 'price_value',
									'class'=>'form-control'))?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row mt-1 mb-2">
			<div class="col-12 col-sm-6 col-md-4 col-lg-3">
				<div class="form-floating mt-1 mb-2">
					<?=$liveform->output_field(array(
						'type' => 'select',
						'id' => 'tax_rate_method',
						'name' => 'tax_rate_method',
						'class'=>'form-select',
						'options' => $tax_rate_method_options))?>
					<label for="tax_rate_method" class="form-label"><?=lang(array('string'=>'Tax Rate' ))?></label>
				</div>
			</div>
			<div class="col-12 col-sm-6 col-md-4 col-lg-3">
				<div class="input-group mt-1 mb-2">
					<?=$liveform->output_field(array(
						'type' => 'number',
						'id' => 'tax_rate_value',
						'name' => 'tax_rate_value',
						'min' => '0',
						'max' => '100',
						'step' => '0.001',
						'placeholder' => '20',
						'class'=>'form-control'))?>
					<span class="input-group-text">%</span>
				</div>
				<div class="form-text"><?=lang(array('string'=>'Used only when the rate is being set.' ))?></div>
			</div>
		</div>
		<div class="row mt-1 mb-2">
			<div class="col-12 col-sm-6 col-md-4 col-lg-3">
				<div class="form-floating  mt-1 mb-2">
					<?=$liveform->output_field(array(
						'type' => 'select',
						'id' => 'inventory',
						'name' => 'inventory',
						'class'=>'form-select',
						'options' => $inventory_options))?>
					<label for="enabled" class="form-label"><?=lang(array('string'=>'Track Inventory' ))?></label>
				</div>
			</div>
			<div class="col-12 col-sm-6 col-md-auto">
				<div class="form-floating  mt-1 mb-2">
					<?=$liveform->output_field(array(
						'type' => 'select',
						'id' => 'inventory_quantity_process',
						'name' => 'inventory_quantity_process',
						'class'=>'form-select collapse-if-selected',
						'data-bs-target'=>'#increase_quantity_row',
						'options' => $inventory_quantity_process_options))?>
					<label for="enabled" class="form-label"><?=lang(array('string'=>'Inventory Quantity Process' ))?></label>
				</div>
				<div class="popover fade bs-popover-bottom p-0 mb-2 w-100 collapse" id="increase_quantity_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
							<div class="input-group number-controls">
                                <button class="btn material-icons minus border border-end-0" type="button">remove</button>
                                <?=$liveform->output_field(array(
									'type' => 'text',
									'id' => 'inventory_quantity',
									'name' => 'inventory_quantity',
									'class'=>'form-control text-center border-start-0 border-end-0',
									'maxlength'=>'9',
									'inputmode'=>'numeric',
									'data-inputmask-alias'=>'decimal',
									'data-inputmask-placeholder'=>'0'						
									
									))?>
                                <button class="btn material-icons plus border border-start-0" type="button">add</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php if (ECOMMERCE_SHIPPING): ?>
		<div class="row ">
			<div class="col-xs-12  col-sm-12 col-md-8 col-lg-6 card-group">
				<div class="card mt-1 mb-2">
					<div class="card-header"><?=lang(array('string'=>'Allow Shipping Zones') )?></div>
					<div class="card-body">
						<?php foreach($zones as $zone): ?>
						<div class="form-check">
							<?=$liveform->output_field(array(
								'type' => 'checkbox',
								'id' => 'allowed_zone_' . $zone['id'],
								'name' => 'allowed_zones',
								'value' => $zone['id'],
								'class' => 'checkbox form-check-input'))?>
							<label class="form-check-label" for="allowed_zone_<?=$zone['id']?>"><?=h($zone['name'])?></label>    
						</div>
						<?php endforeach ?>
					</div>
				</div>
				<div class="card mt-1 mb-2">
					<div class="card-header"><?=lang(array('string'=>'Disallow Shipping Zones') )?></div>
					<div class="card-body">
						<?php foreach($zones as $zone): ?>
						<div class="form-check">
							<?=$liveform->output_field(array(
								'type' => 'checkbox',
								'id' => 'disallowed_zone_' . $zone['id'],
								'name' => 'disallowed_zones',
								'value' => $zone['id'],
								'class' => 'checkbox form-check-input'))?>
							<label class="form-check-label" for="disallowed_zone_<?=$zone['id']?>"><?=h($zone['name'])?></label>
						</div>
						<?php endforeach ?>
					</div>
				</div>
			</div>
		</div>
		<?php endif ?>
		<nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
			<div class="container">
				<div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0">
					<button type="button" name="submit_save" value="Modify Products" class="btn mb-1 mt-1  btn-primary submit-primary" onclick="edit_products()"><span class="material-icons me-2">save</span><?=lang(array('string'=>'Modify Products') )?></button>
					<?php if (defined('BARCODE_ENABLED') && BARCODE_ENABLED): ?>
					<button type="button" class="btn mb-1 mt-1 btn-outline-secondary" onclick="assignBarcodes()"><i class="bi bi-upc-scan me-2"></i><?=lang('Assign Barcodes')?></button>
					<?php endif ?>
				</div>
			</div>
		</nav>
	</form>
</main>
<script>
	function change_price_methodfunc(){
		var base_currency = '<?=h(BASE_CURRENCY_CODE)?>';
		var percentiles = '\%';
		var $value_type =  document.getElementById("value_type");
		var $change_price = document.getElementById("change_price_method").value;
		var $price_value_input = document.getElementById("price_value");
		
		if(($change_price == '1') || ($change_price == '2')){
			$value_type.innerHTML = base_currency;
			$price_value_input.placeholder='12,30';
	        $price_value_input.focus(); 
		}
		else if(($change_price == '3') || ($change_price == '4')){
			$value_type.innerHTML = percentiles;
			$price_value_input.placeholder='30';
	        $price_value_input.focus(); 
		}
		
	}
</script>
<?=output_footer_secure();?>