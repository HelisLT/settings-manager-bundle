<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Helis\SettingsManagerBundle\Model\SettingModel;

#[ORM\Entity()]
#[ORM\Table(name: "settings_test_setting")]
class Setting extends SettingModel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected ?int $id = null;

    #[ORM\ManyToMany(targetEntity: "Tag", cascade:["persist"], fetch:"EAGER")]
    #[ORM\JoinTable(name: "settings_test_setting__tag")]
    protected Collection $tags;

    public function __construct()
    {
        parent::__construct();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
