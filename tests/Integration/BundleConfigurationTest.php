<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Integration;

use IWD\SymfonyDoctrineSearch\Doctrine\Condition\CriteriaApplier;
use IWD\SymfonyDoctrineSearch\Http\Parser\PaginationParser;
use IWD\SymfonyDoctrineSearch\Http\QueryParameterNames;
use IWD\SymfonyDoctrineSearch\Http\SearchQueryValueResolver;
use IWD\SymfonyDoctrineSearch\Search\EntityReader;
use IWD\SymfonyDoctrineSearch\Search\EntitySearch;
use IWD\SymfonyDoctrineSearch\SymfonyDoctrineSearchBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(SymfonyDoctrineSearchBundle::class)]
final class BundleConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame([20, 5000], $container->getDefinition(PaginationParser::class)->getArguments());
        self::assertSame([2, false], array_slice($container->getDefinition(CriteriaApplier::class)->getArguments(), 3));
        self::assertTrue($container->hasAlias(EntitySearch::class));
        self::assertTrue($container->hasAlias(EntityReader::class));
        self::assertTrue($container->getDefinition(SearchQueryValueResolver::class)->hasTag('controller.argument_value_resolver'));
    }

    public function testValuesFromEnvironment(): void
    {
        $_SERVER['SEARCH_MAX_PAGE_SIZE'] = '100';
        $container = $this->load([
            'pagination' => ['max_size' => '%env(int:SEARCH_MAX_PAGE_SIZE)%'],
            'filters' => ['max_relation_depth' => 4, 'reject_unknown_properties' => true],
            'query_parameters' => ['logic' => 'logic'],
        ]);

        self::assertSame(['%env(int:SEARCH_MAX_PAGE_SIZE)%', 'logic'], [
            $container->resolveEnvPlaceholders($container->getDefinition(PaginationParser::class)->getArgument(1), '%%env(%s)%%'),
            $container->getDefinition(QueryParameterNames::class)->getArgument(4),
        ]);
        self::assertSame([4, true], array_slice($container->getDefinition(CriteriaApplier::class)->getArguments(), 3));
        unset($_SERVER['SEARCH_MAX_PAGE_SIZE']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $extension = new SymfonyDoctrineSearchBundle()->getContainerExtension();
        self::assertNotNull($extension);

        $container = new ContainerBuilder();
        $container->register('doctrine')->setSynthetic(true);
        $container->registerExtension($extension);
        $container->loadFromExtension($extension->getAlias(), $config);
        // Конфигурация расширений (и разбор %env()%) применяется на этапе компиляции, как в ядре.
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        return $container;
    }
}
