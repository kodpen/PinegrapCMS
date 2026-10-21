#!/usr/bin/env bash
#
# Pinegrap - Enterprise Website Platform
#
# Builds the Docker images of docker/, starts the compose stack and checks it
# the way a person trying it would use it: the installation screen answers,
# the install service leaves it to the browser when no administrator is
# given, the PHP extensions and settings System Status asks for are there,
# nginx refuses data/ and includes/, the install service installs the
# starter site against the db container when the administrator is given and
# leaves it alone on the next start, the site, the control panel and an
# uploaded file answer, the configuration survives a recreated app container
# and the cron container runs the general job.
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
# The images built from this checkout get their own tag: the check never
# starts a published image in their place, and a latest image pulled on this
# machine stays as it was.
export PG_IMAGE_TAG=check

COMPOSE=(docker compose -f "$ROOT/docker/docker-compose.yml" -p pinegrap-check)

# A file an installation would have under pinegrap/data/: the build context
# has to leave it out of the image.
LEAK_MARKER="$ROOT/pinegrap/data/files/check-docker-not-in-image.txt"

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
		"${COMPOSE[@]}" logs --no-color --tail 25 app web cron install 2>&1 | annotate "container logs" || true
	fi

	"${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true
	rm -f "$LEAK_MARKER"
	rm -rf "$WORK"
}

trap cleanup EXIT

# A command that fails outside fail() still says where.
set -E
trap 'echo "line $LINENO: $BASH_COMMAND failed" | annotate "check_docker.sh"' ERR

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

# "<status> <exit code>" of the container the install service started with the
# stack, once it has stopped.
install_service_state() {
	local id state

	for _ in $(seq 1 60); do
		id="$("${COMPOSE[@]}" ps -a -q install 2>/dev/null | head -1 || true)"

		if [ -n "$id" ]; then
			state="$(docker inspect -f '{{.State.Status}} {{.State.ExitCode}}' "$id" 2>/dev/null || true)"

			case "$state" in
				exited*) echo "$state"; return 0 ;;
			esac
		fi

		sleep 2
	done

	echo "${state:-not started}"
}

# The install service run once more, as a start of the stack runs it, with
# the administrator's variables; its output goes to $1.
run_install() {
	PG_INSTALL_ADMIN_EMAIL="admin@example.test" \
	PG_INSTALL_ADMIN_PASSWORD="Check-docker-1" \
	PG_INSTALL_STARTER="$STARTER" \
		"${COMPOSE[@]}" run --rm -T install > "$1" 2>&1
}

say "Building the images and starting the containers"
echo "installation data" > "$LEAK_MARKER"

if ! "${COMPOSE[@]}" up -d --build > "$WORK/up.log" 2>&1; then
	tail -60 "$WORK/up.log" | annotate "docker compose up --build"
	exit 1
fi
wait_for_installer

say "The install service without an administrator"
STATE="$(install_service_state)"
[ "$STATE" = "exited 0" ] || fail "the install service is '$STATE', expected 'exited 0' when no administrator is given"
"${COMPOSE[@]}" logs --no-color install 2>&1 | grep -q "nothing is installed here" || fail "the install service did not say that the installation is left to the browser"
echo "OK: nothing installed; the installation screen is left to the browser."

say "What the image took from pinegrap/data/"
"${COMPOSE[@]}" exec -T app sh -c '
	set -e
	cd /var/www/html/pinegrap/data
	test ! -e files/check-docker-not-in-image.txt
	test "$(cat config.php)" = "<?php ?>"
	test -f backups/english_default/sql.sql
	test -f backups/turkish_default/sql.sql
	test "$(stat -c %U .)" = "www-data"
' || fail "the data volume should hold the empty config.php and the starter sites, owned by www-data, and no file of the build machine's installation"
echo "OK: only the tracked files of pinegrap/data/, owned by www-data."

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

ASSET="$(cd "$ROOT/pinegrap" && find assets -type f -name '*.css' -print -quit)"
[ -n "$ASSET" ] && [ "$(status_of "/pinegrap/$ASSET")" = "200" ] || fail "the static file /pinegrap/$ASSET is not served"
echo "OK: data/ and includes/ refused, static files served."

say "Installing the $STARTER starter site with the install service"
if ! run_install "$WORK/install.log"; then
	tail -40 "$WORK/install.log" | annotate "install service"
	fail "the install service did not install the site"
fi

EXPECTED="$("${COMPOSE[@]}" exec -T app php -r 'define("INSTALL_OR_UPDATE", true); $v = include "/var/www/html/pinegrap/includes/migrations/versions.php"; echo end($v);')"
VERSION="$(sql "SELECT version FROM config")"

if [ "$VERSION" != "$EXPECTED" ] || ! grep -q '^ok: installed ' "$WORK/install.log"; then
	tail -10 "$WORK/install.log" >&2 || true
	fail "the database is at '${VERSION:-nothing}', expected $EXPECTED"
fi

"${COMPOSE[@]}" exec -T app sh -c 'grep -q DB_HOST /var/www/html/pinegrap/data/config.php && test "$(stat -c %U /var/www/html/pinegrap/data/config.php)" = "www-data"' || fail "the install service wrote no data/config.php into the volume, or not as www-data"

[ "$(sql "SELECT COUNT(*) FROM user WHERE (user_username = 'admin') AND (user_role = '0')")" = "1" ] || fail "the install service created no administrator named admin"

HOSTNAME_SETTING="$(sql "SELECT hostname FROM config")"
[ "$HOSTNAME_SETTING" = "localhost:$PORT" ] || fail "the site's address is '$HOSTNAME_SETTING', expected localhost:$PORT"
echo "OK: installed, the database is at $VERSION, the site's address is $HOSTNAME_SETTING."

say "The install service on the next start"
if ! run_install "$WORK/install-again.log"; then
	tail -20 "$WORK/install-again.log" | annotate "install service, second run"
	fail "the second run of the install service failed"
fi

grep -q '^skipped: a site is already installed' "$WORK/install-again.log" || fail "the second run of the install service did not leave the site alone: $(tail -1 "$WORK/install-again.log")"
echo "OK: the installed site is left alone."

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
