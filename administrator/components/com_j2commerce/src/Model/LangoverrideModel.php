<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Reads and writes a single language override, for one key, across every installed language.
 *
 * com_languages already owns this job, but its model resolves the target client and language from
 * `com_languages.overrides.filter.*` user state rather than from the data handed to it. Driving it
 * from here would rewrite whatever the merchant last selected on System -> Language Overrides. So
 * the file access goes through the same two core helpers com_languages itself calls -
 * LanguageHelper::parseIniFile() and ::saveToIniFile() - and nothing else is shared.
 */
class LangoverrideModel extends BaseDatabaseModel
{
    /**
     * Values Joomla's ini parser reads as booleans, so they can never be override keys.
     * Mirrors the guard in com_languages' OverrideModel::save().
     */
    private const RESERVED_WORDS = ['YES', 'NO', 'NULL', 'FALSE', 'ON', 'OFF', 'NONE', 'TRUE'];

    /**
     * Installed languages as tag => title, admin and site unioned: an override is written to both
     * clients, so a language present on either side is editable here.
     */
    public function getLanguages(): array
    {
        $languages = [];

        foreach ([0, 1] as $clientId) {
            foreach (LanguageHelper::getInstalledLanguages($clientId) as $language) {
                $languages[$language->element] ??= $language->name ?: $language->element;
            }
        }

        if (!$languages) {
            $languages['en-GB'] = 'en-GB';
        }

        ksort($languages);

        return $languages;
    }

    /**
     * The shipped value for a key, ignoring any override.
     *
     * Language::_() cannot answer this: the constructor loads the override file into
     * Language::$override and re-applies it on every load(), so the instance only ever reports the
     * overridden value. The files are read directly instead.
     */
    public function getOriginal(string $key, string $tag): string
    {
        $key   = strtoupper($key);
        $found = '';

        // Reverse Joomla's load order, so the copy loadLanguage() would reach first wins here.
        foreach ($this->candidateFiles($key, $tag) as $file) {
            if (!is_file($file)) {
                continue;
            }

            $strings = LanguageHelper::parseIniFile($file);

            if (isset($strings[$key])) {
                $found = (string) $strings[$key];
            }
        }

        return $found;
    }

    /**
     * The files that can hold a key, derived from the key itself.
     *
     * Joomla names an extension's language file after the extension and prefixes that extension's
     * keys with the same name uppercased, so PLG_J2COMMERCE_APP_SUBSCRIPTIONPRODUCT_EMAIL_SUBJECT
     * can only live in a file called plg_j2commerce_app_subscriptionproduct.ini or in a shorter
     * prefix of that name. Every underscore-bounded prefix is tried.
     *
     * Walking the extension trees instead - which is what com_languages' StringsModel does before
     * caching the result in #__overrider - takes about five seconds here. That is why core caches
     * it, and why this reads the handful of files the convention points at instead.
     */
    private function candidateFiles(string $key, string $tag): array
    {
        $tag   = $this->safeTag($tag);
        $parts = explode('_', strtolower($key));
        $names = [];

        // Shortest prefix first: the most specific file is therefore read last, and wins.
        for ($i = 1; $i < \count($parts); $i++) {
            $names[] = implode('_', \array_slice($parts, 0, $i));
        }

        $files = [];

        foreach ($names as $name) {
            // Ordered least- to most-specific: the client language folder is the installed mirror,
            // which is the copy Joomla loads first and so the one that must win.
            $files[] = JPATH_SITE . '/components/' . $name . '/language/' . $tag . '/' . $name . '.ini';
            $files[] = JPATH_ADMINISTRATOR . '/components/' . $name . '/language/' . $tag . '/' . $name . '.ini';
            $files[] = JPATH_SITE . '/modules/' . $name . '/language/' . $tag . '/' . $name . '.ini';
            $files[] = JPATH_ADMINISTRATOR . '/modules/' . $name . '/language/' . $tag . '/' . $name . '.ini';

            foreach (glob(JPATH_PLUGINS . '/*/*/language/' . $tag . '/' . $name . '.ini') ?: [] as $file) {
                $files[] = $file;
            }

            $files[] = JPATH_SITE . '/language/' . $tag . '/' . $name . '.ini';
            $files[] = JPATH_ADMINISTRATOR . '/language/' . $tag . '/' . $name . '.ini';
        }

        // Core strings carry no extension prefix of their own.
        $files[] = JPATH_SITE . '/language/' . $tag . '/joomla.ini';
        $files[] = JPATH_ADMINISTRATOR . '/language/' . $tag . '/joomla.ini';

        return $files;
    }

