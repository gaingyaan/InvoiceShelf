<?php

use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\CustomField;
use App\Models\Setting;
use App\Support\Setup\InstallUtils;
use Illuminate\Support\Str;

/**
 * Get company setting
 *
 * @return string
 */
function get_company_setting($key, $company_id)
{
    if (! InstallUtils::isDbCreated()) {
        return null;
    }

    return CompanySetting::getSetting($key, $company_id);
}

/**
 * Get app setting
 *
 * @param  $company_id
 * @return string
 */
function get_app_setting($key)
{
    if (! InstallUtils::isDbCreated()) {
        return null;
    }

    return Setting::getSetting($key);
}

/**
 * Get page title
 *
 * @return string
 */
function get_page_title($company_id)
{
    if (! InstallUtils::isDbCreated()) {
        return null;
    }

    $routeName = Route::currentRouteName();

    $defaultPageTitle = 'InvoiceShelf - Self Hosted Invoicing Platform';

    if ($routeName === 'customer.dashboard') {
        $pageTitle = CompanySetting::getSetting('customer_portal_page_title', $company_id);

        return $pageTitle ? $pageTitle : $defaultPageTitle;
    }

    $pageTitle = Setting::getSetting('admin_page_title');

    return $pageTitle ? $pageTitle : $defaultPageTitle;
}

/**
 * Set Active Path
 *
 * @param  string  $active
 * @return string
 */
function set_active($path, $active = 'active')
{
    return call_user_func_array('Request::is', (array) $path) ? $active : '';
}

/**
 * @return mixed
 */
function is_url($path)
{
    return call_user_func_array('Request::is', (array) $path);
}

/**
 * @return string
 */
function getCustomFieldValueKey(string $type)
{
    switch ($type) {
        case 'Input':
            return 'string_answer';

        case 'TextArea':
            return 'string_answer';

        case 'Phone':
            return 'number_answer';

        case 'Url':
            return 'string_answer';

        case 'Number':
            return 'number_answer';

        case 'Dropdown':
            return 'string_answer';

        case 'Switch':
            return 'boolean_answer';

        case 'Date':
            return 'date_answer';

        case 'Time':
            return 'time_answer';

        case 'DateTime':
            return 'date_time_answer';

        default:
            return 'string_answer';
    }
}

/**
 * Format an amount given in cents as currency markup for PDF templates.
 *
 * The magnitude is formatted first and a single minus sign is prefixed to the
 * whole assembled string, so a negative amount reads "-$24,738.00" rather than
 * "$-24,738.00" and the symbol stays glued to the digits in both symbol
 * positions. The symbol is wrapped in a DejaVu Sans span so it renders even
 * when the active font has no glyph for it.
 *
 * @param  int|float|string|null  $money  Amount in cents.
 * @param  Currency|null  $currency  Defaults to the company currency setting.
 * @return string
 */
function format_money_pdf($money, $currency = null)
{
    $money = $money / 100;

    if (! $currency) {
        $currency = Currency::findOrFail(CompanySetting::getSetting('currency', 1));
    }

    $format_money = number_format(
        abs($money),
        $currency->precision,
        $currency->decimal_separator,
        $currency->thousand_separator
    );

    $symbol = '<span style="font-family: DejaVu Sans;">'.$currency->symbol.'</span>';

    $currency_with_symbol = $currency->swap_currency_symbol
        ? $format_money.$symbol
        : $symbol.$format_money;

    // The sign is decided on the formatted digits, not on the raw input, so an
    // amount that rounds away at the currency's precision (a stray cent on a
    // zero-precision currency) renders as zero instead of "-0".
    $is_negative = $money < 0 && preg_match('/[1-9]/', $format_money) === 1;

    return $is_negative ? '-'.$currency_with_symbol : $currency_with_symbol;
}

/**
 * @param  $string
 * @return string
 */
function clean_slug($model, $title, $id = 0)
{
    // Normalize the title
    $slug = Str::upper('CUSTOM_'.$model.'_'.Str::slug($title, '_'));

    // Get any that could possibly be related.
    // This cuts the queries down by doing it once.
    $allSlugs = getRelatedSlugs($model, $slug, $id);

    // If we haven't used it before then we are all good.
    if (! $allSlugs->contains('slug', $slug)) {
        return $slug;
    }

    // Just append numbers like a savage until we find not used.
    for ($i = 1; $i <= 10; $i++) {
        $newSlug = $slug.'_'.$i;
        if (! $allSlugs->contains('slug', $newSlug)) {
            return $newSlug;
        }
    }

    throw new Exception('Can not create a unique slug');
}

function getRelatedSlugs($type, $slug, $id = 0)
{
    return CustomField::select('slug')->where('slug', 'like', $slug.'%')
        ->where('model_type', $type)
        ->where('id', '<>', $id)
        ->get();
}

function respondJson($error, $message)
{
    return response()->json([
        'error' => $error,
        'message' => $message,
    ], 422);
}
