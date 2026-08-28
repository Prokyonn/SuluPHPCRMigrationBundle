<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\PhpcrMigrationBundle\Tests\Unit\PhpcrMigration\Application\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\PhpcrMigrationBundle\PhpcrMigration\Application\Persister\AbstractPersister;
use Sulu\Bundle\PhpcrMigrationBundle\PhpcrMigration\Application\Persister\PagePersister;
use Sulu\Bundle\PhpcrMigrationBundle\PhpcrMigration\Application\Repository\EntityRepositoryInterface;
use Sulu\Bundle\PhpcrMigrationBundle\PhpcrMigration\Application\Service\RouteCollisionCollector;
use Symfony\Component\PropertyAccess\PropertyAccess;

#[CoversClass(AbstractPersister::class)]
final class AbstractPersisterRouteCollisionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<EntityRepositoryInterface>
     */
    private ObjectProphecy $repository;

    private RouteCollisionCollector $collector;

    private PagePersister $persister;

    protected function setUp(): void
    {
        $this->repository = $this->prophesize(EntityRepositoryInterface::class);
        $this->collector = new RouteCollisionCollector();
        $this->persister = new PagePersister(
            PropertyAccess::createPropertyAccessor(),
            $this->repository->reveal(),
            $this->collector,
        );

        $this->repository->removeBy(AbstractPersister::ROUTE_TABLE, Argument::that(
            static fn (array $where): bool => ($where['resource_key'] ?? null) === AbstractPersister::ROUTE_RESOURCE_KEY
        ))->willReturn(0);
        $this->repository->findOneBy(AbstractPersister::ROUTE_TABLE, [
            'resource_id' => 'incoming-uuid',
            'resource_key' => 'pages',
            'locale' => 'de',
        ])->willReturn(['id' => 100]);
    }

    public function testDraftMovesToNextFreeSlugWhenSlugIsTakenByPublishedPage(): void
    {
        $this->stubSlugOwner('/foo', 'other-uuid');
        $this->stubSlugOwner('/foo-1', 'third-uuid');
        $this->stubSlugOwner('/foo-2', null);

        $this->repository->insertOrUpdate(
            Argument::withEntry('slug', '/foo-2'),
            AbstractPersister::ROUTE_TABLE,
            Argument::cetera(),
        )->shouldBeCalled();
        $this->repository->removeBy(AbstractPersister::ROUTE_TABLE, Argument::withKey('id'))->shouldNotBeCalled();

        $routes = $this->invokeCreateOrUpdateRoutes(state: 1);

        $this->assertSame(100, $routes['de']['id'], 'The draft keeps a route; Sulu 3.0 cannot restore or publish a page locale without one.');
        $this->assertSame(
            [['resourceKey' => 'pages', 'resourceId' => 'incoming-uuid', 'locale' => 'de', 'requestedSlug' => '/foo', 'assignedSlug' => '/foo-2', 'ownerKey' => 'pages', 'ownerId' => 'other-uuid']],
            $this->collector->all(),
        );
    }

    public function testRerunReusesTheSuffixedSlugAlreadyOwnedByTheDocument(): void
    {
        $this->stubSlugOwner('/foo', 'other-uuid');
        $this->stubSlugOwner('/foo-1', 'incoming-uuid');

        $this->repository->insertOrUpdate(
            Argument::withEntry('slug', '/foo-1'),
            AbstractPersister::ROUTE_TABLE,
            Argument::cetera(),
        )->shouldBeCalled();

        $this->invokeCreateOrUpdateRoutes(state: 1);

        $this->assertSame('/foo-1', $this->collector->all()[0]['assignedSlug'], 'A second migration run must not walk on to /foo-2.');
    }

    private function stubSlugOwner(string $slug, ?string $ownerUuid): void
    {
        $this->repository->findOneBy(AbstractPersister::ROUTE_TABLE, [
            'webspace' => 'website',
            'locale' => 'de',
            'slug' => $slug,
        ])->willReturn(null === $ownerUuid ? null : [
            'id' => \crc32($slug),
            'resource_id' => $ownerUuid,
            'resource_key' => 'pages',
            'locale' => 'de',
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function invokeCreateOrUpdateRoutes(int $state): array
    {
        $document = [
            'jcr' => ['uuid' => 'incoming-uuid', 'mixinTypes' => ['sulu:page']],
            'sulu' => ['webspaceKey' => 'website', 'parentId' => null],
            'localizations' => [
                'de' => [
                    'state' => $state,
                    'template' => 'default',
                    AbstractPersister::URL => '/foo',
                ],
            ],
        ];

        /** @var array<string, array<string, mixed>> $routes */
        $routes = (new \ReflectionMethod(PagePersister::class, 'createOrUpdateRoutes'))->invoke($this->persister, $document, false);

        return $routes;
    }
}
