<?php

declare(strict_types=1);

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks;

use J2Commerce\Component\J2commerce\Administrator\SetupGuide\AbstractSetupCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\SetupCheckResult;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

class PaymentMethodCheck extends AbstractSetupCheck
{
    public function getId(): string
    {
        return 'payment_method';
    }

    public function getGroup(): string
    {
        return 'payments_shipping';
    }

    public function getGroupOrder(): int
    {
        return 500;
    }

    public function isDismissible(): bool
    {
        return false;
    }

    public function getLabel(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_PAYMENT_METHOD');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_PAYMENT_METHOD_DESC');
    }

    public function check(): SetupCheckResult
    {
        $db     = $this->getDatabase();
        $folder = 'j2commerce';
        $like   = 'payment_%';
        $type   = 'plugin';

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('folder') . ' = :folder')
            ->where($db->quoteName('element') . ' LIKE :element')
            ->where($db->quoteName('enabled') . ' = 1')
            ->bind(':type', $type)
            ->bind(':folder', $folder)
            ->bind(':element', $like);

        $count = (int) $db->setQuery($query)->loadResult();

        return $count > 0
            ? new SetupCheckResult('pass', Text::sprintf('COM_J2COMMERCE_SETUP_GUIDE_CHECK_PAYMENT_METHOD_PASS', $count))
            : new SetupCheckResult('fail', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_PAYMENT_METHOD_FAIL'));
    }

    public function getDetailView(): string
    {
        $db     = $this->getDatabase();
        $folder = 'j2commerce';
        $like   = 'payment_%';
        $type   = 'plugin';

        $query = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('element'), $db->quoteName('name')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('folder') . ' = :folder')
            ->where($db->quoteName('element') . ' LIKE :element')
            ->where($db->quoteName('enabled') . ' = 1')
            ->bind(':type', $type)
            ->bind(':folder', $folder)
            ->bind(':element', $like)
            ->order($db->quoteName('name') . ' ASC');

        $plugins = $db->setQuery($query)->loadObjectList();

        $appsUrl = 'index.php?option=com_j2commerce&view=apps&folder=payment';

        $html = '<h5>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_PAYMENT_METHOD') . '</h5>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_PAYMENT_METHOD_DESC') . '</p>';

        if ($plugins) {
            $html .= '<p class="text-muted small mb-1">' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_ENABLED_PAYMENT_METHODS') . '</p>'
                . '<ul class="list-unstyled mb-3">';

            $lang = \Joomla\CMS\Factory::getApplication()->getLanguage();

            foreach ($plugins as $plugin) {
                $ext = 'plg_j2commerce_' . $plugin->element;
                $lang->load($ext, JPATH_ADMINISTRATOR) || $lang->load($ext, JPATH_PLUGINS . '/j2commerce/' . $plugin->element);
                $title = Text::_($plugin->name);

                $html .= '<li class="py-1 small">'
                    . '<span class="fa-regular fa-circle-check text-success me-1" aria-hidden="true"></span>'
                    . htmlspecialchars($title)
                    . '</li>';
            }

            $html .= '</ul>';
        }

        $html .= '<a href="' . $appsUrl . '" class="btn btn-primary w-100">'
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_ACTION_MANAGE_PAYMENT_PLUGINS')
            . '</a>';

        return $html;
    }
}
