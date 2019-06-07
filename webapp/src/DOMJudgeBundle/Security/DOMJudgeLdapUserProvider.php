<?php declare(strict_types=1);

namespace DOMJudgeBundle\Security;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\TeamAffiliation;
use DOMJudgeBundle\Entity\TeamCategory;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Service\DOMJudgeService;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
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
        // first search ldap to bind ldap connection
        $ldap_user = $this->ldapUserProvider->loadUserByUsername($username);
        try {
            return $this->entityUserProvider->loadUserByUsername($username);
        } catch (UsernameNotFoundException $e) {
            //user login for first time
            //try to load user from ldap
        }
        $registrationCategoryName = $this->dj->dbconfig_get('registration_category_name', '');
        $registrationCategory = $this->em->getRepository(TeamCategory::class)->findOneBy(['name' => $registrationCategoryName]);
        if ($registrationCategory === null) {
            throw new HttpException(400, "Registration not enabled");
        }
        $user = new User();
        $team_role = $this->em->getRepository('DOMJudgeBundle:Role')->findOneBy(['dj_role' => 'team']);
        $user
            ->setUsername($username)
            ->setPassword($ldap_user->getPassword())
            ->setName($username)
            ->addRole($team_role);

        // Create a team to go with the user, then set some team attributes
        $team = new Team();
        $user->setTeam($team);
        $team
            ->addUser($user)
            ->setName($username)
            ->setCategory($registrationCategory)
            ->setComments('Registered on ' . date('r'));
        $this->em->persist($user);
        $this->em->persist($team);
        $this->em->flush();
        return $user;
    }

    public function refreshUser(UserInterface $user)
    {
        return $this->entityUserProvider->refreshUser($user);
    }

    public function supportsClass($class)
    {
        return $this->entityUserProvider->supportsClass($class);
    }

}
