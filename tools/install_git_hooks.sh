#!/usr/bin/env bash
#
# Pinegrap - Enterprise Website Platform
#
# Installs the git pre-push hook that runs tools/pre_push.sh, so that a push
# runs the fast CI checks on the commit first. The hook is a small shim in
# the clone's hooks folder, which every worktree of the clone shares: it runs
# the pre_push.sh of the worktree a push starts from and does nothing in a
# checkout that has none (main and the release branches). A pre-push hook
# that is not this shim, or a hooks folder moved by core.hooksPath, is left
# as it is, with a note on how to call the checks from it.
#
# A cloud session that starts inside the clone runs it when it starts (the
# SessionStart hook in .claude/settings.json). A session that starts
# elsewhere (several repositories, a repository added later) and another
# machine run it once by hand.
#
# Usage:  bash tools/install_git_hooks.sh
#         bash tools/install_git_hooks.sh --session-start
#                                 as the SessionStart hook: only in a cloud
#                                 session (CLAUDE_CODE_REMOTE=true); what it
#                                 prints on standard output becomes part of
#                                 the session's context
# Exit:   0; a hook it does not install is reported, not an error.
#
# @author      Erdal Güral (Kodpen)
# @link        https://kodpen.com
# @copyright   2017–2026 Kodpen
# @license     https://opensource.org/licenses/mit-license.html MIT License

set -u

session_start=0

if [ "${1:-}" = '--session-start' ]; then
	session_start=1
	if [ "${CLAUDE_CODE_REMOTE:-}" != 'true' ]; then
		exit 0
	fi
fi

marker='# pinegrap-pre-push'

# What to put first in a pre-push hook of one's own: it reads the refs once
# and hands them on, and pushes unchecked where the script is missing.
# shellcheck disable=SC2016
snippet='refs=$(cat); if [ -f tools/pre_push.sh ]; then printf '"'"'%s\n'"'"' "$refs" | bash tools/pre_push.sh --hook "$@" || exit 1; fi'

# A session sees only standard output; by hand the note goes to stderr.
not_installed() {
	if [ "$session_start" = 1 ]; then
		echo "git push does NOT run tools/pre_push.sh in this clone: $1. Run 'bash tools/pre_push.sh' before each push."
	else
		echo "$1; the pre-push hook was not installed. Put this first in that hook, and give \"\$refs\" the same way to anything else in it that reads the refs: $snippet" >&2
	fi
	exit 0
}

cd "$(dirname "$0")/.." 2>/dev/null || exit 0

if ! git rev-parse --git-dir >/dev/null 2>&1; then
	exit 0
fi

if [ -n "$(git config --get core.hooksPath 2>/dev/null)" ]; then
	not_installed 'core.hooksPath is set'
fi

hooks=$(git rev-parse --git-path hooks 2>/dev/null) || exit 0
hook="$hooks/pre-push"

if [ -e "$hook" ] && ! grep -qF "$marker" "$hook" 2>/dev/null; then
	not_installed "$hook exists and is not Pinegrap's"
fi

mkdir -p "$hooks" || exit 0

# A file of its own, renamed into place, so that sessions starting at the
# same moment never see a half-written hook.
tmp=$(mktemp "$hooks/pre-push.XXXXXX") || exit 0

cat >"$tmp" <<EOF
#!/bin/sh
$marker: written by tools/install_git_hooks.sh. Runs the fast CI checks
# of tools/pre_push.sh on the commit being pushed and stops a push that fails
# them. A checkout without the script pushes unchecked.
script="\$(git rev-parse --show-toplevel 2>/dev/null)/tools/pre_push.sh"
[ -f "\$script" ] || exit 0
exec bash "\$script" --hook "\$@"
EOF

if ! chmod 755 "$tmp" || ! mv -f "$tmp" "$hook"; then
	rm -f "$tmp"
	exit 0
fi

if [ "$session_start" = 1 ]; then
	echo 'git push runs tools/pre_push.sh first: on the pushed commit, the memory index check and, when anything beside the documentation changed, PHP syntax, translation keys and the unit tests. A push that fails them is stopped; fix what it lists, commit and push again.'
fi

exit 0
