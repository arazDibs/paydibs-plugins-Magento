# Adobe Commerce Marketplace — Technical Submission (reference)

Use this when filling the Technical Submission form. **Rebuild the ZIP** after any `composer.json` change (`./scripts/build-submission-packages.sh`).

## Extension Code Package

- **Upload:** `dist/paydibs-module-paymentgateway-open-source.zip` (Magento Open Source listing) **or** `dist/paydibs-module-paymentgateway-commerce.zip` (Adobe Commerce listing), depending on which product you submit.
- **Max size:** 30 MB (these ZIPs are small).
- **Version inside package:** `composer.json` contains **`"version": "103.0.0"`** — must match **Marketplace Version Number** below.

## Marketplace Version Number

- Enter: **`v103.0.0`** (or **`103.0.0`** if the field does not accept a `v` — match what Adobe’s UI shows; the package is verified against **`composer.json`**).

## Adobe Commerce / Magento version compatibility

- **Adobe Commerce (cloud):** **2.4** (extension `composer.json` requires `magento/framework` **~103.0.0||~104.0.0**, aligned with Magento / Adobe Commerce **2.4.x**).
- **Adobe Commerce (on-prem):** **2.4**
- **Magento Open Source:** **2.4**

If the form asks for a single “compatibility version,” choose the **2.4** line that matches your tested release (e.g. **2.4.7** if you validated on that patch).

## Page Builder Compatibility

This extension is a **payment gateway**; it does **not** add Page Builder content types or use Page Builder for content creation. Leave Page Builder options **unchecked** / select **None** / **Not applicable** per the form’s wording.

## Licensing

- **License type:** **Apache License 2.0** (OSI-approved open source).
- **`composer.json`:** `"license": "Apache-2.0"`.
- **Full text:** `packages/magento-open-source/LICENSE.txt` · **Attribution / trademark note:** `NOTICE.txt`.
- **Custom license URL (if required):** Raw `LICENSE.txt` on GitHub, e.g.  
  `https://github.com/arazDibs/paydibs-plugins-Magento/blob/main/packages/magento-open-source/LICENSE.txt`

## Documentation (mandatory PDF, ≤ 5 MB)

Upload **one** PDF that matches your listing edition:

| Listing | Open in browser → Print → Save as PDF |
|---------|----------------------------------------|
| **Magento Open Source** | **`docs/marketplace/Paydibs-Guide-Magento-Open-Source.html`** |
| **Adobe Commerce** | **`docs/marketplace/Paydibs-Guide-Adobe-Commerce.html`** |

Content is mirrored in Markdown under **`docs/guides/`** with the same screenshots (**`docs/images/user-guide/`**). Technical review expects installation steps, configuration path, and behavior to match the **uploaded extension package**.

## Shared packages (optional)

- **None** unless you formally depend on another Marketplace shared package (this extension does not list shared packages in Composer).

## Release notes (paste into portal)

See **`RELEASE_NOTES_FOR_PORTAL.txt`** (plain text, no HTML). Minimum length is satisfied.
