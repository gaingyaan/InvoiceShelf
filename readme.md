<img src="https://github.com/user-attachments/assets/a6ae2080-e865-4fde-b41d-5a09964d7de2">

## Introduction

InvoiceShelf is an open-source web & mobile app that helps you track expenses, payments & create professional invoices & estimates.

The Web Application is made using Laravel & VueJS while the Mobile Apps are built using React Native.

> **This is a fork of [InvoiceShelf](https://github.com/InvoiceShelf/InvoiceShelf)**, tracking the `stable-2.4.2` branch. It exists to provide a native, non-Docker, local-SQLite installation path for Windows. All upstream credit belongs to the original InvoiceShelf project and contributors — see [Credits](#credits) below.

To get started with InvoiceShelf using Docker Compose, check out the upstream [Installation docs](https://docs.invoiceshelf.com/installation.html). To install this fork natively on Windows with a local SQLite database (no Docker), see the guide below.

# Table of Contents

1. [Documentation](#documentation)
2. [System Requirements](#system-requirements)
3. [Historical Invoices Import](#historical-invoices-import)
4. [Download](#download)
5. [Mobile Apps](#mobile-apps)
6. [Discord - **NEW**](#discord) 🔥
7. [Roadmap](#roadmap)
8. [Credits](#credits)
9. [Help us translate](#translate)
10. [License](#license)

## Documentation

- [Installation Steps (Docker, upstream)](https://docs.invoiceshelf.com/installation.html)
- [Windows Local Installation (this fork, SQLite, no Docker)](windows_installtion.md)
- [User Guide](https://docs.invoiceshelf.com/)
- [Developer Guide](https://docs.invoiceshelf.com/developer-guide.html)
- [API Documentation](https://api-docs.invoiceshelf.com)

## System Requirements

- This fork tracks `stable-2.4.2` and requires **PHP 8.4+**, **Node.js 24+**, and **pnpm 11.6.0** (via Corepack).
- Before updating from the app settings, verify your server PHP version and required extensions.
- The in-app updater checks requirements and blocks the update if they are not met.

## Historical Invoices Import (PDF & Markdown)

InvoiceShelf includes a built-in Artisan command supporting **two flexible approaches** to import historical invoices:

1. **Standalone PDF Files (No `.md` required)**: Simply drop your `.pdf` invoice files into the folder. The system automatically reads and extracts the invoice number, dates, client name, addresses, line items, rates, taxes, and bank details directly from the PDF, and attaches the original document.
2. **Markdown (`.md`) Files (With or without PDF)**: Place `.md` files containing YAML frontmatter for granular control over invoice records, with optional matching `.pdf` files attached automatically.

### Features
- **Dual-Mode Import**: Process standalone PDFs, Markdown files, or a mix of both in a single run.
- **Automated Customer & Address Setup**: Automatically creates customers with full **Billing** and **Shipping** addresses.
- **Line Items & HSN/SAC Support**: Ingests line items, calculates minor currency units (cents), and records HSN/SAC codes in item descriptions.
- **Complete Context Preservation**: Preserves Subject, Place of Supply, Company Bank/Remittance details, and legal Terms & Conditions in invoice notes.
- **Payment Reconciliation**: Automatically logs payment records in the ledger for invoices marked with `PAID` status.
- **Direct PDF Attachment**: Automatically links matching or standalone PDF files directly to the invoice record via Spatie MediaLibrary.

### Usage

1. Place your historical files (`.pdf` only, or `.md` + `.pdf`) into:
   ```
   storage/import/invoices/
   ```

2. Run the import command:
   ```bash
   php artisan invoices:import-historical
   ```

   *Options:*
   - `--path=path/to/folder`: Specify a custom import directory (defaults to `storage/import/invoices`).
   - `--company=ID_OR_SLUG`: Specify target company (defaults to the first company).

### Sample Markdown Format

```yaml
---
invoice_number: "INV/GG/26-27/6"
invoice_date: "2026-08-19"
due_date: "2026-09-03"
po_number: "PO/GTPL/25-26/03"
terms: "Net 15"
status: "PAID"
currency: "USD"
place_of_supply: "Complete address of the client"
subject: "INVOICE | SUBJECT OF THE INVOICE"
bank_account_name: "GAINGYAAN IT SOLUTIONS PRIVATE LIMITED"
bank_account_number: "MY ACCOUNT NUMBER"
bank_branch: "BRANCH NAME AND ADDR"
bank_ifsc: "CORRECT IFSC CODE"
bank_swift: "SWIFT CODE"
terms_conditions: "• IGST Exempted for Export of Services\n• Payable within 15 days"
client_name: "CLIENT'S COMPLETE REGISTERED NAME"
billing_address_1: "CLIENT ADDR LINE 1"
billing_address_2: "ADDR LINE 2"
billing_city: "CITY"
billing_state: "STATE"
billing_country: "India"
shipping_address_1: "SHIIPING ADDR LINE 1"
shipping_address_2: "LINE 2"
shipping_city: "CITY/STATE"
shipping_country: "India"
sub_total: 1234.60
tax: 0.00
total: 1234.60
balance_due: 0.00
notes: "ANY NOTE THAT NEEDS TO BE ADDED FOR THE CLIENT. Thanks for your business."
items:
  - name: "Project consulting"
    description: "Agile Test Pro Consulting"
    hsn_sac: "999293"
    qty: 1.00
    rate: 1234.60
    amount: 1234.60
---
```

## Download

- Upstream releases: [invoiceshelf.com](https://invoiceshelf.com)
- This fork: clone directly from this repository's `stable-2.4.2` branch (see [Windows Local Installation](windows_installation.md))

## Mobile Apps

- Android - Coming Soon
- iOS - Coming Soon
- [Source](https://github.com/InvoiceShelf/mobile)

## Discord

This fork does not run its own community server. For general InvoiceShelf discussion, the upstream project has a Discord:
[Invite Link](https://discord.gg/eHXf4zWhsR)

## Roadmap

Upstream project roadmap (not specific to this fork):

- [x] Automatic Update
- [x] Email Configuration
- [x] Installation Wizard
- [x] Address Customisation & Default notes
- [x] Edit Email before Sending Invoice
- [x] Available as a docker image
- [x] Performance Improvements
- [x] Customer View page
- [x] Add and Use Custom Fields on Invoices & Estimates.
- [x] Multiple Companies
- [x] Recurring Invoices
- [x] Customer Portal
- [ ] Accept Payments (Stripe Integration)
- [ ] Improved template system (invoices and estimate)
- [ ] Modules and templates marketplace

This fork's own focus is a maintained native Windows / local-SQLite installation path.

## Credits

This is a fork of [InvoiceShelf/InvoiceShelf](https://github.com/InvoiceShelf/InvoiceShelf). InvoiceShelf itself is made possible thanks to the contributions and support from many people and projects:

- [InvoiceShelf](https://github.com/InvoiceShelf/InvoiceShelf) (original project)
- [Crater](https://github.com/crater-invoice-inc/crater) (project inspiration and code base)
- All contributors who have provided code, translations, reported issues, or supported the project in any way.

## Translate

Help translate the upstream project on https://crowdin.com/project/invoiceshelf

## License

InvoiceShelf, and this fork, are released under the GNU AFFERO GENERAL PUBLIC LICENSE Version 3.
See [LICENSE](LICENSE) for details.