    /**
     * The current override for a key, or an empty string when none is set.
     */
    public function getOverride(string $key, string $tag): string
    {
        $strings = $this->parseOverrideFile($this->getOverrideFile($tag, 'administrator'));

        return (string) ($strings[strtoupper($key)] ?? '');
    }

    /**
     * Read an override file, deliberately bypassing LanguageHelper::parseIniFile().
     *
     * That helper caches parsed strings under a key of filename plus filemtime. filemtime has
     * one-second granularity, so two writes to an override file inside the same second reuse one
     * cache entry and every later read - in this request and in every request after it, until the
     * file changes again - is served the earlier content. Saving a subject and immediately undoing
     * it is exactly that pattern. The file is small and read once per dialog, so the cache buys
     * nothing here worth that risk. The post-parse unescaping matches what the helper does.
     */
    private function parseOverrideFile(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $strings = parse_ini_file($file, false, \INI_SCANNER_RAW);

        return \is_array($strings) ? str_replace('\\"', '"', $strings) : [];
    }

    /**
     * Write the override to both clients, or remove it when the text is back to the shipped value.
     *
     * Both clients always, deliberately: an email subject is rendered from the site on a customer
     * copy and from the administrator on the merchant copy, so an override on one side only would
     * make the same template say two different things.
     *
     * Returns the resulting state - original, override, and the wording that now takes effect - or
     * null on failure. It reports what it wrote rather than reading the files back, because
     * LanguageHelper::parseIniFile() caches parsed strings under a key of filename plus filemtime:
     * a second write inside the same wall-clock second lands on the same cache key and is read back
     * as the previous content.
     */
    public function saveOverride(string $key, string $tag, string $text): ?array
    {
        $key = strtoupper($key);

        if (\in_array($key, self::RESERVED_WORDS, true)) {
            $this->setError(Text::_('COM_LANGUAGES_OVERRIDE_ERROR_RESERVED_WORDS'));

            return null;
        }

        $original = $this->getOriginal($key, $tag);
        $remove   = $text === '' || $text === $original;

        foreach (['site', 'administrator'] as $client) {
            $file    = $this->getOverrideFile($tag, $client);
            $strings = $this->parseOverrideFile($file);

            if ($remove) {
                if (!isset($strings[$key])) {
                    continue;
                }

                unset($strings[$key]);
            } else {
                $strings = [$key => $text] + $strings;
            }

            if (LanguageHelper::saveToIniFile($file, $strings) === false) {
                $this->setError(Text::sprintf('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_WRITE_FAILED', $file));

                return null;
            }
        }

        return [
            'original' => $original,
            'override' => $remove ? '' : $text,
            'resolved' => $remove ? $original : $text,
        ];
    }

    private function getOverrideFile(string $tag, string $client): string
    {
        return \constant('JPATH_' . strtoupper($client)) . '/language/overrides/' . $this->safeTag($tag) . '.override.ini';
    }

    /**
     * The language tag, refused unless it is shaped like one.
     *
     * The controller already rejects any tag outside the installed set, so this never fires on the
     * dialog's own traffic. It is here because the tag reaches a filesystem path and these methods
     * are public: confinement that lives only in the one current caller is confinement a second
     * caller silently does without.
     */
    private function safeTag(string $tag): string
    {
        if (!preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $tag)) {
            throw new \InvalidArgumentException('Invalid language tag.');
        }

        return $tag;
    }
}
