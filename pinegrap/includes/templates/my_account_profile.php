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
	<h2><?=h(lang('Contact Information'))?></h2>
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
		<label for="suffix"><?=h(lang('Suffix'))?></label>
		<select name="suffix" id="suffix" class="form-control"></select>
	</div>
	<div class="form-group">
		<label for="title"><?=h(lang('Title'))?></label>
		<input type="text" name="title" id="title" class="form-control">
	</div>
	<div class="form-group">
		<label for="business_phone"><?=h(lang('Main Phone'))?></label>
		<input type="tel" name="business_phone" id="business_phone" class="form-control">
	</div>
	<div class="form-group">
		<label for="mobile_phone"><?=h(lang('Mobile Phone'))?></label>
		<input type="tel" name="mobile_phone" id="mobile_phone" class="form-control">
	</div>
	<div class="form-group">
		<label for="home_phone"><?=h(lang('Home Phone'))?></label>
		<input type="tel" name="home_phone" id="home_phone" class="form-control">
	</div>
	<div class="form-group">
		<label for="business_fax"><?=h(lang('Fax'))?></label>
		<input type="tel" name="business_fax" id="business_fax" class="form-control">
	</div>
	<h2><?=h(lang('Billing / Mailing Address'))?></h2>
	<div class="form-group">
		<label for="company"><?=h(lang('Organization'))?></label>
		<input type="text" name="company" id="company" class="form-control">
	</div>
	<div class="form-group">
		<label for="business_address_1"><?=h(lang('Address 1'))?></label>
		<input type="text" name="business_address_1" id="business_address_1" class="form-control">
	</div>
	<div class="form-group">
		<label for="business_address_2"><?=h(lang('Address 2'))?></label>
		<input type="text" name="business_address_2" id="business_address_2" class="form-control">
	</div>
	<div class="form-group">
		<label for="business_city"><?=h(lang('City'))?></label>
		<input type="text" name="business_city" id="business_city" class="form-control">
	</div>
	<div class="form-group">
		<label for="business_country"><?=h(lang('Country'))?></label>
		<select name="business_country" id="business_country" class="form-control"></select>
	</div>
	<div class="form-group">
		<label for="business_state_text_box"><?=h(lang('State / Province'))?></label>
		<input type="text" name="business_state" id="business_state_text_box" class="form-control">
		<label for="business_state_pick_list" style="display: none"><?=h(lang('State / Province'))?></label>
		<select name="business_state" id="business_state_pick_list" class="form-control" style="display: none"></select>
	</div>
	<div class="form-group">
		<label for="business_zip_code"><?=h(lang('Zip / Postal Code'))?></label>
		<input type="text" name="business_zip_code" id="business_zip_code" class="form-control">
	</div>
	<?php if ($timezone): ?>
	<div class="form-group">
		<label for="timezone"><?=h(lang('Timezone'))?></label>
		<select name="timezone" id="timezone" class="form-control"></select>
	</div>
	<?php endif ?>
	<button type="submit" class="btn btn-primary"><?=h(lang('Update'))?></button>
	<a href="<?=h($my_account_url)?>" class="btn btn-default btn-secondary"><?=h(lang('Cancel'))?></a>
	<!-- Required hidden fields and JS (do not remove) -->
	<?=$system?>
</form>
<?=$account_security ?? ''?>