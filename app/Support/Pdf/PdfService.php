<?php

namespace App\Support\Pdf;

class PdfService
{
    public static function loadView(string $template, array $metadata = [], ?PdfPageSetup $page = null): ResponseStream
    {
        $driver = config('pdf.driver');

        return PdfDriverFactory::create($driver)->loadView($template, $metadata, $page);
    }
}
