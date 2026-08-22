<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

class ImageRegenerationHelper
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    private const ALLOWED_SCOPES = ['thumbs', 'tiny'];

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    public function countTotal(string $scope): int
    {
        $this->assertScope($scope);

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__j2commerce_productimages'));
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    public function processBatch(string $scope, int $offset, int $limit): array
    {
        $this->assertScope($scope);

        $cols             = $this->columnsFor($scope);
        $params           = ComponentHelper::getParams('com_j2commerce');
        $processor        = $this->processorFor($scope, $params);
        [$width, $height] = $this->dimensionsFor($scope, $params);

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName([
                'j2commerce_productimage_id',
                'main_image',
                'main_image_alt',
                'additional_images',
                'additional_images_alt',
                $cols['image_col'],
                $cols['additional_col'],
            ]))
            ->from($this->db->quoteName('#__j2commerce_productimages'))
            ->order($this->db->quoteName('j2commerce_productimage_id') . ' ASC')
            ->setLimit($limit, $offset);
        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList();

        $processed = 0;
        $generated = 0;
        $skipped   = 0;
        $failed    = 0;
        $errors    = [];

        foreach ($rows as $row) {
            $processed++;

            $mainResult = $this->regenerateOne($row->main_image, $scope, $processor, $width, $height);
            $this->tally($mainResult, $generated, $skipped, $failed, $errors);

            $newMainValue = $mainResult['status'] === 'generated'
                ? $mainResult['value']
                : ($row->{$cols['image_col']} ?? '');

            $isObjectShape       = $this->isObjectShape($row->additional_images);
            $sourceItems         = $this->decodeJsonField($row->additional_images);
            $existingDerivatives = $this->decodeJsonField($row->{$cols['additional_col']});

            $newDerivatives = [];

            foreach ($sourceItems as $key => $sourcePath) {
                $itemResult = $this->regenerateOne(\is_string($sourcePath) ? $sourcePath : null, $scope, $processor, $width, $height);
                $this->tally($itemResult, $generated, $skipped, $failed, $errors);

                $newDerivatives[$key] = $itemResult['status'] === 'generated'
                    ? $itemResult['value']
                    : ($existingDerivatives[$key] ?? '');
            }

            $this->updateRow(
                (int) $row->j2commerce_productimage_id,
                $cols,
                $newMainValue,
                (string) ($row->main_image_alt ?? ''),
                $this->encodeJsonField($newDerivatives, $isObjectShape),
                (string) ($row->additional_images_alt ?? '')
            );
        }

        return [
            'processed' => $processed,
            'generated' => $generated,
            'skipped'   => $skipped,
            'failed'    => $failed,
            'errors'    => $errors,
        ];
    }

    private function assertScope(string $scope): void
    {
        if (!\in_array($scope, self::ALLOWED_SCOPES, true)) {
            throw new \InvalidArgumentException('Invalid regeneration scope.');
        }
    }

    /** @return array{image_col: string, alt_col: string, additional_col: string, additional_alt_col: string} */
    private function columnsFor(string $scope): array
    {
        return match ($scope) {
            'thumbs' => [
                'image_col'          => 'thumb_image',
                'alt_col'            => 'thumb_image_alt',
                'additional_col'     => 'additional_thumb_images',
                'additional_alt_col' => 'additional_thumb_images_alt',
            ],
            'tiny' => [
                'image_col'          => 'tiny_image',
                'alt_col'            => 'tiny_image_alt',
                'additional_col'     => 'additional_tiny_images',
                'additional_alt_col' => 'additional_tiny_images_alt',
            ],
        };
    }

    /** @return array{0: int, 1: int} */
    private function dimensionsFor(string $scope, Registry $params): array
    {
        return $scope === 'thumbs'
            ? [(int) $params->get('image_thumb_width', 300), (int) $params->get('image_thumb_height', 300)]
            : [(int) $params->get('image_tiny_width', 100), (int) $params->get('image_tiny_height', 100)];
    }

    private function processorFor(string $scope, Registry $params): ImageProcessorHelper
    {
        $qualityKey = $scope === 'thumbs' ? 'image_thumb_quality' : 'image_tiny_quality';

        return new ImageProcessorHelper(
            (int) $params->get('image_webp_quality', 80),
            (int) $params->get($qualityKey, 80)
        );
    }

    /** @return array{status: string, value: ?string, error: ?string} */
    private function regenerateOne(?string $rawSource, string $scope, ImageProcessorHelper $processor, int $width, int $height): array
    {
        if ($rawSource === null || trim($rawSource) === '') {
            return ['status' => 'skipped', 'value' => null, 'error' => null];
        }

        $clean     = ltrim($this->stripSiteRoot($this->stripJoomlaImageMeta(trim($rawSource))), '/');
        $extension = strtolower(pathinfo($clean, PATHINFO_EXTENSION));

        if (!\in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return ['status' => 'skipped', 'value' => null, 'error' => $clean . ': unsupported file type'];
        }

        if (\in_array(basename(\dirname($clean)), ['thumbs', 'tiny'], true)) {
            return ['status' => 'skipped', 'value' => null, 'error' => $clean . ': already a derivative image'];
        }

        $absolute = $this->resolveAndConfine($clean);

        if ($absolute === null) {
            return ['status' => 'skipped', 'value' => null, 'error' => $clean . ': source file not found'];
        }

        $targetDir = \dirname($absolute) . '/' . $scope . '/';

        if (!is_dir($targetDir)) {
            Folder::create($targetDir);
        }

        $webpBasename = File::stripExt(basename($absolute)) . '.webp';
        $targetPath   = $targetDir . $webpBasename;

        if (!$processor->createThumbnail($absolute, $targetPath, $width, $height) || !is_file($targetPath)) {
            Log::add('J2Commerce image regeneration failed for ' . $clean, Log::WARNING, 'j2commerce');

            return ['status' => 'failed', 'value' => null, 'error' => $clean . ': regeneration failed'];
        }

        $dimensions   = @getimagesize($targetPath);
        $actualWidth  = $dimensions ? (int) $dimensions[0] : $width;
        $actualHeight = $dimensions ? (int) $dimensions[1] : $height;

        return [
            'status' => 'generated',
            'value'  => $this->buildStoredValue($clean, $scope, $webpBasename, $actualWidth, $actualHeight),
            'error'  => null,
        ];
    }

    private function tally(array $result, int &$generated, int &$skipped, int &$failed, array &$errors): void
    {
        match ($result['status']) {
            'generated' => $generated++,
            'skipped'   => $skipped++,
            'failed'    => $failed++,
        };

        if ($result['error'] !== null) {
            $errors[] = $result['error'];
        }
    }

    private function stripJoomlaImageMeta(string $path): string
    {
        $hashPos = strpos($path, '#joomlaImage://');

        return $hashPos !== false ? substr($path, 0, $hashPos) : $path;
    }

    private function stripSiteRoot(string $path): string
    {
        $root = rtrim(Uri::root(), '/') . '/';

        while ($root !== '/' && str_starts_with($path, $root)) {
            $path = substr($path, \strlen($root));
        }

        return $path;
    }

    /** Realpath-confine a repo-relative path to JPATH_ROOT; null if missing or escapes the root. */
    private function resolveAndConfine(string $relativePath): ?string
    {
        $real = realpath(JPATH_ROOT . '/' . $relativePath);

        if ($real === false) {
            return null;
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', JPATH_ROOT), '/') . '/';
        $normalizedReal = str_replace('\\', '/', $real);

        return str_starts_with($normalizedReal, $normalizedRoot) ? $normalizedReal : null;
    }

    /**
     * Joomla's default local media adapter aliases the "images" root as "local-images"
     * in the #joomlaImage:// metadata fragment.
     */
    private function buildStoredValue(string $sourceRelativePath, string $scope, string $webpBasename, int $width, int $height): string
    {
        $sourceDir = \dirname($sourceRelativePath);
        $sourceDir = $sourceDir === '.' ? '' : $sourceDir . '/';

        $relativeTarget  = $sourceDir . $scope . '/' . $webpBasename;
        $adapterRelative = str_starts_with($relativeTarget, 'images/')
            ? substr($relativeTarget, \strlen('images/'))
            : $relativeTarget;

        return $relativeTarget . '#joomlaImage://local-images/' . $adapterRelative . '?width=' . $width . '&height=' . $height;
    }

    private function isObjectShape(?string $raw): bool
    {
        return $raw !== null && str_starts_with(ltrim($raw), '{');
    }

    private function decodeJsonField(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    private function encodeJsonField(array $data, bool $asObject): string
    {
        if ($asObject) {
            $object = new \stdClass();

            foreach ($data as $key => $value) {
                $object->{(string) $key} = $value;
            }

            return json_encode($object, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        return json_encode(array_values($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    private function updateRow(
        int $productImageId,
        array $cols,
        string $imageValue,
        string $altValue,
        string $additionalValue,
        string $additionalAltValue
    ): void {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__j2commerce_productimages'))
            ->set($this->db->quoteName($cols['image_col']) . ' = :imageValue')
            ->set($this->db->quoteName($cols['alt_col']) . ' = :altValue')
            ->set($this->db->quoteName($cols['additional_col']) . ' = :additionalValue')
            ->set($this->db->quoteName($cols['additional_alt_col']) . ' = :additionalAltValue')
            ->where($this->db->quoteName('j2commerce_productimage_id') . ' = :id')
            ->bind(':imageValue', $imageValue)
            ->bind(':altValue', $altValue)
            ->bind(':additionalValue', $additionalValue)
            ->bind(':additionalAltValue', $additionalAltValue)
            ->bind(':id', $productImageId, ParameterType::INTEGER);
        $this->db->setQuery($query);
        $this->db->execute();
    }
}
