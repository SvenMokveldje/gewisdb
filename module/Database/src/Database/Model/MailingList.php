<?php

namespace Database\Model;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Mailing List model.
 *
 * @ORM\Entity
 */
class MailingList
{

    /**
     * Mailman-identifier / name.
     *
     * @ORM\Id
     * @ORM\Column(type="string")
     */
    protected $name;

    /**
     * Description of the mailing list.
     *
     * @ORM\Column(type="text")
     */
    protected $description;

    /**
     * If the mailing list should be on the form.
     *
     * @ORM\Column(type="boolean")
     */
    protected $onForm;

    /**
     * If members should be subscribed by default.
     *
     * (when it is on the form, that means that the checkbox is checked by default)
     *
     * @ORM\Column(type="boolean")
     */
    protected $defaultSub;

    /**
     * Mailing list members.
     *
     * @ORM\ManyToMany(targetEntity="Member", mappedBy="lists")
     */
    protected $members;


    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->members = new ArrayCollection();
    }

    /**
     * Get the name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the name.
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get the description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set the description.
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Get if it should be on the form.
     *
     * @return boolean
     */
    public function getOnForm()
    {
        return $this->onForm;
    }

    /**
     * Set if it should be on the form.
     *
     * @param boolean $onForm
     */
    public function setOnForm($onForm)
    {
        $this->onForm = $onForm;
    }

    /**
     * Get if it is a default list.
     *
     * @return boolean
     */
    public function getDefaultSub()
    {
        return $this->defaultSub;
    }

    /**
     * Set if it is a default list.
     *
     * @param boolean $default
     */
    public function setDefaultSub($default)
    {
        $this->defaultSub = $default;
    }

    /**
     * Get subscribed members.
     *
     * @return ArrayCollection of members
     */
    public function getMembers()
    {
        return $this->name;
    }

    /**
     * Add a member.
     *
     * @param Member $member
     */
    public function addMember(Member $member)
    {
        $this->members[] = $member;
    }
}
