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
<h2><?=h($album_name)?></h2>
<?php if (!$albums and !$photos): ?>
<p><strong>There are no albums or photos in this photo gallery.</strong></p>
<?php else: ?>
<?php if ($albums): ?>
<h3>Albums</h3>
<div class="albums">
	<?php foreach($albums as $album): ?>
	<div class="album text-center">
		<a href="<?=h($album['url'])?>">
			<div class="image">
				<img src="<?=h($album['image_url'])?>" class="img-responsive img-thumbnail center-block">
			</div>
			<div class="name"><strong><?=h($album['name'])?></strong></div>
			<div class="number_of_photos">
				(<?=number_format($album['number_of_photos'])?>
				Photo<?php if ($album['number_of_photos'] > 1): ?>s<?php endif ?>)
			</div>
		</a>
	</div>
	<?php endforeach ?>
</div>
<?php endif ?>
<?php if ($photos): ?>
<h3>Photos</h3>
<div class="photos">
	<?php foreach($photos as $photo): ?>
	<div class="photo text-center">
		<a href="<?=h($photo['url'])?>">
			<div class="image">
				<img src="<?=h($photo['url'])?>" class="img-responsive img-thumbnail center-block">
			</div>
			<div class="name"><?=h($photo['name'])?></div>
			<div class="description"><?=h($photo['description'])?></div>
		</a>
	</div>
	<?php endforeach ?>
</div>
<?php endif ?>
<?php endif ?>
<?php if ($back_button_url): ?>
<a href="<?=h($back_button_url)?>" class="btn btn-default btn-secondary">
Back
</a>
<?php endif ?>