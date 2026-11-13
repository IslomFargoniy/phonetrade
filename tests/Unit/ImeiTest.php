<?php

namespace Tests\Unit;

use App\Services\Stock\Imei;
use PHPUnit\Framework\TestCase;

class ImeiTest extends TestCase
{
    public function test_luhn_check(): void
    {
        $this->assertTrue(Imei::isValid('490154203237518'));
        $this->assertFalse(Imei::isValid('490154203237519'));
        $this->assertFalse(Imei::isValid('49015420323751'));
        $this->assertFalse(Imei::isValid('49015420323751a'));
    }

    public function test_normalize_strips_spaces_and_dashes(): void
    {
        $this->assertSame('490154203237518', Imei::normalize(' 49-015420-323751-8 '));
        $this->assertSame('', Imei::normalize(null));
    }
}
