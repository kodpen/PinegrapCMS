<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The OpenAPI description, generated from the route table.
//
// It is generated rather than written because a hand-kept description drifts
// from the code within weeks, and a description that lies is worse than none:
// the integrator trusts it and the endpoint does something else. Everything
// here comes out of api_schema(), so a document that is wrong means the router
// is wrong too, and both are fixed in one place.
//
// The document is what feeds the panel's test screen and what an outside
// developer loads into their own tooling to generate a client.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(dirname(__FILE__) . '/preconditions.php');

function api_openapi_base_url() {

	$scheme = defined('URL_SCHEME') ? URL_SCHEME : 'https://';

	$host = defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '';

	$path = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '');

	return $scheme . $host . $path . '/integration.php';

}

function api_openapi_build() {

	$paths = array();

	$tags = array();

	$routes = api_schema();

	foreach ($routes as $route) {

		$tags[] = api_openapi_tag($route['id']);

		$path = $route['path'];

		if (!isset($paths[$path])) {

			$paths[$path] = array();

		}

		$operation = array(
			'operationId' => str_replace('.', '_', $route['id']),
			'summary'     => $route['summary'],
			'tags'        => array(api_openapi_tag($route['id'])),
			// Either credential opens an endpoint: an application's key and
			// secret, or a Bearer access token - a person's own team devices
			// session or an OAuth connection. The session's own sign-in calls
			// ('public': login, refresh, logout) need neither.
			'security'    => !empty($route['public'])
				? array()
				: array(array('applicationKey' => array()), array('deviceToken' => array())),
			'responses'   => api_openapi_responses($route)
		);

		if (isset($route['description'])) {

			$operation['description'] = $route['description'];

		}

		if ($route['scope'] !== '') {

			$operation['description'] = (isset($operation['description']) ? $operation['description'] . ' ' : '')
				. 'Requires the ' . $route['scope'] . ' scope.';

		}

		$parameters = array();

		$body_properties = array();

		$body_required = array();

		foreach ($route['params'] as $param) {

			$where = isset($param['in']) ? $param['in'] : 'query';

			if ($where === 'body') {

				$property = api_openapi_type($param);

				// A body field is described in its own schema: unlike a query
				// parameter it has no entry of its own to carry the text.
				$said = isset($param['description']) ? $param['description'] : api_openapi_id_description($route, $param);

				if ($said !== null) {

					$property['description'] = isset($property['description'])
						? $said . ' ' . $property['description']
						: $said;

				}

				// The router takes JSON null for a nullable field as the
				// clear (api_validate_input()), and the MCP tool says so too
				// (pg_mcp_param_schema()); a generated client that was not
				// told cannot send it.
				if (!empty($param['nullable'])) {

					$property['nullable'] = true;

					// An enum lists every value it takes, so a nullable one
					// lists null as well, or a validator refuses the clear.
					if (isset($property['enum'])) {

						$property['enum'][] = null;

					}

				}

				$body_properties[$param['name']] = $property;

				if (!empty($param['required'])) {

					$body_required[] = $param['name'];

				}

				continue;

			}

			$entry = array(
				'name'     => $param['name'],
				'in'       => ($where === 'path') ? 'path' : 'query',
				'required' => ($where === 'path') ? true : !empty($param['required']),
				'schema'   => api_openapi_type($param)
			);

			if (isset($param['description'])) {

				$entry['description'] = $param['description'];

			} elseif (($generated = api_openapi_id_description($route, $param)) !== null) {

				$entry['description'] = $generated;

			}

			$parameters[] = $entry;

		}

		// Every read can be trimmed and asked conditionally; the response layer
		// does both for every endpoint, so they are declared here once rather
		// than on each route. See api_response_fields() and api_etag_matches().
		if ($route['method'] === 'GET') {

			// A file (api_send_file()) has no fields to keep.
			if (empty($route['binary'])) {

				$parameters[] = array(
					'name'        => 'fields',
					'in'          => 'query',
					'required'    => false,
					'description' => 'Comma separated top-level fields to keep in each record, for example id,name,price. id is always kept; an unknown name is ignored.',
					'schema'      => array('type' => 'string')
				);

			}

			$parameters[] = array(
				'name'        => 'If-None-Match',
				'in'          => 'header',
				'required'    => false,
				'description' => 'The ETag of an answer you already hold. When nothing has changed the answer is 304 with no body.',
				'schema'      => array('type' => 'string')
			);

			// A file is also sent in parts (api_send_file()).
			if (!empty($route['binary'])) {

				$parameters[] = array(
					'name'        => 'Range',
					'in'          => 'header',
					'required'    => false,
					'description' => 'One range of bytes, such as bytes=0-1048575 or bytes=-500. The answer is 206 with that part and Content-Range; a range past the end is 416. Several ranges, or one that cannot be read, get the whole file.',
					'schema'      => array('type' => 'string')
				);

				$parameters[] = array(
					'name'        => 'If-Range',
					'in'          => 'header',
					'required'    => false,
					'description' => 'The ETag of the file you hold a part of. When the file has changed since, the whole new file comes back (200) rather than a part of it.',
					'schema'      => array('type' => 'string')
				);

			}

		}

		if (!empty($parameters)) {

			$operation['parameters'] = $parameters;

		}

		if (!empty($body_properties)) {

			$body_schema = array('type' => 'object', 'properties' => $body_properties);

			if (!empty($body_required)) {

				$body_schema['required'] = $body_required;

			}

			// One body that passes the router's validator: the same values
			// tests/api_route_examples_test.php sends through it.
			$content = array('application/json' => array('schema' => $body_schema, 'example' => api_openapi_example_body($route)));

			// A route that also takes its file as a form part names the part
			// ('form_file'): the form carries the same fields, with the file
			// itself in place of its base64.
			if (!empty($route['form_file'])) {

				$form_schema = $body_schema;

				unset($form_schema['properties']['content_base64']);

				$form_schema['properties'][(string)$route['form_file']] = array('type' => 'string', 'format' => 'binary');

				$content['multipart/form-data'] = array('schema' => $form_schema);

			}

			$operation['requestBody'] = array(
				'required' => !empty($body_required),
				'content'  => $content
			);

		}

		// A write may carry an idempotency key. Declared here rather than in the
		// route table because it is true of every write and repeating it on each
		// one is how the two lists start to disagree.
		if (in_array($route['method'], array('POST', 'PUT', 'PATCH', 'DELETE'), true)) {

			if (!isset($operation['parameters'])) {

				$operation['parameters'] = array();

			}

			$operation['parameters'][] = array(
				'name'        => 'Idempotency-Key',
				'in'          => 'header',
				'required'    => false,
				'description' => 'A value of your choosing, up to 64 characters. Repeating a request with the same key returns the first answer instead of applying the change again. A repeat sent while the first request is still being carried out is answered 409 idempotency_in_progress with Retry-After; send it again then.',
				'schema'      => array('type' => 'string', 'maxLength' => 64)
			);

			// Declared only where the handler stops for it. A module writes its
			// own routes through the seam, and one that has not put the stop in
			// must not advertise a rehearsal that would write.
			if (!empty($route['dry_run'])) {

				$operation['parameters'][] = array(
					'name'        => 'X-Dry-Run',
					'in'          => 'header',
					'required'    => false,
					'description' => 'true runs every check this endpoint makes and then answers instead of writing. The answer is {"dry_run": true, "would": "created", "resource": "product"}; a refusal is the real refusal, with the same code and the same field.',
					'schema'      => array('type' => 'string', 'enum' => array('true', 'false'))
				);

				// The same, for a caller that cannot set a header.
				$operation['parameters'][] = array(
					'name'        => 'dry_run',
					'in'          => 'query',
					'required'    => false,
					'description' => 'The same as X-Dry-Run, in the query string: ?dry_run=true. Either one asking for a dry run is enough.',
					'schema'      => array('type' => 'string', 'enum' => array('true', 'false'))
				);

			}

		}

		// An update whose path a GET reads takes that GET's ETag in If-Match
		// (api_if_match_check()); one without a GET twin ignores the header
		// and does not list it.
		if (api_update_get_twin($route, $routes) !== null) {

			$operation['parameters'][] = array(
				'name'        => 'If-Match',
				'in'          => 'header',
				'required'    => false,
				'description' => 'The ETag of GET ' . $route['path'] . ' (read in full, without fields=). When the record has changed since, the answer is 412 precondition_failed and nothing is written; * matches any record that exists. Without it the later write stands. Checked right before the write, with no lock.',
				'schema'      => array('type' => 'string')
			);

			$operation['responses']['412'] = array(
				'description' => 'precondition_failed: the record no longer answers with the ETag sent in If-Match. Read it again and repeat the change.',
				'content'     => $operation['responses']['409']['content']
			);

			ksort($operation['responses'], SORT_STRING);

		}

		$paths[$path][strtolower($route['method'])] = $operation;

		// A route that answers to a second verb is listed under that verb too.
		//
		// A generated client, or a test suite built from this document, reads
		// nothing but what is written here. Leaving the alias out is what turns a
		// documented fallback into a test that fails: a default IIS install
		// answers DELETE itself before PHP is reached, the API accepts POST for
		// exactly that reason, and a document that only names DELETE sends the
		// caller down the road that does not arrive.
		if (!empty($route['also_accepts'])) {

			foreach ($route['also_accepts'] as $spare) {

				$alias = $operation;

				$alias['operationId'] = $operation['operationId'] . '_' . strtolower($spare);

				$alias['summary'] = $operation['summary'] . ' (' . strtoupper($spare) . ')';

				$paths[$path][strtolower($spare)] = $alias;

			}

		}

	}

	return array(
		'openapi' => '3.0.3',
		'info' => array(
			'title'       => (defined('TITLE') ? TITLE : 'Pinegrap') . ' API',
			'version'     => (string)api_version(),
			'description' => 'Read and write products, stock, orders and customers. '
				. 'Money is always a whole number of minor units - 1999 is 19.99. '
				. 'Times are ISO-8601 in UTC. Listings are cursor paged: follow page.next_cursor until it is null. '
				. 'Every read answers with an ETag and takes If-None-Match (304 when unchanged) and fields= (only the fields named). '
				. 'An integration authenticates with its application key and secret (HTTP Basic). A person\'s own team devices session (POST /auth/login) and an OAuth connection send their access token as Authorization: Bearer. The security schemes say which calls each of the three can make; while the site runs an OAuth server, the oauth2 scheme names its addresses.'
		),
		'servers' => array(array('url' => api_openapi_base_url())),
		'components' => array(
			'schemas' => api_openapi_components(),
			'securitySchemes' => api_openapi_security_schemes(api_openapi_oauth_server())
		),
		'security' => array(array('applicationKey' => array()), array('deviceToken' => array())),
		'tags' => api_openapi_tags($tags),
		'paths' => $paths
	);

}

