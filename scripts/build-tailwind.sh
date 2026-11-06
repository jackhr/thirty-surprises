#!/usr/bin/env bash
# Builds public/stylesheets/output.css from public/stylesheets/style.css.
# Uses the standalone Tailwind CLI (no Node/npm needed). It is downloaded to bin/ on first run.
# Usage: ./scripts/build-tailwind.sh [--minify] [--watch]
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TAILWIND_VERSION="${TAILWIND_VERSION:-v3.4.17}"
TAILWIND_BIN="${TAILWIND_BIN:-$ROOT_DIR/bin/tailwindcss}"

download_tailwind() {
  local os arch
  case "$(uname -s)" in
    Darwin) os="macos" ;;
    Linux) os="linux" ;;
    *) echo "Unsupported OS: $(uname -s). Download the Tailwind CLI to $TAILWIND_BIN manually." >&2; exit 1 ;;
  esac
  case "$(uname -m)" in
    arm64|aarch64) arch="arm64" ;;
    x86_64|amd64) arch="x64" ;;
    *) echo "Unsupported architecture: $(uname -m). Download the Tailwind CLI to $TAILWIND_BIN manually." >&2; exit 1 ;;
  esac

  local url="https://github.com/tailwindlabs/tailwindcss/releases/download/${TAILWIND_VERSION}/tailwindcss-${os}-${arch}"
  echo "Downloading Tailwind CLI ${TAILWIND_VERSION} (${os}-${arch})..."
  mkdir -p "$(dirname "$TAILWIND_BIN")"
  curl -fsSL "$url" -o "$TAILWIND_BIN"
  chmod +x "$TAILWIND_BIN"
}

if [[ ! -x "$TAILWIND_BIN" ]]; then
  download_tailwind
fi

"$TAILWIND_BIN" \
  -c "$ROOT_DIR/tailwind.config.js" \
  -i "$ROOT_DIR/public/stylesheets/style.css" \
  -o "$ROOT_DIR/public/stylesheets/output.css" \
  "$@"
