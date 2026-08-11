<?php

namespace Flyo\Yii\Tests;

use Flyo\Model\EntityinterfaceInner;
use Flyo\Yii\Actions\SitemapAction;
use Flyo\Yii\Tests\Stubs\SitemapItemStub;
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
        $this->assertStringNotContainsString('detail', $action->generateXml([new SitemapItemStub('/about-me')]));
    }

    public function testHrefIsUsedAsLocation()
    {
        $xml = $this->createAction()->generateXml([
            new SitemapItemStub('/about-me'),
            new SitemapItemStub('news/news-title-1'),
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
            new SitemapItemStub('/about-me', 1770000000),
        ]);

        $this->assertStringContainsString('<loc>https://example.com/about-me</loc><lastmod>2026-02-02T02:40:00+00:00</lastmod>', $xml);
    }

    public function testItemWithoutUpdatedAtHasNoLastmod()
    {
        $xml = $this->createAction()->generateXml([
            new SitemapItemStub('/about-me', 0),
            new SitemapItemStub('/contact', null),
            // an sdk model which does not expose the `updated_at` value at all
            new EntityinterfaceInner(['href' => '/imprint']),
        ]);

        $this->assertStringNotContainsString('<lastmod>', $xml);
        $this->assertStringContainsString('<url><loc>https://example.com/imprint</loc></url>', $xml);
    }

    public function testItemsWithoutHrefAreSkipped()
    {
        $xml = $this->createAction()->generateXml([
            new SitemapItemStub(null),
            new SitemapItemStub(''),
            new SitemapItemStub('/about-me'),
        ]);

        $this->assertSame(1, substr_count($xml, '<url>'));
    }

    public function testDuplicateHrefsAreSkipped()
    {
        $xml = $this->createAction()->generateXml([
            new SitemapItemStub('/about-me', 1770000000),
            new SitemapItemStub('/about-me', 1780000000),
        ]);

        $this->assertSame(1, substr_count($xml, '<url>'));
        $this->assertStringContainsString('<lastmod>2026-02-02T02:40:00+00:00</lastmod>', $xml);
    }
}
