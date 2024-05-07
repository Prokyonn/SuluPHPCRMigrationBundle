<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\PhpcrMigrationBundle\PhpcrMigration\UserInterface\Command;

use PHPCR\SessionInterface;
use Sulu\Bundle\PhpcrMigrationBundle\PhpcrMigration\Application\Parser\NodeParser;
use Sulu\Bundle\PhpcrMigrationBundle\PhpcrMigration\Application\Persister\PersisterInterface;
use Sulu\Bundle\PhpcrMigrationBundle\PhpcrMigration\Application\Session\SessionManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'sulu:phpcr-migration:migrate', description: 'Migrate the PHPCR content repository to the SuluContentBundle.')]
class MigratePhpcrCommand extends Command
{
    public function __construct(
        private readonly SessionManager $sessionManager,
        private readonly NodeParser $nodeParser,
        private readonly PersisterInterface $articlePersister
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('documentTypes', InputArgument::OPTIONAL, 'The document type to migrate. (e.g. snippet, page, article)', 'article');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $session = $this->sessionManager->getDefaultSession();
        $liveSession = $this->sessionManager->getLiveSession();

        $documentTypes = \explode(',', $input->getArgument('documentTypes') ?? 'article');

        /** @var SessionInterface $session */
        foreach ([$session, $liveSession] as $session) {
            foreach ($documentTypes as $documentType) {
                $nodes = $this->fetchPhpcrNodes($session, $documentType);
                foreach ($nodes as $node) {
                    $document = $this->nodeParser->parse($node);
                    // TODO persisterPool
                    $this->articlePersister->persist($document, \str_ends_with($session->getWorkspace()->getName(), '_live'));
                }
            }
        }

        return Command::SUCCESS;
    }

    private function fetchPhpcrNodes(SessionInterface $session, string $documentType): \Traversable
    {
        $queryManager = $session->getWorkspace()->getQueryManager();

        $sql = \sprintf(
            'SELECT * FROM [nt:unstructured] as document WHERE [jcr:mixinTypes] = "sulu:%s"',
            $documentType
        );
        $query = $queryManager->createQuery($sql, 'JCR-SQL2');
        $result = $query->execute();

        return $result->getNodes();
    }
}