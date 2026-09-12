#!/usr/bin/env bash
# The zip WordPress installs: the plugin in a `goya24/` folder, without the
# development files listed in .distignore. The same output locally and in CI.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
out="${1:-$root/build}"
stage="$out/goya24"

rm -rf "$stage" "$out/goya24.zip"
mkdir -p "$stage"

rsync -a --exclude-from="$root/.distignore" "$root/" "$stage/"

version="$(sed -n 's/^ \* Version: *\([0-9.]*\).*/\1/p' "$root/goya24.php")"
stable="$(sed -n 's/^Stable tag: *\([0-9.]*\).*/\1/p' "$root/readme.txt")"
if [ "$version" != "$stable" ]; then
  echo "goya24.php says $version but readme.txt's Stable tag says $stable" >&2
  exit 1
fi

(cd "$out" && zip -qr goya24.zip goya24)
echo "built $out/goya24.zip (version $version)"
unzip -l "$out/goya24.zip" | tail -1