/**
 * The two ways a call proves who makes it, and what each kind of caller may
 * do. Three kinds share the two schemes: an application key (Basic), and a
 * person's own team devices session or an OAuth connection (both Bearer, both
 * rows of api_devices - api_device_authenticate()). The refusals the person-only
 * calls answer with (ws_api_person_only(), ws_api_own_words_only()) name them
 * the same way.
 *
 * The Bearer scheme keeps the name deviceToken: a generated client refers to a
 * scheme by its name, and renaming it would break every client built so far.
 *
 * While the site's OAuth application exists, a third scheme, oauth2, names
 * where a connection is made: the site's own authorization and token
 * addresses and the scopes of the operator's ceiling. Without the application
 * every OAuth address answers 404, so the scheme is left out.
 *
 * @param array|null $oauth api_openapi_oauth_server(): authorizationUrl,
 *                          tokenUrl, refreshUrl and scopes (a list), or null
 * @return array
 */
function api_openapi_security_schemes($oauth = null) {

	$schemes = array(
		'applicationKey' => array(
			'type'        => 'http',
			'scheme'      => 'basic',
			'description' => 'HTTP Basic. The user name is the application key, the password is its secret. '
				. 'An application key calls in the name of the account that owns it, within the scopes it was given, '
				. 'and is the only credential that can manage webhook subscriptions. '
				. 'A call only the person can make - marking what they have read, answering an approval, changing or deleting their own messages - answers 403 forbidden.'
		),
		'deviceToken' => array(
			'type'        => 'http',
			'scheme'      => 'bearer',
			'description' => 'An access token, of one of two kinds. '
				. 'The person\'s own team devices session (POST /auth/login) calls as that person, within the team devices application\'s permissions, '
				. 'and is the only credential that can change the person\'s own words: change, delete or schedule their messages, ask or change a poll, pin a message. '
				. 'An OAuth connection, made through the site\'s OAuth server, calls as the person who approved it, within the scopes they agreed to: '
				. 'it can make the other calls only the person can make, such as marking what they have read or answering an approval, '
				. 'but changing their own words answers 403 forbidden. '
				. 'Neither can manage webhook subscriptions. '
				. 'An access token lives an hour: renew a session with POST /auth/refresh, a connection with its refresh token at the OAuth server\'s token endpoint.'
		)
	);

	if (is_array($oauth)) {

		$schemes['oauth2'] = api_openapi_oauth_scheme($oauth);

	}

	return $schemes;

}

