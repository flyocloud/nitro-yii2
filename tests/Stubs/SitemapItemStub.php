<?php

namespace Flyo\Yii\Tests\Stubs;

/**
 * A sitemap item as delivered by the sitemap endpoint, including the `updated_at` value.
 */
class SitemapItemStub
{
    public function __construct(private ?string $href, private ?int $updatedAt = null)
    {
    }

    public function getHref(): ?string
    {
        return $this->href;
    }

    public function getUpdatedAt(): ?int
    {
        return $this->updatedAt;
    }
}
