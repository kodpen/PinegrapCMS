#!/bin/sh
#
# Pinegrap - Enterprise Website Platform
#
# The cron service of docker-compose.yml: runs the general job
# (pinegrap/job.php) once a minute, as the crontab line in the README does on
# a host. One run at a time: the next starts a minute after the previous one
# ended. The job starts only while the site is installed and its database is
# at the version of the code (pinegrap-job-ready); it says once when it
# starts waiting and once when it goes on.
#
# PG_CRON_INTERVAL sets the pause in seconds (default 60).
#
# @author      Erdal Güral (Kodpen)
# @link        https://kodpen.com
# @copyright   2017–2026 Kodpen
# @license     https://opensource.org/licenses/mit-license.html MIT License

set -u

interval="${PG_CRON_INTERVAL:-60}"

case "$interval" in
    ''|*[!0-9]*) interval=60 ;;
esac

# docker stop sends TERM to this shell; the pause runs in the background so
# the trap is answered at once instead of after the sleep.
trap 'exit 0' TERM INT

state=""

while :; do
    if php /usr/local/bin/pinegrap-job-ready; then
        if [ "$state" != "ready" ]; then
            echo "pinegrap-cron: running job.php every ${interval}s"
            state="ready"
        fi

        php /var/www/html/pinegrap/job.php
        status=$?

        if [ "$status" -ne 0 ]; then
            echo "pinegrap-cron: job.php exited with status $status" >&2
        fi
    elif [ "$state" != "waiting" ]; then
        echo "pinegrap-cron: waiting until the site is installed and its database is up to date"
        state="waiting"
    fi

    sleep "$interval" &
    wait $!
done
