<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Event;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\AbstractImmutableEvent;

/**
 * Base event class for J2Commerce plugin events
 *
 * Provides modern Joomla 5 event handling with type safety and structured result collection.
 * This class extends Joomla's AbstractImmutableEvent to provide PSR-14 compliant event handling
 * with additional helper methods for common plugin event patterns.
 *
 * @since  6.0.0
 */
class PluginEvent extends AbstractImmutableEvent
{
    /**
     * Arguments that can be modified by event handlers
     *
     * @var array
     * @since 6.0.0
     */
    private const MUTABLE_ARGUMENTS = ['result', 'forms', 'html'];

    /**
     * Set an argument value by numeric index.
     *
     * This is used for pass-by-reference arguments in events where plugins
     * need to modify scalar values (like prices) that are passed as the first
     * argument in an indexed array.
     *
     * @param   int     $index  The argument index (0-based).
     * @param   mixed   $value  The new value.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    public function setArgumentByIndex(int $index, $value): void
    {
        $this->arguments[$index] = $value;
    }

    /**
     * Get an argument value by numeric index.
     *
     * This is used for pass-by-reference arguments in events where the caller
     * needs to read back modified values after plugins have processed them.
     *
     * @param   int     $index   The argument index (0-based).
     * @param   mixed   $default  Default value if argument doesn't exist.
     *
     * @return  mixed  The argument value or default.
     *
     * @since   6.0.6
     */
    public function getArgumentByIndex(int $index, $default = null)
    {
        return $this->arguments[$index] ?? $default;
    }

    /**
     * Set the value of an event argument.
     *
     * Overrides parent to allow specific arguments to be modified.
     *
     * @param   string  $name   The argument name.
     * @param   mixed   $value  The argument value.
     *
     * @return  void
     *
     * @since   6.0.0
     * @throws  \BadMethodCallException
     */
    public function offsetSet($name, $value): void
    {
        if (\in_array($name, self::MUTABLE_ARGUMENTS, true)) {
            $this->arguments[$name] = $value;
            return;
        }

        parent::offsetSet($name, $value);
    }

    /**
     * Set argument value
     *
     * @param   string  $name
     * @param   mixed  $value
     * @return void
     *
     * @since   6.0.0
     */
    public function setArgument($name, $value)
    {
        // Convert name to string for compatibility with parent class expectations
        $name = (string) $name;

        if (\in_array($name, self::MUTABLE_ARGUMENTS, true)) {
            $this->arguments[$name] = $value;
            return;
        }

        parent::setArgument($name, $value);
    }

    /**
     * Get the event result
     *
     * Returns the accumulated result from all plugins that handled this event.
     * The result format depends on how plugins set the data (can be arrayed, string, object, etc.)
     *
     * @return  mixed  The event result, or null if no result has been set
     *
     * @since   6.0.0
     */
    public function getEventResult()
    {
        return $this->arguments['result'] ?? null;
    }

    /**
     * Set the event result
     *
     * Replaces the entire event result with the given value.
     * Use this when you want to completely override previous results.
     *
     * @param   mixed  $value  The result value to set
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setEventResult($value): void
    {
        $this->arguments['result'] = $value;
    }

    /**
     * Add to the event result array
     *
     * Appends a value to the event result, treating the result as an array.
     * If no result exists yet, initialize it as an empty array.
     * This is useful for plugins that want to contribute data without overwriting other plugins' contributions.
     *
     * @param   mixed  $value  The value to add to the result array
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function addResult($value): void
    {
        // Initialize a result as an empty array if first plugin
        if (!isset($this->arguments['result'])) {
            $this->arguments['result'] = [];
        }

        // Append to result an array
        if (\is_array($this->arguments['result'])) {
            $this->arguments['result'][] = $value;
        }
    }


    /**
     * Get all event arguments
     *
     * Returns the complete array of arguments passed to this event.
     * This provides access to all data passed to plugins during event dispatch.
     *
     * @return  array  The event arguments array
     *
     * @since   6.0.0
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Get a specific argument by name
     *
     * Retrieves a single named argument from the event data.
     *
     * @param   string  $name     The argument name
     * @param   mixed   $default  Default value if argument doesn't exist
     *
     * @return  mixed  The argument value or default
     *
     * @since   6.0.0
     */
    public function getArgument($name, $default = null)
    {
        return $this->arguments[$name] ?? $default;
    }

    /**
     * Convert the event to a string for direct echo in templates.
     *
     * Returns the HTML content from the event, allowing templates to
     * directly echo the event object result from eventWithHtml().
     *
     * @return  string  The HTML content from the event.
     *
     * @since   6.0.6
     */
    public function __toString(): string
    {
        return (string) ($this->arguments['html'] ?? '');
    }
}
