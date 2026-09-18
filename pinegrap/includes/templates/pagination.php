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
<?php if ($number_of_screens > 1): ?>
	<nav class="mt-3 navigation " aria-label="data pagination"> 
		<ul class="pagination pagination-sm flex-wrap justify-content-center">
			<?php if ($previous): ?>
				<li class="page-item mt-1 mb-1"><a class="page-link" href="<?=h(escape_url($_SERVER['PHP_SELF']))?>?screen=<?=h($previous)?>" aria-label="<?=h(lang('Previous'))?>"><span aria-hidden="true">&laquo;</span></a></li>
			<?php else: ?>
				<li class="page-item mt-1 mb-1 disabled"><a class="page-link" href="#!" aria-label="<?=h(lang('Previous'))?>"><span aria-hidden="true">&laquo;</span></a></li>
			<?php endif ?>
			<?php for ($i = 1; $i <= $number_of_screens; $i++): ?>
				<li class="page-item mt-1 mb-1 <?php if ($i == $screen): ?>active<?php endif; ?>"><a class="page-link " href="<?=h(escape_url($_SERVER['PHP_SELF']))?>?screen=<?=h($i)?>"><?=h($i)?></a></li>			
			<?php endfor ?>
			<?php if ($next <= $number_of_screens): ?>
				<li class="page-item mt-1 mb-1"><a class="page-link" href="<?=h(escape_url($_SERVER['PHP_SELF']))?>?screen=<?=h($next)?>" aria-label="<?=h(lang('Next'))?>"><span aria-hidden="true">&raquo;</span></a></li>
			<?php else: ?>
				<li class="page-item mt-1 mb-1 disabled"><a class="page-link" href="#!" aria-label="<?=h(lang('Next'))?>"><span aria-hidden="true">&raquo;</span></a></li>
			<?php endif ?>
		</ul>
	</nav>
<?php endif ?>


