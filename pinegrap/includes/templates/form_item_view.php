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
<h2>New Account</h2>
<p>We have created a new account for you on our site. You can find your login info below.</p>
<dl class="dl-horizontal">
	<dt>Email</dt>
	<dd><?=h($auto_registration_email_address)?></dd>
	<dt>Password</dt>
	<dd><?=h($auto_registration_password)?></dd>
</dl>
<?php endif ?>
<div class="form-group">
	<?php if ($back_button_url): ?>
	<a href="<?=h($back_button_url)?>" class="btn btn-default btn-secondary">
	Back
	</a>
	<?php endif ?>
	<?php if ($edit_button_url): ?>
	<a href="<?=h($edit_button_url)?>" class="btn btn-primary">
	Edit
	</a>
	<?php endif ?>
</div>