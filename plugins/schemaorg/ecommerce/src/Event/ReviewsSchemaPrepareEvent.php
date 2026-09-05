<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Schemaorg.ecommerce
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Schemaorg\Ecommerce\Event;

use Joomla\CMS\Log\Log;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Event triggered when preparing Review/Rating schema data.
 *
 * This event is how a review provider -- app_reviews, app_trustpilot, or any future one --
 * contributes review and rating data to the product's schema. It is the only supported way
 * to do so: a provider that prints its own JSON-LD instead leaves the page with two Product
 * nodes describing one product, and the rating attached to the node that carries no offer
 * and no @id.
 *
 * Event name: onJ2CommerceSchemaReviewsPrepare
 *
 * What a provider must honour, and why:
 *
 * - Contribute only reviews the page is actually rendering. Google requires that "it must be
 *   immediately obvious to users that the page has review content", and that an
 *   AggregateRating shown in markup is visible on the page.
 * - Always pass ratingCount or reviewCount with an aggregate. Google requires at least one of
 *   the two; an aggregate without either is not eligible and the builder discards it.
 * - Contribute nothing when the product has no visible reviews. Do not set an empty review
 *   list or a zeroed aggregate -- absent is the correct output, and the builder strips those
 *   shells if they are set anyway.
 * - Never contribute reviews aggregated from another site. Google prohibits it, which matters
 *   most for providers backed by an external review service.
 *
 * Contribute through contribute(), not through the setters below. It takes the aggregate and
 * the reviews as one unit keyed by your plugin, so when two providers are enabled the first in
 * plugin order supplies both halves and the second is ignored rather than half-merged. The
 * setters remain for backwards compatibility and are NOT guarded: two providers both using
 * them still produce one provider's aggregate over both providers' reviews, which is the
 * situation contribute() exists to prevent.
 *
 * Example usage in a plugin:
 * ```php
 * public function onReviewsPrepare(ReviewsSchemaPrepareEvent $event): void
 * {
 *     $reviews = $this->getReviewsForProduct($event->getProductId());
 *
 *     if (empty($reviews)) {
 *         return;
 *     }
 *
 *     $event->contribute(
 *         'app_reviews',
 *         [
 *             '@type'       => 'AggregateRating',
 *             'ratingValue' => 4.5,
 *             'reviewCount' => \count($reviews),
 *             'bestRating'  => 5,
 *             'worstRating' => 1,
 *         ],
 *         $reviews
 *     );
 * }
 * ```
 *
 * @since  6.0.0
 */
class ReviewsSchemaPrepareEvent extends AbstractSchemaEvent
{
    /**
     * The plugin whose contribution this event has accepted, or null while none has been made.
     */
    private ?string $contributor = null;

    /**
     * Contribute this provider's review data, as one indivisible unit.
     *
     * The first provider to call wins, and a later call from a different plugin is ignored in
     * full -- both halves, not just the aggregate. That is the point: an aggregate from one
     * provider sitting over another provider's reviews describes neither of them, and Google
     * requires the rating in the markup to be the one the visitor can see on the page.
     *
     * Which provider is first is decided by plugin ordering, because PluginHelper::load()
     * orders by `ordering` and the dispatcher appends listeners within a priority band. So an
     * admin who wants the other provider to win reorders them in the Plugins manager; there is
     * no separate setting for it.
     *
     * The same plugin may call again to refine its own contribution.
     *
     * @param   string  $pluginId          Identifies the contributing plugin, e.g. 'app_reviews'.
     * @param   array   $aggregateRating   AggregateRating schema, or [] to contribute none.
     * @param   array   $reviews           Review schema entries, or [] to contribute none.
     *
     * @return  bool  True when this contribution was accepted.
     */
    public function contribute(string $pluginId, array $aggregateRating = [], array $reviews = []): bool
    {
        if ($this->contributor !== null && $this->contributor !== $pluginId) {
            // Silence here would leave an admin with two providers enabled, one of them
            // missing from the page, and nothing anywhere saying why.
            Log::add(
                \sprintf(
                    'Review schema from "%s" ignored: "%s" contributed first. Reorder the plugins to change which one is used.',
                    $pluginId,
                    $this->contributor
                ),
                Log::WARNING,
                'schemaorg'
            );

            return false;
        }

        $this->contributor = $pluginId;

        if ($aggregateRating !== []) {
            $this->setSchemaProperty('aggregateRating', $aggregateRating);
        }

        if ($reviews !== []) {
            $this->setSchemaProperty('review', $reviews);
        }

        return true;
    }

    /** The plugin whose contribution was accepted, or null if none has been made. */
    public function getContributor(): ?string
    {
        return $this->contributor;
    }

    /**
     * Constructor.
     *
     * @param   string  $name       The event name.
     * @param   array   $arguments  The event arguments.
     *
     * @throws  \BadMethodCallException
     *
     * @since   6.0.0
     */
    public function __construct(string $name, array $arguments = [])
    {
        if (!\array_key_exists('productId', $arguments)) {
            throw new \BadMethodCallException("Argument 'productId' of event {$name} is required but has not been provided");
        }

        parent::__construct($name, $arguments);
    }

    /**
     * Setter for the subject argument (schema data).
     *
     * @param   array  $value  The value to set
     *
     * @return  array
     *
     * @since   6.0.0
     */
    protected function onSetSubject(array $value): array
    {
        return $value;
    }

    /**
     * Get the product ID.
     *
     * @return  int  The product ID
     *
     * @since   6.0.0
     */
    public function getProductId(): int
    {
        return (int) $this->arguments['productId'];
    }

    /**
     * Get the article ID if available.
     *
     * @return  int|null  The article ID or null
     *
     * @since   6.0.0
     */
    public function getArticleId(): ?int
    {
        return $this->arguments['articleId'] ?? null;
    }

    /**
     * Set the aggregate rating schema.
     *
     * @param   array  $aggregateRating  The AggregateRating schema array
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setAggregateRating(array $aggregateRating): void
    {
        $this->setSchemaProperty('aggregateRating', $aggregateRating);
    }

    /**
     * Get the aggregate rating schema.
     *
     * @return  array|null  The AggregateRating schema or null
     *
     * @since   6.0.0
     */
    public function getAggregateRating(): ?array
    {
        return $this->getSchemaProperty('aggregateRating');
    }

    /**
     * Set the reviews array.
     *
     * @param   array  $reviews  Array of Review schema objects
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setReviews(array $reviews): void
    {
        $this->setSchemaProperty('review', $reviews);
    }

    /**
     * Get the reviews array.
     *
     * @return  array  Array of Review schema objects
     *
     * @since   6.0.0
     */
    public function getReviews(): array
    {
        return $this->getSchemaProperty('review', []);
    }

    /**
     * Add a single review to the reviews array.
     *
     * @param   array  $review  A Review schema object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function addReview(array $review): void
    {
        $reviews   = $this->getReviews();
        $reviews[] = $review;
        $this->setReviews($reviews);
    }

    /**
     * Check if reviews have been added.
     *
     * @return  bool  True if reviews exist
     *
     * @since   6.0.0
     */
    public function hasReviews(): bool
    {
        return !empty($this->getReviews());
    }

    /**
     * Check if aggregate rating has been set.
     *
     * @return  bool  True if aggregate rating exists
     *
     * @since   6.0.0
     */
    public function hasAggregateRating(): bool
    {
        return $this->hasSchemaProperty('aggregateRating');
    }
}
