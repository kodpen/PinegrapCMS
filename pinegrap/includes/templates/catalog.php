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
<div class="row">
	<div class="col-sm-9 col-sm-push-3">
		<?=$edit_start // Add edit button and grid around product group in edit mode ?> <?=$full_description?> <?php if ($items): ?> <?php if ($mode == 'search'): ?> 
		<p>
			<strong><?=($number_of_items > 1) ? lang(array('string' => 'Found {var:1} items for: {var:2}', 'vars' => array(pg_format_number($number_of_items, 0), h($query)))) : lang(array('string' => 'Found {var:1} item for: {var:2}', 'vars' => array(pg_format_number($number_of_items, 0), h($query))))?></strong>
		</p>
		<?php endif ?> 
		<div>
			<?php foreach($items as $item): ?> 
			<div>
				<?=$item['edit_start'] // Add edit button and grid around item in edit mode ?> <?php if ($item['url']): ?> 
				<a href="<?=h($item['url'])?>">
					<?php endif ?> <?php if ($item['image_url']): ?> 
					<div>
						<img src="<?=h($item['image_url'])?>" class="img-responsive">
					</div>
					<?php endif ?> <?php if ($item['short_description']): ?> 
					<div> <?=h($item['short_description'])?> </div>
					<?php endif ?> <?php if ($item['url']): ?> 
				</a>
				<?php endif ?> <?php if ($item['price_info']): ?> 
				<div> <?=$item['price_info']?> </div>
				<?php endif ?> <?=$item['edit_end'] // Close the edit grid ?> 
			</div>
			<?php endforeach ?> 
		</div>
		<?php else: // Otherwise no items were found, so output a message. ?> <?php if ($mode == 'browse'): ?> 
		<p>
			<strong><?=h(lang('There are no items in this group.'))?></strong>
		</p>
		<?php else: // Otherwise the mode is search ?> 
		<p>
			<strong><?=lang(array('string' => 'No items were found for: {var:1}', 'vars' => array(h($query))))?></strong>
		</p>
		<?php endif ?> <?php endif ?>
		<!-- HTML or JS from the product group's code field (e.g. tracking, remarketing) --> <?=$code?> <?=$edit_end // Close the edit grid ?> <?php if ($back_button_url): ?> 
		<div class="form-group">
			<a href="<?=h($back_button_url)?>" class="btn btn-default btn-secondary"> <?=h($back_button_label)?> </a>
		</div>
		<?php endif ?>
	</div>
	<div class="col-sm-3 col-sm-pull-9">
		<form <?=$search_attributes?>>
			<div class="form-group input-group">
				<span class="input-group-btn" title="<?=h(lang('Search'))?>">
				<button type="submit" name="<?=$page_id?>_submit" class="btn btn-default btn-secondary">
				<span class="glyphicon glyphicon-search"></span>
				</button>
				</span>
				<input type="search" name="<?=$page_id?>_query" class="form-control" placeholder="<?=h(lang('Search'))?>"> <?php if ($query != ''): ?> <span class="input-group-btn" title="<?=h(lang('Clear'))?>">
				<button type="submit" name="<?=$page_id?>_clear" class="btn btn-default btn-secondary">
				<span class="glyphicon glyphicon-remove"></span>
				</button>
				</span> <?php endif ?>
			</div>
			<!-- Required hidden fields (do not remove) --> <?=$search_system?>
		</form>
		<h3><?=h(lang('Categories'))?></h3>
		<nav>
			<ul class="nav nav-pills nav-stacked">
				<?php foreach($product_groups as $product_group): ?> 
				<li <?php if ($product_group['current']): ?> class="active" <?php endif ?>>
					<a href="<?=h($product_group['url'])?>" <?php if ($product_group['level']): ?> style="padding-left: <?=(2*$product_group['level'])?>em" <?php endif ?>> <?=h($product_group['name'])?> </a>
				</li>
				<?php endforeach ?> 
			</ul>
		</nav>
		<br> <?php if ($currency): ?> 
		<form <?=$currency_attributes?>>
			<div class="form-group">
				<label for="currency_id" class="sr-only"><?=h(lang('Currency'))?></label>
				<select name="currency_id" id="currency_id" class="form-control"></select>
			</div>
			<!-- Required hidden fields and JS (do not remove) --> <?=$currency_system?>
		</form>
		<?php endif ?>
	</div>
</div>