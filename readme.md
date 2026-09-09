<img src="https://github.com/user-attachments/assets/a6ae2080-e865-4fde-b41d-5a09964d7de2">

## Introduction

InvoiceShelf is an open-source web & mobile app that helps you track expenses, payments & create professional invoices & estimates.

The Web Application is made using Laravel & VueJS while the Mobile Apps are built using React Native.

> **This is a fork of [InvoiceShelf](https://github.com/InvoiceShelf/InvoiceShelf)**, tracking the `stable-2.4.2` branch. It exists to provide a native, non-Docker, local-SQLite installation path for Windows. All upstream credit belongs to the original InvoiceShelf project and contributors — see [Credits](#credits) below.

To get started with InvoiceShelf using Docker Compose, check out the upstream [Installation docs](https://docs.invoiceshelf.com/installation.html). To install this fork natively on Windows with a local SQLite database (no Docker), see the guide below.

# Table of Contents

1. [Documentation](#documentation)
2. [System Requirements](#system-requirements)
3. [Download](#download)
4. [Mobile Apps](#mobile-apps)
5. [Discord - **NEW**](#discord) 🔥
6. [Roadmap](#roadmap)
7. [Credits](#credits)
8. [Help us translate](#translate)
9. [License](#license)

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