/**
 * The oauth2 scheme: the authorization code flow (with PKCE, which the
 * discovery document announces) at the given addresses. Each scope is
 * described by its group's label, so a platform's sign-in screen can show
 * what it asks for. Pure: the caller reads the site.
 *
 * @param array $oauth authorizationUrl, tokenUrl, refreshUrl, scopes (a list)
 * @return array
 */
function api_openapi_oauth_scheme(array $oauth) {

	$labels = array();

	foreach (api_scope_groups() as $group) {

		if ($group['read'] !== '') {

			$labels[$group['read']] = 'Read ' . $group['label'];

		}

		if ($group['write'] !== '') {

			$labels[$group['write']] = 'Write ' . $group['label'];

		}

	}

	$scopes = array();

	foreach ((array)$oauth['scopes'] as $scope) {

		$scopes[(string)$scope] = isset($labels[$scope]) ? $labels[$scope] : (string)$scope;

	}

	ksort($scopes);

	return array(
		'type'        => 'oauth2',
		'description' => 'The site\'s own OAuth server. A person signs in on this site and approves the connection; '
			. 'the scopes offered are the ones the site\'s operator allows, within the person\'s own rights. '
			. 'The access token it hands out is sent as Authorization: Bearer, so every call that names deviceToken takes it, '
			. 'with what deviceToken says an OAuth connection may do. PKCE (S256) is required.',
		'flows'       => array(
			'authorizationCode' => array(
				'authorizationUrl' => (string)$oauth['authorizationUrl'],
				'tokenUrl'         => (string)$oauth['tokenUrl'],
				'refreshUrl'       => (string)$oauth['refreshUrl'],
				// An empty PHP array would encode as [], and OpenAPI wants a map.
				'scopes'           => empty($scopes) ? new stdClass() : $scopes
			)
		)
	);

}

/**
 * The site's OAuth server as the oauth2 scheme needs it, or null while the
 * site has no OAuth application (every OAuth address then answers 404).
 *
 * @return array|null
 */
function api_openapi_oauth_server() {

	if (!function_exists('pg_oauth_app')) {

		require_once(dirname(dirname(__FILE__)) . '/oauth/server.php');

	}

	$app = pg_oauth_app();

	if ($app === null) {

		return null;

	}

	return array(
		'authorizationUrl' => pg_oauth_endpoint('authorize'),
		'tokenUrl'         => pg_oauth_endpoint('token'),
		'refreshUrl'       => pg_oauth_endpoint('token'),
		'scopes'           => pg_oauth_ceiling($app)
	);

}

/**
 * One sentence for each group the document lists its operations under, by the
 * tag api_openapi_tag() gives a route. tests/api_openapi_explains_test.php
 * keeps it in step with the route table: a group without a sentence, or a
 * sentence without a group, fails it.
 *
 * @return array tag => sentence
 */
function api_openapi_tag_sentences()
{
    return array(
        'Meta'               => 'What this site is: the API version, the software, its time zone, the store\'s currency and order statuses.',
        'Guide'              => 'How to work on this site, written for AI agents: the topic list, a topic, or one section of it.',
        'Products'           => 'Products of the store: read, create, change, delete, and set prices in bulk.',
        'Inventory'          => 'Stock of the products: levels by location, moves between locations, and the history of every change.',
        'Product_groups'     => 'Product groups, which show several products on one page, and their variant sets.',
        'Product_attributes' => 'Product attributes, such as size or colour, and their options.',
        'Orders'             => 'Orders: read them, change their status, ship them, print their documents and record what was paid.',
        'Returns'            => 'Return requests of orders: read them and decide them.',
        'Cart_quotes'        => 'Price quotes for a cart: what checkout would charge for a set of products and an address.',
        'Customers'          => 'The store\'s customers: their accounts, addresses, store credit and consents.',
        'Store'              => 'How checkout charges: shipping methods, shipping and tax zones, countries, currencies and product feeds.',
        'Campaigns'          => 'E-mail and SMS campaigns, their sending profiles and their recipients.',
        'Contact_groups'     => 'Contact groups, filled by hand or by a rule, and their members.',
        'Site_log'           => 'The site log: what was done on the site, by whom and when.',
        'Fediverse'          => 'The site\'s fediverse account: who follows it and how its posts were delivered.',
        'Audit_log'          => 'The audit trail: who changed what, as the Audit trail screen lists it.',
        'Gift_cards'         => 'Gift cards: their balance, expiry and redemptions.',
        'Commissions'        => 'Affiliate commissions and their recurring profiles.',
        'Affiliates'         => 'Affiliates: approving a contact as one.',
        'Calendars'          => 'Calendars, their events, locations and reservations.',
        'Webhooks'           => 'Webhook subscriptions: the events the site sends to an address, signed, with their deliveries.',
        'Subscriptions'      => 'Subscriptions the store charges from a stored card.',
        'Users'              => 'The site\'s users: their accounts, roles and permissions.',
        'System'             => 'The state of the site\'s server: maintenance, storage and the checks it runs.',
        'Reports'            => 'Read-only figures: sales, cart recovery, traffic, visitors, search and the most read content.',
        'Seo'                => 'Search engine findings: the issues of each page and the addresses that answered not found.',
        'Content_review'     => 'Pages due for a review of their content, and the reviews made.',
        'Forms'              => 'Forms, their fields and the submissions they received.',
        'Pages'              => 'The site\'s pages: their settings, folder and publishing.',
        'Folders'            => 'Folders, which hold pages and files and decide who can open them.',
        'Menus'              => 'Menus and their items.',
        'Comments'           => 'Comments and product reviews, with the store\'s replies.',
        'Experiments'        => 'A/B experiments between versions of a page, and their results.',
        'Short_links'        => 'Short links that send a visitor on to an address.',
        'Files'              => 'Files of the site: upload, read, replace and delete.',
        'Offers'             => 'Offers, the discounts checkout applies, and their rules.',
        'Key_codes'          => 'Key codes, which a customer enters at checkout to unlock an offer.',
        'Design'             => 'The visual designer: designs, pages, proposals, revisions, ads, auto dialogs and common regions.',
        'Auth'               => 'A person\'s own team devices session: sign in, pair, refresh, sign out, and the devices signed in.',
        'Notifications'      => 'The caller\'s notifications and what of them was read.',
        'Push'               => 'Push notifications to the caller\'s browsers and devices.',
        'Bookings'           => 'Bookings of a service, and the free slots to book.',
        'Erp'                => 'The accounting module: accounts, invoices, payments, cash, stock documents and e-documents.',
        'Workspace'          => 'The team workspace: channels, messages, discussions, tasks, notes, tables, flows and agents.',
        'Translations'       => 'Translations of the site: languages, strings, pages and the glossary.',
    );
}

