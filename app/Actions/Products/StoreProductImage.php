<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Support\ImageResizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Mahsulot (model) rasmi: aksesuarlar va o'z rasmi hali yo'q telefonlar uchun zaxira rasm.
 * Eski rasm o'chiriladi.
 */
class StoreProductImage
{
    public const DISK = 'public';

    public function handle(Product $product, UploadedFile|string $source): Product
    {
        $file = $source instanceof UploadedFile ? $source->getRealPath() : $source;
        $path = sprintf('products/%d/%d-%s.jpg', $product->tenant_id, $product->id, Str::lower(Str::random(8)));

        Storage::disk(self::DISK)->put($path, ImageResizer::squareJpeg((string) $file));

        $this->delete($product);
        $product->forceFill(['image_path' => $path])->save();

        return $product;
    }

    public function delete(Product $product): void
    {
        if ($product->image_path !== null) {
            Storage::disk(self::DISK)->delete($product->image_path);
            $product->forceFill(['image_path' => null])->save();
        }
    }
}
