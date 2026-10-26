<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Which shop product a marketplace's product most likely is.
//
// A store that has sold on a marketplace for years arrives here with two
// catalogues that were typed separately: its codes over there are rarely the
// codes it keeps here, and the titles were written for their search box. So
// the suggestion is built from three things, strongest first:
//
//   the barcode, when both sides carry the same one;
//   the code, written the same way once case and separators are dropped, or
//     one inside the other (a store that put its own prefix in front of every
//     code over there), or the shop's code standing as a word in their title;
//   the title, word by word. A word that many shop products share ("korna")
//     says little about which one it is, a word few share says a lot, and a
//     word with a digit in it (H7, 55W, 6202) is how spare parts are told
//     apart, so it counts more.
//
// Every suggestion carries its reason and a score from 0 to 100, and it is
// only ever a suggestion: the screen asks for a click before anything is
// matched. A suggestion is "sure" when it scores high and nothing else comes
// close, which is what lets the operator accept a whole page of them at once.
//
// Matches are one to one, as marketplace_product_map is: a shop product is
// offered to one remote row at most, the closest one.
//
// Everything here is pure: arrays in, arrays out, no database.

if (!defined('PG_INIT_LOADED')) {

	exit;

}

// Below this a title is not offered at all.
function mp_match_min_score() {

	return 50;

}

// Text as lowercase ASCII words separated by single spaces.
//
// Transliterated before it is lowercased: mb_strtolower() turns the dotted
// capital İ into "i" followed by a combining dot, which no longer matches the
// "i" of the same word typed in small letters.
function mp_match_text_key($text) {

	$text = (string) $text;

	// The suffix after an apostrophe is grammar, not a word: 12V'li, Korna'sı.
	$text = preg_replace("/['\x{2019}`\x{00B4}][\\p{L}]+/u", '', $text);

	$text = strtolower(pg_transliterate_to_ascii($text));

	// A decimal keeps its two halves together and apart from the whole number
	// (1,5 mm is not 15 mm).
	$text = preg_replace('/(\d)[.,](\d)/', '$1d$2', $text);

	$text = preg_replace('/[^a-z0-9]+/', ' ', $text);

	return trim(preg_replace('/\s+/', ' ', $text));

}

// The words of a title, each once, with a number and the unit after it made
// one word ("12 volt" and "12V" both read 12v).
function mp_match_tokens($text) {

	static $units = array(
		'v' => 'v', 'volt' => 'v', 'w' => 'w', 'watt' => 'w', 'a' => 'a', 'amp' => 'a', 'amper' => 'a',
		'ah' => 'ah', 'mah' => 'mah', 'mm' => 'mm', 'cm' => 'cm', 'm' => 'm', 'mt' => 'm', 'metre' => 'm',
		'kg' => 'kg', 'g' => 'g', 'gr' => 'g', 'l' => 'l', 'lt' => 'l', 'ml' => 'ml', 'inc' => 'inc',
		'inch' => 'inc', 'hz' => 'hz', 'kw' => 'kw', 'kva' => 'kva', 'rpm' => 'rpm', 'bar' => 'bar'
	);

	static $stop = array(
		've' => 1, 'ile' => 1, 'icin' => 1, 'veya' => 1, 'ya' => 1, 'da' => 1, 'de' => 1, 'ki' => 1,
		'bu' => 1, 'bir' => 1, 'the' => 1, 'and' => 1, 'or' => 1, 'for' => 1, 'with' => 1, 'of' => 1,
		'adet' => 1, 'pcs' => 1
	);

	$key = mp_match_text_key($text);

	if ($key === '') {

		return array();

	}

	$words = explode(' ', $key);

	$tokens = array();

	$count = count($words);

	for ($i = 0; $i < $count; $i++) {

		$word = $words[$i];

		// A number followed by a unit.
		if (preg_match('/^\d+(d\d+)?$/', $word) && ($i + 1 < $count) && isset($units[$words[$i + 1]])) {

			$word .= $units[$words[$i + 1]];

			$i++;

		} elseif (preg_match('/^(\d+(?:d\d+)?)([a-z]+)$/', $word, $parts) && isset($units[$parts[2]])) {

			// Written together already, with the unit's long name (12volt).
			$word = $parts[1] . $units[$parts[2]];

		}

		if (isset($stop[$word])) {

			continue;

		}

		// A single letter on its own is noise; a single digit is a size.
		if ((strlen($word) < 2) && !ctype_digit($word)) {

			continue;

		}

		$tokens[$word] = true;

	}

	return array_keys($tokens);

}

