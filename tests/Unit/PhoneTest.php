<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null}>
     */
    public static function numbers(): array
    {
        return [
            'full with plus' => ['+998901234567', '998901234567'],
            'formatted' => ['+998 (90) 123-45-67', '998901234567'],
            'without country code' => ['90 123 45 67', '998901234567'],
            'too short' => ['12345', null],
            'wrong country' => ['+7 900 123 45 67', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_normalize(string $input, ?string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($input));
    }

    public function test_format(): void
    {
        $this->assertSame('+998 90 123 45 67', Phone::format('998901234567'));
    }
}
