<?php

declare(strict_types=1);

namespace J2Commerce\Tests\Unit\Helper;

use J2Commerce\Component\J2commerce\Administrator\Helper\WeightHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Unit test for J2Commerce's weight conversion logic.
 *
 * WeightHelper normally loads its weight classes from #__j2commerce_weights on
 * first use. We bypass the database entirely by reflecting the private static
 * cache and marking the helper initialised — so convert() runs pure arithmetic.
 * This is the cheapest, fastest layer of the pyramid: real shipped code, zero
 * infrastructure.
 */
#[CoversClass(WeightHelper::class)]
final class WeightHelperTest extends TestCase
{
    protected function setUp(): void
    {
        // weight_value is the ratio relative to the base unit (kg here).
        $weights = new ReflectionProperty(WeightHelper::class, 'weights');
        $weights->setValue(null, [
            1 => ['weight_class_id' => 1, 'title' => 'Kilogram', 'unit' => 'kg', 'value' => 1.0,     'num_decimals' => 2],
            2 => ['weight_class_id' => 2, 'title' => 'Gram',     'unit' => 'g',  'value' => 1000.0,  'num_decimals' => 0],
            3 => ['weight_class_id' => 3, 'title' => 'Pound',    'unit' => 'lb', 'value' => 2.20462, 'num_decimals' => 2],
        ]);

        $initialized = new ReflectionProperty(WeightHelper::class, 'initialized');
        $initialized->setValue(null, true);
    }

    protected function tearDown(): void
    {
        // Clear the static state so tests stay isolated.
        WeightHelper::reset();
    }

    public function testSameClassReturnsValueUnchanged(): void
    {
        self::assertSame(5.0, WeightHelper::convert(5.0, 1, 1));
    }

    public function testUnknownSourceClassFallsBackSafely(): void
    {
        // Missing source class => factor 1.0, never a division-by-zero fatal.
        self::assertSame(2.0, WeightHelper::convert(2.0, 999, 999));
    }

    #[DataProvider('conversions')]
    public function testConvertBetweenClasses(float $value, int $from, int $to, float $expected): void
    {
        self::assertEqualsWithDelta($expected, WeightHelper::convert($value, $from, $to), 0.0001);
    }

    /**
     * @return array<string, array{0: float, 1: int, 2: int, 3: float}>
     */
    public static function conversions(): array
    {
        return [
            'kg to g'  => [1.0,   1, 2, 1000.0],
            'g to kg'  => [500.0, 2, 1, 0.5],
            'kg to lb' => [1.0,   1, 3, 2.20462],
            'lb to kg' => [2.20462, 3, 1, 1.0],
        ];
    }
}
