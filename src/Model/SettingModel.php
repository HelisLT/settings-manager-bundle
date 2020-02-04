<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class SettingModel
{
    protected $name;
    protected $description;
    protected $domain;
    protected $tags;
    protected $type;
    protected $data = [];
    protected $providerName;
    protected $choices = [];

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function __clone()
    {
        if ($this->domain instanceof DomainModel) {
            $this->domain = clone $this->domain;
        }
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): SettingModel
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): SettingModel
    {
        $this->description = $description;

        return $this;
    }

    public function getDomain(): ?DomainModel
    {
        return $this->domain;
    }

    public function setDomain(DomainModel $domain): SettingModel
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * @return TagModel[]|Collection
     */
    public function getTags(): Collection
    {
        return $this->tags ?? new ArrayCollection();
    }

    /**
     * @param TagModel[]|Collection $tags
     */
    public function setTags(Collection $tags): SettingModel
    {
        $this->tags = $tags;

        return $this;
    }

    public function addTag(TagModel $tag): SettingModel
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function hasTag(string $string): bool
    {
        foreach ($this->tags as $tag) {
            if ($tag->getName() === $string) {
                return true;
            }
        }

        return false;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(Type $type): SettingModel
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return mixed|null
     */
    public function getData()
    {
        return isset($this->data['value']) ? $this->data['value'] : null;
    }

    public function setData($data): SettingModel
    {
        $this->data['value'] = $data;

        return $this;
    }

    /**
     * @internal Used by serializer
     */
    public function getDataValue(): array
    {
        return $this->data ?? [];
    }

    /**
     * @internal Used by serializer
     */
    public function setDataValue(array $data): SettingModel
    {
        $this->data = $data;

        return $this;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    public function setProviderName(string $providerName): SettingModel
    {
        $this->providerName = $providerName;

        return $this;
    }

    public function getChoices(): array
    {
        return $this->choices;
    }

    public function setChoices(array $choices): SettingModel
    {
        $this->choices = $choices;

        return $this;
    }
}
