<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Google Search Console: the searches that bring people to the site, how
 * often its pages are shown in Google and where they rank, and the one-click
 * setup that verifies the site, adds it to Search Console and submits its
 * sitemap (includes/fn/search_console.php).
 *
 * Anyone who may see the visitor figures sees this screen; linking, the
 * buttons that call Google and unlinking are a manager's, the same right as
 * connecting the account they use.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
include_once('liveform.class.php');

$user = validate_user();
validate_visitors_access($user);

require_once(PG_FUNCTIONS_DIR . '/includes/fn/search_console.php');

if (!pg_gsc_schema_ready()) {
	output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('search_console');

$can_manage = ($user['role'] <= 2);

$screen_url = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/search_console.php';

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

if ($_POST) {

	validate_token_field();
	validate_area_access($user, 'manager');

	// Linking reads up to ninety days from Google; the person waits for it,
	// and a closed tab must not leave the setup half written.
	ignore_user_abort(true);
	@set_time_limit(300);

	$action = isset($_POST['search_console_action']) ? (string) $_POST['search_console_action'] : '';

	if ($action === 'link') {

		$account = pg_connect_account(isset($_POST['connection_id']) ? (int) $_POST['connection_id'] : 0);

		if (is_array($account) && ($account['user_id'] === 0) && ($account['provider'] === 'google')
			&& in_array('search_console', $account['purposes'], true)) {

			$link = pg_gsc_link($account, 60);

			if ($link['ok']) {
				pg_connect_flash('notice', implode(' ', $link['notices']));
			} else {
				pg_connect_flash('error', implode(' ', array_merge($link['notices'], array($link['error']))));
			}

		}

	} elseif (($action === 'sync') && pg_gsc_linked()) {

		$sync = pg_gsc_sync(120);

		if ($sync['ok']) {
			pg_connect_flash('notice', lang(array('string' => 'The search figures up to {var:1} were downloaded.', 'vars' => prepare_form_data_for_output($sync['to'], 'date', false))));
		} else {
			pg_connect_flash('error', lang(array('string' => 'The search figures could not be downloaded yet: {var:1}', 'vars' => $sync['error'])));
		}

	} elseif (($action === 'sitemap') && pg_gsc_linked()) {

		$settings = pg_gsc_settings();
		$account = pg_gsc_account();

		$sitemap = is_array($account)
			? pg_gsc_submit_sitemap($account['id'], $settings['property'])
			: array('ok' => false, 'error' => lang('No Google account is connected for Search Console.'));

		if ($sitemap['ok']) {
			pg_connect_flash('notice', lang('The sitemap was submitted to Google.'));
		} else {
			pg_connect_flash('error', lang(array('string' => 'The sitemap could not be submitted: {var:1}', 'vars' => $sitemap['error'])));
		}

	} elseif ($action === 'unlink') {

		pg_gsc_unlink();

		pg_connect_flash('notice', lang('The site is no longer linked to Search Console. The verification code stays on the pages.'));

	}

	header('Location: ' . $screen_url);

	exit();

}

/* ---------------------------------------------------------------------------
   Reads
   --------------------------------------------------------------------------- */

$flash = pg_connect_flash_take();

// Coming back from Google with the account just connected: the link runs on
// its own, so the whole setup is one click. Only the connection screen leaves
// a notice behind together with this address, so a link to it alone does
// nothing.
$just_connected = false;

if (is_array($flash)) {

	if ($flash['kind'] === 'error') {
		$liveform->add_error($flash['text']);
	} else {
		$liveform->add_notice($flash['text']);
		$just_connected = (isset($_GET['connected']) && ($_GET['connected'] === '1'));
	}

}

$settings = pg_gsc_settings();
$linked = pg_gsc_linked();
$account = pg_gsc_account();
$accounts = pg_connect_accounts(array('provider' => 'google', 'user_id' => 0, 'purpose' => 'search_console'));
$can_connect = $can_manage && pg_connect_can_seal() && pg_connect_app_ready(pg_connect_app('google'));

function search_console_number($value, $decimals = 0) {

	return h(pg_format_number($value, $decimals));

}

function search_console_percent($fraction, $decimals = 1) {

	return h(pg_format_number($fraction * 100, $decimals)) . '%';

}

// The movement against the period before, coloured by whether it is good
// news: more clicks is, a higher position number is not.
function search_console_change($now, $before, $lower_is_better = false) {

	if ($lower_is_better) {

		if (($now <= 0) || ($before <= 0)) {
			return '';
		}

		$difference = $now - $before;
		$good = ($difference < 0);
		$text = (($difference > 0) ? '+' : '') . pg_format_number($difference, 1);

	} else {

		$change = pg_gsc_change($now, $before);

		if ($change === null) {
			return '';
		}

		$good = ($change > 0);
		$text = (($change > 0) ? '+' : '') . pg_format_number($change * 100, 0) . '%';

	}

	$class = (abs($now - $before) < 0.00001) ? 'text-body-secondary' : ($good ? 'text-success' : 'text-danger');

	return '<span class="' . $class . '">' . h($text) . '</span>';

}

// The form that starts the Google connection, posted to connect.php.
$connect_form = '
	<form method="post" action="connect.php" id="search_console_connect" class="disable_shortcut d-none">
		' . pg_connect_start_fields('google', 'search_console', 'search_console.php?connected=1', 'site', ($linked && is_array($account) && ($account['status'] === 'reauth')) ? $account['id'] : 0) . '
	</form>';

$connect_button = function ($label, $class = 'btn btn-sm btn-primary') use ($can_connect) {

	return '<button type="submit" form="search_console_connect" class="' . $class . '"' . ($can_connect ? '' : ' disabled') . '>
		<i class="bi bi-google me-1" aria-hidden="true"></i>' . h($label) . '</button>';

};

$action_form = '
	<form method="post" action="search_console.php" id="search_console_form" class="disable_shortcut d-none">
		' . get_token_field() . '
		<input type="hidden" name="search_console_action" value="" id="search_console_action">
	</form>';

$body = '';

if (!$linked) {

	/* -----------------------------------------------------------------------
	   Not linked yet: connect a Google account, then link the site.
	   ----------------------------------------------------------------------- */

	$steps = '
		<ul class="small mb-3">
			<li>' . h(lang('finds this site among the account\'s Search Console properties, or verifies it with a meta tag on its pages and adds it;')) . '</li>
			<li>' . h(lang('submits the sitemap;')) . '</li>
			<li>' . h(lang('downloads the figures of the last 90 days, and from then on every day.')) . '</li>
		</ul>';

	if (!$can_manage) {

		$setup = '<p class="mb-0">' . h(lang('The site is not linked to Google Search Console yet. An administrator or a manager can link it here.')) . '</p>';

	} elseif (!pg_connect_can_seal() || !pg_connect_app_ready(pg_connect_app('google'))) {

		$setup = '
			<p>' . h(lang('Search Console is read through a Google account connected to the site. Set up the Google application on Connected accounts first, then come back here.')) . '</p>
			<p class="small text-body-secondary">' . h(lang('In the application\'s Google Cloud project, enable the Google Search Console API and the Site Verification API.')) . '</p>
			<a class="btn btn-sm btn-outline-primary" href="connections.php"><i class="bi bi-plug me-1" aria-hidden="true"></i>' . h(lang('Connected accounts')) . '</a>';

	} elseif (!$accounts) {

		$setup = '
			<p>' . h(lang('Connect the Google account that owns the site in Search Console, or the one that should own it. Linking then:')) . '</p>
			' . $steps . '
			' . $connect_button(lang('Connect a Google account')) . '
			<div class="form-text">' . h(lang('Google asks for permission to manage Search Console and to verify sites. The account itself stays yours.')) . '</div>';

	} else {

		$options = '';

		foreach ($accounts as $option) {
			$options .= '<option value="' . (int) $option['id'] . '">' . h($option['label'] . (($option['status'] !== 'active') ? ' - ' . lang('Connect again') : '')) . '</option>';
		}

		$setup = '
			<p>' . h(lang('Linking the site:')) . '</p>
			' . $steps . '
			<form method="post" action="search_console.php" id="search_console_link" class="row g-2 align-items-end">
				' . get_token_field() . '
				<input type="hidden" name="search_console_action" value="link">
				<div class="col-12 col-md-6">
					<label class="form-label" for="connection_id">' . h(lang('Google account')) . '</label>
					<select class="form-select form-select-sm" name="connection_id" id="connection_id">' . $options . '</select>
				</div>
				<div class="col-12 col-md-auto">
					<button type="submit" class="btn btn-sm btn-primary" id="search_console_link_button">
						<i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' . h(lang('Link the site')) . '</button>
				</div>
			</form>
			<div class="small text-body-secondary mt-3">' . h(lang('Another account:')) . ' ' . $connect_button(lang('Connect a Google account'), 'btn btn-sm btn-link p-0 align-baseline') . '</div>';

	}

	$body = '
		<div class="row g-3">
			<div class="col-12 col-xl-8">
				<div class="card h-100">
					<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . h(lang('Link the site to Google Search Console')) . '</div>
					<div class="card-body">' . $setup . '</div>
				</div>
			</div>
			<div class="col-12 col-xl-4">
				<div class="card h-100">
					<div class="card-body small text-body-secondary">
						<p>' . h(lang('Search Console shows which searches bring people to the site, how often its pages are shown in Google and in which position. The visitor reports count the people who arrive; Search Console counts the searches before they do.')) . '</p>
						<p class="mb-0">' . h(lang('Google publishes the figures two or three days late and keeps sixteen months of them.')) . '</p>
					</div>
				</div>
			</div>
		</div>';

} else {

	/* -----------------------------------------------------------------------
	   Linked: the figures, and the state of the link.
	   ----------------------------------------------------------------------- */

	$days = isset($_GET['days']) ? (int) $_GET['days'] : 28;

	if (!in_array($days, array(7, 28, 90), true)) {
		$days = 28;
	}

	$latest = pg_gsc_latest_day();

	$period_links = '';

	foreach (array(7, 28, 90) as $choice) {
		$period_links .= '<a class="btn btn-sm ' . (($choice === $days) ? 'btn-primary' : 'btn-outline-primary') . '" href="search_console.php?days=' . $choice . '">'
			. h(lang(array('string' => '{var:1} days', 'vars' => $choice))) . '</a>';
	}

	$figures = '';

	if ($latest === '') {

		$figures = '
			<div class="card my-3">
				<div class="card-body text-center text-body-secondary py-5">
					<i class="bi bi-hourglass-split fs-2 d-block mb-2" aria-hidden="true"></i>
					' . h(lang('No figures yet. Google publishes them two or three days late; the daily job downloads them as they come.')) . '
				</div>
			</div>';

	} else {

		$period = pg_gsc_period($latest, $days);
		$now = pg_gsc_totals($period['from'], $period['to']);
		$before = pg_gsc_totals($period['previous_from'], $period['previous_to']);
		$daily = pg_gsc_daily($period['from'], $period['to']);

		$chart = array('labels' => array(), 'clicks' => array(), 'impressions' => array());

		foreach ($daily as $day => $values) {
			$chart['labels'][] = date('d.m', strtotime($day . ' 12:00:00'));
			$chart['clicks'][] = $values['clicks'];
			$chart['impressions'][] = $values['impressions'];
		}

		$tile = function ($label, $value, $change, $note) {
			return '
				<div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
					<div class="small text-uppercase text-body-secondary">' . h($label) . '</div>
					<div class="h4 mb-0">' . $value . '</div>
					<div class="small text-body-secondary">' . (($change !== '') ? $change . ' ' : '') . h($note) . '</div>
				</div></div></div>';
		};

		$compare = lang(array('string' => 'against the {var:1} days before', 'vars' => $days));

		$tiles =
			$tile(lang('Clicks'), search_console_number($now['clicks']), search_console_change($now['clicks'], $before['clicks']), $compare)
			. $tile(lang('Impressions'), search_console_number($now['impressions']), search_console_change($now['impressions'], $before['impressions']), $compare)
			. $tile(lang('Click rate'), search_console_percent($now['ctr']), search_console_change($now['ctr'], $before['ctr']), $compare)
			. $tile(lang('Average position'), ($now['impressions'] > 0) ? search_console_number($now['position'], 1) : '–', search_console_change($now['position'], $before['position'], true), $compare);

		$table = function ($kind, $label) use ($period) {

			$rows = '';
			$site_url = pg_gsc_site_url();

			foreach (pg_gsc_top($kind, $period['from'], $period['to'], 50) as $row) {

				$name = ($kind === 'page')
					? '<a href="' . h($row['name']) . '" target="_blank" rel="noopener" class="text-break">' . h(pg_gsc_page_label($row['name'], $site_url)) . '</a>'
					: '<span class="text-break">' . h($row['name']) . '</span>';

				$rows .= '
					<tr>
						<td>' . $name . '</td>
						<td class="text-end" data-sort="' . (int) $row['clicks'] . '">' . search_console_number($row['clicks']) . '</td>
						<td class="text-end" data-sort="' . (int) $row['impressions'] . '">' . search_console_number($row['impressions']) . '</td>
						<td class="text-end" data-sort="' . h(sprintf('%.4f', $row['ctr'])) . '">' . search_console_percent($row['ctr']) . '</td>
						<td class="text-end" data-sort="' . h(sprintf('%.2f', $row['position'])) . '">' . search_console_number($row['position'], 1) . '</td>
					</tr>';

			}

			if ($rows === '') {
				$rows = '<tr><td colspan="5" class="text-center text-body-secondary py-4">' . h(lang('Nothing in this period.')) . '</td></tr>';
			}

			return '
				<table class="table table-sm table-hover align-middle mb-0" data-pg-sort id="search_console_' . $kind . '">
					<thead class="sticky-top">
						<tr>
							<th>' . h($label) . '</th>
							<th class="text-end">' . h(lang('Clicks')) . '</th>
							<th class="text-end">' . h(lang('Impressions')) . '</th>
							<th class="text-end">' . h(lang('Click rate')) . '</th>
							<th class="text-end">' . h(lang('Avg. position')) . '</th>
						</tr>
					</thead>
					<tbody>' . $rows . '</tbody>
				</table>';

		};

		$figures = '
			<div class="row g-3">' . $tiles . '</div>

			<div class="card my-3">
				<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . h(lang('By day')) . '</div>
				<div class="card-body">
					<div style="height:16rem"><canvas id="search_console_chart" aria-label="' . h(lang('Clicks and impressions by day')) . '" role="img"></canvas></div>
				</div>
			</div>

			<div class="row g-3 my-1">
				<div class="col-12 col-xxl-6">
					<div class="card h-100">
						<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . h(lang('Searches')) . '</div>
						<div class="card-body p-0 table-responsive" style="max-height:36rem">' . $table('query', lang('Search query')) . '</div>
					</div>
				</div>
				<div class="col-12 col-xxl-6">
					<div class="card h-100">
						<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . h(lang('Pages')) . '</div>
						<div class="card-body p-0 table-responsive" style="max-height:36rem">' . $table('page', lang('Page')) . '</div>
					</div>
				</div>
			</div>

			<p class="small text-body-secondary my-3">' . h(lang('Searches Google keeps anonymous for privacy are in the totals but not in the list of searches, so the list adds up to less.')) . '</p>

			<script src="assets/lib/chartjs/chart.umd.min.js"></script>
			<script>
			(function () {
				var canvas = document.getElementById("search_console_chart");
				if (!canvas || typeof Chart === "undefined") { return; }
				var data = ' . json_encode($chart) . ';
				var styles = getComputedStyle(document.documentElement);
				var color = function (name, fallback) { return (styles.getPropertyValue(name) || "").trim() || fallback; };
				var locale = document.documentElement.lang || undefined;
				var format = function (value) { return Number(value).toLocaleString(locale, { maximumFractionDigits: 0 }); };
				new Chart(canvas, {
					type: "line",
					data: {
						labels: data.labels,
						datasets: [
							{ label: ' . json_encode(lang('Clicks')) . ', data: data.clicks, yAxisID: "clicks", borderColor: color("--bs-primary", "#0d6efd"), backgroundColor: color("--bs-primary", "#0d6efd"), tension: 0.3, pointRadius: 2 },
							{ label: ' . json_encode(lang('Impressions')) . ', data: data.impressions, yAxisID: "impressions", borderColor: color("--bs-purple", "#6f42c1"), backgroundColor: color("--bs-purple", "#6f42c1"), tension: 0.3, pointRadius: 2 }
						]
					},
					options: {
						maintainAspectRatio: false,
						interaction: { mode: "index", intersect: false },
						plugins: {
							legend: { position: "bottom" },
							tooltip: { callbacks: { label: function (ctx) { return ctx.dataset.label + ": " + format(ctx.parsed.y); } } }
						},
						scales: {
							clicks: { position: "left", beginAtZero: true, ticks: { callback: function (value) { return format(value); } } },
							impressions: { position: "right", beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { callback: function (value) { return format(value); } } }
						}
					}
				});
			})();
			</script>';

	}

	// The state of the link.

	$permission_labels = array(
		'owner'      => lang('Owner'),
		'full'       => lang('Full user'),
		'restricted' => lang('Restricted user'),
	);

	$facts = array(
		array(lang('Property'), '<a href="https://search.google.com/search-console?resource_id=' . h(rawurlencode($settings['property'])) . '" target="_blank" rel="noopener" class="text-break">' . h($settings['property']) . ' <i class="bi bi-box-arrow-up-right small" aria-hidden="true"></i></a>'),
		array(lang('Permission'), h(isset($permission_labels[$settings['permission']]) ? $permission_labels[$settings['permission']] : $settings['permission'])),
		array(lang('Google account'), is_array($account) ? h($account['label']) : '<span class="text-danger">' . h(lang('None connected')) . '</span>'),
		array(lang('Figures up to'), ($settings['synced_to'] !== '') ? h(prepare_form_data_for_output($settings['synced_to'], 'date', false)) : '–'),
		array(lang('Last download'), ($settings['synced_at'] > 0) ? get_relative_time(array('timestamp' => $settings['synced_at'])) : h(lang('Never'))),
		array(lang('Sitemap submitted'), ($settings['sitemap_at'] > 0)
			? get_relative_time(array('timestamp' => $settings['sitemap_at']))
			: h(lang('Not submitted yet'))),
	);

	$output_facts = '';

	foreach ($facts as $fact) {
		$output_facts .= '<dt class="col-5 fw-normal text-body-secondary">' . h($fact[0]) . '</dt><dd class="col-7">' . $fact[1] . '</dd>';
	}

	$problems = '';

	if (!is_array($account)) {
		$problems .= '<div class="alert alert-warning small py-2">' . h(lang('The Google account the site was linked with is no longer connected. Connect it again to keep the figures coming.')) . '<div class="mt-2">' . $connect_button(lang('Connect a Google account')) . '</div></div>';
	} elseif ($account['status'] === 'reauth') {
		$problems .= '<div class="alert alert-warning small py-2">' . h(lang('Google no longer accepts the account\'s permission. Connect it again to keep the figures coming.')) . '<div class="mt-2">' . $connect_button(lang('Connect again')) . '</div></div>';
	}

	if ($settings['sync_error'] !== '') {
		$problems .= '<div class="alert alert-danger small py-2">' . h(lang('Last download:')) . ' ' . h($settings['sync_error']) . '</div>';
	}

	if ($settings['sitemap_error'] !== '') {
		$problems .= '<div class="alert alert-danger small py-2">' . h(lang('Sitemap:')) . ' ' . h($settings['sitemap_error']) . '</div>';
	}

	$buttons = '';

	if ($can_manage) {
		$buttons = '
			<div class="d-flex flex-wrap gap-2">
				<button type="button" class="btn btn-sm btn-outline-primary search-console-action" data-action="sync"' . (is_array($account) ? '' : ' disabled') . '>
					<i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(lang('Download now')) . '</button>
				' . (in_array($settings['permission'], array('owner', 'full'), true)
					? '<button type="button" class="btn btn-sm btn-outline-primary search-console-action" data-action="sitemap"' . (is_array($account) ? '' : ' disabled') . '>
						<i class="bi bi-diagram-3 me-1" aria-hidden="true"></i>' . h(lang('Submit the sitemap again')) . '</button>'
					: '') . '
				<button type="button" class="btn btn-sm btn-outline-danger search-console-action" data-action="unlink"
					data-confirm="' . h(lang('The figures downloaded so far are deleted and no new ones come. The site stays verified in Search Console.')) . '">
					<i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('Unlink')) . '</button>
			</div>';
	}

	$body = '
		<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
			<div class="btn-group" role="group" aria-label="' . h(lang('Period')) . '">' . $period_links . '</div>
			' . (isset($period) ? '<div class="small text-body-secondary">' . h(prepare_form_data_for_output($period['from'], 'date', false) . ' – ' . prepare_form_data_for_output($period['to'], 'date', false)) . '</div>' : '') . '
		</div>
		<div class="row g-3">
			<div class="col-12 col-xxl-9">' . $figures . '</div>
			<div class="col-12 col-xxl-3">
				<div class="card">
					<div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . h(lang('Search Console')) . '</div>
					<div class="card-body">
						' . $problems . '
						<dl class="row small mb-3">' . $output_facts . '</dl>
						' . $buttons . '
					</div>
				</div>
			</div>
		</div>';

}