/**
 * The document's tags list: each group with its sentence, in the order the
 * groups first appear. A group with no sentence is listed by name alone.
 *
 * @param array $names tags in use
 * @return array
 */
function api_openapi_tags(array $names)
{
    $sentences = api_openapi_tag_sentences();

    $tags = array();

    foreach (array_values(array_unique($names)) as $name) {

        $tag = array('name' => $name);

        if (isset($sentences[$name])) {

            $tag['description'] = $sentences[$name];

        }

        $tags[] = $tag;

    }

    return $tags;
}

/**
 * One value a client would send for a parameter, read only from its
 * declaration: the smallest accepted number, the first enum value, one item.
 * The document's example bodies and tests/helpers/api_route_examples_run.php
 * (which sends every one through the router's validator) both use it.
 *
 * @param array $param a route parameter
 * @return mixed
 */
function api_openapi_example_value($param)
{
    $type = isset($param['type']) ? $param['type'] : 'string';

    switch ($type) {

        case 'int':
        case 'money':

            return isset($param['min']) ? (int)$param['min'] : (isset($param['max']) ? min(1, (int)$param['max']) : 1);

        case 'decimal':

            return isset($param['min']) ? $param['min'] : (isset($param['max']) ? min(1, $param['max']) : 1);

        case 'bool':

            return true;

        case 'enum':

            return isset($param['values'][0]) ? $param['values'][0] : '';

        case 'datetime':

            return '2026-11-07';

        case 'object':

            return array('key' => 'value');

        case 'list':

            $of = isset($param['of']) ? $param['of'] : null;

            if ($of === 'integer') {

                return array(1);

            }

            if ($of === 'string') {

                return array('a');

            }

            return array(array());

        default:

            return 'a';

    }
}

/**
 * An example body for a route: every body field with the value
 * api_openapi_example_value() gives it. Null when the route takes no body.
 *
 * @param array $route
 * @return array|null
 */
function api_openapi_example_body($route)
{
    $body = array();

    foreach ($route['params'] as $param) {

        if ((isset($param['in']) ? $param['in'] : 'query') === 'body') {

            $body[$param['name']] = api_openapi_example_value($param);

        }

    }

    return empty($body) ? null : $body;
}

/**
 * A description for an id parameter the route table leaves undescribed:
 * which record the id names, read from its name (or, for a bare id, from the
 * path). Null for a parameter that has a description or is not an id. Only
 * the document carries it; the MCP tool list reads the route table.
 *
 * @param array $route
 * @param array $param
 * @return string|null
 */
function api_openapi_id_description($route, $param)
{
    if (isset($param['description'])) {

        return null;

    }

    $name = (string)$param['name'];

    if ($name === 'id') {

        return 'Id of the record at ' . (isset($route['path']) ? $route['path'] : '') . '.';

    }

    if (!preg_match('/^([a-z][a-z0-9_]*?)_(id|ids)$/', $name, $match)) {

        return null;

    }

    $words = str_replace('_', ' ', $match[1]);

    if ($match[2] === 'ids') {

        $plural = preg_match('/[^aeiou]y$/', $words) ? substr($words, 0, -1) . 'ies'
            : (preg_match('/(s|x|ch|sh)$/', $words) ? $words . 'es' : $words . 's');

        return 'Ids of the ' . $plural . ', as a list.';

    }

    switch ($words) {

        case 'since':

            return 'Only the records after the one with this id.';

        case 'before':

            return 'Only the records before the one with this id.';

        case 'after':

            return 'Only the records after the one with this id.';

        case 'ref':

            return 'Id of the record ref_type names.';

    }

    return 'Id of the ' . $words . '.';
}


function api_openapi_tag($route_id) {

	$parts = explode('.', $route_id);

	return ucfirst($parts[0]);

}

