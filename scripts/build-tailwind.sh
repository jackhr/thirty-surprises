#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TAILWIND_BIN="${TAILWIND_BIN:-$ROOT_DIR/bin/tailwindcss}"

if [[ ! -x "$TAILWIND_BIN" ]]; then
  echo "Tailwind CLI not found or not executable at: $TAILWIND_BIN"
  echo "Download the standalone binary and place it there, then run chmod +x on it."
  exit 1
fi

"$TAILWIND_BIN" -i "$ROOT_DIR/public/stylesheets/style.css" -o "$ROOT_DIR/public/stylesheets/output.css" "$@"
