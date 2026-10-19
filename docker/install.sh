#!/bin/sh
#
# Pinegrap - Enterprise Website Platform
#
# The install service of docker-compose.yml: installs the starter site the
# first time the stack starts, without the installation screen, when the
# administrator's e-mail address and password are given (PG_INSTALL_ADMIN_EMAIL,
# PG_INSTALL_ADMIN_PASSWORD). The installation is the installer's own, run
# from the command line: php pinegrap/install/index.php install
# (pinegrap/includes/install_cli.php lists the variables it reads).
#
# Without those two variables it only says where the installation screen is.
# Once a site is installed the installer says so and leaves it alone, so the
# service runs again on every start and does nothing.
#
# The site's address defaults to localhost and the published port
# (PG_HTTP_PORT); the server rules file is the nginx sample, as an
# installation through the web container writes it.
#
# @author      Erdal Güral (Kodpen)
# @link        https://kodpen.com
# @copyright   2017–2026 Kodpen
# @license     https://opensource.org/licenses/mit-license.html MIT License

set -u

port="${PG_HTTP_PORT:-8080}"

case "$port" in
    80) address="localhost" ;;
    *) address="localhost:$port" ;;
esac

if [ -z "${PG_INSTALL_HOSTNAME:-}" ]; then
    PG_INSTALL_HOSTNAME="$address"
fi

if [ -z "${PG_INSTALL_WEB_SERVER:-}" ]; then
    PG_INSTALL_WEB_SERVER="nginx"
fi

export PG_INSTALL_HOSTNAME PG_INSTALL_WEB_SERVER

if [ -z "${PG_INSTALL_ADMIN_EMAIL:-}" ] && [ -z "${PG_INSTALL_ADMIN_PASSWORD:-}" ]; then
    echo "pinegrap-install: PG_INSTALL_ADMIN_EMAIL and PG_INSTALL_ADMIN_PASSWORD are not set, so nothing is installed here; the installation screen is http://$PG_INSTALL_HOSTNAME/pinegrap/install/"
    exit 0
fi

exec php /var/www/html/pinegrap/install/index.php install
