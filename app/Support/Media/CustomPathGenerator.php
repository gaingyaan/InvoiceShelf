<?php

namespace App\Support\Media;

use App\Platform\Persistence\ModelIdentityMap;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class CustomPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media).'/conversations/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media).'/responsive-images/';
    }

    /*
     * Get a unique base path for the given media.
     */
    protected function getBasePath(Media $media): string
    {
        $folderName = null;

        if ($media->model_type === ModelIdentityMap::INVOICE_ALIAS) {
            $folderName = 'Invoices';
        } elseif ($media->model_type === ModelIdentityMap::ESTIMATE_ALIAS) {
            $folderName = 'Estimates';
        } elseif ($media->model_type === ModelIdentityMap::PAYMENT_ALIAS) {
            $folderName = 'Payments';
        } else {
            $folderName = $media->getKey();
        }

        return $folderName;
    }
}
