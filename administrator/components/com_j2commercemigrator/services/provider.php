<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commercemigrator\Administrator\Extension\J2commercemigratorComponent;
use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\AdapterRegistry;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\ConfigurationMigrator;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\ConnectionManager;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\DataTransformer;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\ErrorRepository;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\IdmapRepository;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\ImageCopyService;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\ImageRebuildService;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\J2CoreMigrator;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\MenuMigrator;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\MigrationEngine;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\PostMigrationNormalizer;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\PreflightAnalyzer;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\PreflightRepository;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\RunRepository;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\SchemaIntrospector;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\SubTemplateMigrator;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\TemplateCodeTransformer;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\VerificationService;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('\\J2Commerce\\Component\\J2commercemigrator'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\J2Commerce\\Component\\J2commercemigrator'));

        $container->set(
            ComponentInterface::class,
            static function (Container $container) {
                $component = new J2commercemigratorComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                return $component;
            }
        );

        // ── Stateless / no-arg services ────────────────────────────────────────
        $container->set(MigrationLogger::class, static fn () => new MigrationLogger());
        $container->set(AdapterRegistry::class, static fn () => new AdapterRegistry());
        $container->set(DataTransformer::class, static fn () => new DataTransformer());
        $container->set(TemplateCodeTransformer::class, static fn () => new TemplateCodeTransformer());

        // ── Repositories (DatabaseInterface only) ──────────────────────────────
        $container->set(RunRepository::class, static fn (Container $c) => new RunRepository($c->get(DatabaseInterface::class)));
        $container->set(IdmapRepository::class, static fn (Container $c) => new IdmapRepository($c->get(DatabaseInterface::class)));
        $container->set(ErrorRepository::class, static fn (Container $c) => new ErrorRepository($c->get(DatabaseInterface::class)));
        $container->set(PreflightRepository::class, static fn (Container $c) => new PreflightRepository($c->get(DatabaseInterface::class)));

        // ── Schema / introspection ─────────────────────────────────────────────
        $container->set(SchemaIntrospector::class, static fn (Container $c) => new SchemaIntrospector($c->get(DatabaseInterface::class)));

        // ── Connection manager (needs Application + DB) ────────────────────────
        $container->set(
            ConnectionManager::class,
            static fn (Container $c) => new ConnectionManager(
                $c->get(CMSApplicationInterface::class),
                $c->get(DatabaseInterface::class)
            )
        );

        // ── Migration services (DB + Logger; reader defaults to JoomlaSourceReader) ──
        $container->set(
            PostMigrationNormalizer::class,
            static fn (Container $c) => new PostMigrationNormalizer(
                $c->get(DatabaseInterface::class),
                $c->get(MigrationLogger::class)
            )
        );

        $container->set(
            MenuMigrator::class,
            static fn (Container $c) => new MenuMigrator(
                $c->get(DatabaseInterface::class),
                $c->get(MigrationLogger::class)
            )
        );

        $container->set(
            ConfigurationMigrator::class,
            static fn (Container $c) => new ConfigurationMigrator(
                $c->get(DatabaseInterface::class),
                $c->get(MigrationLogger::class)
            )
        );

        $container->set(
            MigrationEngine::class,
            static fn (Container $c) => new MigrationEngine(
                $c->get(DatabaseInterface::class),
                $c->get(MigrationLogger::class)
            )
        );

        $container->set(
            J2CoreMigrator::class,
            static fn (Container $c) => new J2CoreMigrator(
                $c->get(DatabaseInterface::class),
                $c->get(MigrationLogger::class)
            )
        );

        // ── Preflight analyzer (DB; optionally uses PreflightRepository) ───────
        $container->set(
            PreflightAnalyzer::class,
            static fn (Container $c) => new PreflightAnalyzer(
                $c->get(DatabaseInterface::class),
                null,
                $c->get(PreflightRepository::class)
            )
        );

        // ── Verification service (DB; reader defaults to JoomlaSourceReader) ───
        $container->set(
            VerificationService::class,
            static fn (Container $c) => new VerificationService($c->get(DatabaseInterface::class))
        );

        // ── Template/SubTemplate services ──────────────────────────────────────
        $container->set(
            SubTemplateMigrator::class,
            static fn (Container $c) => new SubTemplateMigrator($c->get(MigrationLogger::class))
        );

        // ── Image services ─────────────────────────────────────────────────────
        $container->set(
            ImageCopyService::class,
            static fn (Container $c) => new ImageCopyService($c->get(MigrationLogger::class))
        );

        $container->set(
            ImageRebuildService::class,
            static fn (Container $c) => new ImageRebuildService(
                $c->get(DatabaseInterface::class),
                $c->get(MigrationLogger::class)
            )
        );

        // NOTE: ImageManifestService is NOT registered here.
        // Its constructor requires a non-null SourceDatabaseReaderInterface (runtime
        // PDO connection supplied by the user), so callers must construct it ad-hoc
        // once ConnectionManager::getReader() returns a live reader.
    }
};