function api_openapi_type($param) {

	$type = isset($param['type']) ? $param['type'] : 'string';

	switch ($type) {

		case 'int':

			$schema = array('type' => 'integer');

			if (isset($param['min'])) {

				$schema['minimum'] = $param['min'];

			}

			if (isset($param['max'])) {

				$schema['maximum'] = $param['max'];

			}

			if (isset($param['default'])) {

				$schema['default'] = $param['default'];

			}

			return $schema;

		case 'money':

			$schema = array('type' => 'integer', 'description' => 'Whole number of minor units. 1999 is 19.99.');

			if (isset($param['min'])) {

				$schema['minimum'] = $param['min'];

			}

			if (isset($param['max'])) {

				$schema['maximum'] = $param['max'];

			}

			return $schema;

		case 'decimal':

			// The router refuses a decimal outside its bounds
			// (api_validate_bounds()), so the document states them.
			$schema = array('type' => 'number');

			if (isset($param['min'])) {

				$schema['minimum'] = $param['min'];

			}

			if (isset($param['max'])) {

				$schema['maximum'] = $param['max'];

			}

			return $schema;

		case 'bool':

			$schema = array('type' => 'boolean');

			if (isset($param['default'])) {

				$schema['default'] = (bool)$param['default'];

			}

			return $schema;

		case 'enum':

			$schema = array('type' => 'string', 'enum' => $param['values']);

			if (isset($param['default'])) {

				$schema['default'] = $param['default'];

			}

			return $schema;

		case 'datetime':

			return array('type' => 'string', 'description' => 'ISO-8601, YYYY-MM-DD, or a unix timestamp.');

		case 'object':

			return array('type' => 'object');

		case 'list':

			// 'of' names what the list holds, in the spelling of the field
			// maps below: 'integer', 'string', or an inline object. A list
			// that does not say is a list of objects.
			$schema = array(
				'type'  => 'array',
				'items' => isset($param['of']) ? api_openapi_type_spec($param['of']) : array('type' => 'object')
			);

			if (isset($param['max_items'])) {

				$schema['maxItems'] = (int)$param['max_items'];

			}

			return $schema;

		default:

			$schema = array('type' => 'string');

			if (isset($param['max_length'])) {

				$schema['maxLength'] = $param['max_length'];

			}

			if (isset($param['default'])) {

				$schema['default'] = (string)$param['default'];

			}

			return $schema;

	}

}

// The failure shapes are the same everywhere, so they are described once and
// attached to every operation. An integrator reading any endpoint sees the whole
// error contract without having to find the one page that documents it.
/**
 * A declared field map, compiled into JSON Schema.
 *
 * Every resource file says what it returns as one line per field, beside the
 * function that builds it: 'price' => 'integer', 'seo' => 'Seo',
 * 'group_ids' => 'integer[]'. The declaration is read and changed in the same
 * place as the code it describes, which is the only arrangement that survives
 * a year of edits.
 *
 * The spelling:
 *   string | integer | number | boolean | object   a JSON type
 *   Product                                        another declared object ($ref)
 *   integer[] | Product[]                          a list of those
 *   string?                                        may be null
 *   array('a' => 'integer', ...)                   an object written inline
 *   array(array('a' => 'integer'))                 a list of those
 *
 * The question mark matters more than it looks: a generated client that maps a
 * nullable column to a non-nullable field breaks on the first order placed
 * without a phone number.
 *
 * What decides it is what the presenter can hand back, not the column. A field
 * carries the question mark when it can really be null: some create path
 * leaves the column unwritten or writes NULL into it, the presenter itself
 * answers null (nothing to refer to, a part the record does not have), or the
 * row is one the site's own data ships that way. A DEFAULT NULL column that
 * every create path writes - a product's or a group's name, a page's name,
 * title and meta description, a file's name and type - is declared without
 * it, and so is one the presenter casts ((string) $row['x']). A new create
 * path that leaves such a column out adds the question mark in the same
 * change; tests/api_openapi_status_test.php reads the create paths for the
 * fields above.
 *
 * @param mixed $spec
 * @return array
 */
function api_openapi_type_spec($spec)
{
    if (is_array($spec)) {

        // A list with one member describes an array of that member.
        if (isset($spec[0])) {

            return array('type' => 'array', 'items' => api_openapi_type_spec($spec[0]));

        }

        $properties = array();

        foreach ($spec as $name => $field) {

            $properties[$name] = api_openapi_type_spec($field);

        }

        return array('type' => 'object', 'properties' => $properties);

    }

    $spec = trim((string) $spec);

    $nullable = false;

    if (substr($spec, -1) === '?') {

        $nullable = true;

        $spec = substr($spec, 0, -1);

    }

    if (substr($spec, -2) === '[]') {

        $schema = array('type' => 'array', 'items' => api_openapi_type_spec(substr($spec, 0, -2)));

    } elseif ($spec !== '' && ctype_upper(substr($spec, 0, 1))) {

        $schema = array('$ref' => '#/components/schemas/' . $spec);

    } else {

        $words = api_openapi_field_words();

        $word = ($spec === '') ? 'string' : $spec;

        // tools/check_api_schema.php refuses a word that is not in the map, so
        // the fallback only keeps an unchecked declaration readable.
        $schema = isset($words[$word]) ? $words[$word] : array('type' => $word);

    }

    if ($nullable) {

        // In 3.0 a $ref cannot carry a sibling keyword, so a nullable reference
        // is written as a one-member allOf with the flag outside it.
        $schema = isset($schema['$ref'])
            ? array('nullable' => true, 'allOf' => array($schema))
            : array_merge($schema, array('nullable' => true));

    }

    return $schema;
}

/**
 * The type words a field declaration may use, and what each says in the
 * document. A moment (api_time(): ISO-8601 in UTC) is datetime, a day
 * (YYYY-MM-DD) is date; which one a field is comes from its presenter, never
 * from its name. A time of day or a minute without a zone stays a string.
 *
 * tools/check_api_schema.php reads the keys of this array and refuses a
 * declaration that uses any other word. Parameters have their own types
 * (api_openapi_type()): a datetime parameter also takes a day or a unix
 * timestamp, so it says no format.
 *
 * @return array word => schema
 */
function api_openapi_field_words()
{
    return array(
        'string'   => array('type' => 'string'),
        'integer'  => array('type' => 'integer'),
        'number'   => array('type' => 'number'),
        'boolean'  => array('type' => 'boolean'),
        'object'   => array('type' => 'object'),
        'array'    => array('type' => 'array'),
        'datetime' => array('type' => 'string', 'format' => 'date-time'),
        'date'     => array('type' => 'string', 'format' => 'date'),
    );
}

/**
 * The objects the API returns, by the name routes refer to them with.
 *
 * Each entry names the function that declares that object's fields. The
 * functions live beside the presenters that build the same shape, and
 * tools/check_api_schema.php is what notices when one of the two is changed
 * without the other.
 *
 * @return array name => declaring function
 */
