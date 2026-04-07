<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Contract;

\defined('_JEXEC') or die;

/**
 * Interface for plugins that provide custom email types.
 *
 * Plugins must implement this interface and register via
 * onJ2CommerceRegisterEmailTypes event.
 *
 * @since  6.1.0
 */
interface EmailTypeProviderInterface
{
    /**
     * Returns array of email type definitions.
     *
     * Each definition contains:
     * - type: string - Unique identifier (e.g., 'giftcertificate')
     * - label: string - Display name (will be translated)
     * - description: string - Short description
     * - icon: string - FontAwesome class (e.g., 'fa-solid fa-gift')
     * - contexts: array - Available contexts (e.g., ['sent', 'expired'])
     * - tags: array - Tag definitions: tag_name => [label, description, group]
     * - default_subject: string - Language key for default subject
     * - default_body: string - Language key for default body
     * - receiver_types: array - Allowed receiver types ['customer', 'admin', '*']
     *
     * @return  array
     *
     * @since   6.1.0
     */
    public function getEmailTypes(): array;

    /**
     * Returns processed tag values for a given email type and context.
     *
     * This method is called when an email is being sent. The plugin
     * receives context data (e.g., order object) and returns tag values.
     *
     * @param   string  $emailType  The email type identifier
     * @param   string  $context    The email context (e.g., 'sent', 'expired')
     * @param   object  $data       Context data (order, voucher, etc.)
     *
     * @return  array  Tag name => value pairs
     *
     * @since   6.1.0
     */
    public function getEmailTagValues(string $emailType, string $context, object $data): array;

    /**
     * Returns whether this provider handles the given email type.
     *
     * @param   string  $emailType  The email type to check
     *
     * @return  bool
     *
     * @since   6.1.0
     */
    public function handlesEmailType(string $emailType): bool;
}
