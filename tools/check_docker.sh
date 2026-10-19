#!/usr/bin/env bash
#
# Pinegrap - Enterprise Website Platform
#
# Builds the Docker images of docker/, starts the compose stack and checks it
# the way a person trying it would use it: the installation screen answers,
# the PHP extensions and settings System Status asks for are there, nginx
# refuses data/ and includes/, the installer finishes against the db
# container, the site, the control panel and an uploaded file answer, the
# configuration survives a recreated app container and the cron container
# runs the general job.
#
# CI runs it (.github/workflows/docker-check.yml). Locally it needs Docker
# with the compose plugin and network access to the base images:
#
#   bash tools/check_docker.sh [starter] [port]
#
# starter defaults to english_default, port to 8089. It uses its own compose
# project (pinegrap-check) and removes its containers and volumes at the
# end, so it never touches a stack started from docker/ by hand.
#
# @author      Erdal Güral (Kodpen)
# @link        https://kodpen.com
# @copyright   2017–2026 Kodpen
# @license     https://opensource.org/licenses/mit-license.html MIT License

set -euo pipefail

STARTER="${1:-english_default}"
PORT="${2:-8089}"

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BASE_URL="http://127.0.0.1:$PORT"
WORK="$(mktemp -d)"

export PG_HTTP_PORT="$PORT"
export PG_CRON_INTERVAL=5

COMPOSE=(docker compose -f "$ROOT/docker/docker-compose.yml" -p pinegrap-check)

say() { printf '\n== %s\n' "$1"; }

# On GitHub Actions the text read from stdin also becomes an error
# annotation, which the API returns where the raw log is out of reach.
annotate() {
	local text

	text="$(tail -c 3000)"
	printf '%s\n' "$text" >&2

	if [ -n "${GITHUB_ACTIONS:-}" ]; then
		printf '::error title=%s::%s\n' "$1" "$(printf '%s' "$text" | sed 's/%/%25/g' | awk '{ printf "%s%%0A", $0 }')"
	fi
}

fail() {
	echo "FAIL: $1" | annotate "check_docker.sh"
	exit 1
}

cleanup() {
	local status=$?

	if [ "$status" -ne 0 ]; then
		"${COMPOSE[@]}" logs --no-color --tail 25 2>&1 | annotate "container logs" || true
	fi

	"${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true
	rm -rf "$WORK"
}

trap cleanup EXIT

# The HTTP status of an address, without following redirects.
status_of() {
	curl -s -o /dev/null -w '%{http_code}' "$BASE_URL$1" || true
}

sql() {
	"${COMPOSE[@]}" exec -T db mariadb -N -upinegrap -ppinegrap pinegrap -e "$1" 2>/dev/null || true
}

wait_for_installer() {
	for _ in $(seq 1 60); do
		if [ "$(status_of /pinegrap/install/)" = "200" ]; then
			return 0
		fi
		sleep 2
	done

	fail "the installation screen did not answer at $BASE_URL/pinegrap/install/"
}

say "Building the images and starting the containers"
if ! "${COMPOSE[@]}" up -d --build > "$WORK/up.log" 2>&1; then
	tail -60 "$WORK/up.log" | annotate "docker compose up --build"
	exit 1
fi
wait_for_installer

say "PHP extensions and settings"
MODULES="$("${COMPOSE[@]}" exec -T app php -m)"

for module in mysqli curl openssl mbstring zip fileinfo "Zend OPcache" gd imagick pdo_mysql intl exif ftp; do
	grep -qx "$module" <<< "$MODULES" || fail "the app image has no $module"
done

# opcache is enabled for PHP-FPM only; php -m on the command line still lists it.
SETTINGS="$("${COMPOSE[@]}" exec -T app php -r 'echo ini_get("short_open_tag"), ini_get("allow_url_fopen"), (function_exists("imagewebp") && function_exists("imageavif")) ? "1" : "0";')"
[ "$SETTINGS" = "111" ] || fail "short_open_tag, allow_url_fopen and GD WebP/AVIF should be on, got $SETTINGS"
echo "OK: extensions and settings."

say "Web server rules"
for address in /pinegrap/data/config.php /pinegrap/data/backups/english_default/sql.sql /pinegrap/includes/server_config.php /data/config.php /pinegrap/router.php.bak; do
	code="$(status_of "$address")"
	[ "$code" = "403" ] || fail "$address answered $code, expected 403"
done

ASSET="$(cd "$ROOT/pinegrap" && find assets -type f -name '*.css' | head -1)"
[ -n "$ASSET" ] && [ "$(status_of "/pinegrap/$ASSET")" = "200" ] || fail "the static file /pinegrap/$ASSET is not served"
echo "OK: data/ and includes/ refused, static files served."

