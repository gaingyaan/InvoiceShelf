<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\FileDisk;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\PublicToken;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportHistoricalInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:import-historical 
                            {--path=storage/import/invoices : Path to folder containing .md files}
                            {--company= : Company ID or slug to import into}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import historical invoices from Markdown (.md) files and link existing PDFs.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rawPath = $this->option('path');
        $folderPath = base_path($rawPath);

        if (!File::isDirectory($folderPath)) {
            $this->error("Directory not found: {$folderPath}");
            return Command::FAILURE;
        }

        $files = File::files($folderPath);
        $mdFiles = array_filter($files, function ($file) {
            return strtolower($file->getExtension()) === 'md';
        });

        if (empty($mdFiles)) {
            $this->warn("No .md files found in {$folderPath}");
            return Command::SUCCESS;
        }

        $this->info("Found " . count($mdFiles) . " markdown invoice file(s) in {$rawPath}.");

        // Determine Company
        $companyOption = $this->option('company');
        if ($companyOption) {
            $company = Company::where('id', $companyOption)
                ->orWhere('slug', $companyOption)
                ->first();
        } else {
            $company = Company::first();
        }

        if (!$company) {
            $this->error("No company found in database. Please run database seeders first.");
            return Command::FAILURE;
        }

        $user = User::where('id', $company->owner_id)->first() ?? User::first();
        $this->info("Importing into Company: {$company->name} (ID: {$company->id})");

        $bar = $this->output->createProgressBar(count($mdFiles));
        $bar->start();

        $successCount = 0;
        $errors = [];

        foreach ($mdFiles as $mdFile) {
            try {
                $content = File::get($mdFile->getRealPath());
                $data = $this->parseMarkdownInvoice($content);

                if (empty($data['invoice_number'])) {
                    $data['invoice_number'] = pathinfo($mdFile->getFilename(), PATHINFO_FILENAME);
                }

                DB::transaction(function () use ($data, $company, $user, $mdFile, $folderPath) {
                    // 1. Currency
                    $currencyCode = strtoupper($data['currency'] ?? 'USD');
                    $currency = Currency::where('code', $currencyCode)->first() ?? Currency::first();

                    // 2. Customer
                    $clientName = $data['client_name'] ?? 'General Customer';
                    $customer = Customer::firstOrCreate(
                        [
                            'name' => $clientName,
                            'company_id' => $company->id,
                        ],
                        [
                            'currency_id' => $currency ? $currency->id : 1,
                            'creator_id' => $user ? $user->id : 1,
                        ]
                    );

                    // 3. Billing & Shipping Address
                    $country = Country::where('name', 'like', '%' . ($data['billing_country'] ?? 'India') . '%')->first();
                    Address::updateOrCreate(
                        [
                            'customer_id' => $customer->id,
                            'type' => Address::BILLING_TYPE,
                            'company_id' => $company->id,
                        ],
                        [
                            'name' => $clientName,
                            'address_street_1' => $data['billing_address_1'] ?? '',
                            'address_street_2' => $data['billing_address_2'] ?? '',
                            'city' => $data['billing_city'] ?? '',
                            'state' => $data['billing_state'] ?? '',
                            'country_id' => $country ? $country->id : 1,
                        ]
                    );

                    if (!empty($data['shipping_address_1']) || !empty($data['shipping_city'])) {
                        $shipCountry = Country::where('name', 'like', '%' . ($data['shipping_country'] ?? 'India') . '%')->first();
                        Address::updateOrCreate(
                            [
                                'customer_id' => $customer->id,
                                'type' => Address::SHIPPING_TYPE,
                                'company_id' => $company->id,
                            ],
                            [
                                'name' => $clientName,
                                'address_street_1' => $data['shipping_address_1'] ?? '',
                                'address_street_2' => $data['shipping_address_2'] ?? '',
                                'city' => $data['shipping_city'] ?? '',
                                'state' => $data['shipping_state'] ?? '',
                                'country_id' => $shipCountry ? $shipCountry->id : ($country ? $country->id : 1),
                            ]
                        );
                    }

                    // 4. Monetary amounts (stored in minor units: cents/cents * 100)
                    $subTotal = (int) round(($data['sub_total'] ?? 0) * 100);
                    $taxTotal = (int) round(($data['tax'] ?? 0) * 100);
                    $total = (int) round(($data['total'] ?? ($data['sub_total'] ?? 0)) * 100);
                    $isPaid = strtoupper($data['status'] ?? 'DRAFT') === 'PAID';
                    $dueAmount = $isPaid ? 0 : $total;

                    // Build comprehensive notes preserving subject, place of supply, terms, and bank details
                    $noteSections = [];
                    if (!empty($data['subject'])) {
                        $noteSections[] = "Subject: " . $data['subject'];
                    }
                    if (!empty($data['place_of_supply'])) {
                        $noteSections[] = "Place of Supply: " . $data['place_of_supply'];
                    }
                    if (!empty($data['notes'])) {
                        $noteSections[] = $data['notes'];
                    }
                    if (!empty($data['bank_account_name']) || !empty($data['bank_account_number'])) {
                        $bankLines = ["Bank / Account Details:"];
                        if (!empty($data['bank_account_name'])) $bankLines[] = "• Account Name: " . $data['bank_account_name'];
                        if (!empty($data['bank_account_number'])) $bankLines[] = "• Account Number: " . $data['bank_account_number'];
                        if (!empty($data['bank_branch'])) $bankLines[] = "• Branch: " . $data['bank_branch'];
                        if (!empty($data['bank_ifsc'])) $bankLines[] = "• IFSC: " . $data['bank_ifsc'];
                        if (!empty($data['bank_swift'])) $bankLines[] = "• SWIFT: " . $data['bank_swift'];
                        $noteSections[] = implode("\n", $bankLines);
                    }
                    if (!empty($data['terms_conditions'])) {
                        $noteSections[] = "Terms & Conditions:\n" . trim($data['terms_conditions']);
                    }
                    $compiledNotes = implode("\n\n", $noteSections);

                    // 5. Create or Update Invoice
                    $invoice = Invoice::updateOrCreate(
                        [
                            'invoice_number' => $data['invoice_number'],
                            'company_id' => $company->id,
                        ],
                        [
                            'invoice_date' => Carbon::parse($data['invoice_date'] ?? now()),
                            'due_date' => !empty($data['due_date']) ? Carbon::parse($data['due_date']) : Carbon::parse($data['invoice_date'] ?? now())->addDays(15),
                            'reference_number' => $data['po_number'] ?? null,
                            'status' => $isPaid ? Invoice::STATUS_PAID : Invoice::STATUS_SENT,
                            'paid_status' => $isPaid ? Invoice::STATUS_PAID : Invoice::STATUS_UNPAID,
                            'tax_per_item' => 'NO',
                            'discount_per_item' => 'NO',
                            'discount_type' => 'fixed',
                            'discount' => 0,
                            'discount_val' => 0,
                            'base_discount_val' => 0,
                            'sub_total' => $subTotal,
                            'total' => $total,
                            'tax' => $taxTotal,
                            'due_amount' => $dueAmount,
                            'base_sub_total' => $subTotal,
                            'base_total' => $total,
                            'base_tax' => $taxTotal,
                            'base_due_amount' => $dueAmount,
                            'notes' => $compiledNotes,
                            'unique_hash' => PublicToken::make(),
                            'customer_id' => $customer->id,
                            'currency_id' => $currency ? $currency->id : 1,
                            'user_id' => $user ? $user->id : 1,
                            'creator_id' => $user ? $user->id : 1,
                            'template_name' => 'invoice1',
                            'exchange_rate' => 1.0,
                        ]
                    );

                    // 6. Line Items
                    $invoice->items()->delete();
                    $items = $data['items'] ?? [];
                    if (empty($items)) {
                        $items[] = [
                            'name' => 'Consulting Services',
                            'description' => 'Professional Services',
                            'qty' => 1,
                            'rate' => $data['sub_total'] ?? 0,
                            'amount' => $data['sub_total'] ?? 0,
                        ];
                    }

                    foreach ($items as $itemData) {
                        $qty = (float) ($itemData['qty'] ?? 1);
                        $rate = (int) round(($itemData['rate'] ?? 0) * 100);
                        $itemAmount = (int) round(($itemData['amount'] ?? ($qty * ($itemData['rate'] ?? 0))) * 100);
                        
                        $description = $itemData['description'] ?? '';
                        if (!empty($itemData['hsn_sac']) && !str_contains($description, (string) $itemData['hsn_sac'])) {
                            $description = trim($description . " (HSN/SAC: " . $itemData['hsn_sac'] . ")");
                        }

                        $invoice->items()->create([
                            'name' => $itemData['name'] ?? 'Service',
                            'description' => $description,
                            'discount_type' => 'fixed',
                            'discount' => 0,
                            'discount_val' => 0,
                            'tax' => 0,
                            'quantity' => $qty,
                            'price' => $rate,
                            'total' => $itemAmount,
                            'base_price' => $rate,
                            'base_discount_val' => 0,
                            'base_tax' => 0,
                            'base_total' => $itemAmount,
                            'company_id' => $company->id,
                            'exchange_rate' => 1.0,
                        ]);
                    }

                    // 7. Payment record if PAID
                    if ($isPaid) {
                        Payment::updateOrCreate(
                            [
                                'invoice_id' => $invoice->id,
                                'company_id' => $company->id,
                            ],
                            [
                                'payment_date' => Carbon::parse($data['invoice_date'] ?? now()),
                                'payment_number' => 'PAY-' . str_replace(['/', '\\'], '-', $invoice->invoice_number),
                                'amount' => $total,
                                'base_amount' => $total,
                                'customer_id' => $customer->id,
                                'currency_id' => $currency ? $currency->id : 1,
                                'company_id' => $company->id,
                                'user_id' => $user ? $user->id : 1,
                                'payment_method_id' => PaymentMethod::first()->id ?? 1,
                                'notes' => 'Historical payment recorded via import',
                                'unique_hash' => PublicToken::make(),
                                'exchange_rate' => 1.0,
                            ]
                        );
                    }

                    // 8. Link Matching PDF Attachment if present
                    $baseName = pathinfo($mdFile->getFilename(), PATHINFO_FILENAME);
                    $possiblePdfPaths = [
                        $folderPath . DIRECTORY_SEPARATOR . $baseName . '.pdf',
                        $folderPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], ['_', '_'], $data['invoice_number']) . '.pdf',
                        $folderPath . DIRECTORY_SEPARATOR . 'sample-format.pdf',
                    ];

                    foreach ($possiblePdfPaths as $pdfPath) {
                        if (File::exists($pdfPath)) {
                            try {
                                $invoice->clearMediaCollection('invoice');
                                $fileDisk = FileDisk::whereSetAsDefault(true)->first();
                                $media = $invoice->addMedia($pdfPath)
                                    ->preservingOriginal()
                                    ->withCustomProperties(['file_disk_id' => $fileDisk ? $fileDisk->id : 1])
                                    ->toMediaCollection('invoice');
                                break;
                            } catch (\Exception $e) {
                                // PDF linking fallback
                            }
                        }
                    }
                });

                $successCount++;
            } catch (\Exception $e) {
                $errors[] = "File: " . $mdFile->getFilename() . " - Error: " . $e->getMessage();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Successfully imported {$successCount} historical invoice(s) into InvoiceShelf!");

        if (!empty($errors)) {
            $this->warn("Warnings / Errors:");
            foreach ($errors as $error) {
                $this->error(" - " . $error);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Parses the Markdown file containing metadata and content.
     */
    protected function parseMarkdownInvoice(string $content): array
    {
        $data = [
            'invoice_number' => '',
            'invoice_date' => null,
            'due_date' => null,
            'po_number' => null,
            'terms' => 'Net 15',
            'status' => 'DRAFT',
            'currency' => 'USD',
            'client_name' => '',
            'billing_address_1' => '',
            'billing_address_2' => '',
            'billing_city' => '',
            'billing_state' => '',
            'billing_country' => '',
            'sub_total' => 0.0,
            'tax' => 0.0,
            'total' => 0.0,
            'balance_due' => 0.0,
            'notes' => '',
            'items' => [],
        ];

        // 1. Check for YAML frontmatter between --- and ---
        if (preg_match('/^---[\r\n]+([\s\S]*?)[\r\n]+---/', $content, $matches)) {
            $frontmatter = $matches[1];
            $lines = explode("\n", $frontmatter);

            $inItems = false;
            $currentItem = null;

            foreach ($lines as $rawLine) {
                $line = trim($rawLine);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                if (str_starts_with($line, 'items:')) {
                    $inItems = true;
                    continue;
                }

                if ($inItems) {
                    if (str_starts_with($line, '- ')) {
                        if ($currentItem) {
                            $data['items'][] = $currentItem;
                        }
                        $currentItem = [];
                        $line = trim(substr($line, 2));
                    }

                    if ($currentItem !== null && strpos($line, ':') !== false) {
                        [$k, $v] = explode(':', $line, 2);
                        $k = trim($k);
                        $v = trim(trim($v), '"\'');
                        $currentItem[$k] = is_numeric($v) ? (float) $v : $v;
                        continue;
                    }

                    if (!str_starts_with($rawLine, ' ') && !str_starts_with($rawLine, "\t") && !str_starts_with($line, '-')) {
                        if ($currentItem) {
                            $data['items'][] = $currentItem;
                            $currentItem = null;
                        }
                        $inItems = false;
                    }
                }

                if (!$inItems && strpos($line, ':') !== false) {
                    [$key, $val] = explode(':', $line, 2);
                    $key = trim($key);
                    $val = trim(trim($val), '"\'');
                    $val = str_replace('\n', "\n", $val);
                    if (is_numeric($val)) {
                        $val = (float) $val;
                    }
                    $data[$key] = $val;
                }
            }

            if ($currentItem) {
                $data['items'][] = $currentItem;
            }
        }

        // 2. Fallback Regex Parsing from Markdown body if fields missing
        if (empty($data['invoice_number']) && preg_match('/Invoice Number[:\*#\s]+([A-Za-z0-9\/\-_]+)/i', $content, $m)) {
            $data['invoice_number'] = trim($m[1]);
        }
        if (empty($data['invoice_date']) && preg_match('/Invoice Date[:\*#\s]+([0-9\/\-\.]+)/i', $content, $m)) {
            $data['invoice_date'] = trim($m[1]);
        }
        if (empty($data['due_date']) && preg_match('/Due Date[:\*#\s]+([0-9\/\-\.]+)/i', $content, $m)) {
            $data['due_date'] = trim($m[1]);
        }
        if (empty($data['po_number']) && preg_match('/P\.?O\.?#?[:\*#\s]+([A-Za-z0-9\/\-_]+)/i', $content, $m)) {
            $data['po_number'] = trim($m[1]);
        }
        if (empty($data['client_name']) && preg_match('/### Bill To[\r\n]+\*\*([^\*]+)\*\*/i', $content, $m)) {
            $data['client_name'] = trim($m[1]);
        }
        if (empty($data['total']) && preg_match('/Total[:\*#\s]+[\$€£₹]?\s*([0-9,]+\.?[0-9]*)/i', $content, $m)) {
            $data['total'] = (float) str_replace(',', '', $m[1]);
        }

        return $data;
    }
}
