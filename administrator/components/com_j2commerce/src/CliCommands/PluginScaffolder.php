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

use Joomla\Filesystem\Folder;

class PluginScaffolder
{
    private string $type;
    private string $name;
    private array $options;
    private StubProcessor $processor;

    private const PAYMENT_FILES = [
        'manifest.xml'         => '{{element}}.xml',
        'provider.php'         => 'services/provider.php',
        'Extension.php'        => 'src/Extension/Payment{{Name}}.php',
        'language.ini'         => 'language/en-GB/plg_j2commerce_payment_{{name}}.ini',
        'language.sys.ini'     => 'language/en-GB/plg_j2commerce_payment_{{name}}.sys.ini',
        'tmpl/prepayment.php'  => 'tmpl/prepayment.php',
        'tmpl/postpayment.php' => 'tmpl/postpayment.php',
        'tmpl/message.php'     => 'tmpl/message.php',
    ];

    private const SHIPPING_FILES = [
        'manifest.xml'             => '{{element}}.xml',
        'provider.php'             => 'services/provider.php',
        'Extension.php'            => 'src/Extension/Shipping{{Name}}.php',
        'language.ini'             => 'language/en-GB/plg_j2commerce_shipping_{{name}}.ini',
        'language.sys.ini'         => 'language/en-GB/plg_j2commerce_shipping_{{name}}.sys.ini',
        'forms/shippingmethod.xml' => 'forms/shippingmethod.xml',
        'tmpl/method.php'          => 'tmpl/method.php',
    ];

    public function __construct(string $type, string $name, array $options = [])
    {
        $this->type      = $type;
        $this->name      = $name;
        $this->options   = $options;
        $this->processor = new StubProcessor();

        $this->setupReplacements();
    }

    private function setupReplacements(): void
    {
        $name        = $this->name;
        $className   = $this->toPascalCase($name);
        $displayName = !empty($this->options['display_name']) && \is_string($this->options['display_name'])
            ? $this->options['display_name']
            : $this->toDisplayName($name);
        $element     = $this->type . '_' . $name;
        $namespace   = 'J2Commerce\\Plugin\\J2Commerce\\' . ucfirst($this->type) . $className;

        $this->processor->setReplacements([
            'name'         => $name,
            'Name'         => $className,
            'NAME'         => strtoupper($name),
            'display_name' => $displayName,
            'type'         => $this->type,
            'Type'         => ucfirst($this->type),
            'element'      => $element,
            'namespace'    => $namespace,
            'year'         => '2024-2026',
            'year_full'    => date('Y'),
            'month'        => date('F'),
        ]);

        // Set conditional flags (skip non-boolean keys already handled above)
        foreach ($this->options as $key => $value) {
            if ($key === 'display_name') {
                continue;
            }
            $this->processor->setReplacement($key, $value ? '1' : '');
        }
    }

    private function toPascalCase(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    private function toDisplayName(string $name): string
    {
        return ucwords(str_replace('_', ' ', $name));
    }

    public function generate(string $outputPath, bool $force = false): array
    {
        $files      = $this->type === 'payment' ? self::PAYMENT_FILES : self::SHIPPING_FILES;
        $created    = [];
        $element    = $this->type . '_' . $this->name;
        $pluginPath = rtrim($outputPath, '/\\') . '/' . $element;

        // Create directory structure
        if (!is_dir($pluginPath)) {
            Folder::create($pluginPath);
        }

        foreach ($files as $stubFile => $outputFile) {
            // Replace placeholders in output filename
            $outputFile = str_replace(
                ['{{element}}', '{{name}}', '{{Name}}'],
                [$this->type . '_' . $this->name, $this->name, $this->toPascalCase($this->name)],
                $outputFile
            );

            $fullPath = $pluginPath . '/' . $outputFile;
            $fullDir  = \dirname($fullPath);

            if (file_exists($fullPath) && !$force) {
                throw new \RuntimeException(\sprintf('File already exists: %s (use --force to overwrite)', $fullPath));
            }

            if (!is_dir($fullDir)) {
                Folder::create($fullDir);
            }

            $content = $this->processor->processStub($this->type, $stubFile);

            if (file_put_contents($fullPath, $content) === false) {
                throw new \RuntimeException(\sprintf('Failed to write file: %s', $fullPath));
            }

            $created[] = $fullPath;
        }

        return $created;
    }
}
