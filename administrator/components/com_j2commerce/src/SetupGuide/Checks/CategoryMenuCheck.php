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

class CategoryMenuCheck extends AbstractSetupCheck
{
    public function getId(): string
    {
        return 'category_menu';
    }

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

    public function getLabel(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CATEGORY_MENU');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CATEGORY_MENU_DESC');
    }

    public function check(): SetupCheckResult
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('title'), $db->quoteName('published')])
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_j2commerce%'))
            ->extendWhere('AND', [
                $db->quoteName('link') . ' LIKE ' . $db->quote('%view=categories%'),
                $db->quoteName('link') . ' LIKE ' . $db->quote('%view=products%'),
                $db->quoteName('link') . ' LIKE ' . $db->quote('%view=producttags%'),
            ], 'OR');

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
            'action' => 'open_category_wizard',
            'label'  => 'COM_J2COMMERCE_SETUP_GUIDE_ACTION_WIZARD',
            'params' => [],
        ]];
    }

    public function getDetailView(): string
    {
        $result = $this->check();

        $html = '<h5>' . $this->getLabel() . '</h5>';

        if ($result->status === 'pass') {
            $html .= '<p>' . $result->message . '</p>';

            // Show menu item details with link to frontend page
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('title')])
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('client_id') . ' = 0')
                ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_j2commerce%'))
                ->where($db->quoteName('published') . ' = 1')
                ->extendWhere('AND', [
                    $db->quoteName('link') . ' LIKE ' . $db->quote('%view=categories%'),
                    $db->quoteName('link') . ' LIKE ' . $db->quote('%view=products%'),
                    $db->quoteName('link') . ' LIKE ' . $db->quote('%view=producttags%'),
                ], 'OR');
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
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_MENU_DETAIL') . '</p>'
            . '<button type="button" class="btn btn-primary w-100 mb-2" id="j2c-open-category-wizard"'
            . ' data-bs-toggle="modal" data-bs-target="#j2commerceCategoryWizardModal">'
            . '<span class="fa-solid fa-wand-magic-sparkles me-2" aria-hidden="true"></span>'
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_ACTION_WIZARD')
            . '</button>';

        return $html;
    }
}
