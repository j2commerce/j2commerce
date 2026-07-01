<?php

declare(strict_types=1);

namespace J2Commerce\Tests\Integration\Helper;

use J2Commerce\Component\J2commerce\Administrator\Helper\WeightHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Integration test: the same conversion logic, but driven through the real
 * #__j2commerce_weights table and Joomla's database container.
 *
 * Requires JOOMLA_TESTS_BASE to point at a Joomla 6 install with J2Commerce
 * installed. Skipped automatically when that env var is absent, so the suite
 * still runs cleanly in a unit-only context.
 */
final class WeightHelperDatabaseTest extends TestCase
{
    private ?DatabaseInterface $db = null;

    protected function setUp(): void
    {
        if (!getenv('JOOMLA_TESTS_BASE')) {
            self::markTestSkipped('Set JOOMLA_TESTS_BASE to run integration tests.');
        }

        $this->db = Factory::getContainer()->get(DatabaseInterface::class);

        // Seed a known, isolated set of weight classes.
        $this->db->setQuery('DELETE FROM ' . $this->db->quoteName('#__j2commerce_weights'))->execute();
        $rows = [
            "(1, 'Kilogram', 'kg', 1.0, 2, 1, 1)",
            "(2, 'Gram', 'g', 1000.0, 0, 1, 2)",
        ];
        $cols = $this->db->quoteName(
            ['j2commerce_weight_id', 'weight_title', 'weight_unit', 'weight_value', 'num_decimals', 'enabled', 'ordering']
        );
        $this->db->setQuery(
            'INSERT INTO ' . $this->db->quoteName('#__j2commerce_weights')
            . ' (' . implode(',', $cols) . ') VALUES ' . implode(',', $rows)
        )->execute();

        WeightHelper::reset(); // force a reload from the freshly seeded table
    }

    protected function tearDown(): void
    {
        WeightHelper::reset();
    }

    public function testConvertReadsFromDatabase(): void
    {
        self::assertEqualsWithDelta(2500.0, WeightHelper::convert(2.5, 1, 2), 0.0001);
    }
}
