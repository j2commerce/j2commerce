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

defined('_JEXEC') or die;

abstract class AbstractMenuItemCheck extends AbstractSetupCheck
{
    abstract protected function getViewPattern(): string;
    abstract protected function getMenuTitle(): string;
    abstract protected function getMenuLink(): string;

    public function getGroup(): string
    {
        return 'storefront_pages';
    }

    public function getGroupOrder(): int
    {
        return 300;
    }

    public function isDismissible(): bool
    {
        return false;
    }

    public function check(): SetupCheckResult
    {
        $db      = $this->getDatabase();
        $pattern = '%' . $this->getViewPattern() . '%';

        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('title'), $db->quoteName('published')])
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('link') . ' LIKE :pattern')
            ->where($db->quoteName('client_id') . ' = 0')
            ->bind(':pattern', $pattern);

        $item = $db->setQuery($query)->loadObject();

        if ($item && (int) $item->published === 1) {
            return new SetupCheckResult('pass', Text::sprintf('COM_J2COMMERCE_SETUP_GUIDE_CHECK_MENU_PASS', $item->title));
        }

        if ($item) {
            return new SetupCheckResult('fail', Text::sprintf('COM_J2COMMERCE_SETUP_GUIDE_CHECK_MENU_UNPUBLISHED', $item->title), ['menuItemId' => (int) $item->id]);
        }

        return new SetupCheckResult('fail', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_MENU_MISSING'));
    }

    public function getActions(): array
    {
        $result = $this->check();

        if ($result->status === 'pass') {
            return [];
        }

        if (!empty($result->data['menuItemId'])) {
            return [[
                'action' => 'publish_menu_item',
                'label'  => 'COM_J2COMMERCE_SETUP_GUIDE_ACTION_PUBLISH',
                'params' => ['menuItemId' => $result->data['menuItemId']],
            ]];
        }

        return [[
            'action' => 'create_menu_item',
            'label'  => 'COM_J2COMMERCE_SETUP_GUIDE_ACTION_CREATE',
            'params' => ['link' => $this->getMenuLink(), 'title' => $this->getMenuTitle()],
        ]];
    }

    public function getDetailView(): string
    {
        $result = $this->check();

        $html = '<h5>' . $this->getLabel() . '</h5>';

        if ($result->status === 'pass') {
            $html .= '<p>' . $result->message . '</p>';

            // Show menu item details with link to frontend page
            $db      = $this->getDatabase();
            $pattern = '%' . $this->getViewPattern() . '%';
            $query   = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('title')])
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('link') . ' LIKE :pattern')
                ->where($db->quoteName('client_id') . ' = 0')
                ->where($db->quoteName('published') . ' = 1')
                ->bind(':pattern', $pattern);
            $item = $db->setQuery($query)->loadObject();

            if ($item) {
                $editUrl = 'index.php?option=com_menus&task=item.edit&id=' . (int) $item->id;
                $html .= '<p class="text-muted small mb-1">' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_MENU_ITEM_INFO') . '</p>'
                    . '<p class="small"><strong>' . htmlspecialchars($item->title) . '</strong> (ID: ' . (int) $item->id . ')</p>'
                    . '<a href="' . $editUrl . '" class="btn btn-outline-primary w-100 mb-2">'
                    . Text::_('COM_J2COMMERCE_SETUP_GUIDE_EDIT_MENU_ITEM')
                    . '</a>';
            }

            return $html;
        }

        $html .= '<p>' . $result->message . '</p>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_MENU_DETAIL') . '</p>';

        // Add action button matching the card's data-setup-action pattern
        $actions = $this->getActions();

        if (!empty($actions[0])) {
            $act       = $actions[0];
            $label     = Text::_($act['label']);
            $action    = htmlspecialchars($act['action'], ENT_QUOTES, 'UTF-8');
            $checkId   = htmlspecialchars($this->getId(), ENT_QUOTES, 'UTF-8');
            $params    = htmlspecialchars(json_encode($act['params'] ?? []), ENT_QUOTES, 'UTF-8');

            $html .= '<button type="button" class="btn btn-primary w-100 mt-2"'
                . ' data-setup-action="' . $checkId . '"'
                . ' data-action="' . $action . '"'
                . ' data-params=\'' . $params . '\''
                . ' data-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                . '</button>';
        }

        return $html;
    }
}
