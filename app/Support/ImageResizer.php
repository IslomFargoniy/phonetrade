<?php

namespace App\Support;

use App\Exceptions\BusinessRuleException;
use GdImage;

/**
 * Yuklangan rasmni kvadrat JPEG'ga keltiradi: oq fonga markazlanadi va berilgan o'lchamgacha
 * kichraytiriladi (mobil ilovada tez yuklanishi uchun). EXIF bo'yicha telefon rasmi to'g'rilanadi.
 */
final class ImageResizer
{
    public static function squareJpeg(string $path, int $size = 800): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($path));

        if (! $image instanceof GdImage) {
            throw new BusinessRuleException('IMAGE_INVALID', "Rasmni o'qib bo'lmadi. JPG, PNG yoki WEBP yuklang.", 'photos');
        }

        $image = self::orient($image, $path);
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $size / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $side = max($targetWidth, $targetHeight);

        $canvas = imagecreatetruecolor($side, $side);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled(
            $canvas, $image,
            intdiv($side - $targetWidth, 2), intdiv($side - $targetHeight, 2), 0, 0,
            $targetWidth, $targetHeight, $width, $height,
        );

        ob_start();
        imagejpeg($canvas, null, 85);

        return (string) ob_get_clean();
    }

    private static function orient(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }
}