function api_openapi_objects()
{
    $objects = array(
        'Meta'         => 'api_meta_schema',
        'MetaWorkspace' => 'api_meta_workspace_schema',
        'Product'      => 'api_product_schema',
        'ProductBundle' => 'api_product_bundle_schema',
        'ProductGroup' => 'api_product_group_schema',
        'Order'        => 'api_order_schema',
        'LocalSale'    => 'api_local_sale_schema',
        'LocalSaleInvoice' => 'api_local_sale_invoice_schema',
        'LocalSaleReceipt' => 'api_local_sale_receipt_schema',
        'OrderItemFile' => 'api_order_item_file_schema',
        'OrderShipment' => 'api_order_shipment_schema',
        'OrderShipmentLabel' => 'api_order_label_schema',
        'OrderDocument' => 'api_order_document_schema',
        'OrderReturn'  => 'api_order_return_schema',
        'OrderReturnRefund' => 'api_order_return_refund_schema',
        'OrderReturnWithdrawal' => 'api_order_return_withdrawal_schema',
        'CartQuote'    => 'api_cart_quote_schema',
        'CartQuoteLine' => 'api_cart_quote_line_schema',
        'Customer'     => 'api_customer_schema',
        'Page'         => 'api_page_schema',
        'Folder'       => 'api_folder_schema',
        'Menu'         => 'api_menu_schema',
        'MenuItem'     => 'api_menu_item_schema',
        'Comment'      => 'api_comment_schema',
        'CommentAnswer' => 'api_comment_answer_schema',
        'ShortLink'    => 'api_short_link_schema',
        'Experiment'   => 'api_experiment_schema',
        'ExperimentVariant' => 'api_experiment_variant_schema',
        'Booking'      => 'api_booking_schema',
        'BookingSlot'  => 'api_booking_slot_schema',
        'StoreShippingMethod' => 'api_store_shipping_method_schema',
        'StoreZone'    => 'api_store_zone_schema',
        'StoreTaxZone' => 'api_store_tax_zone_schema',
        'StoreState'   => 'api_store_state_schema',
        'StoreCountry' => 'api_store_country_schema',
        'StoreCountryState' => 'api_store_country_state_schema',
        'StoreCurrency' => 'api_store_currency_schema',
        'StoreArrivalDate' => 'api_store_arrival_date_schema',
        'StoreShipDateAdjustment' => 'api_store_ship_date_adjustment_schema',
        'StoreVerifiedAddress' => 'api_store_verified_address_schema',
        'StoreReferralSource' => 'api_store_referral_source_schema',
        'StoreFeed'    => 'api_store_feed_schema',
        'GiftCard'     => 'api_gift_card_schema',
        'GiftCardRedemption' => 'api_gift_card_redemption_schema',
        'Commission'   => 'api_commission_schema',
        'CommissionRecurringProfile' => 'api_commission_recurring_profile_schema',
        'Affiliate'    => 'api_affiliate_schema',
        'TrafficReport' => 'api_traffic_report_schema',
        'TopContent'   => 'api_top_content_schema',
        'VisitorBreakdown' => 'api_visitor_breakdown_schema',
        'AiVisibilityReport' => 'api_ai_visibility_report_schema',
        'SearchReport' => 'api_search_report_schema',
        'PagesReport' => 'api_pages_report_schema',
        'SiteLogEntry' => 'api_site_log_schema',
        'FediverseFollower' => 'api_fediverse_follower_schema',
        'FediverseDelivery' => 'api_fediverse_delivery_schema',
        'AuditLogEntry' => 'api_audit_log_schema',
        'Campaign'     => 'api_campaign_schema',
        'CampaignRecipient' => 'api_campaign_recipient_schema',
        'CampaignProfile' => 'api_campaign_profile_schema',
        'ContactGroup' => 'api_contact_group_schema',
        'ContactGroupMember' => 'api_contact_group_member_schema',
        'KeyCode'      => 'api_key_code_schema',
        'ProductAttribute' => 'api_product_attribute_schema',
        'ProductAttributeOption' => 'api_product_attribute_option_schema',
        'File'         => 'api_file_schema',
        'Offer'        => 'api_offer_schema',
        'Webhook'      => 'api_webhook_schema',
        'WebhookEvent' => 'api_webhook_event_schema',
        'Inventory'    => 'api_inventory_schema',
        'InventoryBatch' => 'api_inventory_batch_schema',
        'StockLocation'  => 'api_stock_location_schema',
        'StockLevel'     => 'api_stock_level_schema',
        'StockTransfer'  => 'api_stock_transfer_schema',
        'StockMove'      => 'api_stock_move_schema',
        'PriceBatch'     => 'api_price_batch_schema',
        'PriceHistoryEntry' => 'api_price_history_entry_schema',
        'WishlistItem'      => 'api_wishlist_item_schema',
        'Consent'           => 'api_consent_schema',
        'Subscription'      => 'api_subscription_schema',
        'CustomerCredit'    => 'api_customer_credit_schema',
        'StoreCreditCard'   => 'api_store_credit_card_schema',
        'User'              => 'api_user_schema',
        'UserPermissions'   => 'api_user_permissions_schema',
        'UserTwoStep'       => 'api_user_two_step_schema',
        'ProductMarketplace' => 'api_product_marketplace_schema',
        'Seo'          => 'api_seo_schema',
        'Form'           => 'api_form_schema',
        'FormField'      => 'api_form_field_schema',
        'FormHtmlAllowed' => 'api_form_html_allowed_schema',
        'FormSubmission' => 'api_form_submission_schema',
        'FormSubmissionRevision' => 'api_form_revision_schema',
        'FormSubmissionRevisionDetail' => 'api_form_revision_detail_schema',
        'FormSubmissionRestore' => 'api_form_revision_restore_schema',
        'SeoIssue'       => 'api_seo_issue_schema',
        'SeoNotFound'    => 'api_seo_not_found_schema',
        'ContentReviewItem' => 'api_content_review_schema',
        'SystemStatus'   => 'api_system_status_schema',
        'SalesReport'    => 'api_sales_report_schema',
        'CartRecoveryReport' => 'api_cart_recovery_report_schema',
        'CustomerValueReport' => 'api_customer_value_report_schema',
        'VitalsReport'   => 'api_vitals_report_schema',
        'PageClicksReport' => 'api_page_clicks_report_schema',
        'PageClicksElement' => 'api_page_clicks_element_schema',
        'PageClicksPage' => 'api_page_clicks_page_schema',
        'Design'         => 'api_design_schema',
        'DesignPage'     => 'api_design_page_schema',
        'DesignView'     => 'api_design_view_schema',
        'DesignOutlineItem' => 'api_design_outline_item_schema',
        'DesignProposal' => 'api_design_proposal_schema',
        'DesignRevision' => 'api_design_revision_schema',
        'DesignRestore'  => 'api_design_restore_schema',
        'DesignPrototypeLink' => 'api_design_prototype_link_schema',
        'DesignBundle'   => 'api_design_bundle_schema',
        'DesignBundlePage' => 'api_design_bundle_page_schema',
        'DesignPrototypeLinkCreated' => 'api_design_prototype_link_created_schema',
        'AuthSession'    => 'api_auth_session_schema',
        'Me'             => 'api_auth_me_schema',
        'Device'         => 'api_device_schema',
        'Notification'   => 'api_notification_schema',
        'Calendar'           => 'api_calendar_schema',
        'CalendarEvent'      => 'api_calendar_event_schema',
        'CalendarRecurrence' => 'api_calendar_recurrence_schema',
        'CalendarLocation'   => 'api_calendar_location_schema',
        'CalendarReservation' => 'api_calendar_reservation_schema',
        'CalendarReservationCustomer' => 'api_calendar_reservation_customer_schema',
        'SmsCampaign'        => 'api_sms_campaign_schema',
        'SmsCampaignOutbox'  => 'api_sms_campaign_outbox_schema',
        'DesignAd'           => 'api_design_ad_schema',
        'DesignAdRegion'     => 'api_design_ad_region_schema',
        'DesignAutoDialog'   => 'api_design_auto_dialog_schema',
        'DesignCommonRegion' => 'api_design_common_region_schema',
    );

    // A module declares the objects its own endpoints answer with, the same way
    // and under its own names.
    require_once(dirname(__FILE__) . '/modules.php');

    return $objects + api_module_contributions('openapi_objects');
}