/* ---------------------------------------------------------------------------
   The screen
   --------------------------------------------------------------------------- */

$liveform_messages = $liveform->output_errors() . $liveform->output_notices();

$liveform->remove_form();

// Linking runs on its own after the account was just connected, when there
// is no account to choose between.
$auto_link = $just_connected && !$linked && $can_manage && (count($accounts) === 1);

echo pg_page_shell(array(
	'title'               => lang('Google Search Console'),
	'extra classes'       => 'visitor',
	'icon'                => 'visitor',
	'heading'             => lang('Google Search Console'),
	'heading_description' => lang('The searches that bring people to the site, how often its pages are shown in Google and where they rank.'),
	'cancel'              => false,
	'code_editor'         => false,
)) . '

<main id="content" class="container-fluid">
	<div class="row"><div class="col-12">
		' . $liveform_messages . '
		' . ($auto_link
			? '<div class="alert alert-info d-flex align-items-center gap-2" id="search_console_linking"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>' . h(lang('Linking the site to Search Console...')) . '</div>'
			: '') . '
	</div></div>

	' . $body . '

	' . $connect_form . '
	' . $action_form . '
</main>

<script>
(function () {

	document.addEventListener("click", function (e) {

		var action = e.target.closest(".search-console-action");

		if (!action) { return; }

		if (action.dataset.confirm && !window.confirm(action.dataset.confirm)) { return; }

		document.getElementById("search_console_action").value = action.dataset.action;
		action.disabled = true;
		action.insertAdjacentHTML("afterbegin", "<span class=\"spinner-border spinner-border-sm me-1\" aria-hidden=\"true\"></span>");
		document.getElementById("search_console_form").submit();

	});

	// Linking calls Google several times and downloads the first figures; the
	// button says it is working until the answer comes.
	var link = document.getElementById("search_console_link");

	if (link) {
		link.addEventListener("submit", function () {
			var button = document.getElementById("search_console_link_button");
			button.disabled = true;
			button.insertAdjacentHTML("afterbegin", "<span class=\"spinner-border spinner-border-sm me-1\" aria-hidden=\"true\"></span>");
		});

		' . ($auto_link ? 'link.requestSubmit ? link.requestSubmit() : link.submit();' : '') . '
	}

})();
</script>' . output_footer();
