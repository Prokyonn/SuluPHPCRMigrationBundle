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

use PHPCR\NodeInterface;

class LocaleDiscoveryService
{
    /**
     * @var array<string, string[]>
     */
    private array $localeCache = [];

    /**
     * @return string[]
     */
    public function discoverLocales(NodeInterface $node): array
    {
        $nodeIdentifier = $node->getIdentifier();
        if (isset($this->localeCache[$nodeIdentifier])) {
            return $this->localeCache[$nodeIdentifier];
        }

        $localesWithTitle = [];
        $localesWithTemplate = [];
        $discoveredLocales = [];
        foreach ($node->getProperties('i18n:*') as $property) {
            $name = $property->getName();

            $afterPrefix = \substr($name, 5);

            if (\str_ends_with($afterPrefix, '-changed')) {
                $locale = \substr($afterPrefix, 0, -\strlen('-changed'));

                if (!isset($discoveredLocales[$locale])) {
                    $localesWithTitle[$locale] = true;

                    if (isset($localesWithTemplate[$locale])) {
                        $discoveredLocales[$locale] = $locale;
                    }
                }
            }

            if (\str_ends_with($afterPrefix, '-created')) {
                $locale = \substr($afterPrefix, 0, -\strlen('-created'));

                if (!isset($discoveredLocales[$locale])) {
                    $localesWithTemplate[$locale] = true;

                    if (isset($localesWithTitle[$locale])) {
                        $discoveredLocales[$locale] = $locale;
                    }
                }
            }
        }

        $this->localeCache[$nodeIdentifier] = $discoveredLocales;

        return $discoveredLocales;
    }

    public function clearCache(): void
    {
        $this->localeCache = [];
    }
}
