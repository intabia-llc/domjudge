<?php declare(strict_types=1);

namespace DOMJudgeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Notification;
use DOMJudgeBundle\Entity\User;
use FOS\RestBundle\View\View;
use phpDocumentor\Reflection\Types\Integer;
use Swift_SendmailTransport;
use Swift_Transport_LoadBalancedTransport;


/**
 * Class NotificationService
 * @package DOMJudgeBundle\Service
 */
class NotificationService extends BaseController
{

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
    }

    /**
     * @param User $user
     * @param string $subject
     * @param string $body
     * @param int $cid
     */
    public function sendMessage(User $user, string $subject, string $body, int $cid)
    {
        $notification = new Notification();
        $notification->setUserid($user->getUserid());
        $notification->setTemplate($subject);
        $notification->setCid($cid);

        $this->em->persist($notification);
        $this->em->flush();

        // Create the Transport
        $transport = new \Swift_SmtpTransport('localhost', 25);

        // Create the Mailer using your created Transport
        $mailer = new \Swift_Mailer($transport);

        error_log("Body: " .$body);

        $message = (new \Swift_Message($subject))
            ->setFrom('incode@intabia.ru')
            ->setTo($user->getUsername().'@intabia.ru')
            ->setBody($body, 'text/html');

        error_log('Message: ' .$message);

        $mailer->send($message);
    }
}
