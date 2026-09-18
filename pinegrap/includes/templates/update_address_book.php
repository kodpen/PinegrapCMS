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
	<div class="form-group">
		<label for="ship_to_name"><?=h(lang('Ship to Name'))?>*</label>
		<input type="text" name="ship_to_name" id="ship_to_name" class="form-control">
	</div>
	<div class="form-group">
		<label for="salutation"><?=h(lang('Salutation'))?></label>
		<select name="salutation" id="salutation" class="form-control"></select>
	</div>
	<div class="form-group">
		<label for="first_name"><?=h(lang('First Name'))?>*</label>
		<input type="text" name="first_name" id="first_name" class="form-control">
	</div>
	<div class="form-group">
		<label for="last_name"><?=h(lang('Last Name'))?>*</label>
		<input type="text" name="last_name" id="last_name" class="form-control">
	</div>
	<div class="form-group">
		<label for="company"><?=h(lang('Organization'))?></label>
		<input type="text" name="company" id="company" class="form-control">
	</div>
	<div class="form-group">
		<label for="address_1"><?=h(lang('Address 1'))?>*</label>
		<input type="text" name="address_1" id="address_1" class="form-control">
	</div>
	<div class="form-group">
		<label for="address_2"><?=h(lang('Address 2'))?></label>
		<input type="text" name="address_2" id="address_2" class="form-control">
	</div>
	<div class="form-group">
		<label for="city"><?=h(lang('City'))?>*</label>
		<input type="text" name="city" id="city" class="form-control">
	</div>
	<div class="form-group">
		<label for="country"><?=h(lang('Country'))?>*</label>
		<select name="country" id="country" class="form-control"></select>
	</div>
	<div class="form-group">
		<label for="state_text_box"><?=h(lang('State / Province'))?></label>
		<input type="text" name="state" id="state_text_box" class="form-control">
		<label for="state_pick_list" style="display: none"><?=h(lang('State / Province'))?>*</label>
		<select name="state" id="state_pick_list" class="form-control" style="display: none"></select>
	</div>
	<div class="form-group">
		<label for="zip_code"><?=h(lang('Zip / Postal Code'))?><span id="zip_code_required" style="display: none">*</span></label>
		<input type="text" name="zip_code" id="zip_code" class="form-control">
	</div>
	<?php if ($address_type): ?>
	<div>
		<?=h(lang('Address Type'))?>*
		<?php if ($address_type_url): ?>
		&nbsp;<a href="<?=h($address_type_url)?>" target="_blank"><?=h(lang('What is this?'))?></a>
		<?php endif ?>
	</div>
	<div class="radio">
		<label>
		<input type="radio" name="address_type" value="residential">
		<?=h(lang('Residential'))?>
		</label>
	</div>
	<div class="radio">
		<label>
		<input type="radio" name="address_type" value="business">
		<?=h(lang('Business'))?>
		</label>
	</div>
	<?php endif ?>
	<div class="form-group">
		<label for="phone_number"><?=h(lang('Phone'))?></label>
		<input type="tel" name="phone_number" id="phone_number" class="form-control">
	</div>
	<button type="submit" class="btn btn-primary"><?=h(lang('Update'))?></button>
	<a href="<?=h($my_account_url)?>" class="btn btn-default btn-secondary"><?=h(lang('Cancel'))?></a>
	<!-- Required hidden fields and JS (do not remove) -->
	<?=$system?>
</form>