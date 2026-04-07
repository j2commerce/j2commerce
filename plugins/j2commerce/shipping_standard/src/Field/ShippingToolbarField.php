<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.ShippingStandard
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\ShippingStandard\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Toolbar\Button\LinkButton;
use Joomla\CMS\Toolbar\Toolbar;

/**
 * Custom form field that adds toolbar buttons to the plugin config page.
 *
 * This field produces no visible form output. Instead, it adds "Manage Standard
 * Methods" and "New Shipping Method" link buttons to the Joomla toolbar when
 * the plugin configuration page renders. The toolbar is a singleton rendered
 * by the Atum template after all component output, so buttons added during
 * form field rendering appear correctly.
 *
 * @since  6.0.0
 */
class ShippingToolbarField extends FormField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $type = 'ShippingToolbar';

    /**
     * Add toolbar buttons and return empty HTML.
     *
     * @return  string  Empty string (no visible form output).
     *
     * @since   6.0.0
     */
    protected function getInput(): string
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator')) {
            return '';
        }

        // Load the component language file for button text
        $app->getLanguage()->load('com_j2commerce', JPATH_ADMINISTRATOR);

        $toolbar = Toolbar::getInstance('toolbar');

        // Prepend so buttons appear to the LEFT of Save/Apply/Close/Help
        // (prependButton uses array_unshift, so add in reverse order)
        $newBtn = new LinkButton('standard-new');
        $newBtn->text('COM_J2COMMERCE_SHIPPING_NEW_STANDARD')
            ->url('index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=method')
            ->icon('icon-plus');
        $toolbar->prependButton($newBtn);

        $methodsBtn = new LinkButton('standard-methods');
        $methodsBtn->text('COM_J2COMMERCE_SHIPPING_MANAGE_STANDARD')
            ->url('index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=methods')
            ->icon('icon-list');
        $toolbar->prependButton($methodsBtn);

        return '';
    }

    /**
     * Return empty label.
     *
     * @return  string  Empty string.
     *
     * @since   6.0.0
     */
    protected function getLabel(): string
    {
        return '';
    }
}
