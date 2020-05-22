<?php


namespace App\Entity;


trait Timestamps
{
    /**
    * @ORM\Column(type="datetime")
    */
    private $createdAt;

    /**
    * @return mixed
    */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
    * @ORM\PrePersist
    */

    public function prePersist()
    {
     $this->createdAt = new \DateTime();
    }
}