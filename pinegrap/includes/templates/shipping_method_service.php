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
<select id="service" name="service" class="form-select">
	<option value=""></option>
	<?php if (USPS_USER_ID): ?>
	<optgroup label="USPS">
		<option value="usps_express"><?=h(get_shipping_service_name('usps_express'))?></option>
		<option value="usps_priority"><?=h(get_shipping_service_name('usps_priority'))?></option>
		<option value="usps_ground"><?=h(get_shipping_service_name('usps_ground'))?></option>
	</optgroup>
	<?php endif ?>
	<?php if (UPS): ?>
	<optgroup label="UPS">
		<option value="ups_next_day_air"><?=h(get_shipping_service_name('ups_next_day_air'))?></option>
		<option value="ups_next_day_air_early"><?=h(get_shipping_service_name('ups_next_day_air_early'))?></option>
		<option value="ups_next_day_air_saver"><?=h(get_shipping_service_name('ups_next_day_air_saver'))?></option>
		<option value="ups_2nd_day_air"><?=h(get_shipping_service_name('ups_2nd_day_air'))?></option>
		<option value="ups_2nd_day_air_am"><?=h(get_shipping_service_name('ups_2nd_day_air_am'))?></option>
		<option value="ups_3_day_select"><?=h(get_shipping_service_name('ups_3_day_select'))?></option>
		<option value="ups_ground"><?=h(get_shipping_service_name('ups_ground'))?></option>
	</optgroup>
	<?php endif ?>
	<?php if (FEDEX): ?>
	<optgroup label="FedEx">
		<option value="fedex_first_overnight"><?=h(get_shipping_service_name('fedex_first_overnight'))?></option>
		<option value="fedex_priority_overnight"><?=h(get_shipping_service_name('fedex_priority_overnight'))?></option>
		<option value="fedex_standard_overnight"><?=h(get_shipping_service_name('fedex_standard_overnight'))?></option>
		<option value="fedex_2_day_am"><?=h(get_shipping_service_name('fedex_2_day_am'))?></option>
		<option value="fedex_2_day"><?=h(get_shipping_service_name('fedex_2_day'))?></option>
		<option value="fedex_express_saver"><?=h(get_shipping_service_name('fedex_express_saver'))?></option>
		<option value="fedex_ground"><?=h(get_shipping_service_name('fedex_ground'))?></option>
	</optgroup>
	<?php endif ?>
</select>