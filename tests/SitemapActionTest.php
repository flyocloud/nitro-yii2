<?php

namespace Flyo\Yii\Tests;

use Flyo\Model\SitemapinterfaceInner;
use Flyo\Yii\Actions\SitemapAction;
use yii\base\InvalidConfigException;
use yii\console\Controller;

class SitemapActionTest extends BaseTestCase
{
    private function createAction(): SitemapAction
    {
        return new SitemapAction('sitemap', new Controller('test', $this->app), [
            'domain' => 'https://example.com/',
        ]);
    }

    private function createItem(?string $href, ?int $updatedAt = null): SitemapinterfaceInner
    {
        return new SitemapinterfaceInner(['href' => $href, 'updated_at' => $updatedAt]);
    }

    public function testDomainIsRequired()
    {
        $this->expectException(InvalidConfigException::class);
        new SitemapAction('sitemap', new Controller('test', $this->app));
    }

    public function testDetailRouteNameIsDeprecated()
    {
        $notices = [];
        set_error_handler(function ($errno, $errstr) use (&$notices) {
            $notices[] = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        $action = new SitemapAction('sitemap', new Controller('test', $this->app), [
            'domain' => 'https://example.com',
            'detailRouteName' => 'detail',
        ]);

        restore_error_handler();

        $this->assertCount(1, $notices);
        $this->assertStringContainsString('is deprecated and has no effect', $notices[0]);
        $this->assertStringNotContainsString('detail', $action->generateXml([$this->createItem('/about-me')]));
    }

    public function testHrefIsUsedAsLocation()
    {
        $xml = $this->createAction()->generateXml([
            $this->createItem('/about-me'),
            $this->createItem('news/news-title-1'),
        ]);

        $this->assertSame(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . '<url><loc>https://example.com/about-me</loc></url>'
            . '<url><loc>https://example.com/news/news-title-1</loc></url>'
            . '</urlset>',
            $xml
        );
    }

    public function testUpdatedAtIsUsedAsLastmod()
    {
        $xml = $this->createAction()->generateXml([
            $this->createItem('/about-me', 1770000000),
        ]);

        $this->assertStringContainsString('<loc>https://example.com/about-me</loc><lastmod>2026-02-02T02:40:00+00:00</lastmod>', $xml);
    }

    public function testItemWithoutUpdatedAtHasNoLastmod()
    {
        $xml = $this->createAction()->generateXml([
            $this->createItem('/about-me', 0),
            $this->createItem('/contact', null),
        ]);

        $this->assertStringNotContainsString('<lastmod>', $xml);
        $this->assertStringContainsString('<url><loc>https://example.com/contact</loc></url>', $xml);
    }

    public function testItemsWithoutHrefAreSkipped()
    {
        $xml = $this->createAction()->generateXml([
            $this->createItem(null),
            $this->createItem(''),
            $this->createItem('/about-me'),
        ]);

        $this->assertSame(1, substr_count($xml, '<url>'));
    }

    public function testDuplicateHrefsAreSkipped()
    {
        $xml = $this->createAction()->generateXml([
            $this->createItem('/about-me', 1770000000),
            $this->createItem('/about-me', 1780000000),
        ]);

        $this->assertSame(1, substr_count($xml, '<url>'));
        $this->assertStringContainsString('<lastmod>2026-02-02T02:40:00+00:00</lastmod>', $xml);
    }
}
