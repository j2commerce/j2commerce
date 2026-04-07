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

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper as JoomlaToolbarHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Toolbar helper class for J2Commerce administrator views.
 *
 * Provides reusable toolbar button utilities for admin views.
 * Note: In Joomla 6, toolbars are primarily built in each HtmlView's
 * addToolbar() method. This helper provides common button patterns
 * that can be reused across multiple views.
 *
 * @since  6.0.0
 */
class ToolbarHelper
{
    /**
     * Singleton instance
     *
     * @var   ToolbarHelper|null
     * @since 6.0.0
     */
    protected static ?ToolbarHelper $instance = null;

    /**
     * Get the singleton instance
     *
     * @param   array|null  $properties  Optional properties (unused, for interface compatibility)
     *
     * @return  ToolbarHelper
     *
     * @since   6.0.0
     */
    public static function getInstance(?array $properties = null): ToolbarHelper
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Add an export CSV button to the toolbar.
     *
     * Creates a link button that exports the current view's data to CSV format
     * by appending format=csv to the current URL parameters.
     *
     * @param   string  $view      The view name for the export link.
     * @param   string  $task      Optional task (default: 'browse').
     * @param   string  $label     Optional button label language key.
     * @param   string  $icon      Optional icon class (default: 'download').
     *
     * @return  void
     *
     * @since   6.0.0
     *
     * @example
     * // In HtmlView::addToolbar():
     * ToolbarHelper::addExportButton('orders');
     * ToolbarHelper::addExportButton('customers', 'export', 'COM_J2COMMERCE_EXPORT_CUSTOMERS');
     */
    public static function addExportButton(
        string $view,
        string $task = 'browse',
        string $label = 'JTOOLBAR_EXPORT',
        string $icon = 'download'
    ): void {
        if (empty($view)) {
            return;
        }

        $toolbar = self::getToolbar();

        // Build export URL with CSV format
        $uri             = Uri::getInstance();
        $query           = $uri->getQuery(true);
        $query['format'] = 'csv';
        $query['option'] = 'com_j2commerce';
        $query['view']   = $view;
        $query['task']   = $task;

        $uri->setQuery($query);
        $exportUrl = $uri->toString();

        // Add divider before export button
        JoomlaToolbarHelper::divider();

        // Add the export link button
        $toolbar->appendButton('Link', $icon, Text::_($label), $exportUrl);
    }

    /**
     * Add a back button to the toolbar.
     *
     * Creates a link button that navigates back to a specified view
     * or the dashboard.
     *
     * @param   string  $targetView  The view to navigate back to (default: 'cpanel').
     * @param   string  $label       Optional button label language key.
     * @param   string  $icon        Optional icon class (default: 'arrow-left').
     *
     * @return  void
     *
     * @since   6.0.0
     *
     * @example
     * // In HtmlView::addToolbar():
     * ToolbarHelper::addBackButton('orders');
     * ToolbarHelper::addBackButton('cpanel', 'COM_J2COMMERCE_BACK_TO_DASHBOARD');
     */
    public static function addBackButton(
        string $targetView = 'cpanel',
        string $label = 'JTOOLBAR_BACK',
        string $icon = 'arrow-left'
    ): void {
        $toolbar = self::getToolbar();

        $backUrl = Route::_('index.php?option=com_j2commerce&view=' . $targetView);

        $toolbar->appendButton('Link', $icon, Text::_($label), $backUrl);
    }

    /**
     * Add a custom link button to the toolbar.
     *
     * Creates a generic link button for navigation to any URL.
     *
     * @param   string  $url    The destination URL.
     * @param   string  $label  Button label (already translated or language key).
     * @param   string  $icon   Icon class (default: 'link').
     *
     * @return  void
     *
     * @since   6.0.0
     *
     * @example
     * // In HtmlView::addToolbar():
     * ToolbarHelper::addLinkButton(
     *     'index.php?option=com_j2commerce&view=coupon&task=history&coupon_id=' . $id,
     *     'COM_J2COMMERCE_COUPON_HISTORY',
     *     'list'
     * );
     */
    public static function addLinkButton(string $url, string $label, string $icon = 'link'): void
    {
        $toolbar = self::getToolbar();

        // Check if label is a language key
        $translatedLabel = Text::_($label);

        $toolbar->appendButton('Link', $icon, $translatedLabel, $url);
    }

