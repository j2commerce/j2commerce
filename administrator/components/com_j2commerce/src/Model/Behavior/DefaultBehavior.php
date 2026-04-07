<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model\Behavior;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\PluginHelper;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;

/**
 * Default Product Behavior Class
 *
 * Base/fallback behavior class for all product types. Handles generic
 * lifecycle events that apply to all products regardless of type.
 * Product-type-specific behaviors (Simple, Variable, etc.) should extend
 * or delegate to this class for common operations.
 *
 * @since 6.0.0
 */
class DefaultBehavior
{
    /**
     * Raw data storage for passing between events
     *
     * @var  array
     * @since 6.0.0
     */
    private array $_rawData = [];

    /**
     * MVC Factory for creating models and tables
     *
     * @var  MVCFactoryInterface
     * @since 6.0.0
     */
    protected MVCFactoryInterface $mvcFactory;

    /**
     * Plugin Helper instance
     *
     * @var  PluginHelper
     * @since 6.0.0
     */
    protected PluginHelper $pluginHelper;

    /**
     * Constructor
     *
     * @param   MVCFactoryInterface|null  $mvcFactory  MVC Factory (optional)
     *
     * @since   6.0.0
     */
    public function __construct(?MVCFactoryInterface $mvcFactory = null)
    {
        $this->mvcFactory = $mvcFactory
            ?: Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory();
        $this->pluginHelper = J2CommerceHelper::plugin();
    }

    public function onAfterGetItem(object &$model, object &$record): void
    {
        $app = Factory::getApplication();

        // Import catalog (content) plugins
        $this->pluginHelper->importCatalogPlugins();

        // Trigger plugin event to allow modification of product data
        $this->pluginHelper->event('AfterGetProduct', [&$record]);
    }

    public function onBeforeSave(object &$model, array &$data): void
    {
        // Store raw data for onAfterSave
        $this->_rawData = $data;

        // Trigger plugin event
        $this->pluginHelper->event('BeforeSaveProduct', [&$data, $model]);
    }

    public function onAfterSave(object &$model): void
    {
        if (empty($this->_rawData)) {
            return;
        }

        $table = $model->getTable();

        // Trigger plugin event
        $this->pluginHelper->event('AfterSaveProduct', [$table, $this->_rawData]);
    }

    public function onBeforeDelete(object &$model): void
    {
        $id = $model->getState('product.id');

        if (!$id) {
            return;
        }

        // Trigger plugin event
        $this->pluginHelper->event('BeforeDeleteProduct', [$id, $model]);
    }

    public function onAfterGetProduct(AbstractEvent $event): void
    {
        $model   = $event->getArgument('subject');
        $product = $event->getArgument('product');

        // Import catalog plugins
        $this->pluginHelper->importCatalogPlugins();

        // Trigger plugin event for frontend product processing
        $this->pluginHelper->event('AfterGetProduct', [&$product, $model]);
    }

    public function onUpdateProduct(object &$model, object &$product): array|false
    {
        // Trigger plugin event
        $this->pluginHelper->event('UpdateProduct', [&$product, $model]);

        return [];
    }

    /**
     * Get the raw data stored during onBeforeSave
     *
     * @return  array  The raw data array
     *
     * @since   6.0.0
     */
    public function getRawData(): array
    {
        return $this->_rawData;
    }

    /**
     * Set raw data for passing between events
     *
     * @param   array  $data  The data to store
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setRawData(array $data): void
    {
        $this->_rawData = $data;
    }
}
