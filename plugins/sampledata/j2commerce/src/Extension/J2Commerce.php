<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_sampledata_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\SampleData\J2Commerce\Extension;

use J2Commerce\Component\J2commerce\Administrator\Helper\SampleDataHelper;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Event\SampleData\GetOverviewEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class J2Commerce extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onSampledataGetOverview'    => 'onSampledataGetOverview',
            'onAjaxSampledataApplyStep1' => 'onAjaxSampledataApplyStep1',
            'onAjaxSampledataApplyStep2' => 'onAjaxSampledataApplyStep2',
            'onAjaxSampledataApplyStep3' => 'onAjaxSampledataApplyStep3',
            'onAjaxSampledataApplyStep4' => 'onAjaxSampledataApplyStep4',
        ];
    }

    public function onSampledataGetOverview(GetOverviewEvent $event): void
    {
        if (!$this->getApplication()->getIdentity()->authorise('core.manage', 'com_j2commerce')) {
            return;
        }

        $data              = new \stdClass();
        $data->name        = $this->_name;
        $data->title       = Text::_('PLG_SAMPLEDATA_J2COMMERCE_OVERVIEW_TITLE');
        $data->description = Text::_('PLG_SAMPLEDATA_J2COMMERCE_OVERVIEW_DESC');
        $data->icon        = 'fa-solid fa-store';
        $data->steps       = 4;

        $event->addResult($data);
    }

    /**
     * Step 1: Create categories and manufacturers.
     */
    public function onAjaxSampledataApplyStep1(AjaxEvent $event): void
    {
        if (!Session::checkToken('get') || $this->getApplication()->getInput()->get('type') !== $this->_name) {
            return;
        }

        if (!$this->getApplication()->getIdentity()->authorise('core.manage', 'com_j2commerce')) {
            $response            = [];
            $response['success'] = false;
            $response['message'] = Text::_('JERROR_ALERTNOAUTHOR');

            $event->addResult($response);
            return;
        }

        try {
            $helper  = new SampleDataHelper($this->getDatabase());
            $summary = $helper->load('standard');

            // Store summary in user state for subsequent steps to reference
            $this->getApplication()->setUserState('sampledata.j2commerce.loaded', true);
            $this->getApplication()->setUserState('sampledata.j2commerce.summary', $summary);

            $response            = [];
            $response['success'] = true;
            $response['message'] = Text::sprintf(
                'PLG_SAMPLEDATA_J2COMMERCE_STEP1_SUCCESS',
                (int) ($summary['categories'] ?? 0),
                (int) ($summary['manufacturers'] ?? 0)
            );

            $event->addResult($response);
        } catch (\Throwable $e) {
            $response            = [];
            $response['success'] = false;
            $response['message'] = Text::sprintf('PLG_SAMPLEDATA_J2COMMERCE_STEP_FAILED', 1, $e->getMessage());

            $event->addResult($response);
        }
    }

    /**
     * Step 2: Products and images were already created in step 1 (via load()).
     * This step reports the product creation results.
     */
    public function onAjaxSampledataApplyStep2(AjaxEvent $event): void
    {
        if (!Session::checkToken('get') || $this->getApplication()->getInput()->get('type') !== $this->_name) {
            return;
        }

        $summary = $this->getApplication()->getUserState('sampledata.j2commerce.summary', []);

        $response            = [];
        $response['success'] = true;
        $response['message'] = Text::sprintf(
            'PLG_SAMPLEDATA_J2COMMERCE_STEP2_SUCCESS',
            (int) ($summary['products_simple'] ?? 0) + (int) ($summary['products_variable'] ?? 0),
            (int) ($summary['product_images'] ?? 0)
        );

        $event->addResult($response);
    }

    /**
     * Step 3: Customers and orders were already created in step 1 (via load()).
     * This step reports the results.
     */
    public function onAjaxSampledataApplyStep3(AjaxEvent $event): void
    {
        if (!Session::checkToken('get') || $this->getApplication()->getInput()->get('type') !== $this->_name) {
            return;
        }

        $summary = $this->getApplication()->getUserState('sampledata.j2commerce.summary', []);

        $response            = [];
        $response['success'] = true;
        $response['message'] = Text::sprintf(
            'PLG_SAMPLEDATA_J2COMMERCE_STEP3_SUCCESS',
            (int) ($summary['customers'] ?? 0),
            (int) ($summary['orders'] ?? 0)
        );

        $event->addResult($response);
    }

    /**
     * Step 4: Coupons and vouchers were already created in step 1 (via load()).
     * This step reports the final results.
     */
    public function onAjaxSampledataApplyStep4(AjaxEvent $event): void
    {
        if (!Session::checkToken('get') || $this->getApplication()->getInput()->get('type') !== $this->_name) {
            return;
        }

        $summary = $this->getApplication()->getUserState('sampledata.j2commerce.summary', []);

        $response            = [];
        $response['success'] = true;
        $response['message'] = Text::sprintf(
            'PLG_SAMPLEDATA_J2COMMERCE_STEP4_SUCCESS',
            (int) ($summary['coupons'] ?? 0),
            (int) ($summary['vouchers'] ?? 0)
        );

        // Clear user state
        $this->getApplication()->setUserState('sampledata.j2commerce.loaded', null);
        $this->getApplication()->setUserState('sampledata.j2commerce.summary', null);

        $event->addResult($response);
    }
}
