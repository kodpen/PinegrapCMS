#!/usr/bin/env bash
#
# Pinegrap - Enterprise Website Platform
#
# Static analysis of the product tree, as CI runs it (.github/workflows/
# php-checks.yml):
#
#   phpstan    PHPStan level 0 (tools/phpstan.neon) against
#              tools/phpstan-baseline.neon
#   phpcompat  PHPCompatibility for PHP 7.1 and up (tools/phpcs.xml), the
#              report compared with tools/phpcs-baseline.json by
#              tools/check_php_compat.php
#
# Neither tool is part of the product. The pinned releases below are fetched
# into a cache folder on first use and reused after that: PHPStan and
# PHP_CodeSniffer as phars checked against their SHA-256, the PHPCompatibility
# and PHPCSUtils standards by commit. Changing a version means changing its
# pin here and regenerating both baselines in the same pull request.
#
# Usage:  bash tools/static_analysis.sh [phpstan|phpcompat]   (default: both)
#         bash tools/static_analysis.sh --generate-baseline [phpstan|phpcompat]
#             rewrites the baseline(s) from the tree as it is: after a fix,
#             so that they shrink; never to let a new finding through.
#         PG_ANALYSIS_TOOLS=<folder> sets the cache folder (default:
#         $RUNNER_TEMP/pinegrap-analysis in GitHub Actions, otherwise
#         ~/.cache/pinegrap-analysis).
# Exit:   0 when every check run passes, 1 when one finds something beyond
#         its baseline or cannot run.
#
# @author      Erdal Güral (Kodpen)
# @link        https://kodpen.com
# @copyright   2017–2026 Kodpen
# @license     https://opensource.org/licenses/mit-license.html MIT License

set -u

PHPSTAN_VERSION="2.3.1"
PHPSTAN_SHA256="85157dba3d95d77ca603d2b7303aa78a538f9e3b56ccad07dd794c53aa96d1ae"
PHPCS_VERSION="4.0.4"
PHPCS_SHA256="4b010cd21d8bc8a17e2504792e3c77ef8259126f24caf7eafe7eddef1fa871e9"
# PHPCompatibility 10.0.0-alpha2, PHPCSUtils 1.2.3.
PHPCOMPATIBILITY_COMMIT="e0f0e5a3dc819a4a0f8d679a0f2453d941976e18"
PHPCSUTILS_COMMIT="5f35d9408c54d7b529501f3c688b6eae562aea1f"

cd "$(dirname "$0")/.." || exit 1

generate=0
checks=()

for argument in "$@"; do
	case "$argument" in
		--generate-baseline) generate=1 ;;
		phpstan|phpcompat) checks+=("$argument") ;;
		*) echo "Unknown argument: $argument (phpstan, phpcompat, --generate-baseline)" >&2; exit 1 ;;
	esac
done

if [ "${#checks[@]}" -eq 0 ]; then
	checks=(phpstan phpcompat)
fi

if [ -n "${PG_ANALYSIS_TOOLS:-}" ]; then
	cache="$PG_ANALYSIS_TOOLS"
elif [ -n "${RUNNER_TEMP:-}" ]; then
	cache="$RUNNER_TEMP/pinegrap-analysis"
else
	cache="$HOME/.cache/pinegrap-analysis"
fi

mkdir -p "$cache" || exit 1

sha256_of () {
	if command -v sha256sum > /dev/null 2>&1; then
		sha256sum "$1" | cut -d ' ' -f 1
	else
		shasum -a 256 "$1" | cut -d ' ' -f 1
	fi
}

# A phar from a release, kept under its version's name; a file whose checksum
# does not match is fetched again, and a download that does not match is
# refused.
fetch_phar () {
	local file="$cache/$1" url="$2" sha="$3"

	if [ -f "$file" ] && [ "$(sha256_of "$file")" = "$sha" ]; then
		return 0
	fi

	curl -fsSL --retry 3 -o "$file.part" "$url" || { echo "Could not download $url" >&2; return 1; }

	if [ "$(sha256_of "$file.part")" != "$sha" ]; then
		echo "$url does not match its pinned SHA-256; not used." >&2
		rm -f "$file.part"
		return 1
	fi

	mv "$file.part" "$file"
}

# A repository at one commit, fetched by its hash, so the content is what the
# pin names.
fetch_commit () {
	local directory="$cache/$1" url="$2" commit="$3"

	if [ "$(git -C "$directory" rev-parse HEAD 2> /dev/null)" = "$commit" ]; then
		return 0
	fi

	rm -rf "$directory"

	git init -q "$directory" \
		&& git -C "$directory" fetch -q --depth 1 "$url" "$commit" \
		&& git -C "$directory" checkout -q FETCH_HEAD \
		|| { echo "Could not fetch $url at $commit" >&2; return 1; }
}

run_phpstan () {
	local phar="phpstan-$PHPSTAN_VERSION.phar"

	fetch_phar "$phar" "https://github.com/phpstan/phpstan/releases/download/$PHPSTAN_VERSION/phpstan.phar" "$PHPSTAN_SHA256" || return 1

	if [ "$generate" -eq 1 ]; then
		php "$cache/$phar" analyse -c tools/phpstan.neon --memory-limit=3G --no-progress --generate-baseline tools/phpstan-baseline.neon
		return
	fi

	# In GitHub Actions each error becomes an annotation on the diff.
	local format="table"

	if [ "${GITHUB_ACTIONS:-}" = "true" ]; then
		format="github"
	fi

	php "$cache/$phar" analyse -c tools/phpstan.neon --memory-limit=3G --no-progress --error-format="$format"
}

run_phpcompat () {
	local phar="phpcs-$PHPCS_VERSION.phar" report="$cache/phpcs-report.json" status=0

	fetch_phar "$phar" "https://github.com/PHPCSStandards/PHP_CodeSniffer/releases/download/$PHPCS_VERSION/phpcs.phar" "$PHPCS_SHA256" || return 1
	fetch_commit phpcompatibility https://github.com/PHPCompatibility/PHPCompatibility "$PHPCOMPATIBILITY_COMMIT" || return 1
	fetch_commit phpcsutils https://github.com/PHPCSStandards/PHPCSUtils "$PHPCSUTILS_COMMIT" || return 1

	rm -f "$report"

	# phpcs exits 2 whenever it finds anything, baseline or not; the report
	# decides. 16 and up is phpcs itself failing (ruleset or processing).
	php "$cache/$phar" --standard=tools/phpcs.xml -q \
		--runtime-set installed_paths "$cache/phpcompatibility,$cache/phpcsutils" \
		--report=json --report-file="$report" || status=$?

	if [ "$status" -ge 16 ]; then
		echo "phpcs could not run (exit $status)." >&2
		return 1
	fi

	if [ "$generate" -eq 1 ]; then
		php tools/check_php_compat.php "$report" --generate-baseline
		return
	fi

	php tools/check_php_compat.php "$report"
}

failed=0

for check in "${checks[@]}"; do
	echo "== $check"
	"run_$check" || failed=1
done

exit "$failed"
