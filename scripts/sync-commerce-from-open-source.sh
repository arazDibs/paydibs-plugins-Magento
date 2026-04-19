#!/usr/bin/env bash
# Copy Open Source package tree into Commerce package, then restore Commerce composer name/description.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
rsync -a --delete "$ROOT/packages/magento-open-source/" "$ROOT/packages/magento-commerce-cloud/"
python3 - "$ROOT/packages/magento-commerce-cloud/composer.json" <<'PY'
import json, sys
path = sys.argv[1]
with open(path) as f:
    d = json.load(f)
d["name"] = "paydibs/module-paymentgateway-commerce"
d["description"] = "Paydibs payment gateway for Adobe Commerce and Magento Commerce Cloud (Magento 2.4.x)."
with open(path, "w") as f:
    json.dump(d, f, indent=4)
    f.write("\n")
PY
sed -i '' 's/composer require paydibs\/module-paymentgateway$/composer require paydibs\/module-paymentgateway-commerce/' "$ROOT/packages/magento-commerce-cloud/README.md" 2>/dev/null || \
  sed -i 's/composer require paydibs\/module-paymentgateway$/composer require paydibs\/module-paymentgateway-commerce/' "$ROOT/packages/magento-commerce-cloud/README.md"
echo "Synced. Review packages/magento-commerce-cloud/README.md if sed did not match."
