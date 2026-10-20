#!/usr/bin/env bash
#
# Pinegrap - Enterprise Website Platform
#
# Installs Pinegrap through the real installation screen against a database
# server that is already running, and fails unless every upgrade step from the
# starter site's version up to the last version of versions.php went through
# and the data/config.php it wrote runs and names the database it was given.
# A fresh installation loads a starter site (data/backups/<starter>/sql.sql,
# taken at 2021.3) and runs the whole schema history from there, so this is
# the check that the steps run on a given MySQL or MariaDB version. The
# installation is run twice: the second run is a reinstallation into the same
# database, over the tables of the first.
#
# CI runs it once per database server (.github/workflows/install-check.yml).
# Locally: start a server, create an empty database and a user for it, then
#
#   DB_HOST=127.0.0.1 DB_USERNAME=pinegrap DB_PASSWORD=pinegrap \
#   DB_DATABASE=pinegrap bash tools/check_install.sh [starter] [port]
#
# starter defaults to english_default, port to 8000. It overwrites
# pinegrap/data/config.php and the Pinegrap tables of that database, so never
# point it at a site.
#
# @author      Erdal Güral (Kodpen)
# @link        https://kodpen.com
# @copyright   2017–2026 Kodpen
# @license     https://opensource.org/licenses/mit-license.html MIT License

set -euo pipefail

STARTER="${1:-english_default}"
PORT="${2:-8000}"

: "${DB_HOST:?DB_HOST is not set}"
: "${DB_USERNAME:?DB_USERNAME is not set}"
: "${DB_PASSWORD:?DB_PASSWORD is not set}"
: "${DB_DATABASE:?DB_DATABASE is not set}"

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB_ROOT="$ROOT/pinegrap"
BASE_URL="http://127.0.0.1:$PORT"
WORK="$(mktemp -d)"

# The last version of the package: the number the database has to end on.
EXPECTED="$(php -r 'define("INSTALL_OR_UPDATE", true); $v = include $argv[1]; echo end($v);' "$WEB_ROOT/includes/migrations/versions.php")"

SERVER_PID=""

cleanup() {
	if [ -n "$SERVER_PID" ]; then
		kill "$SERVER_PID" 2>/dev/null || true
	fi
}

trap cleanup EXIT

php -S "127.0.0.1:$PORT" -t "$ROOT" > "$WORK/server.log" 2>&1 &
SERVER_PID=$!

for _ in $(seq 1 30); do
	if curl -fsS -o /dev/null "$BASE_URL/pinegrap/install/" 2>/dev/null; then
		break
	fi
	sleep 1
done

# The version string the server gives, for the report.
SERVER_VERSION="$(php -r 'mysqli_report(MYSQLI_REPORT_OFF); $c = @mysqli_connect($argv[1], $argv[2], $argv[3], $argv[4]); echo $c ? mysqli_get_server_info($c) : "no connection: " . mysqli_connect_error();' "$DB_HOST" "$DB_USERNAME" "$DB_PASSWORD" "$DB_DATABASE")"

echo "Database server: $SERVER_VERSION"
echo "Starter site:    $STARTER"
echo "Expected:        $EXPECTED"

token_from() {
	grep -oE 'name="token" value="[^"]+"' "$1" | head -1 | sed -E 's/.*value="([^"]+)".*/\1/' || true
}

