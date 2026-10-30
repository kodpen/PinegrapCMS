<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The listings view of marketplace_products.php: the shop's articles (product
// groups that carry attributes, mp_listing_articles()) with their pictures,
// the marketplace category each is filed under, what that category still
// asks for, and the steps that file, answer and list them.
//
// The category and the answers are the same marketplace_category_map row the
// listing job reads (listings.php); nothing here talks to the marketplace
// except reading a category's attributes once, when it is chosen.

if (!defined('PG_INIT_LOADED')) {

	exit;

}

require_once(dirname(__FILE__) . '/product_matches.php');

/* ----------------------------------------------------------------- pure */

// A category's attributes in the order the form asks them: what the article
// must answer, then what its variants answer, then the rest, which the form
// folds away. Each keeps the marketplace's own order.
function mp_articles_sections($definitions) {

	$sections = array('required' => array(), 'variant' => array(), 'optional' => array());

	foreach ((array) $definitions as $definition) {

		if (!empty($definition['is_variant'])) {

			$sections['variant'][] = $definition;

		} elseif (!empty($definition['mandatory'])) {

			$sections['required'][] = $definition;

		} else {

			$sections['optional'][] = $definition;

		}

	}

	return $sections;

}

// The names of the required attributes that have no answer, the same test
// mp_listing_items() refuses an article by.
function mp_articles_missing($definitions, $chosen) {

	$missing = array();

	foreach (mp_articles_sections($definitions)['required'] as $definition) {

		$id = (string) $definition['attribute_id'];

		if (!isset($chosen[$id]) || trim((string) $chosen[$id]) === '') {

			$missing[] = (string) $definition['name'];

		}

	}

	return $missing;

}

// Where an article stands: 'error' (the last listing was refused), 'none' (no
// category), 'unread' (a category whose attributes are not in the cache),
// 'missing' (required answers left) or 'ready'. $definitions is null when the
// category's attributes have not been read.
function mp_articles_state($article, $definitions) {

	if ((string) $article['last_error'] !== '') {

		return 'error';

	}

	if ((string) $article['remote_category_id'] === '') {

		return 'none';

	}

	if ($definitions === null) {

		return 'unread';

	}

	$chosen = json_decode((string) $article['attributes_json'], true);

	return mp_articles_missing($definitions, is_array($chosen) ? $chosen : array()) ? 'missing' : 'ready';

}

// The states each filter of the view holds; 'all' and an unknown name hold
// every state.
function mp_articles_filter_states($filter) {

	$filters = array(
		'none'    => array('none'),
		'missing' => array('missing', 'unread'),
		'ready'   => array('ready'),
		'errors'  => array('error')
	);

	return isset($filters[$filter]) ? $filters[$filter] : null;

}

// How many articles each filter holds.
function mp_articles_counts($articles) {

	$counts = array('all' => 0, 'none' => 0, 'missing' => 0, 'ready' => 0, 'errors' => 0);

	foreach ((array) $articles as $article) {

		$counts['all']++;

		foreach (array('none', 'missing', 'ready', 'errors') as $filter) {

			if (in_array($article['state'], mp_articles_filter_states($filter), true)) {

				$counts[$filter]++;

			}

		}

	}

	return $counts;

}

// One page of the articles a filter and a search hold: array('rows', 'total').
// The search looks in the article's code and title.
function mp_articles_page($articles, $filter, $search, $page, $per_page) {

	$states = mp_articles_filter_states($filter);

	// Folded on both sides (İ/I/ı/i and accents), as the Shop and Remote
	// tabs' LIKE under utf8mb4_unicode_ci matches.
	$search = fold_keyword_for_matching($search);

	$found = array();

	foreach ((array) $articles as $article) {

		if (($states !== null) && !in_array($article['state'], $states, true)) {

			continue;

		}

		if (($search !== '') && (mb_strpos(fold_keyword_for_matching($article['name']), $search) === false)
			&& (mb_strpos(fold_keyword_for_matching($article['title']), $search) === false)) {

			continue;

		}

		$found[] = $article;

	}

	$per_page = max(1, (int) $per_page);

	return array(
		'rows'  => array_slice($found, (max(1, (int) $page) - 1) * $per_page, $per_page),
		'total' => count($found)
	);

}

