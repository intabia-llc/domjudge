<?php declare(strict_types=1);

namespace DOMJudgeBundle\Security;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\TeamAffiliation;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Service\DOMJudgeService;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Symfony\Component\Security\Core\User\LdapUserProvider;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class DOMJudgeLdapUserProvider implements UserProviderInterface
{
    private $ldapUserProvider;
    private $entityUserProvider;
    private $em;
    private $dj;

    public function __construct(LdapUserProvider $ldapUserProvider, EntityUserProvider $entityUserProvider, EntityManagerInterface $em, DOMJudgeService $dj)
    {
        $this->ldapUserProvider = $ldapUserProvider;
        $this->entityUserProvider = $entityUserProvider;
        $this->em = $em;
        $this->dj = $dj;
    }

    public function loadUserByUsername($username)
    {

        $ldapUser = $this->ldapUserProvider->loadUserByUsername($username);
        $user = new User();

        $team_role = $this->em->getRepository('DOMJudgeBundle:Role')->findOneBy(['dj_role' => 'team']);

        $user->setPassword($ldapUser->getPassword());
        $user->setName($username);
        $user->addRole($team_role);

        // Create a team to go with the user, then set some team attributes
        $team = new Team();
        $user->setTeam($team);
        $team
            ->addUser($user)
            ->setName($username)
            ->setComments('Registered on ' . date('r'));
        $this->em->persist($user);
        $this->em->persist($team);
        $this->em->flush();
        return $user;
    }

    public function refreshUser(UserInterface $user)
    {
    }

    public function supportsClass($class)
    {
        return $this->entityUserProvider->supportsClass($class);
    }

    private function getObjectManager()
    {
        return $this->registry->getManager($this->managerName);
    }

    private function getRepository()
    {
        return $this->getObjectManager()->getRepository($this->classOrAlias);
    }

}
