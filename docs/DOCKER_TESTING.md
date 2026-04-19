# Test Paydibs extension with Magento in Docker (macOS)

This uses the common **markshust/docker-magento** stack (Magento **Open Source**, good enough to exercise your module; behavior matches Adobe Commerce core payment/checkout for this extension).

Full guides with screenshots: **`docs/guides/Paydibs-Magento-Open-Source-User-Guide.md`** (and Adobe Commerce variant). Quick index: **`docs/INSTALLATION.md`**. License: Apache-2.0.

### Recommended Magento version

Use **Magento Open Source 2.4.7** as a solid default: it matches the ecosystem around **`magento/framework` `~103.0.0||~104.0.0`** declared in this extension’s `composer.json`, is widely used, and is a good Marketplace-aligned smoke-test target. You can also use **2.4.6** or **2.4.8+** if you prefer—stay consistent with what you claim in the listing.

### Magento Composer authentication (required for `bin/download`)

Installing Magento CE via Composer needs **public + private keys** from [Magento Marketplace → My products → Access Keys](https://marketplace.magento.com/customer/accessKeys/) for **`repo.magento.com`**. Add them to `~/.composer/auth.json` (or run the docker-magento auth script **in a real terminal** where `/dev/tty` exists). Non-interactive / CI environments will fail at `bin/setup-composer-auth` without this.

## 1. Install Docker Desktop

Install [Docker Desktop for Mac](https://docs.docker.com/desktop/install/mac-install/), start it, and confirm in Terminal:

```bash
docker --version
docker compose version
```

## 2. Create a Magento project (one-time)

From a folder **outside** this repo (e.g. `~/Sites`):

```bash
cd ~/Sites
curl -s https://raw.githubusercontent.com/markshust/docker-magento/master/lib/onelinesetup | bash -s -- magento.test 2.4.7
```

Adjust **`2.4.7`** to the **2.4.x** patch you want to align with your Marketplace claim (e.g. `2.4.6`, `2.4.8`). Wait until containers are up and Magento is installed (can take several minutes).

Details and troubleshooting: [markshust/docker-magento](https://github.com/markshust/docker-magento).

## 3. Add your extension as a Composer path repository

Assume:

- Magento project: `~/Sites/magento`
- This repo: `~/Codes/plugins/Paydibs-Magento-V3 Portal/paydibs-plugins-magento/packages/magento-open-source`

In the **Magento project** `composer.json`, add under **`repositories`** (and keep a trailing comma correctly):

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "/absolute/path/to/paydibs-plugins-magento/packages/magento-open-source",
            "options": {
                "symlink": true
            }
        }
    ]
}
```

Use your **real absolute path** to `packages/magento-open-source`.

Then require the package (from Magento project root, **inside** the PHP container if you use `bin/cli`):

```bash
cd ~/Sites/magento
./bin/cli composer require paydibs/module-paymentgateway:@dev
```

Or from host if `composer` is wired to the container the way the project documents.

## 4. Enable the module and compile

```bash
./bin/magento module:enable Paydibs_PaymentGateway
./bin/magento setup:upgrade
./bin/magento setup:di:compile
./bin/cache:flush
```

Use `./bin/magento` / `./bin/cli` as in docker-magento docs.

## 5. Configure and test

1. Admin: **Stores → Configuration → Sales → Payment Methods → Paydibs** — enable, **Test** API, sandbox credentials.
2. Storefront: checkout with **Paydibs**, complete sandbox payment, confirm order state and `var/log/paydibs.log` if logging is enabled.

## 6. Production-mode check (optional, closer to Marketplace)

```bash
./bin/magento deploy:mode:set production
./bin/magento setup:di:compile
```

Repeat one checkout; fix any compile/runtime errors.

## Adobe Commerce (enterprise) in Docker

The stack above is **Magento Open Source**. For **Adobe Commerce** parity you need an Adobe-licensed install (Cloud / on-prem) or an internal image; the **extension code path** for Paydibs is the same module—testing on Open Source 2.4.x is still valid for most technical review items.

## Quick verification without full checkout

- `bin/magento module:status Paydibs_PaymentGateway` → enabled  
- `bin/magento setup:di:compile` → success  
- Admin: payment method appears and saves config  

---

**Tip:** If path repositories are awkward, you can copy the module into `app/code/Paydibs/PaymentGateway` **only for local testing** (not for Marketplace packaging), then `module:enable` and `setup:upgrade`.

---

## Automated checks run in Docker (extension package only)

These do **not** replace a full Magento install, but confirm packaging and PHP syntax (run from any machine with Docker):

```bash
# Validate composer.json
docker run --rm -v "$PWD/packages/magento-open-source:/app" -w /app composer:2 composer validate --no-check-publish

# Lint all PHP files in the module
docker run --rm -v "$PWD/packages/magento-open-source:/app" -w /app php:8.3-cli bash -c \
  'find . -name "*.php" -not -path "./vendor/*" | while read f; do php -l "$f" || exit 1; done; echo OK'
```

**Last run (example):** `composer.json` valid (warning: `version` field optional on Packagist); all module PHP files **No syntax errors**.
