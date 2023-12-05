<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Settings\Traits;

use Helis\SettingsManagerBundle\Model\DomainModel;

trait DomainNameExtractTrait
{
    /**
     * @param DomainModel[] $domainModels
     *
     * @return string[]
     */
    protected function extractDomainNames(array $domainModels): array
    {
        return array_map(fn (DomainModel $model) => $model->getName(), $domainModels);
    }
}
