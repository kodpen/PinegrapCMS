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
	<?php
		// If there is a requested arrival date, then start two-column structure
		
		// for the address in one column and the requested arrival date in the other.
		
		if ($arrival_date):
		
		?>
	<div class="row">
		<div class="col-sm-6">
			<?php endif ?>
			<h2>
				<?=h(lang('Shipping Address'))?>
				<?php if (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient'): ?>
				<?=lang(array('string' => 'for <strong>{var:1}</strong>', 'vars' => array(h($ship_to_name))))?>
				<?php endif ?>
				<a href="<?=h($update_url)?>" class="btn btn-default btn-secondary btn-sm">
				<?=h(lang('Update'))?>
				</a>
			</h2>
			<p>
				<?=h($salutation)?> <?=h($first_name)?> <?=h($last_name)?><br>
				<?php if ($company): ?>
				<?=h($company)?><br>
				<?php endif ?>
				<?=h($address_1)?><br>
				<?php if ($address_2): ?>
				<?=h($address_2)?><br>
				<?php endif ?>
				<?=h($city)?>, <?=h($state)?> <?=h($zip_code)?><br>
				<?=h($country)?>
			</p>
			<?php if ($arrival_date): ?>
		</div>
		<div class="col-sm-6">
			<h2>
				<?=h(lang('Requested Arrival Date'))?>
				<a href="<?=h($update_url)?>" class="btn btn-default btn-secondary btn-sm">
				<?=h(lang('Update'))?>
				</a>
			</h2>
			<p>
				<?php if ($arrival_date['custom']): ?>
				<?=$arrival_date['date_info']?>
				<?php else: ?>
				<?=h($arrival_date['name'])?>
				<?php endif ?>
			</p>
		</div>
	</div>
	<?php endif ?>
	<?php if (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient'): ?>
	<h2><?=h(lang('Ship to'))?> <strong><?=h($ship_to_name)?></strong></h2>
	<?php endif ?>
	<table class="table mobile_stacked">
		<tr>
			<th><?=h(lang('Item'))?></th>
			<th><?=h(lang('Description'))?></th>
			<th class="text-center"><?=h(lang('Qty'))?></th>
			<th class="text-right"><?=h(lang('Price'))?></th>
			<th class="text-right"><?=h(lang('Amount'))?></th>
			<th></th>
		</tr>
		<?php foreach($items as $item): ?>
		<tr
			<?php if ($item['product_restriction']): ?>
			class="danger"
			<?php endif ?>
			>
			<td>
				<span class="visible-xs-inline"><?=h(lang('Item'))?>:</span>
				<?=h($item['name'])?>
			</td>
			<td>
				<?php
					// Use the page property to determine whether the
					
					// full or short description should be shown.
					
					if ($product_description_type == 'full_description'):
					
					?>
				<?php
					// If this item has an image, then start row structure
					
					// and output column for image.
					
					if ($item['image_url']):
					
					?>
				<div class="row">
					<div class="col-md-6">
						<?php
							// The containers around the image fixes Firefox
							
							// issue with responsive images in tables.
							
							?>
						<div class="responsive_table_image_1">
							<div class="responsive_table_image_2">
								<img src="<?=h($item['image_url'])?>" class="img-responsive img-fluid center-block">
							</div>
						</div>
					</div>
					<div class="col-md-6">
						<?php endif ?>
						<span style="font-weight:bold"><?=h($item['short_description'])?></span>
						<?=$item['full_description']?>
						<?php else: ?>
						<?=h($item['short_description'])?>
						<?php endif ?>
						<?php if ($item['show_out_of_stock_message']): ?>
						<?=$item['out_of_stock_message']?>
						<?php endif ?>
						<?php if ($item['calendar_event']): ?>
						<p>
							<?=h($item['calendar_event']['name'])?><br>
							<?=$item['calendar_event']['date_and_time_range']?>
						</p>
						<?php endif ?>
						<?php if ($item['product_restriction']): ?>
						<p class="alert alert-danger" role="alert">
							<span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
							<strong><?=h($product_restriction_message)?></strong>
						</p>
						<?php endif ?>
						<?php
							// If there was an image shown for this item,
							
							// then close column and row structure.
							
							if (
							
							    $item['image_url']
							
							    and ($product_description_type == 'full_description')
							
							):
							
							?>
					</div>
				</div>
				<?php endif ?>
			</td>
			<td class="text-center">
				<?=number_format($item['quantity'])?>
			</td>
			<td class="text-right">
				<span class="visible-xs-inline"><?=h(lang('Price'))?>:</span>
				<?=$item['price_info']?>
			</td>
			<td class="text-right">
				<span class="visible-xs-inline"><?=h(lang('Amount'))?>:</span>
				<?=$item['amount_info']?>
			</td>
			<td class="text-center">
				<a href="<?=h($item['remove_url'])?>" class="btn btn-default btn-secondary btn-sm" title="<?=h(lang('Remove'))?>">
				<span class="glyphicon glyphicon-remove"></span>
				</a>
			</td>
		</tr>
		<?php endforeach ?>
	</table>
	<h2>
		<?=h($number_of_shipping_methods > 1 ? lang('Shipping Methods') : lang('Shipping Method'))?>
		<?php if (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient'): ?>
		<?=lang(array('string' => 'for <strong>{var:1}</strong>', 'vars' => array(h($ship_to_name))))?>
		<?php endif ?>
	</h2>
	<?php
		// If there are no valid shipping methods for this recipient, then show message.
		
		if (!$shipping_methods):
		
		?>
	<p class="alert alert-danger" role="alert">
		<span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
		<strong><?=h($no_shipping_methods_message)?></strong>
	</p>
	<?php
		// Otherwise there is at least one valid shipping method for this recipient,
		
		// so show shipping methods in a table.
		
		else:
		
		?>
	<table class="table mobile_stacked">
		<tr>
			<th><?=h(lang('Select One'))?></th>
			<th class="text-right"><?=h(lang('Cost'))?></th>
			<th><?=h(lang('Details'))?></th>
		</tr>
		<?php foreach($shipping_methods as $shipping_method): ?>
		<tr>
			<td>
				<div class="radio">
					<label>
					<input type="radio" name="shipping_method" value="<?=$shipping_method['id']?>">
					<?=h($shipping_method['name'])?>
					</label>
				</div>
			</td>
			<td class="text-right">
				<?=$shipping_method['cost_info']?>
			</td>
			<td>
				<?=h($shipping_method['description'])?>
			</td>
		</tr>
		<?php endforeach ?>
	</table>
	<div class="form-group">
		<button type="submit" class="btn btn-primary"><?=h($submit_button_label)?></button>
	</div>
	<?php endif ?>
	<!-- Required hidden fields (do not remove) -->
	<?=$system?>
</form>
<?php if ($currency): ?>
<form <?=$currency_attributes?>>
	<div class="form-group">
		<label for="currency_id" class="sr-only"><?=h(lang('Currency'))?></label>
		<select name="currency_id" id="currency_id" class="form-control"></select>
	</div>
	<?=$currency_system // Required hidden fields and JS (do not remove) ?>
</form>
<?php endif ?>