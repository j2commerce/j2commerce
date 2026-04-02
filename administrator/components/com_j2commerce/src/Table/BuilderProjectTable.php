<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Table;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

\defined('_JEXEC') or die;

final class BuilderProjectTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__j2commerce_builder_projects', 'j2commerce_builder_project_id', $db);
        $this->setColumnAlias('published', 'enabled');
    }

    public function check(): bool
    {
        if (empty($this->plugin_element)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', 'Plugin element'));

            return false;
        }

        if (empty($this->file_id)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', 'File ID'));

            return false;
        }

        if (!isset($this->enabled) || $this->enabled === '') {
            $this->enabled = 1;
        }

        if (empty($this->file_type)) {
            $this->file_type = 'list_layout';
        }

        if (empty($this->builder_type)) {
            $this->builder_type = 'grapesjs';
        }

        return true;
    }
}
