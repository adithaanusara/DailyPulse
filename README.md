# DailyPulse

A fast, modern WordPress news portal: a custom theme plus two small plugins.

- **Theme `dailypulse`** — newspaper-style layout: top bar with breaking-news ticker, centred masthead, dark menu bar, featured slider with side tiles, filterable "Latest" grid beside a "Just in" sidebar, a colour-coded box per category, article page with gallery and source links, dark/light mode, self-hosted variable fonts (with Sinhala and Tamil). One stylesheet and one deferred script; no jQuery, no framework, no build step.
- **Plugin `dailypulse-core`** — the newsroom tools: story fields, Breaking Alerts, category colours and layouts, site options, share/SEO metadata, demo content.
- **Plugin `dailypulse-cleanup`** — once a day, moves published stories older than 3 days to the Trash (or deletes them).

Tested on WordPress 7.1 with PHP 7.4 – 8.4.

## Run it locally

### Option A — Docker (one command)

Requires Docker Desktop to be running.

```bash
./bin/setup.sh
```

This starts WordPress + MariaDB, installs WordPress, activates the theme and both plugins, creates the categories and menus, and imports 22 demo stories with photos. When it finishes:

| | |
|---|---|
| Site | http://localhost:8090 |
| Dashboard | http://localhost:8090/wp-admin |
| Login | `admin` / `admin` (local only) |

If port 8090 is taken, the script picks the next free port and saves it to `.env`.

```bash
./bin/setup.sh --no-demo      # without demo stories
./bin/setup.sh --acf          # also install Secure Custom Fields (free ACF build)
docker compose down           # stop
docker compose up -d          # start again
docker compose down -v        # stop and wipe the database and uploads
docker compose run --rm wpcli wp <command>   # WP-CLI
```

The theme and plugins are mounted from this repository, so edits appear on refresh.

### Option B — MAMP / XAMPP / any existing WordPress

1. Copy (or symlink) three folders into your WordPress install:

   ```bash
   WP=/Applications/MAMP/htdocs/your-site/wp-content
   ln -s "$PWD/wp-content/themes/dailypulse"          "$WP/themes/dailypulse"
   ln -s "$PWD/wp-content/plugins/dailypulse-core"    "$WP/plugins/dailypulse-core"
   ln -s "$PWD/wp-content/plugins/dailypulse-cleanup" "$WP/plugins/dailypulse-cleanup"
   ```

2. In the dashboard: **Appearance → Themes** → activate *DailyPulse*; **Plugins** → activate *DailyPulse Core* and *DailyPulse Auto-Cleanup*.
3. **DailyPulse → Setup & Demo** → *Run quick setup*, then *Import demo content*.

## Using it

| Task | Where |
|---|---|
| Write a story | Posts → Add New. Title, category, content and featured image as usual; **Story Details**, **Source Links** and **Media Gallery** are below the editor. |
| Mark a story as breaking / put it in the hero | Story Details → *Breaking news* / *Hero slider* |
| Send a one-line alert to the ticker | Breaking Alerts → Add Alert |
| Category colour, homepage order and layout | Posts → Categories → edit a category |
| Ticker, hero, article and footer settings, accent colour | DailyPulse → Site Options |
| Auto-cleanup settings, preview, run now | Tools → Auto-Cleanup |

### Auto-cleanup

Runs daily at 03:00 site time (configurable). Defaults: published **Posts** older than **3 days** → **Trash** (WordPress empties the Trash after 30 days). You can switch to permanent deletion, change the age, exempt categories, and optionally delete a story's unused images with it.

- Tick **Keep this story** in the editor sidebar to exempt a single story. Restoring a story from the Trash exempts it automatically.
- Pages and media library items are never touched.
- WordPress runs scheduled tasks when someone visits the site. On a low-traffic server, add a real cron job: `*/15 * * * * wp cron event run --due-now --path=/path/to/site`.
- WP-CLI: `wp dailypulse cleanup status`, `wp dailypulse cleanup run --dry-run`, `wp dailypulse cleanup run`.
- Because demo stories are ordinary posts, they expire too. Re-run *Import demo content* (or `wp dailypulse demo import`) to refresh their dates.

### ACF

All fields are defined once in `dailypulse-core/includes/schema.php`.

- **No ACF installed** — native WordPress meta boxes and a native options page.
- **ACF (free)** — Story Details are edited through ACF; gallery, sources and options stay native.
- **ACF PRO or Secure Custom Fields** — Story Details, Media Gallery and the Site Options page all use ACF.

Values are stored in the same post meta and option rows in every mode, so you can switch ACF on or off without losing anything. In templates read them with `dailypulse_field( 'dp_kicker' )` and `dailypulse_option( 'ticker_label' )`.

## Project layout

```
bin/setup.sh                    one-command Docker setup
docker-compose.yml              WordPress + MariaDB + WP-CLI
wp-content/
  themes/dailypulse/            theme (templates, inc/, template-parts/, assets/)
  plugins/dailypulse-core/      fields, alerts, options, SEO, demo importer
  plugins/dailypulse-cleanup/   daily cleanup
```

## Going live

- Install a page-cache plugin; the theme is built to be cached (relative times are refreshed in the browser).
- Change the admin password and set `WP_DEBUG` to `0`.
- Fonts in `assets/fonts` are under the SIL Open Font License; demo photos come from picsum.photos (Unsplash licence) and are only downloaded when you import the demo.
