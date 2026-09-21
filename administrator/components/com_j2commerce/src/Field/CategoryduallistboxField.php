<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

/**
 * CategoryDuallistbox field - dual listbox interface for selecting Joomla article categories.
 *
 * @since  6.0.7
 */
class CategoryduallistboxField extends DuallistboxField
{
    protected $type = 'Categoryduallistbox';

    protected string $listboxClass = 'category-duallistbox';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id', 'value'),
                    $db->quoteName('title', 'text'),
                    $db->quoteName('level'),
                ])
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
                ->where($db->quoteName('published') . ' = 1')
                ->order($db->quoteName('lft') . ' ASC');

            $db->setQuery($query);
            $categories = $db->loadObjectList();

            if ($categories) {
                foreach ($categories as $category) {
                    $indent    = str_repeat('— ', max(0, (int) $category->level - 1));
                    $options[] = HTMLHelper::_('select.option', $category->value, $indent . $category->text);
                }
            }
        } catch (\Exception $e) {
            Log::add('Failed to load categories: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_CATEGORIES', Text::_('JERROR_AN_ERROR_HAS_OCCURRED')),
                'error'
            );
        }

        return $options;
    }
}
