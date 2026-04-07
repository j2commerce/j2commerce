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
 * This event allows third-party plugins (like app_reviews) to inject
 * review and rating data into the Product schema. Plugins can add
 * aggregateRating and review properties.
 *
 * Event name: onJ2CommerceSchemaReviewsPrepare
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
