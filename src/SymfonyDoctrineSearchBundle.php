<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch;

use IWD\SymfonyEntryContract\SymfonyEntryContractBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use SymfonyBundles\BundleDependency\BundleDependency;

//todo: добавить модуль авто-тестов для автоматической проверки корректности работы read, search-сценариев со всеми
// сущностями, которые есть в проекте.
//todo: добавить возможность опционально выбирать только часть полей для OutputContract
//todo: добавить возможность изменять поведение на передачу клиентом несуществующих фильтров
class SymfonyDoctrineSearchBundle extends Bundle
{
    use BundleDependency;

    public function getBundleDependencies(): array
    {
        return [
            SymfonyEntryContractBundle::class,
        ];
    }
}