/**
 * Loads the files the API's own objects are declared in.
 *
 * The declarations sit beside the presenters, in the resource files the API
 * entry loads for its handlers. The panel builds the same document for its
 * download link without loading the handlers, and a declaration that is not
 * loaded is skipped by api_openapi_components() - every reference to it would
 * then point at nothing and the document would be refused by the tools it is
 * made for. Loading them is enough: those files only declare functions.
 */
function api_openapi_load_declarations()
{
    static $loaded = false;

    if ($loaded) {

        return;

    }

    $loaded = true;

    $directory = dirname(__FILE__);

    require_once($directory . '/devices.php');
    require_once($directory . '/seo.php');

    foreach (glob($directory . '/resources/*.php') as $file) {

        require_once($file);

    }
}

/**
 * components.schemas, built from those declarations.
 *
 * @return array
 */
function api_openapi_components()
{
    api_openapi_load_declarations();

    $schemas = array();

    foreach (api_openapi_objects() as $name => $declare) {

        if (!function_exists($declare)) {

            continue;

        }

        $schemas[$name] = api_openapi_type_spec(call_user_func($declare));

    }

    // The envelope every listing arrives in. Its shape is the paging contract:
    // follow page.next_cursor until it is null.
    $schemas['Paging'] = api_openapi_type_spec(array(
        'limit'       => 'integer',
        'has_more'    => 'boolean',
        'next_cursor' => 'string?',
        'total'       => 'integer?'
    ));

    $schemas['Error'] = api_openapi_type_spec(array(
        'error' => array(
            'code'       => 'string',
            'message'    => 'string',
            'field'      => 'string?',
            'request_id' => 'string',
            // Every field the router refused, the first one also above
            // (api_validate_collect()); absent on any other error.
            'errors'     => array(array(
                'field'   => 'string',
                'code'    => 'string',
                'message' => 'string'
            ))
        )
    ));

    $schemas['DryRun'] = api_openapi_dry_run_schema();

    return $schemas;
}

/**
 * What a rehearsal answers with (api_dry_run_stop()). detail is there only
 * when the endpoint has something to say about what it would have done; the
 * other three are always sent, and dry_run is a field no record carries - it
 * is what tells a rehearsal from a record where the two share a 200.
 *
 * @return array
 */
function api_openapi_dry_run_schema()
{
    $schema = api_openapi_type_spec(array(
        'dry_run'  => 'boolean',
        'would'    => 'string',
        'resource' => 'string',
        'detail'   => 'object'
    ));

    $schema['required'] = array('dry_run', 'would', 'resource');

    return $schema;
}

/**
 * What one route answers with, as the 200 content.
 *
 * A route declares it as 'returns' => 'Product' for the object itself, or
 * array('list' => 'Product') for the listing envelope around it; a listing
 * that carries a field of its own beside data and page names it there too,
 * array('list' => 'WorkspaceInboxItem', 'reload' => 'boolean'). A route that
 * returns something with no shape worth naming - the OpenAPI document, the
 * console's own HTML - declares nothing and keeps the bare description it had.
 *
 * @param array $route
 * @return array|null
 */
function api_openapi_success_schema($route)
{
    if (!isset($route['returns'])) {

        return null;

    }

    $returns = $route['returns'];

    if (is_array($returns) && isset($returns['list'])) {

        $beside = $returns;
        unset($beside['list']);

        return api_openapi_type_spec(array(
            'data' => $returns['list'] . '[]',
            'page' => 'Paging'
        ) + $beside);

    }

    return api_openapi_type_spec($returns);
}

/**
 * Whether a route answers with a listing: rows in data beside fields of the
 * envelope. A paged listing declares array('list' => 'Product') and carries
 * page; GET /webhooks declares data as a list beside the event catalogue
 * (array('data' => 'Webhook[]', 'events' => 'string[]')) and has no page.
 * ?fields= trims each row of such an answer and leaves the envelope whole
 * (api_response_fields()).
 *
 * @param array $route
 * @return bool
 */
