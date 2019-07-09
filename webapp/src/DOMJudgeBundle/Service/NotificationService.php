<?php declare(strict_types=1);

namespace DOMJudgeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Notification;
use DOMJudgeBundle\Entity\User;

use FOS\RestBundle\View\View;
use phpDocumentor\Reflection\Types\Integer;
use Swift_Mailer;
use Swift_SendmailTransport;
use Swift_SmtpTransport;
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
     * Get a list of submissions that can be displayed in the interface using
     * the submission_list partial.
     *
     * Restrictions can contain the following keys;
     * - rejudgingid: ID of a rejudging to filter on
     * - verified: If true, only return verified submissions.
     *             If false, only return unverified or unjudged submissions.
     * - judged: If true, only return judged submissions.
     *           If false, only return unjudged submissions.
     * - rejudgingdiff: If true, only return judgings that differ from their
     *                  original result in final verdict. Vice versa if false.
     * - teamid: ID of a team to filter on
     * - categoryid: ID of a team category to filter on
     * - probid: ID of a problem to filter on
     * - langid: ID of a language to filter on
     * - judgehost: hostname of a judgehost to filter on
     * - old_result: result of old judging to filter on
     * - result: result of current judging to filter on
     *
     * @param User $user
     * @param string $subject
     * @param string $body
     * @param int $cid
     * @return void An array with two elements: the first one is the list of
     *               submissions and the second one is an array with counts.
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
        $transport = new Swift_SmtpTransport('localhost', 25);

        // Create the Mailer using your created Transport
        $mailer = new Swift_Mailer($transport);

        error_log("Body: " .$body);

        $message = (new \Swift_Message($subject))
            ->setFrom('incode@intabia.ru')
            ->setTo($user->getUsername().'@intabia.ru')
            ->setBody($body, 'text/html');

        error_log('Message: ' .$message);

        $mailer->send($message);
    }
}
