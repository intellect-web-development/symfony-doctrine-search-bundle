<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch;

use IWD\SymfonyDoctrineSearch\Doctrine\Condition\ConditionFactory;
use IWD\SymfonyDoctrineSearch\Doctrine\Condition\CriteriaApplier;
use IWD\SymfonyDoctrineSearch\Doctrine\DoctrineEntityReader;
use IWD\SymfonyDoctrineSearch\Doctrine\DoctrineEntitySearch;
use IWD\SymfonyDoctrineSearch\Doctrine\Eager\EagerLoader;
use IWD\SymfonyDoctrineSearch\Doctrine\Path\PropertyPathResolver;
use IWD\SymfonyDoctrineSearch\Doctrine\Value\ValueKindResolver;
use IWD\SymfonyDoctrineSearch\Http\Parser\FilterParser;
use IWD\SymfonyDoctrineSearch\Http\Parser\PaginationParser;
use IWD\SymfonyDoctrineSearch\Http\Parser\SortParser;
use IWD\SymfonyDoctrineSearch\Http\QueryParameterNames;
use IWD\SymfonyDoctrineSearch\Http\RequestSearchQueryFactory;
use IWD\SymfonyDoctrineSearch\Http\SearchQueryFactory;
use IWD\SymfonyDoctrineSearch\Http\SearchQueryValueResolver;
use IWD\SymfonyDoctrineSearch\Search\EntityReader;
use IWD\SymfonyDoctrineSearch\Search\EntitySearch;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Конфигурация (`config/packages/symfony_doctrine_search.yaml`), все значения принимают `%env()%`:
 *
 *     symfony_doctrine_search:
 *         pagination: { default_size: 20, max_size: 5000 }
 *         filters: { max_relation_depth: 2, reject_unknown_properties: false }
 *         eager_load: { depth: 1 }
 *         query_parameters: { filter: filter, compact_filter: filters, sort: sort, page: page, logic: strategy }
 */
final class SymfonyDoctrineSearchBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('pagination')->addDefaultsIfNotSet()->children()
                    ->integerNode('default_size')->defaultValue(20)->info('Размер страницы, если клиент его не передал.')->end()
                    ->integerNode('max_size')->defaultValue(5000)->info('Больший размер страницы урезается до этого.')->end()
                ->end()->end()
                ->arrayNode('filters')->addDefaultsIfNotSet()->children()
                    ->integerNode('max_relation_depth')->defaultValue(2)
                        ->info('Через сколько связей клиент может фильтровать (a.b.field — 2). На ограничения не влияет.')->end()
                    ->booleanNode('reject_unknown_properties')->defaultFalse()
                        ->info('true — фильтр или сортировка по неизвестному свойству даёт ошибку, false — молча пропускается.')->end()
                ->end()->end()
                ->arrayNode('eager_load')->addDefaultsIfNotSet()->children()
                    ->integerNode('depth')->defaultValue(1)->info('Сколько уровней связей подгружать при eager.')->end()
                ->end()->end()
                ->arrayNode('query_parameters')->addDefaultsIfNotSet()->children()
                    ->scalarNode('filter')->defaultValue('filter')->end()
                    ->scalarNode('compact_filter')->defaultValue('filters')->end()
                    ->scalarNode('sort')->defaultValue('sort')->end()
                    ->scalarNode('page')->defaultValue('page')->end()
                    ->scalarNode('logic')->defaultValue('strategy')->end()
                ->end()->end()
            ->end();
    }

    /**
     * @param array{
     *     pagination: array{default_size: int, max_size: int},
     *     filters: array{max_relation_depth: int, reject_unknown_properties: bool},
     *     eager_load: array{depth: int},
     *     query_parameters: array{filter: string, compact_filter: string, sort: string, page: string, logic: string},
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set(PropertyPathResolver::class);
        $services->set(ValueKindResolver::class);
        $services->set(ConditionFactory::class);
        $services->set(EagerLoader::class)->args([$config['eager_load']['depth']]);
        $services->set(CriteriaApplier::class)->args([
            service(PropertyPathResolver::class),
            service(ValueKindResolver::class),
            service(ConditionFactory::class),
            $config['filters']['max_relation_depth'],
            $config['filters']['reject_unknown_properties'],
        ]);

        $services->set(DoctrineEntitySearch::class)->args([
            service('doctrine'),
            service(CriteriaApplier::class),
            service(EagerLoader::class),
            $config['pagination']['default_size'],
            $config['filters']['reject_unknown_properties'],
        ]);
        $services->alias(EntitySearch::class, DoctrineEntitySearch::class);

        $services->set(DoctrineEntityReader::class)->args([
            service('doctrine'),
            service(CriteriaApplier::class),
            service(ValueKindResolver::class),
            service(EagerLoader::class),
        ]);
        $services->alias(EntityReader::class, DoctrineEntityReader::class);

        $services->set(QueryParameterNames::class)->args([
            $config['query_parameters']['filter'],
            $config['query_parameters']['compact_filter'],
            $config['query_parameters']['sort'],
            $config['query_parameters']['page'],
            $config['query_parameters']['logic'],
        ]);
        $services->set(FilterParser::class);
        $services->set(SortParser::class);
        $services->set(PaginationParser::class)->args([
            $config['pagination']['default_size'],
            $config['pagination']['max_size'],
        ]);
        $services->set(RequestSearchQueryFactory::class)->args([
            service(QueryParameterNames::class),
            service(FilterParser::class),
            service(SortParser::class),
            service(PaginationParser::class),
        ]);
        $services->alias(SearchQueryFactory::class, RequestSearchQueryFactory::class);

        $services->set(SearchQueryValueResolver::class)
            ->args([service(SearchQueryFactory::class)])
            ->tag('controller.argument_value_resolver', ['priority' => 150]);
    }
}
