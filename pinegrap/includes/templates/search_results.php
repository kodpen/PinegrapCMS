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
	<div class="form-group input-group col-sm-4">
		<input type="search" name="<?=$page_id?>_query"
			class="form-control" placeholder="<?=h(lang('Search'))?>" autofocus>
		<span class="input-group-btn">
		<button type="submit" class="btn btn-default btn-secondary">
		<span class="glyphicon glyphicon-search"></span>
		</button>
		</span>
	</div>
	<!-- Required hidden fields (do not remove) -->
	<?=$system?>
</form>
<?php if ($query == ''): ?>
<p><strong><?=h(lang('Please enter a keyword or phrase to search.'))?></strong></p>
<?php elseif ($number_of_results == 0): ?>
<p><strong><?=lang(array('string' => 'No results were found for: {var:1}', 'vars' => array(h($query))))?></strong></p>
<?php else: ?>
<?php if ($limited): ?>
<p>
	<strong><?=lang(array('string' => 'Showing {var:1} of the most relevant results for: {var:2}', 'vars' => array(number_format($number_of_results), h($query))))?></strong>
</p>
<?php else: ?>
<p>
	<strong><?=($number_of_results > 1) ? lang(array('string' => 'Found {var:1} results for: {var:2}', 'vars' => array(number_format($number_of_results), h($query)))) : lang(array('string' => 'Found {var:1} result for: {var:2}', 'vars' => array(number_format($number_of_results), h($query))))?></strong>
</p>
<?php endif ?>
<?php if ($featured_items): ?>
<h2><?=h(lang('Featured Results'))?></h2>
<div>
	<?php foreach($featured_items as $item): ?>
	<div>
		<div>
			<strong>
			<a href="<?=h($item['url'])?>"><?=h($item['title'])?></a>
			</strong>
		</div>
		<div>
			<em><?=h($item['full_url'])?></em>
		</div>
		<?php if ($item['description']): ?>
		<div><?=h($item['description'])?></div>
		<?php endif ?>
	</div>
	<?php endforeach ?>
</div>
<?php endif ?>
<?php if ($catalog_items): ?>
<h2><?=h(lang('Catalog Results'))?></h2>
<div>
	<?php foreach($catalog_items as $item): ?>
	<div>
		<?php if ($item['url']): ?>
		<a href="<?=h($item['url'])?>">
			<?php endif ?>
			<?php if ($item['image_url']): ?>
			<div><img src="<?=h($item['image_url'])?>" class="img-responsive"></div>
			<?php endif ?>
			<?php if ($item['short_description']): ?>
			<div><?=h($item['short_description'])?></div>
			<?php endif ?>
			<?php if ($item['url']): ?>
		</a>
		<?php endif ?>
		<?php if ($item['price_info']): ?>
		<div><?=$item['price_info']?></div>
		<?php endif ?>
	</div>
	<?php endforeach ?>
</div>
<?php endif ?>
<?php if ($results): ?>
<?php if ($featured_items or $catalog_items): ?>
<h2><?=h(lang('Other Results'))?></h2>
<?php endif ?>
<div>
	<?php foreach($results as $result): ?>
	<div>
		<div>
			<strong>
			<a href="<?=h($result['url'])?>"><?=h($result['title'])?></a>
			</strong>
		</div>
		<div><em><?=h($result['full_url'])?></em></div>
		<?php if ($result['description']): ?>
		<div><?=h($result['description'])?></div>
		<?php endif ?>
	</div>
	<?php endforeach ?>
</div>
<?php endif ?>
<?php endif ?>