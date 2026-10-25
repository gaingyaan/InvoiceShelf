# Historical invoice import with Docker

The importer creates historical invoices from PDF files, Markdown files, or
matching PDF/Markdown pairs. Complete the InvoiceShelf installation before
running it so that a company exists.

## 1. Prepare the files

Put all invoices in one folder on your computer. For a PDF/Markdown pair, use
the same filename:

```text
historical-invoices/
├── INV-001.md
├── INV-001.pdf
└── INV-002.pdf
```

PDF-only import requires Python 3 and `pypdf` in the container. Install them
once per container:

```powershell
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml exec --user root webapp sh -lc "apk add --no-cache python3 py3-pip && pip3 install --break-system-packages pypdf"
```

Matching PDF/Markdown pairs do not need PDF extraction: invoice data comes from
the Markdown file and the PDF is attached to the invoice.

## 2. Copy the files into Docker

Create the import directory and copy the folder contents. Replace the Windows
path with the location of your files:

```powershell
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml exec webapp mkdir -p storage/import/invoices
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml cp "D:\billing\historical-invoices\." webapp:/var/www/html/storage/import/invoices/
```

## 3. Run the importer

```powershell
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml exec webapp php artisan invoices:import-historical
```

After the command finishes, open **Invoices** and verify the imported data and
PDF attachments.

## Import into a different company

When InvoiceShelf contains multiple companies, keep each company's files in a
separate folder and identify the destination using its numeric ID or slug:

```powershell
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml exec webapp mkdir -p storage/import/company-two
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml cp "D:\billing\company-two-invoices\." webapp:/var/www/html/storage/import/company-two/
docker compose --env-file custom/docker/.env -f custom/docker/docker-compose.yml exec webapp php artisan invoices:import-historical --path=storage/import/company-two --company=2
```

For a company slug, replace `--company=2` with something like
`--company=company-two`. Run the command separately for each company so its
invoices are imported into the correct account.

> PDF-only extraction works with text-based PDFs. Scanned/image-only PDFs need
> OCR and are not supported by this importer.
