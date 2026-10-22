#!/usr/bin/env bash
# Build the Mail dependencies outside the source / deployable application tree.
# Only the pinned ImapEngine release and its Composer-resolved dependencies are used.
set -euo pipefail

if [[ "$#" -ne 1 ]]; then
  echo "Usage: $0 OUTPUT_DIR" >&2
  exit 2
fi
DEST="$1"
mkdir -p "$DEST"
DEST="$(cd "$DEST" && pwd)"

cat > "$DEST/composer.json" <<'JSON'
{
  "name": "zeijaku/rss-reader-mail-runtime",
  "type": "project",
  "description": "Pinned Mail dependencies for RSS Reader official Runtime ZIP",
  "require": {
    "php": "^8.1",
    "directorytree/imapengine": "1.25.6"
  },
  "config": {
    "sort-packages": true,
    "allow-plugins": false
  }
}
JSON

composer install --working-dir="$DEST" --prefer-dist --no-dev --no-interaction --no-progress --no-scripts --no-plugins --optimize-autoloader
composer audit --working-dir="$DEST" --locked --no-interaction
test -f "$DEST/vendor/autoload.php"
test -f "$DEST/vendor/directorytree/imapengine/composer.json"
test -f "$DEST/composer.lock"

php -r '
  require $argv[1] . "/vendor/autoload.php";
  require "app/mail/mail_client.php";
  foreach ([
    DirectoryTree\ImapEngine\Mailbox::class,
    DirectoryTree\ImapEngine\Connection\ImapConnection::class,
    DirectoryTree\ImapEngine\Connection\Streams\ImapStream::class,
  ] as $symbol) {
    if (!class_exists($symbol)) {
      fwrite(STDERR, "Missing required Mail class: " . $symbol . PHP_EOL);
      exit(1);
    }
  }
  if (!mail_client_available() || !class_exists("AppMailPinnedImapStream")) {
    fwrite(STDERR, "Mail client dependency smoke check failed" . PHP_EOL);
    exit(1);
  }
  echo "PASS: Mail runtime dependencies load" . PHP_EOL;
' "$DEST"
