<?php

namespace App\Platform\Pdf\Rendering;

interface PdfDriver
{
    /**
     * @param  array<string, string>  $metadata  Document properties written into
     *                                           the file: Title, Author, Subject,
     *                                           Keywords, Creator. Both drivers
     *                                           accept the same key names.
     * @param  PdfPageSetup|null  $page  Page geometry to render at. Defaults to
     *                                   the configured one; the reports pass
     *                                   PdfPageSetup::forReports() because they
     *                                   carry no inset of their own.
     */
    public function loadView(string $template, array $metadata = [], ?PdfPageSetup $page = null): ResponseStream;
}