function api_route_returns_listing($route)
{
    if (!isset($route['returns']) || !is_array($route['returns'])) {

        return false;

    }

    if (isset($route['returns']['list'])) {

        return true;

    }

    return isset($route['returns']['data']) && is_string($route['returns']['data'])
        && (substr($route['returns']['data'], -2) === '[]');
}

function api_openapi_responses($route) {

	$error = array(
		'type' => 'object',
		'properties' => array(
			'error' => array(
				'type' => 'object',
				'properties' => array(
					'code'       => array('type' => 'string'),
					'message'    => array('type' => 'string'),
					'field'      => array('type' => 'string'),
					'request_id' => array('type' => 'string'),
					'errors'     => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'field'   => array('type' => 'string'),
								'code'    => array('type' => 'string'),
								'message' => array('type' => 'string')
							)
						)
					)
				)
			)
		)
	);

	$error_content = array('application/json' => array('schema' => $error));

	$success = array('description' => 'Success');

	$schema = api_openapi_success_schema($route);

	if ($schema !== null) {

		$success['content'] = array('application/json' => array('schema' => $schema));

	}

	// A route that answers with a file rather than a record declares
	// 'binary' => true (api_send_file()): the 200 is the file's own bytes.
	if (!empty($route['binary'])) {

		$success = array(
			'description' => 'The file itself; Content-Type and Content-Disposition name it.',
			'content'     => array('application/octet-stream' => array('schema' => array('type' => 'string', 'format' => 'binary')))
		);

	}

	$responses = array(
		'400' => array('description' => 'The request could not be read: the body is not JSON, or a cursor or header is not one this API issues.', 'content' => $error_content),
		'401' => array('description' => 'The key or the secret is wrong, or none was sent.', 'content' => $error_content),
		'403' => array('description' => 'The application is not allowed to do this.', 'content' => $error_content),
		'404' => array('description' => 'No such record.', 'content' => $error_content),
		'422' => array('description' => 'A parameter is missing or the wrong shape.', 'content' => $error_content),
		'429' => array('description' => 'Rate limit reached. Retry-After says how long to wait.', 'content' => $error_content),
		'503' => array('description' => 'The API, or the part of the site this endpoint belongs to, cannot answer right now.', 'content' => $error_content)
	);

	// The status the success arrives with is the one the handler sends. A
	// generated client that was told 200 treats the 201 of a create as an
	// unexpected answer, so a route says what it sends: 'status' => 201 for a
	// create, array(201, 200) for one that may hand back what was already
	// there instead, 204 for an answer with no body at all.
	$codes = api_openapi_success_codes($route);

	// A rehearsal of a write whose real answer is a 200 answers 200 as well,
	// with the dry-run answer in place of the record (api_dry_run_stop()), so
	// that 200 describes both. anyOf rather than oneOf: a record schema names
	// no required field, so a dry-run answer fits it too, and a validator
	// holding oneOf to exactly one match would refuse every rehearsal.
	// DryRun comes first and requires dry_run, which no record carries, so a
	// client that takes the first match reads each answer as what it is.
	$answer_200 = $success;

	$rehearsed = !empty($route['dry_run']) && isset($success['content']['application/json']['schema']);

	if ($rehearsed) {

		$answer_200['content']['application/json']['schema'] = array('anyOf' => array(
			array('$ref' => '#/components/schemas/DryRun'),
			$success['content']['application/json']['schema']
		));

	}

	foreach ($codes as $code) {

		if ($code === 204) {

			$responses['204'] = array('description' => 'Done. The answer has no body.');

		} elseif ($code === 201) {

			$responses['201'] = array('description' => 'Created. The answer is the new record.') + $success;

		} elseif (count($codes) > 1) {

			$responses[(string)$code] = array('description' => 'The record that was already there, handed back instead of a new one; the description of the endpoint says when.'
				. ($rehearsed ? ' A rehearsal (X-Dry-Run: true) answers 200 too, with {"dry_run": true, ...} and nothing written.' : '')) + $answer_200;

		} elseif ($rehearsed) {

			$responses[(string)$code] = array('description' => 'Success. A rehearsal (X-Dry-Run: true) answers 200 too, with {"dry_run": true, ...} and nothing written.') + $answer_200;

		} else {

			$responses[(string)$code] = $success;

		}

	}

	// A rehearsal writes nothing, so it answers 200 even where the real call
	// answers 201 or 204 (api_dry_run_stop()); a client that was not told
	// reads it as an unexpected status.
	if (!empty($route['dry_run']) && !in_array(200, $codes, true)) {

		$responses['200'] = array(
			'description' => 'A rehearsal (X-Dry-Run: true): every check passed and nothing was written.',
			'content'     => array('application/json' => array('schema' => array('$ref' => '#/components/schemas/DryRun')))
		);

	}

	if ($route['method'] === 'GET') {

		$responses['304'] = array('description' => 'Not modified: the ETag sent in If-None-Match still matches. No body.');

		if (!empty($route['binary'])) {

			$responses['206'] = array(
				'description' => 'The part of the file the Range header asked for; Content-Range says which bytes of how many.',
				'content'     => array('application/octet-stream' => array('schema' => array('type' => 'string', 'format' => 'binary')))
			);

			$responses['416'] = array('description' => 'The range starts past the end of the file. Content-Range: bytes */size gives the size. No body.');

		}

	} else {

		// Every write takes an Idempotency-Key, and a key reused for a
		// different request is a conflict; the endpoint's own conflicts are
		// named in its description.
		$responses['409'] = array('description' => 'The change conflicts with the state of the record, the Idempotency-Key was already used for a different request (idempotency_key_reused), or a request with the same key is still being carried out (idempotency_in_progress). The description of the endpoint names its own codes.', 'content' => $error_content);

	}

	ksort($responses, SORT_STRING);

	return $responses;

}

/**
 * The statuses a route succeeds with: what it declares as 'status' - one code
 * or a list - and 200 when it declares nothing.
 *
 * @param array $route
 * @return int[]
 */
function api_openapi_success_codes($route) {

	if (!isset($route['status'])) {

		return array(200);

	}

	return array_values(array_map('intval', (array)$route['status']));

}

function api_openapi_document($params) {

	api_ok(api_openapi_build());

}
