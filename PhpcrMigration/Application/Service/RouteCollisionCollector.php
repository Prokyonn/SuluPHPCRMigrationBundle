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

namespace Sulu\Bundle\PhpcrMigrationBundle\PhpcrMigration\Application\Service;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Routes whose slug was already taken by another document and were created under a suffixed slug instead.
 */
final class RouteCollisionCollector
{
    /**
     * @var list<array{
     *     resourceKey: string,
     *     resourceId: string,
     *     locale: string,
     *     requestedSlug: string,
     *     assignedSlug: string,
     *     ownerKey: string,
     *     ownerId: string,
     * }>
     */
    private array $collisions = [];

    public function record(
        string $resourceKey,
        string $resourceId,
        string $locale,
        string $requestedSlug,
        string $assignedSlug,
        string $ownerKey,
        string $ownerId,
    ): void {
        $this->collisions[] = [
            'resourceKey' => $resourceKey,
            'resourceId' => $resourceId,
            'locale' => $locale,
            'requestedSlug' => $requestedSlug,
            'assignedSlug' => $assignedSlug,
            'ownerKey' => $ownerKey,
            'ownerId' => $ownerId,
        ];
    }

    /**
     * @return list<array{
     *     resourceKey: string,
     *     resourceId: string,
     *     locale: string,
     *     requestedSlug: string,
     *     assignedSlug: string,
     *     ownerKey: string,
     *     ownerId: string,
     * }>
     */
    public function all(): array
    {
        return $this->collisions;
    }

    public function printSummary(SymfonyStyle $io): void
    {
        if ([] === $this->collisions) {
            return;
        }

        $io->warning(\sprintf(
            '%d route(s) were created under a suffixed slug because the slug was already taken. Review these URLs in the admin:',
            \count($this->collisions),
        ));
        $io->listing(\array_map(
            static fn (array $collision): string => \sprintf(
                '%s %s [%s]: %s -> %s (taken by %s::%s)',
                $collision['resourceKey'],
                $collision['resourceId'],
                $collision['locale'],
                $collision['requestedSlug'],
                $collision['assignedSlug'],
                $collision['ownerKey'],
                $collision['ownerId'],
            ),
            $this->collisions,
        ));
    }
}
