#!/usr/bin/env bash
#
# Pinegrap - Enterprise Website Platform
#
# Runs the fast part of the checks CI runs on every pull request
# (.github/workflows/php-checks.yml) before a commit is pushed, so that a
# branch does not go up with a failure CI would report minutes later; every
# failed run also sends a "Run failed" e-mail to the account that pushed.
# The checks run in a temporary worktree of the commit being pushed, so
# nothing else in the work tree (edits, ignored or sparse files) changes the
# result. Which ones run follows from what the branch changes against the
# point it left origin/development, as CI checks the whole pull request:
#
#   every push                  memory index (tools/memory_index.php --check);
#                               the branch's commits: no Co-Authored-By: or
#                               Claude-Session: trailer, Erdal Güral as
#                               author and committer, no change to
#                               pinegrap/changelog.txt or
#                               pinegrap/data/config.php (tools/check_pr.php)
#   any change outside the      php -l of the changed PHP files, translation
#   documentation (docs/,       keys, designer bindings, external API schema,
#   .github/, .claude/, the     get_file.php copies, Bootstrap 6 panel
#                               markup (check_bs6.php --strict), minified
#                               stylesheets (build_css.php --check), unit tests
#   root *.md files)
#   a change to a panel script  node --check of the panel scripts CI checks,
#   or to how they are built    minified scripts against their sources
#
# CI alone runs PHPStan and PHPCompatibility (minutes each), the code editor
# bundle comparison (npm ci), the install check and the Docker check; a step
# added to php-checks.yml that takes seconds belongs here too.
#
# Usage:  bash tools/pre_push.sh            check HEAD against the point it
#                                           left origin/development
#         bash tools/pre_push.sh <base>     check HEAD against <base>
#         bash tools/pre_push.sh --hook <remote> <url>
#                                           as git's pre-push hook, which
#                                           passes the refs to push on
#                                           standard input
#                                           (tools/install_git_hooks.sh)
# Exit:   0 when every check that applies passed or there was nothing to
#         check; 1 when one failed, which stops the push.
#
# @author      Erdal Güral (Kodpen)
# @link        https://kodpen.com
# @copyright   2017–2026 Kodpen
# @license     https://opensource.org/licenses/mit-license.html MIT License

set -u

say() {
	printf 'pre-push: %s\n' "$*" >&2
}

if ! command -v php >/dev/null 2>&1; then
	say 'php is not on the PATH; nothing checked.'
	exit 0
fi

top=$(git rev-parse --show-toplevel 2>/dev/null) || {
	say 'not inside a git work tree; nothing checked.'
	exit 0
}

cd "$top" || exit 0

common=$(cd "$(git rev-parse --git-common-dir)" 2>/dev/null && pwd -P) || common=''

# A deleted ref is pushed as a commit id of zeros (40 or 64 of them).
is_zero() {
	case "$1" in
		*[!0]*) return 1 ;;
		*) return 0 ;;
	esac
}

# Where a branch left development: its changes since then are what CI sees.
fork_point() {
	git merge-base "$1" origin/development 2>/dev/null || true
}

# "<commit>:<base>:<branch>" triples to check (git allows no colon or space
# in a ref name); an empty base means every check but the commit guards.
targets=''

add_target() {
	case " $targets " in
		*" $1:"*) return ;;
	esac
	targets="$targets $1:$2:$3"
}