    /**
     * Add a close button that returns to a list view.
     *
     * Commonly used in edit views to close/cancel without saving.
     *
     * @param   string  $targetView  The list view to return to.
     * @param   string  $label       Optional button label (default: 'JTOOLBAR_CLOSE').
     * @param   string  $icon        Optional icon class (default: 'cancel').
     *
     * @return  void
     *
     * @since   6.0.0
     *
     * @example
     * // In HtmlView::addToolbar():
     * ToolbarHelper::addCloseButton('orders');
     */
    public static function addCloseButton(
        string $targetView,
        string $label = 'JTOOLBAR_CLOSE',
        string $icon = 'cancel'
    ): void {
        $toolbar = self::getToolbar();

        $closeUrl = Route::_('index.php?option=com_j2commerce&view=' . $targetView);

        $toolbar->appendButton('Link', $icon, Text::_($label), $closeUrl);
    }

    /**
     * Add help button with link to documentation.
     *
     * @param   string  $docSection  Documentation section identifier.
     * @param   string  $label       Optional button label.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public static function addHelpButton(string $docSection = '', string $label = 'JTOOLBAR_HELP'): void
    {
        $baseUrl = 'https://docs.j2commerce.com/';
        $helpUrl = $baseUrl . ($docSection ? $docSection : '');

        $toolbar = self::getToolbar();
        $toolbar->appendButton('Link', 'question-circle', Text::_($label), $helpUrl);
    }

    /**
     * Set the standard page title for J2Commerce views.
     *
     * @param   string  $titleKey  Language key for the page title.
     * @param   string  $icon      Optional icon class (default: 'cart').
     *
     * @return  void
     *
     * @since   6.0.0
     *
     * @example
     * // In HtmlView::addToolbar():
     * ToolbarHelper::setTitle('COM_J2COMMERCE_ORDERS');
     * ToolbarHelper::setTitle('COM_J2COMMERCE_PRODUCTS', 'tags');
     */
    public static function setTitle(string $titleKey, string $icon = 'cart'): void
    {
        JoomlaToolbarHelper::title(Text::_($titleKey), $icon);
    }

    /**
     * Add standard CRUD buttons for a list view.
     *
     * Adds common buttons: New, Edit, Publish, Unpublish, Delete.
     * Use ACL checks before calling this method.
     *
     * @param   string  $singularName  Singular entity name (e.g., 'product', 'order').
     * @param   bool    $canCreate     Whether user can create new items.
     * @param   bool    $canEdit       Whether user can edit items.
     * @param   bool    $canEditState  Whether user can publish/unpublish.
     * @param   bool    $canDelete     Whether user can delete items.
     *
     * @return  void
     *
     * @since   6.0.0
     *
     * @example
     * // In HtmlView::addToolbar():
     * $canDo = ContentHelper::getActions('com_j2commerce');
     * ToolbarHelper::addListButtons(
     *     'product',
     *     $canDo->get('core.create'),
     *     $canDo->get('core.edit'),
     *     $canDo->get('core.edit.state'),
     *     $canDo->get('core.delete')
     * );
     */
    public static function addListButtons(
        string $singularName,
        bool $canCreate = false,
        bool $canEdit = false,
        bool $canEditState = false,
        bool $canDelete = false
    ): void {
        $toolbar    = self::getToolbar();
        $pluralName = $singularName . 's';

        if ($canCreate) {
            $toolbar->addNew($singularName . '.add');
        }

        if ($canEdit) {
            $toolbar->edit($singularName . '.edit')->listCheck(true);
        }

        if ($canEditState) {
            $toolbar->publish($pluralName . '.publish')->listCheck(true);
            $toolbar->unpublish($pluralName . '.unpublish')->listCheck(true);
        }

        if ($canDelete) {
            $toolbar->delete($pluralName . '.delete')
                ->text('JTOOLBAR_DELETE')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
        }
    }

    /**
     * Add standard buttons for an edit view.
     *
     * Adds common buttons: Apply, Save, Save & New, Save as Copy, Cancel/Close.
     *
     * @param   string  $singularName  Singular entity name (e.g., 'product', 'order').
     * @param   bool    $isNew         Whether this is a new item (no ID).
     * @param   bool    $canSave       Whether user can save.
     * @param   bool    $canCreate     Whether user can create (for Save & New, Save as Copy).
     *
     * @return  void
     *
     * @since   6.0.0
     *
     * @example
     * // In HtmlView::addToolbar():
     * $isNew = empty($this->item->j2commerce_product_id);
     * $canDo = ContentHelper::getActions('com_j2commerce');
     * ToolbarHelper::addEditButtons(
     *     'product',
     *     $isNew,
     *     $canDo->get('core.edit') || $canDo->get('core.create'),
     *     $canDo->get('core.create')
     * );
     */
    public static function addEditButtons(
        string $singularName,
        bool $isNew = false,
        bool $canSave = false,
        bool $canCreate = false
    ): void {
        $toolbar = self::getToolbar();

        // Hide the main menu while editing
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        if ($canSave) {
            $toolbar->apply($singularName . '.apply');
            $toolbar->save($singularName . '.save');
        }

        if ($canCreate && $canSave) {
            $toolbar->save2new($singularName . '.save2new');
        }

        if (!$isNew && $canCreate) {
            $toolbar->save2copy($singularName . '.save2copy');
        }

        $toolbar->cancel(
            $singularName . '.cancel',
            $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE'
        );
    }