// A code as letters and digits only, in capitals.
function mp_match_code_key($code) {

	return strtoupper(preg_replace('/[^a-z0-9]+/', '', mp_match_text_key($code)));

}

// Whether a code is distinctive enough to be looked for inside another one: a
// short code or one without a digit ("KORNA") would be found everywhere.
function mp_match_code_is_distinctive($key) {

	return (strlen($key) >= 4) && (bool) preg_match('/\d/', $key);

}

// How many suggestions are sure.
function mp_match_sure_count($suggested) {

	$count = 0;

	foreach ((array) $suggested as $row) {

		if (!empty($row['sure'])) {

			$count++;

		}

	}

	return $count;

}

// The suggestions for a page of remote rows.
//
// $remote:   list of array(key, code, barcode, title, price); key is whatever
//            identifies the row to the caller, price is in minor units, 0 when
//            unknown
// $products: list of array(id, code, title, barcodes, price) - the shop's
//            products that may still be matched
// $taken:    ids of shop products already matched on this account; they are
//            never offered
//
// Answers key => array(product_id, score, reason, sure) for the rows that get
// a suggestion. reason is barcode, code, code_part or title.
function mp_match_suggest($remote, $products, $taken = array()) {

	$taken_ids = array();

	foreach ((array) $taken as $id) {

		$taken_ids[(int) $id] = true;

	}

	$index = mp_match_index($products, $taken_ids);

	// Every candidate of every row, closest first per row.
	$candidates = array();

	foreach ((array) $remote as $row) {

		$key = (string) $row['key'];

		$found = mp_match_candidates($row, $index);

		if ($found) {

			$candidates[$key] = $found;

		}

	}

	// One to one: the closest pair anywhere is settled first, then the next,
	// each product and each row used once.
	$pairs = array();

	foreach ($candidates as $key => $found) {

		foreach ($found as $product_id => $candidate) {

			$pairs[] = array((string) $key, (int) $product_id, $candidate['score'], $candidate['reason']);

		}

	}

	usort($pairs, function ($a, $b) {

		if ($a[2] !== $b[2]) {

			return ($a[2] > $b[2]) ? -1 : 1;

		}

		$by_key = strcmp($a[0], $b[0]);

		return ($by_key !== 0) ? $by_key : (($a[1] < $b[1]) ? -1 : (($a[1] > $b[1]) ? 1 : 0));

	});

	$given = array();

	$used = array();

	foreach ($pairs as $pair) {

		list($key, $product_id, $score, $reason) = $pair;

		if (isset($given[$key]) || isset($used[$product_id])) {

			continue;

		}

		// Sure: high, and nothing else this row could be comes close. The
		// closest other candidate counts even when another row took it, so a
		// row that lost its best product is never sure of its second.
		$second = 0;

		foreach ($candidates[$key] as $other_id => $other) {

			if (($other_id !== $product_id) && ($other['score'] > $second)) {

				$second = $other['score'];

			}

		}

		$given[$key] = array(
			'product_id' => $product_id,
			'score'      => $score,
			'reason'     => $reason,
			'sure'       => ($score >= 85) && (($score - $second) >= 10)
		);

		$used[$product_id] = true;

	}

	return $given;

}

