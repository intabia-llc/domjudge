<?php declare(strict_types=1);

namespace DOMJudgeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use JMS\Serializer\Annotation as Serializer;

/**
 * Notification
 * @ORM\Entity()
 * @ORM\Table(name="notification", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 * @UniqueEntity("notid")
 */
class Notification
{

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="notid", options={"comment"="Unique ID"}, nullable=false)
     * @Serializer\SerializedName("id")
     */
    private $notid;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="cid", options={"comment"="Contest ID"}, nullable=true)
     */
    private $cid;

    /**
     * @var int
     * @ORM\Column(type="integer", name="userid", options={"comment"="User ID"}, nullable=true)
     */
    private $userid;

    /**
     * @var string
     * @ORM\Column(type="string", name="template", length=255, options={"comment"="Template message"}, nullable=true)
     */
    private $template;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Set notid
     *
     * @param $notid
     * @return Notification
     */
    public function setNotid($notid)
    {
        $this->$notid = $notid;

        return $this;
    }

    /**
     * Get notid
     *
     * @return int
     */
    public function getNotid()
    {
        return $this->notid;
    }

    /**
     * Set userid
     *
     * @param $userid
     * @return Notification
     */
    public function setUserid($userid)
    {
        $this->userid = $userid;

        return $this;
    }

    /**
     * Get userid
     *
     * @return int
     */
    public function getUserid()
    {
        return $this->userid;
    }

    /**
     * Set contest
     *
     * @param Integer/null $cid
     * @return Notification
     */
    public function setCid($cid)
    {
        $this->cid = $cid;
        return $this;
    }

    /**
     * Get cid
     *
     * @return int
     */
    public function getCid()
    {
        return $this->cid;
    }

    /**
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @param string $template
     */
    public function setTemplate(string $template)
    {
        $this->template = $template;
    }
}

