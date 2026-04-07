<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.ReportProducts
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\ReportProducts\Field;

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
 * This field produces no visible form output. Instead, it adds "View Report"
 * and "Back to Reports" link buttons to the Joomla toolbar when the plugin
 * configuration page renders.
 *
 * @since  6.0.0
 */
class ReportToolbarField extends FormField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $type = 'ReportToolbar';

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

        // Load the plugin language file for button text
        $app->getLanguage()->load(
            'plg_j2commerce_report_products',
            JPATH_PLUGINS . '/j2commerce/report_products',
            null,
            true
        );

        $toolbar = Toolbar::getInstance('toolbar');

        // Prepend so buttons appear to the LEFT of Save/Apply/Close/Help
        // (prependButton uses array_unshift, so add in reverse order)
        $viewBtn = new LinkButton('report-view');
        $viewBtn->text('PLG_J2COMMERCE_REPORT_PRODUCTS_VIEW_REPORT')
            ->url('index.php?option=com_j2commerce&view=reportplugin&plugin=report_products&pluginview=report')
            ->icon('fa-solid fa-chart-bar');
        $toolbar->prependButton($viewBtn);

        $backBtn = new LinkButton('report-back');
        $backBtn->text('PLG_J2COMMERCE_REPORT_PRODUCTS_BACK_TO_REPORTS')
            ->url('index.php?option=com_j2commerce&view=reports')
            ->icon('icon-arrow-left');
        $toolbar->prependButton($backBtn);

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
