<?php

namespace App\Platform\Modules\Http\Controllers\Assets;

use App\Platform\Http\Controller;
use App\Platform\Modules\Runtime\ModuleAssetVersion;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvoiceShelf\Modules\Registry as ModuleRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StyleController extends Controller
{
    /**
     * Serve the requested module-registered stylesheet.
     *
     * Modules call \InvoiceShelf\Modules\Registry::registerStyle($name, $path)
     * from their ServiceProvider::boot() to inject custom CSS into the host app.
     *
     * @throws NotFoundHttpException
     */
    public function __invoke(Request $request, string $style): Response
    {
        $path = ModuleRegistry::styleFor($style);

        abort_if($path === null || ! is_file($path), 404);

        $contents = file_get_contents($path);
        abort_if(! is_string($contents), 404);
        $version = ModuleAssetVersion::forContents($contents);
        $cacheControl = is_string($request->query('v')) && hash_equals($version, $request->query('v'))
            ? 'public, max-age=31536000, immutable'
            : 'no-store';

        $response = response(
            $contents,
            200,
            [
                'Content-Type' => 'text/css',
            ]
        )->setLastModified(DateTime::createFromFormat('U', (string) filemtime($path)));

        $response->headers->set('Cache-Control', $cacheControl);

        return $response;
    }
}
