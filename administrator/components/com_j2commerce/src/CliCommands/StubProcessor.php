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

class StubProcessor
{
    private array $replacements = [];

    public function setReplacement(string $key, string $value): void
    {
        $this->replacements['{{' . $key . '}}'] = $value;
    }

    public function setReplacements(array $replacements): void
    {
        foreach ($replacements as $key => $value) {
            $this->setReplacement($key, $value);
        }
    }

    public function process(string $content): string
    {
        // Process conditional blocks first
        $content = $this->processConditionals($content);

        // Replace simple placeholders
        return str_replace(array_keys($this->replacements), array_values($this->replacements), $content);
    }

    private function processConditionals(string $content): string
    {
        // Iteratively resolve innermost conditional blocks until none remain.
        // Each pass resolves blocks that contain no nested {{#if}}, so this
        // naturally handles the same key appearing multiple times in the file
        // (with or without {{else}}).
        $maxIterations = 50;

        for ($i = 0; $i < $maxIterations; $i++) {
            $previous = $content;

            // Match innermost {{#if key}}...{{else}}...{{/if}} (no nested {{#if inside)
            $content = preg_replace_callback(
                '/\{\{#if\s+(\w+)\}\}((?:(?!\{\{#if\b).)*?)\{\{else\}\}((?:(?!\{\{#if\b).)*?)\{\{\/if\}\}/s',
                function (array $m) {
                    $conditionKey = $m[1];
                    $placeholder  = '{{' . $conditionKey . '}}';

                    return isset($this->replacements[$placeholder]) && !empty($this->replacements[$placeholder])
                        ? $m[2]
                        : $m[3];
                },
                $content
            );

            // Match innermost {{#if key}}...{{/if}} without {{else}}
            $content = preg_replace_callback(
                '/\{\{#if\s+(\w+)\}\}((?:(?!\{\{#if\b).)*?)\{\{\/if\}\}/s',
                function (array $m) {
                    $conditionKey = $m[1];
                    $placeholder  = '{{' . $conditionKey . '}}';

                    return isset($this->replacements[$placeholder]) && !empty($this->replacements[$placeholder])
                        ? $m[2]
                        : '';
                },
                $content
            );

            if ($content === $previous) {
                break;
            }
        }

        return $content;
    }

    public function loadStub(string $type, string $file): string
    {
        $path = JPATH_ADMINISTRATOR . '/components/com_j2commerce/stubs/' . $type . '/' . $file . '.stub';

        if (!file_exists($path)) {
            throw new \RuntimeException(\sprintf('Stub file not found: %s', $path));
        }

        return file_get_contents($path);
    }

    public function processStub(string $type, string $file): string
    {
        $content = $this->loadStub($type, $file);
        return $this->process($content);
    }
}
