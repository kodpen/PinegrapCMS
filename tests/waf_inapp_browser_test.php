<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for waf_classify_bot() (waf.php) with the browsers social apps open
 * a link in: a person reading a shared link classifies as human, while the
 * same companies' crawlers keep their class.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/waf.php');

// User agents of the in-app browsers, as the apps send them.
function test_waf_inapp_browsers_are_human()
{
	$agents = array(
		'TikTok iOS' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 musical_ly_34.1.0 JsSdk/2.0 NetType/WIFI Channel/App Store ByteLocale/tr Region/TR ByteFullLocale/tr isDarkMode/0 WKWebView/1 RevealType/Dialog BytedanceWebview/d8a21c6',
		'TikTok Android' => 'Mozilla/5.0 (Linux; Android 13; SM-A515F Build/TP1A.220624.014; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/120.0.6099.193 Mobile Safari/537.36 trill_330304 JsSdk/1.0 NetType/MOBILE Channel/googleplay AppName/trill app_version/33.3.4 ByteLocale/tr ByteFullLocale/tr Region/TR AppId/1180 Spark/1.5.1.4-bugfix AppVersion/33.3.4 BytedanceWebview/d8a21c6',
		'Pinterest iOS' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [Pinterest/iOS]',
		'Pinterest Android' => 'Mozilla/5.0 (Linux; Android 14; Pixel 7 Build/UQ1A.240205.004; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/121.0.6167.178 Mobile Safari/537.36 [Pinterest/Android]',
		'Naver' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 NAVER(inapp; search; 2000; 12.3.4; 15PRO)',
		'Naver Android' => 'Mozilla/5.0 (Linux; Android 13; SM-S911N Build/TP1A.220624.014; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/119.0.6045.193 Mobile Safari/537.36 NAVER(inapp; search; 2000; 12.1.31)',
		'Instagram' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 321.0.2.24.105 (iPhone15,3; iOS 17_4; tr_TR; tr; scale=3.00; 1290x2796; 571386374)',
		'Facebook' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/455.0.0.44.106;FBBV/583218196;FBDV/iPhone15,3;FBMD/iPhone;FBSN/iOS;FBSV/17.4;FBSS/3;FBID/phone;FBLC/tr_TR;FBOP/5;FBRV/0]',
		'Facebook Android' => 'Mozilla/5.0 (Linux; Android 13; SM-A515F Build/TP1A.220624.014; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/120.0.6099.193 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/448.0.0.37.106;]',
		'LinkedIn' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [LinkedInApp]/9.29.5462',
		'Snapchat' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Snapchat/12.80.0.33 (like Safari/8617.2.4.10.8, panda)',
		'Telegram' => 'Mozilla/5.0 (Linux; Android 14; Pixel 7 Build/UQ1A.240205.004; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/121.0.6167.178 Mobile Safari/537.36 Telegram-Android/10.9.1 (Google Pixel 7; Android 14; SDK 34; HIGH)',
	);

	foreach ($agents as $label => $agent) {
		$result = waf_classify_bot($agent);
		pg_assert_same('human', $result['class'], $label . ' (' . $result['token'] . ')');
	}
}

// The crawlers of the same companies keep their class.
function test_waf_inapp_vendor_crawlers_keep_class()
{
	pg_assert_same('bad', waf_classify_bot('Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)')['class'], 'Bytespider');
	pg_assert_same('good', waf_classify_bot('Mozilla/5.0 (compatible; Pinterestbot/1.0; +http://www.pinterest.com/bot.html)')['class'], 'Pinterestbot');
	pg_assert_same('good', waf_classify_bot('Pinterest/0.2 (+http://www.pinterest.com/bot.html)')['class'], 'Pinterest preview');
	pg_assert_same('good', waf_classify_bot('Mozilla/5.0 (compatible; Yeti/1.1; +https://naver.me/spd)')['class'], 'Naver Yeti');
	pg_assert_same('good', waf_classify_bot('facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)')['class'], 'Facebook preview');
	pg_assert_same('good', waf_classify_bot('LinkedInBot/1.0 (compatible; Mozilla/5.0; Apache-HttpClient +http://www.linkedin.com)')['class'], 'LinkedInBot');
	pg_assert_same('good', waf_classify_bot('TelegramBot (like TwitterBot)')['class'], 'TelegramBot');
}

// Better Uptime's monitor, as it introduces itself.
function test_waf_better_uptime_is_good()
{
	foreach (array(
		'Better Uptime Bot Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
		'Better Stack Better Uptime Bot Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
	) as $agent) {
		$result = waf_classify_bot($agent);
		pg_assert_same('good', $result['class'], $agent);
		pg_assert_same('Better Uptime', $result['name'], $agent);
	}
}
