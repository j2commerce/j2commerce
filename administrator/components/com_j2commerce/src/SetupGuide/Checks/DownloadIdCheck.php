<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks;

use J2Commerce\Component\J2commerce\Administrator\SetupGuide\AbstractSetupCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\SetupCheckResult;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

class DownloadIdCheck extends AbstractSetupCheck
{
    public function getId(): string
    {
        return 'download_id';
    }

    public function getGroup(): string
    {
        return 'system_requirements';
    }

    public function getGroupOrder(): int
    {
        return 200;
    }

    public function isDismissible(): bool
    {
        return false;
    }

    public function getLabel(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_DESC');
    }

    public function check(): SetupCheckResult
    {
        $downloadId = trim((string) $this->getParams()->get('downloadid', ''));

        if ($downloadId === '') {
            return new SetupCheckResult('fail', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_FAIL'));
        }

        return new SetupCheckResult('pass', Text::sprintf('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_PASS', $this->maskValue($downloadId)));
    }

    public function getDetailView(): string
    {
        $downloadId = trim((string) $this->getParams()->get('downloadid', ''));
        $html       = '<h5>' . htmlspecialchars(Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID'), ENT_QUOTES, 'UTF-8') . '</h5>'
            . '<p>' . htmlspecialchars(Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_DESC'), ENT_QUOTES, 'UTF-8') . '</p>';

        $placeholder = htmlspecialchars(Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_PLACEHOLDER'), ENT_QUOTES, 'UTF-8');
        $saveLabel   = htmlspecialchars(Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_SAVE'), ENT_QUOTES, 'UTF-8');
        $clearLabel  = htmlspecialchars(Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_CLEAR'), ENT_QUOTES, 'UTF-8');
        $findLabel   = htmlspecialchars(Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_FIND'), ENT_QUOTES, 'UTF-8');

        if ($downloadId !== '') {
            $html .= '<div class="alert alert-success mb-3">'
                . Text::sprintf('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_PASS', $this->maskValue($downloadId))
                . '</div>'
                . '<div data-setup-param-form="downloadid">'
                . '<button type="button" class="btn btn-outline-danger w-100" data-setup-clear-param data-param-name="downloadid">'
                . '<span class="fa-solid fa-trash-can me-1" aria-hidden="true"></span> ' . $clearLabel
                . '</button>'
                . '</div>';
        } else {
            $html .= '<div data-setup-param-form="downloadid">'
                . '<div class="input-group mb-3">'
                . '<input type="text" class="form-control" name="param_value" placeholder="' . $placeholder . '">'
                . '<button type="button" class="btn btn-primary" data-setup-save-param data-param-name="downloadid">'
                . $saveLabel
                . '</button>'
                . '</div>'
                . '</div>';
        }

        $html .= '<a href="https://www.j2commerce.com/my-account/my-downloads" target="_blank" rel="noopener noreferrer" class="text-muted small">'
            . $findLabel
            . '</a>';

        return $html;
    }

    public function getActions(): array
    {
        return [];
    }

    private function maskValue(string $value): string
    {
        if (\strlen($value) <= 6) {
            return str_repeat('*', \strlen($value));
        }

        return substr($value, 0, 3) . '...' . substr($value, -3);
    }
}
