<?php

namespace Flyo\Yii\Tests;

use Flyo\Model\Entity;
use Flyo\Model\EntityInterface;
use Flyo\Model\Meta;
use Flyo\Model\Page;
use Flyo\Yii\Traits\MetaDataTrait;
use yii\web\View;
use yii\web\Request;

class MetaDataTraitTest extends BaseTestCase
{
    private function createSubject(): object
    {
        return new class () {
            use MetaDataTrait;
        };
    }

    private function renderBody(): string
    {
        /** @var View $view */
        $view = $this->app->view;

        ob_start();
        $view->trigger(View::EVENT_BEGIN_BODY);
        return ob_get_clean();
    }

    public function testEmptyJsonldGeneratesNoScript()
    {
        $subject = $this->createSubject();

        $this->assertSame('', $subject->generateJsonldScript(null));
        $this->assertSame('', $subject->generateJsonldScript([]));
        $this->assertSame('', $subject->generateJsonldScript(new \stdClass()));
    }

    public function testJsonldScriptCanNotBreakOutOfTheScriptTag()
    {
        $script = $this->createSubject()->generateJsonldScript(['name' => '</script><script>alert(1)</script>']);

        $this->assertSame(1, substr_count($script, '</script>'));
        $this->assertStringStartsWith('<script type="application/ld+json">', $script);
        $this->assertStringNotContainsString('<script>alert', $script);
    }

    public function testJsonldIsRegisteredInBody()
    {
        $this->createSubject()->registerJsonld((object) ['@type' => 'WebPage']);

        $this->assertSame(
            '<script type="application/ld+json">{"@type":"WebPage"}</script>',
            $this->renderBody()
        );
    }

    public function testEmptyJsonldIsNotRegistered()
    {
        $this->createSubject()->registerJsonld(null);

        $this->assertSame('', $this->renderBody());
    }

    public function testPageRegistersMetaDataAndJsonld()
    {
        $page = new Page([
            'meta_json' => new Meta(['title' => 'Page Title', 'description' => 'Page Description']),
            'jsonld' => (object) ['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => 'Page Title'],
        ]);

        $this->createSubject()->registerPage($page);

        $this->assertSame('Page Title', $this->app->view->title);
        $this->assertStringContainsString('"@type":"WebPage"', $this->renderBody());
    }

    public function testPageWithoutJsonldRegistersNothingInBody()
    {
        $page = new Page([
            'meta_json' => new Meta(['title' => 'Page Title', 'description' => 'Page Description']),
        ]);

        $this->createSubject()->registerPage($page);

        $this->assertSame('Page Title', $this->app->view->title);
        $this->assertSame('', $this->renderBody());
    }

    public function testEntityRegistersMetaDataAndJsonld()
    {
        $entity = new Entity([
            'entity' => new EntityInterface(['entity_title' => 'Entity Title', 'entity_teaser' => 'Entity Teaser']),
            'jsonld' => (object) ['@context' => 'https://schema.org', '@type' => 'Article'],
        ]);

        $this->createSubject()->registerEntity($entity);

        $this->assertSame('Entity Title', $this->app->view->title);
        $this->assertStringContainsString('"@type":"Article"', $this->renderBody());
    }

    public function testEntityWithoutJsonldRegistersNothingInBody()
    {
        $entity = new Entity([
            'entity' => new EntityInterface(['entity_title' => 'Entity Title', 'entity_teaser' => 'Entity Teaser']),
        ]);

        $this->createSubject()->registerEntity($entity);

        $this->assertSame('', $this->renderBody());
    }

    public function testNonIndexablePageRegistersRobotsNoIndex()
    {
        $page = new Page([
            'meta_json' => new Meta(['title' => 'Page Title', 'description' => 'Page Description']),
            'is_indexable' => 0,
        ]);

        $this->createSubject()->registerPage($page);

        $this->assertStringContainsString('noindex', implode('', $this->app->view->metaTags));
    }

    public function testIndexablePageRegistersNoRobotsTag()
    {
        $page = new Page([
            'meta_json' => new Meta(['title' => 'Page Title', 'description' => 'Page Description']),
            'is_indexable' => 1,
        ]);

        $this->createSubject()->registerPage($page);

        $this->assertStringNotContainsString('noindex', implode('', $this->app->view->metaTags));
    }

    public function testPageWithoutIndexableInformationRegistersNoRobotsTag()
    {
        $page = new Page([
            'meta_json' => new Meta(['title' => 'Page Title', 'description' => 'Page Description']),
        ]);

        $this->createSubject()->registerPage($page);

        $this->assertStringNotContainsString('noindex', implode('', $this->app->view->metaTags));
    }

    public function testNonIndexableEntityRegistersRobotsNoIndex()
    {
        $entity = new Entity([
            'entity' => new EntityInterface(['entity_title' => 'Entity Title', 'entity_teaser' => 'Entity Teaser']),
            'is_indexable' => false,
        ]);

        $this->createSubject()->registerEntity($entity);

        $this->assertStringContainsString('noindex', implode('', $this->app->view->metaTags));
    }

    public function testIndexableEntityRegistersNoRobotsTag()
    {
        $entity = new Entity([
            'entity' => new EntityInterface(['entity_title' => 'Entity Title', 'entity_teaser' => 'Entity Teaser']),
            'is_indexable' => true,
        ]);

        $this->createSubject()->registerEntity($entity);

        $this->assertStringNotContainsString('noindex', implode('', $this->app->view->metaTags));
    }

    public function testRegisterCanonicalBuildsUrlFromDomainBase()
    {
        $request = new Request();
        $request->hostInfo = 'https://example.test';
        $request->baseUrl = '';
        $this->app->set('request', $request);

        $this->createSubject()->registerCanonical('/canonical-path');

        $this->assertStringContainsString(
            'href="https://example.test/canonical-path"',
            $this->app->view->linkTags[0]
        );
    }
}
