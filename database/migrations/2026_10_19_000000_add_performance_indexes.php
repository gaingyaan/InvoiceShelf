<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Core transaction table indexes
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (! Schema::hasIndex('invoices', 'inv_company_date_idx')) {
                    $table->index(['company_id', 'invoice_date'], 'inv_company_date_idx');
                }
                if (! Schema::hasIndex('invoices', 'inv_company_status_idx')) {
                    $table->index(['company_id', 'status'], 'inv_company_status_idx');
                }
                if (! Schema::hasIndex('invoices', 'inv_company_paid_status_idx')) {
                    $table->index(['company_id', 'paid_status'], 'inv_company_paid_status_idx');
                }
                if (! Schema::hasIndex('invoices', 'inv_unique_hash_idx')) {
                    $table->index('unique_hash', 'inv_unique_hash_idx');
                }
                if (! Schema::hasIndex('invoices', 'inv_company_due_date_idx')) {
                    $table->index(['company_id', 'due_date'], 'inv_company_due_date_idx');
                }
            });
        }

        if (Schema::hasTable('estimates')) {
            Schema::table('estimates', function (Blueprint $table) {
                if (! Schema::hasIndex('estimates', 'est_company_date_idx')) {
                    $table->index(['company_id', 'estimate_date'], 'est_company_date_idx');
                }
                if (! Schema::hasIndex('estimates', 'est_company_status_idx')) {
                    $table->index(['company_id', 'status'], 'est_company_status_idx');
                }
                if (! Schema::hasIndex('estimates', 'est_unique_hash_idx')) {
                    $table->index('unique_hash', 'est_unique_hash_idx');
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (! Schema::hasIndex('payments', 'pay_company_date_idx')) {
                    $table->index(['company_id', 'payment_date'], 'pay_company_date_idx');
                }
                if (! Schema::hasIndex('payments', 'pay_unique_hash_idx')) {
                    $table->index('unique_hash', 'pay_unique_hash_idx');
                }
            });
        }

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (! Schema::hasIndex('expenses', 'exp_company_date_idx')) {
                    $table->index(['company_id', 'expense_date'], 'exp_company_date_idx');
                }
            });
        }

        // Recurring invoices scheduling index
        if (Schema::hasTable('recurring_invoices')) {
            Schema::table('recurring_invoices', function (Blueprint $table) {
                if (! Schema::hasIndex('recurring_invoices', 'rec_status_next_idx')) {
                    $table->index(['status', 'next_invoice_at'], 'rec_status_next_idx');
                }
            });
        }

        // Polymorphic indexes
        if (Schema::hasTable('custom_field_values')) {
            Schema::table('custom_field_values', function (Blueprint $table) {
                if (! Schema::hasIndex('custom_field_values', 'cfv_valuable_idx')) {
                    $table->index(['custom_field_valuable_type', 'custom_field_valuable_id'], 'cfv_valuable_idx');
                }
            });
        }

        // Settings lookup indexes
        if (Schema::hasTable('company_settings')) {
            Schema::table('company_settings', function (Blueprint $table) {
                if (! Schema::hasIndex('company_settings', 'cs_company_option_idx')) {
                    $table->index(['company_id', 'option'], 'cs_company_option_idx');
                }
            });
        }

        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                if (! Schema::hasIndex('settings', 'settings_option_idx')) {
                    $table->index('option', 'settings_option_idx');
                }
            });
        }

        // Customer email lookup for portal login
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (! Schema::hasIndex('customers', 'cust_company_email_idx')) {
                    $table->index(['company_id', 'email'], 'cust_company_email_idx');
                }
            });
        }

        // Email logs polymorphic index
        if (Schema::hasTable('email_logs')) {
            Schema::table('email_logs', function (Blueprint $table) {
                if (! Schema::hasIndex('email_logs', 'email_logs_mailable_idx')) {
                    $table->index(['mailable_type', 'mailable_id'], 'email_logs_mailable_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasIndex('invoices', 'inv_company_date_idx')) {
                    $table->dropIndex('inv_company_date_idx');
                }
                if (Schema::hasIndex('invoices', 'inv_company_status_idx')) {
                    $table->dropIndex('inv_company_status_idx');
                }
                if (Schema::hasIndex('invoices', 'inv_company_paid_status_idx')) {
                    $table->dropIndex('inv_company_paid_status_idx');
                }
                if (Schema::hasIndex('invoices', 'inv_unique_hash_idx')) {
                    $table->dropIndex('inv_unique_hash_idx');
                }
                if (Schema::hasIndex('invoices', 'inv_company_due_date_idx')) {
                    $table->dropIndex('inv_company_due_date_idx');
                }
            });
        }

        if (Schema::hasTable('estimates')) {
            Schema::table('estimates', function (Blueprint $table) {
                if (Schema::hasIndex('estimates', 'est_company_date_idx')) {
                    $table->dropIndex('est_company_date_idx');
                }
                if (Schema::hasIndex('estimates', 'est_company_status_idx')) {
                    $table->dropIndex('est_company_status_idx');
                }
                if (Schema::hasIndex('estimates', 'est_unique_hash_idx')) {
                    $table->dropIndex('est_unique_hash_idx');
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasIndex('payments', 'pay_company_date_idx')) {
                    $table->dropIndex('pay_company_date_idx');
                }
                if (Schema::hasIndex('payments', 'pay_unique_hash_idx')) {
                    $table->dropIndex('pay_unique_hash_idx');
                }
            });
        }

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (Schema::hasIndex('expenses', 'exp_company_date_idx')) {
                    $table->dropIndex('exp_company_date_idx');
                }
            });
        }

        if (Schema::hasTable('recurring_invoices')) {
            Schema::table('recurring_invoices', function (Blueprint $table) {
                if (Schema::hasIndex('recurring_invoices', 'rec_status_next_idx')) {
                    $table->dropIndex('rec_status_next_idx');
                }
            });
        }

        if (Schema::hasTable('custom_field_values')) {
            Schema::table('custom_field_values', function (Blueprint $table) {
                if (Schema::hasIndex('custom_field_values', 'cfv_valuable_idx')) {
                    $table->dropIndex('cfv_valuable_idx');
                }
            });
        }

        if (Schema::hasTable('company_settings')) {
            Schema::table('company_settings', function (Blueprint $table) {
                if (Schema::hasIndex('company_settings', 'cs_company_option_idx')) {
                    $table->dropIndex('cs_company_option_idx');
                }
            });
        }

        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                if (Schema::hasIndex('settings', 'settings_option_idx')) {
                    $table->dropIndex('settings_option_idx');
                }
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (Schema::hasIndex('customers', 'cust_company_email_idx')) {
                    $table->dropIndex('cust_company_email_idx');
                }
            });
        }

        if (Schema::hasTable('email_logs')) {
            Schema::table('email_logs', function (Blueprint $table) {
                if (Schema::hasIndex('email_logs', 'email_logs_mailable_idx')) {
                    $table->dropIndex('email_logs_mailable_idx');
                }
            });
        }
    }
};
