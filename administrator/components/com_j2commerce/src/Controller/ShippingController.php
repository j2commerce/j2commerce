<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ShipperHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class ShippingController extends BaseController
{
    public function previewPacking(): void
    {
        if (!Session::checkToken()) {
            $this->sendJson(['success' => false, 'error' => Text::_('JINVALID_TOKEN')]);
            return;
        }

        $testItems   = json_decode($this->input->post->getString('test_items', '[]'), true) ?: [];
        $customBoxes = json_decode($this->input->post->getString('custom_boxes', '[]'), true) ?: [];

        if (empty($testItems)) {
            $this->sendJson(['success' => false, 'error' => Text::_('COM_J2COMMERCE_ERR_NO_TEST_ITEMS')]);
            return;
        }

        $options = [
            'weight_unit_id' => $this->input->post->getInt('weight_unit_id', 1),
            'length_unit_id' => $this->input->post->getInt('length_unit_id', 1),
        ];

        try {
            $result = ShipperHelper::previewPacking($customBoxes, $testItems, $options);
            $this->sendJson($result);
        } catch (\Throwable $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function sendJson(array $data): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $app->sendHeaders();
        echo json_encode($data);
        $app->close();
    }
}
