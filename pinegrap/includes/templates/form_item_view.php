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
<?=$content?>
<?php if ($auto_registration): ?>
<h2><?=lang('New Account')?></h2>
<?php if ($auto_registration_for_submitter): ?>
<p><?=lang('We have created a new account for you on our site.')?></p>
<dl class="dl-horizontal">
	<dt><?=lang('Email')?></dt>
	<dd><?=h($auto_registration_email_address)?></dd>
</dl>
<?php if ($auto_registration_set_password_url): ?>
<p><a href="<?=h($auto_registration_set_password_url)?>" class="btn btn-primary"><?=lang('Set your password')?></a></p>
<?php else: ?>
<p><?=h(lang('You can set a password with "Forgot password" on the sign-in page.'))?></p>
<?php endif ?>
<?php else: ?>
<p><?=lang(array('string' => 'A new account was created with this submission: {var:1}', 'vars' => array(h($auto_registration_email_address))))?></p>
<?php endif ?>
<?php endif ?>
<div class="form-group">
	<?php if ($back_button_url): ?>
	<a href="<?=h($back_button_url)?>" class="btn btn-default btn-secondary">
	<?=lang('Back')?>
	</a>
	<?php endif ?>
	<?php if ($edit_button_url): ?>
	<a href="<?=h($edit_button_url)?>" class="btn btn-primary">
	<?=lang('Edit')?>
	</a>
	<?php endif ?>
</div>