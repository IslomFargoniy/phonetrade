<?php

namespace App\Actions\Stock;

use App\Exceptions\BusinessRuleException;
use App\Models\StockUnit;
use App\Models\StockUnitPhoto;
use App\Support\ImageResizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Telefonning o'z rasmlarini qo'shadi (bir nechta). Birinchi rasm asosiy bo'lib ko'rinadi.
 */
class AddUnitPhotos
{
    public const DISK = 'public';

    public const MAX_PHOTOS = 8;

    /**
     * @param  list<UploadedFile|string>  $files
     * @return list<StockUnitPhoto>
     */
    public function handle(StockUnit $unit, array $files): array
    {
        $existing = $unit->photos()->count();

        if ($existing + count($files) > self::MAX_PHOTOS) {
            throw new BusinessRuleException(
                'PHOTO_LIMIT',
                'Bitta telefonga ko\'pi bilan '.self::MAX_PHOTOS.' ta rasm qo\'shish mumkin.',
                'photos',
            );
        }

        $sort = (int) $unit->photos()->max('sort');
        $created = [];

        foreach ($files as $file) {
            $source = $file instanceof UploadedFile ? $file->getRealPath() : $file;
            $path = sprintf('units/%d/%d-%s.jpg', $unit->tenant_id, $unit->id, Str::lower(Str::random(10)));

            Storage::disk(self::DISK)->put($path, ImageResizer::squareJpeg((string) $source, 1080));

            $created[] = $unit->photos()->create(['path' => $path, 'sort' => ++$sort]);
        }

        return $created;
    }

    public function delete(StockUnitPhoto $photo): void
    {
        Storage::disk(self::DISK)->delete($photo->path);
        $photo->delete();
    }
}
