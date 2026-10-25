# InvoiceShelf

InvoiceShelf is an open-source web application for creating invoices and estimates,
tracking payments, and managing expenses.

## Stable v2 maintenance

This branch is the stable InvoiceShelf v2 line. It receives security patches only
through **2027-09-01**; new feature development is focused on the next
major version.

## Documentation and support

- [Download the stable release](https://invoiceshelf.com/download)
- [Documentation](https://docs.invoiceshelf.com/)
- [Production Docker images](https://github.com/InvoiceShelf/docker)
- [Community Discord](https://discord.gg/eHXf4zWhsR)

For a local contributor environment, see [the development environment guide](docker/development/README.md).

For a local MariaDB deployment whose configuration is supplied through a
required environment file, see the [custom Docker guide](custom/docker/README.md).

## Historical invoice import

This fork adds an Artisan command that imports historical invoices from Markdown
files, standalone PDFs, or matching Markdown/PDF pairs. Matching source PDFs are
attached to the imported invoice, and paid invoices receive a corresponding
payment record.

Place the source files in `storage/import/invoices/`, then run:

```bash
php artisan invoices:import-historical
```

Use `--path=relative/path` to select another directory and
`--company=ID_OR_SLUG` to target a specific company. Markdown files can provide
invoice metadata and line items using YAML-style frontmatter. Direct PDF import
requires Python 3 and `pypdf`:

```bash
python3 -m pip install pypdf
```

Set `PYTHON_BINARY` when the Python executable has a different name or path.

For a short Docker walkthrough, see the
[historical invoice import guide](docs/historical-invoice-import.md).

## Contributing

Contributions that support v2 security maintenance and documentation are welcome.
See the [contribution guide](.github/CONTRIBUTING.md) before opening a pull
request.

## Translations

Help translate InvoiceShelf on [Crowdin](https://crowdin.com/project/invoiceshelf).

## License

InvoiceShelf is licensed under the [GNU Affero General Public License v3.0](LICENSE).