say "Installing the $STARTER starter site through the installation screen"
token_from() {
	grep -oE 'name="token" value="[^"]+"' "$1" | head -1 | sed -E 's/.*value="([^"]+)".*/\1/' || true
}

COOKIES="$WORK/cookies.txt"
curl -fsS -c "$COOKIES" "$BASE_URL/pinegrap/install/" > "$WORK/form.html"
TOKEN="$(token_from "$WORK/form.html")"
[ -n "$TOKEN" ] || fail "the installation screen has no CSRF token; it may have changed"

RESTORE_ID=""

for _ in $(seq 1 100); do
	curl -sS -m 1800 -b "$COOKIES" -c "$COOKIES" \
		-d "token=$TOKEN" \
		-d "submit=1" \
		-d "install_type=install" \
		-d "install_from_folder=$STARTER" \
		-d "db_host=db" \
		-d "db_username=pinegrap" \
		-d "db_password=pinegrap" \
		-d "db_database=pinegrap" \
		-d "admin_username=admin" \
		-d "admin_password=Check-docker-1" \
		-d "admin_confirm_password=Check-docker-1" \
		-d "admin_email_address=admin@example.test" \
		-d "admin_confirm_email_address=admin@example.test" \
		-d "default_software_language=en" \
		-d "software_update_check=false" \
		-d "send_email=false" \
		${RESTORE_ID:+-d "restore_id=$RESTORE_ID"} \
		"$BASE_URL/pinegrap/install/" > "$WORK/answer.html"

	RESTORE_ID="$(grep -oE 'pg_install_stream_continue\("[^"]+"\)' "$WORK/answer.html" | head -1 | sed -E 's/.*\("([^"]+)"\).*/\1/' || true)"

	if [ -z "$RESTORE_ID" ]; then
		break
	fi

	TOKEN="$(token_from "$WORK/answer.html")"

	if [ -z "$TOKEN" ]; then
		curl -fsS -b "$COOKIES" -c "$COOKIES" "$BASE_URL/pinegrap/install/" > "$WORK/form.html"
		TOKEN="$(token_from "$WORK/form.html")"
	fi
done

EXPECTED="$("${COMPOSE[@]}" exec -T app php -r 'define("INSTALL_OR_UPDATE", true); $v = include "/var/www/html/pinegrap/includes/migrations/versions.php"; echo end($v);')"
VERSION="$(sql "SELECT version FROM config")"

if [ "$VERSION" != "$EXPECTED" ] || ! grep -q 'The installation is complete' "$WORK/answer.html"; then
	echo "Last steps of the screen:" >&2
	grep -oE 'pg_install_stream_step\([^;]*' "$WORK/answer.html" | tail -5 >&2 || true
	fail "the database is at '${VERSION:-nothing}', expected $EXPECTED"
fi

"${COMPOSE[@]}" exec -T app grep -q "DB_HOST" /var/www/html/pinegrap/data/config.php || fail "the installer wrote no data/config.php into the volume"
echo "OK: installed, the database is at $VERSION."

say "The installed site"
check_site() {
	local code

	code="$(status_of /)"
	[ "$code" = "200" ] || fail "the home page answered $code"

	code="$(status_of /pinegrap/)"
	[ "$code" = "200" ] || fail "the control panel answered $code"
}

check_site

FILE="$(sql "SELECT name FROM files WHERE (name LIKE '%.jpg') OR (name LIKE '%.png') ORDER BY id LIMIT 1")"
[ -n "$FILE" ] || fail "the starter site has no picture in its files"
TYPE="$(curl -s -o /dev/null -w '%{http_code} %{content_type}' "$BASE_URL/$FILE")"
case "$TYPE" in
	"200 image/"*) ;;
	*) fail "the uploaded file /$FILE answered '$TYPE'" ;;
esac
echo "OK: home page, control panel and /$FILE answer."

say "Recreating the app container"
"${COMPOSE[@]}" up -d --force-recreate --no-deps app
for _ in $(seq 1 30); do
	if [ "$(status_of /)" = "200" ]; then
		break
	fi
	sleep 2
done
check_site
echo "OK: the configuration and the site survive a new app container."

say "The general job in the cron container"
RAN=""

for _ in $(seq 1 30); do
	RAN="$(sql "SELECT last_run_at FROM cron_runs WHERE job_name = 'job'")"
	if [ -n "$RAN" ]; then
		break
	fi
	sleep 2
done

[ -n "$RAN" ] || fail "the cron container did not run job.php"

if "${COMPOSE[@]}" logs --no-color cron 2>&1 | grep -E 'PHP (Fatal|Parse) error|job.php exited' >&2; then
	fail "job.php reported errors in the cron container"
fi

echo "OK: job.php ran from the cron container."

say "All Docker checks passed"
