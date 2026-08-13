# Changelog

Release notes for the 3.x line. Each `##` heading is a released version, and the
section beneath it is what CI publishes to the updater — see
`.github/scripts/changelog-section.php`.

The 2.x line has its own CHANGELOG.md on the `2.x` branch. Releases are also on
GitHub: https://github.com/InvoiceShelf/InvoiceShelf/releases

## 3.0.0-alpha.2 — 2026-08-14

Second public alpha of InvoiceShelf 3.0, with major additions across payments, taxes, PDFs, and the module platform.

⚠️ **Pre-release — not for production.** Back up your database before upgrading and use this release for evaluation and testing only.

### Highlights

- **Credit notes:** Create, send, and render linked full-invoice reversals. Credit notes settle the original balance, preserve the relationship in both directions, and can be safely removed to restore the balance.
- **Customer accounts and payments:** Allocate a payment across multiple invoices, retain overpayments as customer credit, inspect account activity and outstanding balances, and download or email customer statements.
- **Taxes:** Track purchase taxes on expenses and in tax reports, and apply compound sales taxes at document or item level with consistent exclusive, tax-inclusive, discount, and recurring-invoice calculations.
- **PDFs:** Dompdf and Gotenberg now share one rendering contract, with common page settings, repeating headers and footers, page numbers, broader template overrides, document metadata, and optional PDF/A output through Gotenberg.
- **Modules and AI:** Pair with the official marketplace, securely install and update signed modules, and uninstall them with data-preserving or explicitly destructive flows. The AI assistant is now a free official module instead of bundled core functionality.

### Improvements and fixes

- Require invoices to be settled before marking them completed, correct demo document sequences, and surface document save failures instead of silently leaving inconsistent state.
- Improve PDF downloads, report authorization, custom templates, fonts, line heights, margins, and access to private or Docker-internal Gotenberg hosts.
- Add MariaDB support to the installer, make the application timezone configurable, recreate required storage directories on container startup, and avoid secure-context-only browser APIs on plain HTTP installations.
- Reorganize the application into explicit domain and platform boundaries while keeping public v1 API routes and durable database data compatible.
- Harden the tag-driven release pipeline so packages are tested, built, reviewed as drafts, and registered with the updater only after publication.

### Upgrade notes

- The payment migration backfills existing invoice-payment relationships into the new allocation table and validates invalid or orphaned rows before removing the legacy relationship.
- Existing tax types are classified as sales taxes. Create purchase tax types for expense input-tax tracking.
- Core AI screens and services have moved to the official AI Assistant module. Existing AI data is retained so the module can adopt it.
- Internal PHP namespaces changed as part of the domain reorganization. Custom modules or integrations importing internal application classes must update those imports; public API routes remain compatible.
- The module runtime now uses InvoiceShelf Modules SDK 3.3.0. The unsafe `module:delete` command is replaced by `module:uninstall`, with explicit confirmation required before removing module data.

Docker: `invoiceshelf/invoiceshelf:3.0.0-alpha.2` (also `:next`).

## 3.0.0-alpha.1 — 2026-06-14

First public alpha of InvoiceShelf 3.0 — the next-generation rewrite (Laravel 13 / PHP 8.4, Vue 3 + TypeScript, Tailwind v4).

⚠️ **Pre-release — not for production.** For evaluation and testing only.

Docker: `invoiceshelf/invoiceshelf:3.0.0-alpha.1` (also `:next`).