// What the shop's products are looked up by, built once per run.
function mp_match_index($products, $taken_ids) {

	$index = array(
		'products' => array(),
		'barcodes' => array(),
		'codes'    => array(),
		'code_ends' => array(),
		'title_codes' => array(),
		'words'    => array(),
		'df'       => array(),
		'count'    => 0
	);

	foreach ((array) $products as $product) {

		$id = (int) $product['id'];

		if (!$id || isset($taken_ids[$id])) {

			continue;

		}

		$tokens = mp_match_tokens(isset($product['title']) ? $product['title'] : '');

		$code = mp_match_code_key(isset($product['code']) ? $product['code'] : '');

		$index['products'][$id] = array(
			'tokens' => $tokens,
			'code'   => $code,
			'price'  => isset($product['price']) ? (int) $product['price'] : 0
		);

		foreach ((array) (isset($product['barcodes']) ? $product['barcodes'] : array()) as $barcode) {

			$barcode = mp_match_code_key($barcode);

			if (strlen($barcode) >= 8) {

				$index['barcodes'][$barcode][$id] = true;

			}

		}

		if ($code !== '') {

			$index['codes'][$code][$id] = true;

			// The shop's code may hold the marketplace's one at either end
			// (its own prefix or suffix around it); its starts and ends are
			// kept so that is a lookup, not a search through every code.
			if (mp_match_code_is_distinctive($code)) {

				for ($length = 4; $length < strlen($code); $length++) {

					$index['code_ends'][substr($code, 0, $length)][$id] = true;
					$index['code_ends'][substr($code, -$length)][$id] = true;

				}

			}

		}

		foreach ($tokens as $token) {

			$index['words'][$token][$id] = true;

			$index['df'][$token] = isset($index['df'][$token]) ? $index['df'][$token] + 1 : 1;

			// A code-like word of the shop's title (KRN12K written into it).
			$upper = strtoupper($token);

			if (mp_match_code_is_distinctive($upper)) {

				$index['title_codes'][$upper][$id] = true;

			}

		}

		$index['count']++;

	}

	return $index;

}

// How much a word says about which product it is.
function mp_match_weight($token, $index) {

	$df = isset($index['df'][$token]) ? $index['df'][$token] : 0;

	$weight = log(1 + $index['count'] / (1 + $df));

	return preg_match('/\d/', $token) ? $weight * 1.6 : $weight;

}

// One remote row's candidates: product id => array(score, reason), closest
// first, at most $limit. $min_score is the lowest title score kept; the
// picker asks for a lower one than a suggestion needs, to show a wider choice.
function mp_match_candidates($row, $index, $limit = 5, $min_score = null) {

	$found = array();

	$offer = function ($product_id, $score, $reason) use (&$found) {

		if (!isset($found[$product_id]) || ($found[$product_id]['score'] < $score)) {

			$found[$product_id] = array('score' => $score, 'reason' => $reason);

		}

	};

	$barcode = mp_match_code_key(isset($row['barcode']) ? $row['barcode'] : '');

	if ((strlen($barcode) >= 8) && isset($index['barcodes'][$barcode])) {

		foreach ($index['barcodes'][$barcode] as $id => $true) {

			$offer($id, 100, 'barcode');

		}

	}

	$code = mp_match_code_key(isset($row['code']) ? $row['code'] : '');

	if ($code !== '') {

		if (isset($index['codes'][$code])) {

			foreach ($index['codes'][$code] as $id => $true) {

				$offer($id, 98, 'code');

			}

		}

		if (mp_match_code_is_distinctive($code)) {

			// Their code holds the shop's code: every stretch of theirs is
			// looked up as a whole shop code.
			$length = strlen($code);

			for ($start = 0; $start < $length; $start++) {

				for ($size = 4; $start + $size <= $length; $size++) {

					if ($size === $length) {

						continue;

					}

					$part = substr($code, $start, $size);

					if (isset($index['codes'][$part]) && mp_match_code_is_distinctive($part)) {

						foreach ($index['codes'][$part] as $id => $true) {

							$offer($id, 90, 'code_part');

						}

					}

				}

			}

			// The shop's code holds theirs, at one end.
			if (isset($index['code_ends'][$code])) {

				foreach ($index['code_ends'][$code] as $id => $true) {

					$offer($id, 90, 'code_part');

				}

			}

			// Their code written into the shop's title.
			if (isset($index['title_codes'][$code])) {

				foreach ($index['title_codes'][$code] as $id => $true) {

					$offer($id, 88, 'code_part');

				}

			}

		}

	}

	$tokens = mp_match_tokens(isset($row['title']) ? $row['title'] : '');

	// The shop's code written into their title.
	foreach ($tokens as $token) {

		$upper = strtoupper($token);

		if (mp_match_code_is_distinctive($upper) && isset($index['codes'][$upper])) {

			foreach ($index['codes'][$upper] as $id => $true) {

				$offer($id, 88, 'code_part');

			}

		}

	}

	foreach (mp_match_title_candidates($tokens, $row, $index, $min_score) as $id => $score) {

		$offer($id, $score, 'title');

	}

	uasort($found, function ($a, $b) {

		return ($a['score'] === $b['score']) ? 0 : (($a['score'] > $b['score']) ? -1 : 1);

	});

	return array_slice($found, 0, $limit, true);

}