# One installation: the form is posted, and as long as the answer asks for the next step of
# loading the starter site it is posted again with the id of that load.
install_once() {
	local label="$1"
	local cookies="$WORK/cookies_$label.txt"
	local answer="$WORK/answer_$label.html"
	local form="$WORK/form_$label.html"
	local token restore_id

	# The installer only runs against a config file without connection details; the
	# second run replaces the first one's tables, as a reinstallation does.
	printf '<?php ?>' > "$WEB_ROOT/data/config.php"
	rm -f "$WEB_ROOT/data/temp/upgrade_last.json" "$WEB_ROOT/data/temp/upgrade_running.json"

	curl -fsS -c "$cookies" "$BASE_URL/pinegrap/install/" > "$form"

	token="$(token_from "$form")"

	if [ -z "$token" ]; then
		echo "The installation screen has no CSRF token; it may have changed." >&2
		return 1
	fi

	restore_id=""

	for _ in $(seq 1 100); do
		curl -sS -m 1800 -b "$cookies" -c "$cookies" \
			-d "token=$token" \
			-d "submit=1" \
			-d "install_type=install" \
			-d "install_from_folder=$STARTER" \
			-d "db_host=$DB_HOST" \
			-d "db_username=$DB_USERNAME" \
			-d "db_password=$DB_PASSWORD" \
			-d "db_database=$DB_DATABASE" \
			-d "admin_username=admin" \
			-d "admin_password=Check-install-1" \
			-d "admin_confirm_password=Check-install-1" \
			-d "admin_email_address=admin@example.test" \
			-d "admin_confirm_email_address=admin@example.test" \
			-d "default_software_language=en" \
			-d "software_update_check=false" \
			-d "send_email=false" \
			-d "reinstall_software=1" \
			${restore_id:+-d "restore_id=$restore_id"} \
			"$BASE_URL/pinegrap/install/" > "$answer"

		restore_id="$(grep -oE 'pg_install_stream_continue\("[^"]+"\)' "$answer" | head -1 | sed -E 's/.*\("([^"]+)"\).*/\1/' || true)"

		if [ -z "$restore_id" ]; then
			break
		fi

		# the next step is posted with the token of this answer, or of a fresh form
		token="$(token_from "$answer")"

		if [ -z "$token" ]; then
			curl -fsS -b "$cookies" -c "$cookies" "$BASE_URL/pinegrap/install/" > "$form"
			token="$(token_from "$form")"
		fi
	done

	# The steps the screen reported, one per line.
	grep -oE 'pg_install_stream_step\([^;]*' "$answer" | sed -E 's/^pg_install_stream_step\(//' > "$WORK/steps_$label.txt" || true

	local version
	version="$(php -r 'mysqli_report(MYSQLI_REPORT_OFF); $c = @mysqli_connect($argv[1], $argv[2], $argv[3], $argv[4]); $r = $c ? @mysqli_query($c, "SELECT version FROM config") : false; $row = $r ? mysqli_fetch_row($r) : null; echo $row ? $row[0] : "";' "$DB_HOST" "$DB_USERNAME" "$DB_PASSWORD" "$DB_DATABASE")"

	if grep -q '"error", [0-9]*)$' "$WORK/steps_$label.txt" || [ "$version" != "$EXPECTED" ] || ! grep -q 'The installation is complete' "$WORK/steps_$label.txt"; then
		echo "FAIL ($label): the database is at '${version:-nothing}', expected $EXPECTED." >&2
		echo "Last steps of the screen:" >&2
		tail -5 "$WORK/steps_$label.txt" >&2 || true
		if [ ! -s "$WORK/steps_$label.txt" ]; then
			# no steps at all: the form refused the post, so show what it said
			curl -fsS -b "$cookies" "$BASE_URL/pinegrap/install/" | grep -oE 'class="[^"]*(alert|invalid-feedback|text-danger)[^"]*"[^>]*>[^<]{1,400}' | head -5 >&2 || true
		fi
		echo "Server log:" >&2
		grep -E 'pinegrap upgrade|PHP (Fatal|Warning)' "$WORK/server.log" | tail -10 >&2 || true
		return 1
	fi

	# The version above is read with this script's own connection, so a config.php the
	# installer wrote but PHP cannot parse (a password with a quote in it) would still pass.
	# The site only works when the file runs and names the database it was given.
	if ! php -r 'include $argv[1]; exit(((DB_HOST === $argv[2]) && (DB_USERNAME === $argv[3]) && (DB_PASSWORD === $argv[4]) && (DB_DATABASE === $argv[5])) ? 0 : 1);' \
		"$WEB_ROOT/data/config.php" "$DB_HOST" "$DB_USERNAME" "$DB_PASSWORD" "$DB_DATABASE" > /dev/null 2>&1; then
		echo "FAIL ($label): data/config.php does not run, or does not hold the database it was given." >&2
		php -l "$WEB_ROOT/data/config.php" >&2 || true
		return 1
	fi

	echo "OK ($label): $(wc -l < "$WORK/steps_$label.txt") steps, the database is at $version."
}

install_once install
install_once reinstall
