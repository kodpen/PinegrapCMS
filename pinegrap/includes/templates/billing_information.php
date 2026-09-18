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
<?=$messages?>
<form <?=$attributes?>>
	<?php if ($custom_field_1): ?>
	<div class="form-group">
		<label for="custom_field_1"><?=h($custom_field_1_label)?><?php if ($custom_field_1_required): ?>*<?php endif ?></label>
		<input type="text" name="custom_field_1" id="custom_field_1" class="form-control">
	</div>
	<?php endif ?>
	<?php if ($custom_field_2): ?>
	<div class="form-group">
		<label for="custom_field_2"><?=h($custom_field_2_label)?><?php if ($custom_field_2_required): ?>*<?php endif ?></label>
		<input type="text" name="custom_field_2" id="custom_field_2" class="form-control">
	</div>
	<?php endif ?>
	<div class="form-group">
		<label for="billing_salutation"><?=h(lang('Salutation'))?></label>
		<select name="billing_salutation" id="billing_salutation" class="form-control"></select>
	</div>
	<div class="form-group">
		<label for="billing_first_name"><?=h(lang('First Name'))?>*</label>
		<input type="text" name="billing_first_name" id="billing_first_name" class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_last_name"><?=h(lang('Last Name'))?>*</label>
		<input type="text" name="billing_last_name" id="billing_last_name" class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_company"><?=h(lang('Company'))?></label>
		<input type="text" name="billing_company" id="billing_company" class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_address_1"><?=h(lang('Address 1'))?>*</label>
		<input type="text" name="billing_address_1" id="billing_address_1" class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_address_2"><?=h(lang('Address 2'))?></label>
		<input type="text" name="billing_address_2" id="billing_address_2" class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_city"><?=h(lang('City'))?>*</label>
		<input type="text" name="billing_city" id="billing_city" class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_country"><?=h(lang('Country'))?>*</label>
		<select name="billing_country" id="billing_country" class="form-control"></select>
	</div>
	<div class="form-group">
		<label for="billing_state_text_box"><?=h(lang('State / Province'))?></label>
		<input type="text" name="billing_state" id="billing_state_text_box" class="form-control">
		<label for="billing_state_pick_list" style="display: none"><?=h(lang('State / Province'))?>*</label>
		<select name="billing_state" id="billing_state_pick_list" class="form-control" style="display: none"></select>
	</div>
	<div class="form-group">
		<label for="billing_zip_code"><?=h(lang('Zip / Postal Code'))?><span id="billing_zip_code_required" style="display: none">*</span></label>
		<input type="text" name="billing_zip_code" id="billing_zip_code" class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_phone_number"><?=h(lang('Phone'))?>*</label>
		<input type="tel" name="billing_phone_number" id="billing_phone_number" class="form-control">
	</div>
	<div class="form-group">
		<label for="billing_email_address"><?=h(lang('Email'))?>*</label>
		<input type="email" name="billing_email_address" id="billing_email_address" class="form-control">
	</div>
	<?php if ($opt_in): ?>
	<div class="checkbox">
		<label>
		<input type="checkbox" name="opt_in" value="1">
		<?=h($opt_in_label)?>
		</label>
	</div>
	<?php endif ?>
	<?php if ($po_number): ?>
	<div class="form-group">
		<label for="po_number"><?=h(lang('PO Number'))?></label>
		<input type="text" name="po_number" id="po_number" class="form-control">
	</div>
	<?php endif ?>
	<?php if ($referral_source): ?>
	<div class="form-group">
		<label for="referral_source"><?=h(lang('How did you hear about us?'))?></label>
		<select name="referral_source" id="referral_source" class="form-control"></select>
	</div>
	<?php endif ?>
	<?php if ($update_contact): ?>
	<div class="checkbox">
	   <label>
		<input type="checkbox" name="update_contact" value="1">
		<?=h(lang('Update my contact info with this billing info.'))?>
		</label>
	</div>
	<?php endif ?>
	<?php if ($tax_exempt): ?>
	<div class="checkbox">
		<label>
		<input type="checkbox" name="tax_exempt" value="1">
		<?=h($tax_exempt_label)?>
		</label>
	</div>
	<?php endif ?>
	<?php if ($custom_billing_form): ?>
	<?=$edit_start // Add edit button and grid if edit mode is enabled. ?>
	<?php if ($custom_billing_form_title): ?>
	<h2><?=h($custom_billing_form_title)?></h2>
	<?php endif ?>
	<?=eval('?>' . generate_form_layout_content(array('page_id' => $page_id, 'indent' => '        ')))?>
	<?=$edit_end // Close the edit grid. ?>
	<?php endif ?>
	<button type="submit" class="btn btn-primary"><?=h($submit_button_label)?></button>
	<!-- Required hidden fields and JS (do not remove) -->
	<?=$system?>
</form>