if [ "${1:-}" = '--hook' ]; then

	while read -r _ local_sha remote_ref remote_sha; do

		if [ -z "${local_sha:-}" ] || is_zero "$local_sha"; then
			continue
		fi

		# Tags and other refs are the owner's release work, not a branch.
		case "${remote_ref:-}" in
			refs/heads/*) ;;
			*) continue ;;
		esac

		base=$(fork_point "$local_sha")

		if [ -z "$base" ] && [ -n "${remote_sha:-}" ] && ! is_zero "$remote_sha" && git cat-file -e "$remote_sha^{commit}" 2>/dev/null; then
			base=$remote_sha
		fi

		add_target "$local_sha" "$base" "${remote_ref#refs/heads/}"

	done

else

	commit=$(git rev-parse --verify 'HEAD^{commit}') || exit 1

	if [ -n "${1:-}" ]; then
		base=$(git rev-parse --verify "$1^{commit}" 2>/dev/null) || {
			say "unknown base: $1"
			exit 1
		}
	else
		base=$(fork_point "$commit")
	fi

	add_target "$commit" "$base" "$(git symbolic-ref --quiet --short HEAD 2>/dev/null)"

fi

if [ -z "$targets" ]; then
	exit 0
fi

# The temporary trees of a run that was killed before it could clean up.
for stale in "${TMPDIR:-/tmp}"/pg-pre-push.*; do
	if [ ! -f "$stale/pid" ] || [ "$(cat "$stale/repo" 2>/dev/null)" != "$common" ]; then
		continue
	fi
	if kill -0 "$(cat "$stale/pid" 2>/dev/null)" 2>/dev/null; then
		continue
	fi
	for tree in "$stale"/tree-*; do
		if [ -d "$tree" ]; then
			git worktree remove --force "$tree" >/dev/null 2>&1
		fi
	done
	rm -rf "$stale"
done

run_tmp=$(mktemp -d "${TMPDIR:-/tmp}/pg-pre-push.XXXXXX") || {
	say 'no temporary folder; nothing checked.'
	exit 0
}

printf '%s\n' "$$" >"$run_tmp/pid"
printf '%s\n' "$common" >"$run_tmp/repo"

worktrees=()

# Run by the EXIT trap.
# shellcheck disable=SC2317
cleanup() {
	local tree
	for tree in ${worktrees[@]+"${worktrees[@]}"}; do
		git worktree remove --force "$tree" >/dev/null 2>&1
	done
	rm -rf "$run_tmp"
}

trap cleanup EXIT
trap 'exit 130' INT TERM

failed=0
dir=''

# Runs one check in $dir and prints one line, or the check's own report
# when it fails. A git hook gets GIT_DIR from git; a check runs without it,
# so that it sees the tree it runs in.
run_check() {
	local label=$1 started=$SECONDS out lines
	shift
	out="$run_tmp/out"

	if (cd "$dir" && env -u GIT_DIR -u GIT_WORK_TREE -u GIT_INDEX_FILE "$@") >"$out" 2>&1; then
		say "$label: ok ($((SECONDS - started)) s)"
		return 0
	fi

	say "$label: FAILED"

	if [ "$label" = 'unit tests' ]; then
		# Not the three thousand passing tests and the file names: what is
		# left is the failing tests with their messages, a fatal error and
		# the totals.
		grep -vE '^  PASS  |^[^ ].*_test\.php$' "$out" >"$out.short"
		out="$out.short"
	fi

	lines=$(($(wc -l <"$out")))

	if [ "$lines" -gt 80 ]; then
		head -n 70 "$out" >&2
		say "... $((lines - 73)) more lines; run the check by hand to see them all ..."
		tail -n 3 "$out" >&2
	else
		cat "$out" >&2
	fi

	failed=1
	return 1
}

# Runs a tools/ script when the tree being checked has it.
run_tool() {
	local label=$1 script=$2
	shift 2
	if [ -f "$dir/tools/$script" ]; then
		run_check "$label" php "tools/$script" "$@"
	fi
}

check_commit() {
	local commit=$1 base=$2 branch=$3 short status path code=0 js=0 file terser
	local php_files=()

	short=$(git rev-parse --short "$commit")

	if [ -n "$base" ]; then

		while IFS= read -r -d '' status && IFS= read -r -d '' path; do
			# No test reads these, and CI's other checks do not cover them.
			case "$path" in
				docs/*|.github/*|.claude/*|CLAUDE.md|README.md|README.tr.md|CONTRIBUTING.md|CODE_OF_CONDUCT.md|SECURITY.md|license.txt) ;;
				*) code=1 ;;
			esac
			case "$path" in
				pinegrap/assets/js/*|pinegrap/includes/fn/output.php|tools/build_js.php) js=1 ;;
			esac
			if [ "$status" != 'D' ]; then
				case "$path" in
					*.php) php_files+=("$path") ;;
				esac
			fi
		done < <(git -c core.quotePath=false diff -z --name-status --no-renames "$base" "$commit" --)

	else
		# No base to compare with: check everything this script checks.
		code=1
		js=1
	fi

	dir="$run_tmp/tree-$short"
	worktrees+=("$dir")

	# The whole tree, whatever the clone's sparse checkout, and without the
	# clone's checkout hooks.
	if ! git -c core.sparseCheckout=false -c core.hooksPath=/dev/null worktree add --quiet --detach "$dir" "$commit" >/dev/null 2>&1; then
		say "could not check out $short to check it; nothing checked."
		return 0
	fi

	if [ "$code" = 1 ]; then
		say "checking $short: memory index, commits, PHP syntax, translation keys, bindings, API schema, copies, unit tests..."
	else
		say "checking $short: memory index, commits (the branch changes documentation only)..."
	fi

	run_tool 'memory index' memory_index.php --check

	# Without a base the branch's own commits are unknown.
	if [ -n "$base" ]; then
		run_tool 'commit authors, trailers, changelog' check_pr.php "$base" "$commit" "--branch=$branch"
	fi

	if [ "$code" = 1 ]; then

		if [ -n "$base" ]; then
			if [ ${#php_files[@]} -gt 0 ]; then
				# shellcheck disable=SC2016
				run_check 'php -l (changed PHP files)' bash -c 'rc=0; for f; do php -l "$f" >/dev/null 2>&1 || { php -l "$f"; rc=1; }; done; exit $rc' _ "${php_files[@]}"
			fi
		else
			run_tool 'php -l' lint.php
		fi

		run_tool 'translation keys' check_lang.php
		run_tool 'designer bindings' check_bindings.php
		run_tool 'external API schema' check_api_schema.php
		run_tool 'get_file.php copies' check_copies.php
		run_tool 'Bootstrap 6 panel markup' check_bs6.php --strict
		run_tool 'minified stylesheets' build_css.php --check
		run_tool 'unit tests' test.php

	fi

	if [ "$js" = 1 ]; then

		if command -v node >/dev/null 2>&1; then
			for file in pinegrap/assets/js/style_designer.src.js pinegrap/assets/js/workspace.js pinegrap/assets/js/backend.src.js; do
				if [ -f "$dir/$file" ]; then
					run_check "node --check $file" node --check "$file"
				fi
			done
		else
			say 'node is not on the PATH: the panel scripts were not checked (CI checks them).'
		fi

		# build_js.php fetches its pinned terser through npx; without the npm
		# registry that would retry for minutes and then fail a good push.
		terser=$(sed -n "s/^const TERSER = '\(.*\)';\$/\1/p" "$dir/tools/build_js.php" 2>/dev/null)

		if [ ! -f "$dir/tools/build_js.php" ]; then
			:
		elif ! command -v npx >/dev/null 2>&1; then
			say 'npx is not on the PATH: the minified scripts were not compared (CI compares them).'
		elif ! (cd "$dir" && npm_config_fetch_retries=0 npm_config_fetch_timeout=20000 npx --yes "${terser:-terser}" --version) >/dev/null 2>&1; then
			say "npx could not run ${terser:-terser} (no npm registry?): the minified scripts were not compared (CI compares them)."
		else
			run_tool 'minified scripts' build_js.php --check
		fi

	fi

	return 0
}

for target in $targets; do
	rest=${target#*:}
	check_commit "${target%%:*}" "${rest%%:*}" "${rest#*:}"
done

if [ "$failed" = 1 ]; then
	if [ "${1:-}" = '--hook' ]; then
		say 'the push is stopped: fix what is listed above, commit, and push again.'
	else
		say 'fix what is listed above before pushing.'
	fi
	exit 1
fi

exit 0