// Products whose titles share words with this one, scored.
function mp_match_title_candidates($tokens, $row, $index, $min_score = null) {

	if ($min_score === null) {

		$min_score = mp_match_min_score();

	}

	if (!$tokens || !$index['count']) {

		return array();

	}

	$weights = array();

	foreach ($tokens as $token) {

		$weights[$token] = mp_match_weight($token, $index);

	}

	// Candidates come from the words few products share; a word half the
	// catalogue carries would make every product a candidate. The three
	// rarest words are always asked, so a title of common words still finds
	// something.
	$common = max(30, (int) ceil($index['count'] * 0.05));

	$by_rarity = $weights;

	arsort($by_rarity);

	$asked = 0;

	$reach = array();

	foreach ($by_rarity as $token => $weight) {

		if (!isset($index['words'][$token])) {

			continue;

		}

		$asked++;

		if (($asked > 3) && (count($index['words'][$token]) > $common)) {

			continue;

		}

		foreach ($index['words'][$token] as $id => $true) {

			$reach[$id] = (isset($reach[$id]) ? $reach[$id] : 0) + $weight;

		}

	}

	arsort($reach);

	$reach = array_slice($reach, 0, 50, true);

	$remote_weight = array_sum($weights);

	$remote_price = isset($row['price']) ? (int) $row['price'] : 0;

	$scores = array();

	foreach ($reach as $id => $shared) {

		$product = $index['products'][$id];

		$product_weight = 0;

		$common_weight = 0;

		$missing_number = false;

		foreach ($product['tokens'] as $token) {

			$weight = isset($weights[$token]) ? $weights[$token] : mp_match_weight($token, $index);

			$product_weight += $weight;

			if (isset($weights[$token])) {

				$common_weight += $weight;

			} elseif (preg_match('/\d/', $token)) {

				$missing_number = true;

			}

		}

		if (($product_weight <= 0) || ($common_weight <= 0)) {

			continue;

		}

		// How much of the shop's title is in theirs (theirs is usually the
		// longer one, written for search), and how alike the two are overall.
		$contained = $common_weight / $product_weight;

		$dice = 2 * $common_weight / ($product_weight + $remote_weight);

		$score = 100 * (0.65 * $contained + 0.35 * $dice);

		// A number on each side that the other lacks is two different models
		// (H4 and H7), whatever else they share.
		if ($missing_number) {

			foreach ($tokens as $token) {

				if (preg_match('/\d/', $token) && !in_array($token, $product['tokens'], true)) {

					$score *= 0.7;

					break;

				}

			}

		}

		// The price only moves a close call.
		if (($remote_price > 0) && ($product['price'] > 0)) {

			$ratio = min($remote_price, $product['price']) / max($remote_price, $product['price']);

			if ($ratio >= 0.85) {

				$score += 4;

			} elseif ($ratio < 0.5) {

				$score -= 8;

			}

		}

		$score = (int) round(min(99, max(0, $score)));

		if ($score >= $min_score) {

			$scores[$id] = $score;

		}

	}

	return $scores;

}
