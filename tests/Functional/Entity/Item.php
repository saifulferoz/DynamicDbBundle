<?php

namespace Feroz\DynamicDbBundle\Tests\Functional\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Item
{
    #[ORM\Id]
    #[ORM\Column]
    #[ORM\GeneratedValue]
    public ?int $id = null;
}
