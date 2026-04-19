#!/usr/bin/env bash
# Build ZIP archives of each Composer package for Adobe Commerce Marketplace / manual installs.
# Run from the repository root. Output: dist/*.zip (gitignored) and dist/SHA256SUMS.txt
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
mkdir -p "$DIST"

build_one () {
  local src_name="$1"
  local zip_name="$2"
  local src="$ROOT/packages/$src_name"
  local out="$DIST/$zip_name"
  rm -f "$out"
  (cd "$src" && zip -r -q "$out" . -x "*.DS_Store" -x "**/.git/**")
  echo "Wrote $out ($(du -h "$out" | awk '{print $1}'))"
}

build_one "magento-open-source" "paydibs-module-paymentgateway-open-source.zip"
build_one "magento-commerce-cloud" "paydibs-module-paymentgateway-commerce.zip"

(
  cd "$DIST"
  rm -f SHA256SUMS.txt
  shasum -a 256 paydibs-module-paymentgateway-open-source.zip paydibs-module-paymentgateway-commerce.zip > SHA256SUMS.txt
)
echo "Wrote $DIST/SHA256SUMS.txt"
cat "$DIST/SHA256SUMS.txt"
