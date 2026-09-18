<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\CliCommands;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RepairOptionParamsCommand extends AbstractCommand
{
    protected static $defaultName = 'j2commerce:repair:optionparams';

    /**
     * Layers to peel before giving up. Well past anything observed; a value that
     * still decodes to a string after this many rounds is treated as unrecoverable.
     */
    private const MAX_DEPTH = 64;

    protected function configure(): void
    {
        $this->setDescription('Repair #__j2commerce_options rows whose option_params was encoded more than once');
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Write the repairs. Without it the command only reports.');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        $db    = Factory::getContainer()->get(DatabaseInterface::class);

        $io->title('J2Commerce: option_params repair');

        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_option_id', 'option_name', 'type', 'option_params']))
            ->from($db->quoteName('#__j2commerce_options'))
            ->where($db->quoteName('option_params') . ' IS NOT NULL')
            ->where($db->quoteName('option_params') . ' != ' . $db->quote(''));

        $rows = $db->setQuery($query)->loadObjectList() ?: [];

        $repairable    = [];
        $unrecoverable = [];

        foreach ($rows as $row) {
            $raw = (string) $row->option_params;

            if (trim($raw) === '' || trim($raw)[0] !== '"') {
                continue;
            }

            [$value, $layers] = $this->peel($raw);

            $decoded = json_decode($value, true);

            if (\is_array($decoded)) {
                $repairable[] = [$row, $value, $layers];
            } else {
                $unrecoverable[] = [$row, $layers, \strlen($raw)];
            }
        }

        if (!$repairable && !$unrecoverable) {
            $io->success(\sprintf('Scanned %d row(s) with a value. Nothing to repair.', \count($rows)));

            return 0;
        }

        if ($repairable) {
            $io->section(\sprintf('%d row(s) can be decoded', \count($repairable)));
            $io->table(
                ['ID', 'Type', 'Option', 'Layers', 'Repaired value'],
                array_map(
                    static fn (array $r): array => [
                        $r[0]->j2commerce_option_id,
                        $r[0]->type,
                        mb_strimwidth((string) $r[0]->option_name, 0, 28, '…'),
                        $r[2],
                        mb_strimwidth($r[1], 0, 46, '…'),
                    ],
                    $repairable
                )
            );
        }

        if ($unrecoverable) {
            $io->section(\sprintf('%d row(s) cannot be decoded', \count($unrecoverable)));
            $io->text('Re-encoding grew these past the column limit, so the tail is truncated mid-escape.');
            $io->table(
                ['ID', 'Type', 'Option', 'Layers peeled', 'Length'],
                array_map(
                    static fn (array $r): array => [
                        $r[0]->j2commerce_option_id,
                        $r[0]->type,
                        mb_strimwidth((string) $r[0]->option_name, 0, 28, '…'),
                        $r[1],
                        $r[2],
                    ],
                    $unrecoverable
                )
            );
            $io->text('These will be reset to {} — only the four keys the component reads are ever stored here.');
        }

        if (!$apply) {
            $io->note('Dry run. Re-run with --apply to write these changes.');

            return 0;
        }

        $written = 0;

        foreach ([...array_map(static fn (array $r): array => [$r[0], $r[1]], $repairable),
                  ...array_map(static fn (array $r): array => [$r[0], '{}'], $unrecoverable)] as [$row, $value]) {
            $id = (int) $row->j2commerce_option_id;

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_options'))
                ->set($db->quoteName('option_params') . ' = :params')
                ->where($db->quoteName('j2commerce_option_id') . ' = :id')
                ->bind(':params', $value)
                ->bind(':id', $id, ParameterType::INTEGER);

            $db->setQuery($update)->execute();
            $written++;
        }

        $io->success(\sprintf('Repaired %d row(s).', $written));

        return 0;
    }

    /**
     * Peel JSON-string layers until the value is no longer a JSON string.
     *
     * @return array{0: string, 1: int} The innermost value and how many layers came off.
     */
    private function peel(string $value): array
    {
        for ($layers = 0; $layers < self::MAX_DEPTH; $layers++) {
            $trimmed = trim($value);

            if ($trimmed === '' || $trimmed[0] !== '"') {
                return [$value, $layers];
            }

            $decoded = json_decode($trimmed);

            if (!\is_string($decoded)) {
                return [$value, $layers];
            }

            $value = $decoded;
        }

        return [$value, $layers];
    }
}
