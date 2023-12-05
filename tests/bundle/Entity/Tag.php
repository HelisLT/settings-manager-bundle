<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Helis\SettingsManagerBundle\Model\TagModel;

#[ORM\Entity()]
#[ORM\Table(name: "settings_test_tag")]
class Tag extends TagModel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
