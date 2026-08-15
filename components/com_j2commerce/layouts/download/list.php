<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/**
 * @var array        $displayData
 * @var list<object> $displayData['downloads']    Rows from DownloadHelper::getOrderDownloads()
 * @var string       $displayData['framework']    bootstrap5|uikit (default bootstrap5)
 * @var string       $displayData['dateFormat']   Date format for the expiry column
 */

$downloads = $displayData['downloads'] ?? [];

if (empty($downloads)) {
    return;
}

$rawFramework = (string) ($displayData['framework'] ?? 'bootstrap5');
$isUikit      = ($rawFramework === 'uikit' || $rawFramework === 'uikit3');
$dateFormat   = (string) ($displayData['dateFormat'] ?? 'Y-m-d');
$nullDate     = '0000-00-00 00:00:00';

$esc = static fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="j2c-block-downloads <?php echo $isUikit ? 'uk-card uk-card-default uk-card-small uk-margin' : 'card mb-4'; ?>">
    <div class="<?php echo $isUikit ? 'uk-card-body' : 'card-body'; ?>">
        <h3 class="<?php echo $isUikit ? 'uk-card-title uk-h5' : 'h6 mb-3'; ?>">
            <span class="<?php echo $isUikit ? 'uk-margin-small-right' : 'fa-solid fa-download me-1'; ?>"
                <?php echo $isUikit ? 'uk-icon="icon: download"' : ''; ?> aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_MYPROFILE_DOWNLOADS'); ?>
        </h3>

        <ul class="<?php echo $isUikit ? 'uk-list uk-list-divider uk-margin-remove-bottom' : 'list-group list-group-flush'; ?>">
            <?php foreach ($downloads as $download) : ?>
                <?php
                $displayName = (string) ($download->product_file_display_name ?? '');
                $expires     = (string) ($download->access_expires ?? '');
                $hasExpiry   = $expires !== '' && $expires !== $nullDate;
                ?>
                <li class="<?php echo $isUikit
                    ? 'uk-flex uk-flex-middle uk-flex-between uk-flex-wrap'
                    : 'list-group-item d-flex align-items-center justify-content-between flex-wrap gap-2 px-0'; ?>">
                    <span>
                        <span class="<?php echo $isUikit ? '' : 'fw-medium'; ?>">
                            <?php echo $displayName !== ''
                                ? $esc($displayName)
                                : Text::_('COM_J2COMMERCE_FILE_UNAVAILABLE'); ?>
                        </span>
                        <?php if ($hasExpiry) : ?>
                            <small class="<?php echo $isUikit ? 'uk-text-meta uk-display-block' : 'd-block text-body-secondary'; ?>">
                                <?php echo Text::sprintf(
                                    'COM_J2COMMERCE_DOWNLOAD_EXPIRES_ON',
                                    HTMLHelper::_('date', $expires, $dateFormat)
                                ); ?>
                            </small>
                        <?php endif; ?>
                        <?php if ((int) $download->remaining >= 0) : ?>
                            <small class="<?php echo $isUikit ? 'uk-text-meta uk-display-block' : 'd-block text-body-secondary'; ?>">
                                <?php echo Text::sprintf(
                                    'COM_J2COMMERCE_DOWNLOADS_REMAINING_COUNT',
                                    (int) $download->remaining
                                ); ?>
                            </small>
                        <?php endif; ?>
                    </span>

                    <?php if (!empty($download->can_download)) : ?>
                        <a href="<?php echo Route::_(
                            'index.php?option=com_j2commerce&task=myprofile.download'
                            . '&order_id=' . urlencode((string) $download->order_id)
                            . '&fid=' . (int) $download->j2commerce_productfile_id
                        ); ?>" class="<?php echo $isUikit ? 'uk-button uk-button-primary uk-button-small' : 'btn btn-sm btn-primary'; ?>">
                            <span class="<?php echo $isUikit ? 'uk-margin-small-right' : 'fa-solid fa-download me-1'; ?>"
                                <?php echo $isUikit ? 'uk-icon="icon: download"' : ''; ?> aria-hidden="true"></span>
                            <?php echo Text::_('COM_J2COMMERCE_DOWNLOAD'); ?>
                        </a>
                    <?php elseif (!empty($download->pending)) : ?>
                        <span class="<?php echo $isUikit ? 'uk-label' : 'badge text-bg-dark'; ?>">
                            <?php echo Text::_('COM_J2COMMERCE_DOWNLOAD_PENDING'); ?>
                        </span>
                    <?php elseif (!empty($download->expired)) : ?>
                        <span class="<?php echo $isUikit ? 'uk-label uk-label-danger' : 'badge text-bg-danger'; ?>">
                            <?php echo Text::_('COM_J2COMMERCE_EXPIRED'); ?>
                        </span>
                    <?php elseif (!empty($download->limit_reached)) : ?>
                        <span class="<?php echo $isUikit ? 'uk-label uk-label-warning' : 'badge text-bg-warning'; ?>">
                            <?php echo Text::_('COM_J2COMMERCE_LIMIT_REACHED'); ?>
                        </span>
                    <?php else : ?>
                        <span class="<?php echo $isUikit ? 'uk-label' : 'badge text-bg-dark'; ?>">
                            <?php echo Text::_('COM_J2COMMERCE_FILE_UNAVAILABLE'); ?>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