// The words of a category search: the typed text split on spaces and on the
// path's ">", at most six words of at most 50 characters each.
function mp_articles_category_words($query) {

	$words = preg_split('/[\s>]+/u', trim((string) $query), -1, PREG_SPLIT_NO_EMPTY);

	return array_map(function ($word) { return mb_substr($word, 0, 50); }, array_slice((array) $words, 0, 6));

}

// A category path split into its parents and its own name, so the name can be
// shown first: "Auto > Lighting > Bulbs" is array('Auto > Lighting', 'Bulbs').
function mp_articles_category_label($path) {

	$parts = array_map('trim', explode('>', (string) $path));

	$leaf = (string) array_pop($parts);

	return array(implode(' > ', $parts), $leaf);

}

/* ------------------------------------------------------------- reads */

// The cached attributes of some categories, keyed by category; a category
// whose attributes were never read is absent.
function mp_articles_definitions($provider, $category_ids) {

	$category_ids = array_values(array_unique(array_filter(array_map('strval', (array) $category_ids), 'strlen')));

	if (!$category_ids) {

		return array();

	}

	$rows = (array) db_items("SELECT * FROM marketplace_category_attributes
		WHERE (provider = '" . e((string) $provider) . "')
		  AND (category_id IN ('" . implode("','", array_map('e', $category_ids)) . "'))
		ORDER BY sort_order ASC, id ASC");

	$definitions = array();

	foreach (mp_category_attributes_decode($rows) as $row) {

		$definitions[(string) $row['category_id']][] = $row;

	}

	return $definitions;

}

// The account's articles, each with its state, the required answers it
// lacks and its picture; $group_ids narrows it to those articles.
function mp_articles_list($account, $group_ids = null) {

	$articles = mp_listing_articles((int) $account['id'], 5000, $group_ids);

	$definitions = mp_articles_definitions($account['provider'], array_map(function ($article) {
		return (string) $article['remote_category_id'];
	}, $articles));

	foreach ($articles as $index => $article) {

		$article['remote_category_id'] = (string) $article['remote_category_id'];

		$article['last_error'] = (string) $article['last_error'];

		$own = isset($definitions[$article['remote_category_id']]) ? $definitions[$article['remote_category_id']] : null;

		$chosen = json_decode((string) $article['attributes_json'], true);

		$article['state'] = mp_articles_state($article, $own);

		$article['missing'] = $own ? mp_articles_missing($own, is_array($chosen) ? $chosen : array()) : array();

		$article['image'] = ((string) $article['group_image'] !== '') ? (string) $article['group_image'] : (string) $article['product_image'];

		$articles[$index] = $article;

	}

	return $articles;

}

// Leaf categories whose path holds every word typed, shortest paths first.
function mp_articles_category_search($provider, $query, $limit = 40) {

	$words = mp_articles_category_words($query);

	if (!$words) {

		return array();

	}

	$where = '';

	foreach ($words as $word) {

		$where .= " AND (path LIKE '%" . e(addcslashes($word, '%_\\')) . "%')";

	}

	return (array) db_items("SELECT remote_id, path FROM marketplace_categories
		WHERE (provider = '" . e((string) $provider) . "') AND (is_leaf = '1')" . $where . "
		ORDER BY CHAR_LENGTH(path) ASC, path ASC
		LIMIT " . (int) $limit);

}

/* ------------------------------------------------------------- writes */

// File some articles under one category. Only a leaf can hold a product: a
// branch is a shelf on their side too, and a listing filed against one is
// refused, by them, hours later. The category's attributes are read now,
// while somebody is waiting and can be told it failed.
//
// Answers array('ok', 'error', 'count', 'warning').
function mp_articles_set_category($account, $group_ids, $category_id) {

	$account_id = (int) $account['id'];

	$category_id = trim((string) $category_id);

	$leaf = ($category_id !== '') ? db_item("SELECT remote_id, is_leaf FROM marketplace_categories
		WHERE (provider = '" . e($account['provider']) . "') AND (remote_id = '" . e($category_id) . "')") : null;

	if (!$leaf) {

		return array('ok' => false, 'error' => lang('Choose a category from the list.'), 'count' => 0, 'warning' => '');

	}

	if (empty($leaf['is_leaf'])) {

		return array('ok' => false, 'error' => lang('That is a parent category. Pick one of the categories inside it.'), 'count' => 0, 'warning' => '');

	}

	// Articles only: a group without attributes is a shelf, never listed.
	$group_ids = mp_matches_ids($group_ids);

	$group_ids = $group_ids ? array_map('intval', array_column((array) db_items("SELECT DISTINCT product_group_id
		FROM product_groups_attributes_xref
		WHERE product_group_id IN ('" . implode("','", $group_ids) . "')"), 'product_group_id')) : array();

	foreach ($group_ids as $group_id) {

		$existing = db_value("SELECT id FROM marketplace_category_map
			WHERE (account_id = '" . e($account_id) . "') AND (group_id = '" . e($group_id) . "')");

		if ($existing) {

			// A different category means different attributes, so what was
			// answered for the old one is thrown away rather than carried
			// across under ids that mean something else there.
			//
			// attributes_json is assigned BEFORE remote_category_id. MySQL
			// evaluates a SET list left to right and a later assignment sees
			// the new value of an earlier one, so testing the category after
			// setting it compares the new value with itself and the answers
			// are always kept.
			db("UPDATE marketplace_category_map
				SET attributes_json = IF(remote_category_id = '" . e($category_id) . "', attributes_json, ''),
					remote_category_id = '" . e($category_id) . "',
					last_error = '', updated_timestamp = UNIX_TIMESTAMP()
				WHERE id = '" . e((int) $existing) . "'");

		} else {

			db("INSERT INTO marketplace_category_map
				(account_id, group_id, remote_category_id, attributes_json, updated_timestamp)
				VALUES ('" . e($account_id) . "', '" . e($group_id) . "', '" . e($category_id) . "', '',
					UNIX_TIMESTAMP())");

		}

	}

	$warning = '';

	if ($group_ids) {

		$loaded = mp_category_attributes($account, $category_id);

		$warning = $loaded['ok'] ? '' : (string) $loaded['error'];

	}

	return array('ok' => true, 'error' => '', 'count' => count($group_ids), 'warning' => $warning);

}

// Save an article's answers; an empty answer is not kept.
function mp_articles_save_attributes($account, $group_id, $values) {

	$map_id = db_value("SELECT id FROM marketplace_category_map
		WHERE (account_id = '" . e((int) $account['id']) . "') AND (group_id = '" . e((int) $group_id) . "')
		  AND (remote_category_id != '')");

	if (!$map_id) {

		return false;

	}

	$answers = array();

	foreach ((array) $values as $attribute_id => $value) {

		$value = is_scalar($value) ? trim((string) $value) : '';

		if ($value !== '') {

			$answers[(string) $attribute_id] = mb_substr($value, 0, 500);

		}

	}

	db("UPDATE marketplace_category_map
		SET attributes_json = '" . e(json_encode($answers)) . "', last_error = '',
			updated_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . e((int) $map_id) . "'");

	return true;

}

// Queue some articles to be listed. Each is checked first as the job will
// check it (mp_listing_items()), so a refusal is told now rather than
// written on the article hours later. Answers array('queued', 'refused'), the
// refusals keyed by article.
function mp_articles_list_now($account, $group_ids) {

	$done = array('queued' => 0, 'refused' => array());

	foreach (mp_matches_ids($group_ids) as $group_id) {

		$ready = mp_listing_items($account, $group_id);

		if (!$ready['ok']) {

			$done['refused'][$group_id] = (string) $ready['error'];

			continue;

		}

		$done['queued'] += mp_listing_enqueue((int) $account['id'], $group_id);

	}

	return $done;

}
