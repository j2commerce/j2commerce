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

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Upload table class for file uploads.
 *
 * @since  6.0.0
 */
class UploadTable extends Table
{
    /**
     * Constructor.
     *
     * @param   DatabaseDriver  $db  Database connector object.
     *
     * @since   6.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_j2commerce.upload';

        parent::__construct('#__j2commerce_uploads', 'j2commerce_upload_id', $db);
    }

    /**
     * Overloaded check function.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since   6.0.0
     */
    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        // Ensure required fields have values
        if (empty($this->original_name)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', 'Original name'));
            return false;
        }

        if (empty($this->mangled_name)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', 'Mangled name'));
            return false;
        }

        if (empty($this->saved_name)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', 'Saved name'));
            return false;
        }

        // Set default values
        if (empty($this->mime_type)) {
            $this->mime_type = 'application/octet-stream';
        }

        if (empty($this->created_by)) {
            $this->created_by = Factory::getApplication()->getIdentity()->id ?? 0;
        }

        if (empty($this->created_on) || $this->created_on === '0000-00-00 00:00:00') {
            $this->created_on = Factory::getDate()->toSql();
        }

        return true;
    }
}
