<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.AppFlexivariable
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\AppFlexivariable\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

final class AppFlexivariable extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    protected $autoloadLanguage = true;

    protected string $_element = 'app_flexivariable';

    public static function getSubscribedEvents(): array
    {
        return [
            'onJ2CommerceGetProductTypes'           => 'onGetProductTypes',
            'onJ2CommerceAfterAddAssets'            => 'onAfterAddAssets',
            'onJ2CommerceAfterProcessUpSellItem'    => 'onAfterProcessUpSellItem',
            'onJ2CommerceAfterProcessCrossSellItem' => 'onAfterProcessCrossSellItem',
            'onJ2CommerceAfterVariantListAjax'      => 'onAfterVariantListAjax',
            'onJ2CommerceGetVariantForms'           => 'onGetVariantForms',
        ];
    }

    public function onGetProductTypes(Event $event): void
    {
        $args  = $event->getArguments();
        $types = &$args[0];

        $types['flexivariable'] = Text::_('COM_J2COMMERCE_PRODUCT_TYPE_FLEXIVARIABLE');
    }

    public function onAfterAddAssets(Event $event): void
    {
        /** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = $this->getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript(
            'plg_j2commerce_app_flexivariable.flexivariable',
            'media/plg_j2commerce_app_flexivariable/js/flexivariable.js',
            [],
            ['defer' => true]
        );

        Text::script('PLG_J2COMMERCE_APP_FLEXIVARIABLE_CONFIRM_DELETE');
        Text::script('PLG_J2COMMERCE_APP_FLEXIVARIABLE_CONFIRM_DELETE_ALL');
        Text::script('PLG_J2COMMERCE_APP_FLEXIVARIABLE_DELETING');
        Text::script('PLG_J2COMMERCE_APP_FLEXIVARIABLE_LOADING');
        Text::script('PLG_J2COMMERCE_APP_FLEXIVARIABLE_ERROR');
    }

    public function onAfterProcessUpSellItem(Event $event): void
    {
        $args          = $event->getArguments();
        $upsellProduct = $args[0] ?? null;
        $show          = &$args[1];

        if (isset($upsellProduct->product_type) && $upsellProduct->product_type === 'flexivariable') {
            $show = true;
        }
    }

    public function onAfterProcessCrossSellItem(Event $event): void
    {
        $args             = $event->getArguments();
        $crossSellProduct = $args[0] ?? null;
        $show             = &$args[1];

        if (isset($crossSellProduct->product_type) && $crossSellProduct->product_type === 'flexivariable') {
            $show = true;
        }
    }

    public function onAfterVariantListAjax(Event $event): void
    {
        $args = $event->getArguments();
        $view = &$args[0];
        $item = &$args[1];

        if ($item->product_type === 'flexivariable') {
            $item->app_detail = $this->getAppDetails();
            $view->assign('item', $item);
        }
    }

    /**
     * Handle variant form request - returns Form objects for each variant form section
     */
    public function onGetVariantForms(Event $event): void
    {
        $args        = $event->getArguments();
        $productType = $args['product_type'] ?? '';
        $variant     = $args['variant'] ?? null;
        $prefix      = $args['prefix'] ?? '';

        // Only process for flexivariable product type
        if ($productType !== 'flexivariable') {
            return;
        }

        $forms = $event->getArgument('forms', []);

        // Load all variant form sections
        $forms['general']   = $this->loadVariantForm('variant_general', $variant, $prefix);
        $forms['shipping']  = $this->loadVariantForm('variant_shipping', $variant, $prefix);
        $forms['inventory'] = $this->loadVariantForm('variant_inventory', $variant, $prefix);
        $forms['image']     = $this->loadVariantForm('variant_image', $variant, $prefix, true);

        $event->setArgument('forms', $forms);
    }

    /**
     * Load a variant form from the plugin's forms directory
     */
    protected function loadVariantForm(string $formName, ?object $variant, string $prefix, bool $isParams = false): ?Form
    {
        $formPath = JPATH_PLUGINS . '/j2commerce/app_flexivariable/forms/' . $formName . '.xml';

        if (!file_exists($formPath)) {
            return null;
        }

        $variantId = (int) ($variant->j2commerce_variant_id ?? 0);

        // Create unique form name with variant ID
        $form = Form::getInstance(
            'com_j2commerce.variant.' . $formName . '.' . $variantId,
            $formPath,
            ['control' => $prefix]
        );

        if (!$form) {
            return null;
        }

        // Bind variant data to form
        if ($variant !== null) {
            $data = $this->prepareVariantData($variant, $formName, $isParams);
            $form->bind($data);
        }

        return $form;
    }

    /**
     * Prepare variant data for binding to form
     */
    protected function prepareVariantData(object $variant, string $formName, bool $isParams): array
    {
        // For image form, extract from params JSON (Uppy handles images directly, only is_main_as_thum here)
        if ($isParams) {
            $params   = $variant->params ?? '{}';
            $registry = new Registry($params);
            return [
                'is_main_as_thum' => (int) $registry->get('is_main_as_thum', 0),
            ];
        }

        // For inventory, handle nested quantity data
        if ($formName === 'variant_inventory') {
            return [
                'manage_stock'                  => (int) ($variant->manage_stock ?? 0),
                'j2commerce_productquantity_id' => (int) ($variant->j2commerce_productquantity_id ?? 0),
                'quantity'                      => (int) ($variant->quantity ?? 0),
                'allow_backorder'               => (int) ($variant->allow_backorder ?? 0),
                'availability'                  => (int) ($variant->availability ?? 1),
                'notify_qty'                    => $variant->notify_qty ?? '',
                'use_store_config_notify_qty'   => (int) ($variant->use_store_config_notify_qty ?? 0),
                'quantity_restriction'          => (int) ($variant->quantity_restriction ?? 0),
                'max_sale_qty'                  => $variant->max_sale_qty ?? '',
                'use_store_config_max_sale_qty' => (int) ($variant->use_store_config_max_sale_qty ?? 0),
                'min_sale_qty'                  => $variant->min_sale_qty ?? '',
                'use_store_config_min_sale_qty' => (int) ($variant->use_store_config_min_sale_qty ?? 0),
            ];
        }

        // For general and shipping forms, use object properties directly
        return (array) $variant;
    }

    /**
     * Get all variant forms for a specific variant
     */
    public function getVariantForms(object $variant, string $prefix): array
    {
        return [
            'general'   => $this->loadVariantForm('variant_general', $variant, $prefix),
            'shipping'  => $this->loadVariantForm('variant_shipping', $variant, $prefix),
            'inventory' => $this->loadVariantForm('variant_inventory', $variant, $prefix),
            'image'     => $this->loadVariantForm('variant_image', $variant, $prefix, true),
        ];
    }

    protected function getAppDetails(): ?object
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $folder  = 'j2commerce';
        $element = 'app_flexivariable';
        $type    = 'plugin';

        $query->select('*')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('folder') . ' = :folder')
            ->where($db->quoteName('element') . ' = :element')
            ->where($db->quoteName('type') . ' = :type')
            ->bind(':folder', $folder)
            ->bind(':element', $element)
            ->bind(':type', $type);

        $db->setQuery($query);

        return $db->loadObject();
    }

    protected function _getLayout(string $layout, ?\stdClass $vars = null): string
    {
        $layoutPath = JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/tmpl';
        $fileLayout = new FileLayout($layout, $layoutPath);

        return $fileLayout->render($vars);
    }
}
