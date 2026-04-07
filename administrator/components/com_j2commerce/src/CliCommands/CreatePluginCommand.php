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

use Joomla\Console\Command\AbstractCommand;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreatePluginCommand extends AbstractCommand
{
    protected static $defaultName = 'j2commerce:create:plugin';

    private const ALLOWED_TYPES = ['payment', 'shipping'];

    protected function configure(): void
    {
        $this->setDescription('Create a new J2Commerce plugin from stubs');
        $this->addArgument('type', InputArgument::REQUIRED, 'Plugin type: payment or shipping');
        $this->addArgument('name', InputArgument::REQUIRED, 'Plugin name (lowercase, underscores)');
        $this->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Output directory (default: plugins/j2commerce/)');
        $this->addOption('install', null, InputOption::VALUE_NONE, 'Install after creation');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing files');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $type = strtolower($input->getArgument('type'));
        $name = strtolower($input->getArgument('name'));

        // Validate type
        if (!\in_array($type, self::ALLOWED_TYPES)) {
            $io->error(\sprintf('Invalid type "%s". Allowed types: %s', $type, implode(', ', self::ALLOWED_TYPES)));
            return 1;
        }

        // Validate name format
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $io->error('Name must be lowercase letters, numbers, and underscores. Must start with a letter.');
            return 2;
        }

        // Collect options (use defaults if non-interactive mode)
        $options = !$input->isInteractive()
            ? $this->getDefaultOptions($type, $name)
            : $this->collectOptions($io, $type, $name);

        // Get output path — default to plugins/j2commerce/ (overridable via --path)
        $path  = $input->getOption('path') ?? JPATH_PLUGINS . '/j2commerce';
        $force = $input->getOption('force');

        // Generate plugin
        try {
            $scaffolder = new PluginScaffolder($type, $name, $options);
            $files      = $scaffolder->generate($path, $force);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return 4;
        }

        $element = $type . '_' . $name;
        $io->success(\sprintf('Created %s plugin: %s', $type, $element));
        $io->listing(array_map(fn ($f) => basename(\dirname($f)) . '/' . basename($f), $files));

        // Install if requested
        if ($input->getOption('install')) {
            $pluginPath     = rtrim($path, '/\\') . '/' . $element;
            $destPath       = JPATH_PLUGINS . '/j2commerce/' . $element;
            $alreadyInPlace = is_dir($pluginPath) && is_dir($destPath)
                && realpath($pluginPath) === realpath($destPath);

            if ($alreadyInPlace) {
                $io->success('Plugin already in plugins/j2commerce/. Run "Discover" in Extension Manager.');
            } else {
                $result = $this->installPlugin($pluginPath, $output);

                if ($result === 0) {
                    $io->success('Plugin installed successfully. Run "Discover" in Extension Manager if needed.');
                } else {
                    $io->warning('Plugin created but installation failed. Install manually via Extension Manager.');
                }
            }
        }

        return 0;
    }

    private function collectOptions(SymfonyStyle $io, string $type, string $name): array
    {
        $io->title(\sprintf('Creating %s plugin', ucfirst($type)));
        $io->text('Answer the following questions to customize your plugin:');
        $io->newLine();

        $displayName = $io->ask('Display name (human-readable)', $this->guessDisplayName($name));

        $options = ['display_name' => $displayName];

        if ($type === 'payment') {
            $options['sandbox']             = $io->confirm('Include sandbox mode support?', true);
            $options['sandbox_credentials'] = $options['sandbox']
                ? $io->confirm('Include sandbox credential fields?', true)
                : false;
            $options['webhook']         = $io->confirm('Include webhook support?', false);
            $options['surcharge']       = $io->confirm('Include surcharge support?', true);
            $options['geozone']         = $io->confirm('Include geozone restriction?', true);
            $options['minmax_subtotal'] = $io->confirm('Include min/max subtotal limits?', false);
            $options['debug']           = $io->confirm('Include debug logging?', true);
        }

        if ($type === 'shipping') {
            $options['api_credentials']   = $io->confirm('Include API credentials?', true);
            $options['sandbox']           = $io->confirm('Include sandbox mode support?', true);
            $options['surcharge']         = $io->confirm('Include surcharge support?', true);
            $options['geozone']           = $io->confirm('Include geozone restriction?', true);
            $options['shipping_tax']      = $io->confirm('Include shipping tax (tax profile field)?', true);
            $options['custom_rate_table'] = $io->confirm('Include custom rate table?', false);
            $options['debug']             = $io->confirm('Include debug logging?', true);
        }

        return $options;
    }

    private function getDefaultOptions(string $type, string $name): array
    {
        $base = ['display_name' => $this->guessDisplayName($name)];

        return match ($type) {
            'payment' => $base + [
                'sandbox'             => true,
                'sandbox_credentials' => true,
                'webhook'             => false,
                'surcharge'           => true,
                'geozone'             => true,
                'minmax_subtotal'     => false,
                'debug'               => true,
            ],
            default => $base + [
                'api_credentials'   => true,
                'sandbox'           => true,
                'surcharge'         => true,
                'geozone'           => true,
                'shipping_tax'      => true,
                'custom_rate_table' => false,
                'debug'             => true,
            ],
        };
    }

    private function guessDisplayName(string $name): string
    {
        return ucwords(str_replace('_', ' ', $name));
    }

    private function installPlugin(string $pluginPath, OutputInterface $output): int
    {
        // Copy to plugins/j2commerce/
        $destPath = JPATH_PLUGINS . '/j2commerce/' . basename($pluginPath);

        if (is_dir($destPath)) {
            $output->writeln('<comment>Plugin directory already exists in plugins folder.</comment>');
            return 1;
        }

        // Copy directory
        $this->recursiveCopy($pluginPath, $destPath);

        return 0;
    }

    private function recursiveCopy(string $src, string $dst): void
    {
        Folder::create($dst);

        foreach (Folder::files($src, '.', false, true) as $file) {
            File::copy($file, $dst . '/' . basename($file));
        }

        foreach (Folder::folders($src, '.', false, true) as $folder) {
            $this->recursiveCopy($folder, $dst . '/' . basename($folder));
        }
    }
}
