#!/usr/bin/env bash
#
# One-command local setup for DailyPulse (Docker).
#
#   ./bin/setup.sh             start the stack, install WordPress, activate the theme
#                              and plugins, import the demo stories
#   ./bin/setup.sh --no-demo   the same, without demo stories
#   ./bin/setup.sh --acf       also install Secure Custom Fields (the free ACF build on
#                              WordPress.org) so the fields are edited through ACF
#
# Safe to run again: an existing site is kept, only missing pieces are added.

set -euo pipefail

cd "$(dirname "$0")/.."

demo=1
acf=0

for arg in "$@"; do
	case "$arg" in
		--no-demo) demo=0 ;;
		--acf) acf=1 ;;
		-h | --help)
			sed -n '3,11p' "$0" | sed 's/^# \{0,1\}//'
			exit 0
			;;
		*)
			echo "Unknown option: $arg (try --help)" >&2
			exit 1
			;;
	esac
done

if ! command -v docker >/dev/null 2>&1; then
	echo "Docker is not installed. README.md also describes the MAMP / XAMPP route." >&2
	exit 1
fi

if ! docker info >/dev/null 2>&1; then
	echo "Docker is installed but not running. Start Docker Desktop, then run this again." >&2
	exit 1
fi

port_in_use() {
	if command -v nc >/dev/null 2>&1; then
		nc -z 127.0.0.1 "$1" >/dev/null 2>&1
	else
		(echo >"/dev/tcp/127.0.0.1/$1") >/dev/null 2>&1
	fi
}

fresh_env=0
if [ ! -f .env ]; then
	cp .env.example .env
	fresh_env=1
fi

set -a
# shellcheck disable=SC1091
. ./.env
set +a

WP_PORT="${WP_PORT:-8090}"

# A port that is busy is only a problem if it is not our own site that is using it.
if [ -z "$(docker compose ps -q --status running wordpress 2>/dev/null)" ] && port_in_use "$WP_PORT"; then
	if [ "$fresh_env" = 0 ]; then
		echo "Port $WP_PORT is already in use by something else." >&2
		echo "Set WP_PORT in .env to a free port (for example $((WP_PORT + 1))) and run this again." >&2
		exit 1
	fi

	busy_port="$WP_PORT"
	while port_in_use "$WP_PORT"; do
		WP_PORT=$((WP_PORT + 1))
		if [ "$WP_PORT" -gt $((busy_port + 20)) ]; then
			echo "No free port found between $busy_port and $WP_PORT. Set WP_PORT in .env by hand." >&2
			exit 1
		fi
	done

	sed -i.bak "s/^WP_PORT=.*/WP_PORT=$WP_PORT/" .env && rm -f .env.bak
	export WP_PORT
	echo "▸ Port $busy_port is busy — using $WP_PORT instead (saved to .env)."
fi

site_url="http://localhost:${WP_PORT}"

wp() {
	docker compose run --rm -T wpcli wp "$@"
}

echo "▸ Starting containers…"
docker compose up -d --wait

echo "▸ Waiting for WordPress…"
tries=0
until docker compose exec -T wordpress test -f /var/www/html/wp-config.php; do
	tries=$((tries + 1))
	if [ "$tries" -gt 90 ]; then
		echo "WordPress did not come up. Check the logs with: docker compose logs wordpress" >&2
		exit 1
	fi
	sleep 1
done

if wp core is-installed >/dev/null 2>&1; then
	echo "▸ WordPress is already installed — keeping the existing site."
else
	echo "▸ Installing WordPress…"
	wp core install \
		--url="$site_url" \
		--title="${WP_TITLE:-DailyPulse}" \
		--admin_user="${WP_ADMIN_USER:-admin}" \
		--admin_password="${WP_ADMIN_PASSWORD:-admin}" \
		--admin_email="${WP_ADMIN_EMAIL:-admin@example.com}" \
		--skip-email
	wp option update timezone_string "${WP_TIMEZONE:-Asia/Colombo}"
fi

echo "▸ Activating the DailyPulse theme and plugins…"
wp theme activate dailypulse
wp plugin activate dailypulse-core dailypulse-cleanup

if [ "$acf" = 1 ]; then
	echo "▸ Installing Secure Custom Fields…"
	wp plugin install secure-custom-fields --activate
fi

echo "▸ Running quick setup…"
wp dailypulse setup

if [ "$demo" = 1 ]; then
	echo "▸ Importing demo stories…"
	wp dailypulse demo import
fi

cat <<EOF

✔ DailyPulse is ready.

  Site       ${site_url}
  Dashboard  ${site_url}/wp-admin
  Login      ${WP_ADMIN_USER:-admin} / ${WP_ADMIN_PASSWORD:-admin}

  Stop       docker compose down
  WP-CLI     docker compose run --rm wpcli wp <command>
EOF
