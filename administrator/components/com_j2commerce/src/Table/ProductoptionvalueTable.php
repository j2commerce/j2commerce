<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Table;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class ProductoptionvalueTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_j2commerce.productoptionvalue';

        parent::__construct('#__j2commerce_product_optionvalues', 'j2commerce_product_optionvalue_id', $db);
    }
}
