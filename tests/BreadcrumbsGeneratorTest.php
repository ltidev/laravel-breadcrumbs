<?php

namespace BabDev\Breadcrumbs\Tests;

use BabDev\Breadcrumbs\BreadcrumbsGenerator;
use BabDev\Breadcrumbs\Contracts\BreadcrumbsGenerator as BreadcrumbsGeneratorContract;
use BabDev\Breadcrumbs\Events\AfterBreadcrumbGenerated;
use BabDev\Breadcrumbs\Events\BeforeBreadcrumbGenerated;
use BabDev\Breadcrumbs\Exceptions\InvalidBreadcrumbException;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\TestCase;

class BreadcrumbsGeneratorTest extends TestCase
{
    public function testGeneratesABreadcrumb(): void
    {
        $callbacks = [
            'blog' => static function (BreadcrumbsGeneratorContract $trail): void {
                $trail->push('Blog', '/blog');
            },
        ];

        $breadcrumbs = (new BreadcrumbsGenerator($this->createMock(Dispatcher::class)))->generate($callbacks, 'blog', []);

        $this->assertCount(1, $breadcrumbs);
        $this->assertEquals(
            [
                (object) [
                    'title' => 'Blog',
                    'url' => '/blog',
                ],
            ],
            $breadcrumbs->toArray()
        );
    }

    public function testGeneratesABreadcrumbWithBeforeAndAfterEvents(): void
    {
        $dispatcher = $this->createMock(Dispatcher::class);

        $dispatcher->expects($this->at(0))
            ->method('dispatch')
            ->with($this->isInstanceOf(BeforeBreadcrumbGenerated::class))
            ->willReturnCallback(static function (BeforeBreadcrumbGenerated $event): void {
                $event->breadcrumbs->push('Home', '/');
            });

        $dispatcher->expects($this->at(1))
            ->method('dispatch')
            ->with($this->isInstanceOf(AfterBreadcrumbGenerated::class))
            ->willReturnCallback(static function (AfterBreadcrumbGenerated $event): void {
                $event->breadcrumbs->push('Page 2', '/page-2');
            });

        $callbacks = [
            'blog' => static function (BreadcrumbsGeneratorContract $trail): void {
                $trail->push('Blog', '/blog');
            },
        ];

        $breadcrumbs = (new BreadcrumbsGenerator($dispatcher))->generate($callbacks, 'blog', []);

        $this->assertCount(3, $breadcrumbs);
        $this->assertEquals(
            [
                (object) [
                    'title' => 'Home',
                    'url' => '/',
                ],
                (object) [
                    'title' => 'Blog',
                    'url' => '/blog',
                ],
                (object) [
                    'title' => 'Page 2',
                    'url' => '/page-2',
                ],
            ],
            $breadcrumbs->toArray()
        );
    }

    public function testGeneratesABreadcrumbWithAParentAndAnItemWithNoUrl(): void
    {
        $callbacks = [
            'home' => static function (BreadcrumbsGeneratorContract $trail): void {
                $trail->push('Home', '/');
            },
            'blog' => static function (BreadcrumbsGeneratorContract $trail): void {
                $trail->parent('home');
                $trail->push('Blog');
            },
        ];

        $breadcrumbs = (new BreadcrumbsGenerator($this->createMock(Dispatcher::class)))->generate($callbacks, 'blog', []);

        $this->assertCount(2, $breadcrumbs);
        $this->assertEquals(
            [
                (object) [
                    'title' => 'Home',
                    'url' => '/',
                ],
                (object) [
                    'title' => 'Blog',
                    'url' => null,
                ],
            ],
            $breadcrumbs->toArray()
        );
    }

    public function testGeneratesABreadcrumbWithCustomAttributes(): void
    {
        $callbacks = [
            'blog' => static function (BreadcrumbsGeneratorContract $trail): void {
                $trail->push('Blog', '/blog', ['icon' => 'blog']);
            },
        ];

        $breadcrumbs = (new BreadcrumbsGenerator($this->createMock(Dispatcher::class)))->generate($callbacks, 'blog', []);

        $this->assertCount(1, $breadcrumbs);
        $this->assertEquals(
            [
                (object) [
                    'title' => 'Blog',
                    'url' => '/blog',
                    'icon' => 'blog',
                ],
            ],
            $breadcrumbs->toArray()
        );
    }

    public function testGeneratesABreadcrumbWithARecursiveCallback(): void
    {
        $category1 = (object) ['id' => 1, 'title' => 'Category 1', 'parent' => null];
        $category2 = (object) ['id' => 2, 'title' => 'Category 2', 'parent' => $category1];
        $category3 = (object) ['id' => 3, 'title' => 'Category 3', 'parent' => $category2];

        $callbacks = [
            'blog' => static function (BreadcrumbsGeneratorContract $trail): void {
                $trail->push('Blog', '/blog');
            },
            'category' => static function (BreadcrumbsGeneratorContract $trail, object $category): void {
                if ($category->parent) {
                    $trail->parent('category', $category->parent);
                } else {
                    $trail->parent('blog');
                }

                $trail->push($category->title, \sprintf('/category/%s', $category->id));
            },
        ];

        $breadcrumbs = (new BreadcrumbsGenerator($this->createMock(Dispatcher::class)))->generate($callbacks, 'category', [$category3]);

        $this->assertCount(4, $breadcrumbs);
        $this->assertEquals(
            [
                (object) [
                    'title' => 'Blog',
                    'url' => '/blog',
                ],
                (object) [
                    'title' => 'Category 1',
                    'url' => '/category/1',
                ],
                (object) [
                    'title' => 'Category 2',
                    'url' => '/category/2',
                ],
                (object) [
                    'title' => 'Category 3',
                    'url' => '/category/3',
                ],
            ],
            $breadcrumbs->toArray()
        );
    }

    public function testDoesNotGenerateABreadcrumbForAnUnknownName(): void
    {
        $this->expectException(InvalidBreadcrumbException::class);

        (new BreadcrumbsGenerator($this->createMock(Dispatcher::class)))->generate([], 'blog', []);
    }
}