    /**
     * Get the toolbar instance.
     *
     * @param   string  $name  Optional toolbar name (default: 'toolbar').
     *
     * @return  Toolbar  The toolbar instance.
     *
     * @since   6.0.0
     */
    private static function getToolbar(string $name = 'toolbar'): Toolbar
    {
        return Toolbar::getInstance($name);
    }

    /**
     * Get available admin views for J2Commerce.
     *
     * Returns the structured menu configuration for admin navigation.
     * This is primarily for reference - in Joomla 6, the submenu is
     * defined in the component manifest XML.
     *
     * @return  array  Nested array of view categories and views.
     *
     * @since   6.0.0
     */
    public static function getAdminViews(): array
    {
        return [
            'cpanel' => [
                'name' => 'cpanel',
                'icon' => 'home',
            ],
            'catalog' => [
                'label' => 'COM_J2COMMERCE_MAINMENU_CATALOG',
                'views' => [
                    ['name' => 'products', 'icon' => 'tags'],
                    ['name' => 'options', 'icon' => 'list-alt'],
                    ['name' => 'filtergroups', 'icon' => 'filter'],
                    ['name' => 'vendors', 'icon' => 'users'],
                    ['name' => 'manufacturers', 'icon' => 'industry'],
                ],
            ],
            'sales' => [
                'label' => 'COM_J2COMMERCE_MAINMENU_SALES',
                'views' => [
                    ['name' => 'orders', 'icon' => 'shopping-cart'],
                    ['name' => 'customers', 'icon' => 'user'],
                    ['name' => 'coupons', 'icon' => 'ticket'],
                    ['name' => 'vouchers', 'icon' => 'gift'],
                    ['name' => 'promotions', 'icon' => 'bullhorn'],
                ],
            ],
            'localisation' => [
                'label' => 'COM_J2COMMERCE_MAINMENU_LOCALISATION',
                'views' => [
                    ['name' => 'countries', 'icon' => 'globe'],
                    ['name' => 'zones', 'icon' => 'map'],
                    ['name' => 'geozones', 'icon' => 'map-marker'],
                    ['name' => 'taxrates', 'icon' => 'percent'],
                    ['name' => 'taxprofiles', 'icon' => 'file-invoice'],
                    ['name' => 'lengths', 'icon' => 'ruler'],
                    ['name' => 'weights', 'icon' => 'weight'],
                    ['name' => 'orderstatuses', 'icon' => 'info-circle'],
                ],
            ],
            'design' => [
                'label' => 'COM_J2COMMERCE_MAINMENU_DESIGN',
                'views' => [
                    ['name' => 'emailtemplates', 'icon' => 'envelope'],
                    ['name' => 'invoicetemplates', 'icon' => 'file-alt'],
                ],
            ],
            'setup' => [
                'label' => 'COM_J2COMMERCE_MAINMENU_SETUP',
                'views' => [
                    ['name' => 'configuration', 'icon' => 'cog'],
                    ['name' => 'currencies', 'icon' => 'dollar-sign'],
                    ['name' => 'payments', 'icon' => 'credit-card'],
                    ['name' => 'shippings', 'icon' => 'truck'],
                    ['name' => 'shippingtroubles', 'icon' => 'bug'],
                    ['name' => 'customfields', 'icon' => 'puzzle-piece'],
                ],
            ],
            'applications' => [
                'label' => 'COM_J2COMMERCE_MAINMENU_APPLICATIONS',
                'views' => [
                    ['name' => 'apps', 'icon' => 'plug'],
                ],
            ],
            'reports' => [
                'label' => 'COM_J2COMMERCE_MAINMENU_REPORT',
                'views' => [
                    ['name' => 'reports', 'icon' => 'chart-line'],
                ],
            ],
        ];
    }
}
