<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Schemaorg.ecommerce
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Schemaorg\Ecommerce\Event;

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
 * Note on multiple providers: addReview() appends, but setAggregateRating() replaces. Two
 * enabled providers therefore yield one provider's aggregate over both providers' reviews,
 * which do not agree. Enabling more than one provider per product is not currently a
 * supported configuration.
 *
 * Example usage in a plugin:
 * ```php
 * public function onReviewsPrepare(ReviewsSchemaPrepareEvent $event): void
 * {
 *     $productId = $event->getProductId();
 *     $reviews = $this->getReviewsForProduct($productId);
 *
 *     if (!empty($reviews)) {
 *         $event->setAggregateRating([
 *             '@type' => 'AggregateRating',
 *             'ratingValue' => 4.5,
 *             'reviewCount' => count($reviews),
 *             'bestRating' => 5,
 *             'worstRating' => 1
 *         ]);
 *
 *         $event->setReviews($reviews);
 *     }
 * }
 * ```
 *
 * @since  6.0.0
 */
class ReviewsSchemaPrepareEvent extends AbstractSchemaEvent
{
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
