<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Team;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Judging;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Language;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\Testcase;
use DOMJudgeBundle\Form\Type\SubmitProblemType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\SubmissionService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

//use Dompdf\Options;
use PdfParser;


/**
 * Class SubmissionController
 *
 * @Route("/team")
 * @Security("is_granted('ROLE_TEAM')")
 * @Security("user.getTeam() !== null", message="You do not have a team associated with your account.")
 * @package DOMJudgeBundle\Controller\Team
 */
class SubmissionController extends BaseController
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var SubmissionService
     */
    protected $submissionService;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    public function __construct(
        EntityManagerInterface $em,
        SubmissionService $submissionService,
        DOMJudgeService $dj,
        FormFactoryInterface $formFactory
    ) {
        $this->em                = $em;
        $this->submissionService = $submissionService;
        $this->dj                = $dj;
        $this->formFactory       = $formFactory;
    }

    /**
     * @Route("/submit", name="team_submit")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function createAction(Request $request)
    {
        $user    = $this->dj->getUser();
        $team    = $user->getTeam();
        $contest = $this->dj->getCurrentContest($user->getTeamid());
        $form    = $this->formFactory
            ->createBuilder(SubmitProblemType::class)
            ->setAction($this->generateUrl('team_submit'))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($contest === null) {
                $this->addFlash('danger', 'No active contest');
            } elseif (!$this->dj->checkrole('jury') && !$contest->getFreezeData()->started()) {
                $this->addFlash('danger', 'Contest has not yet started');
            } else {
                /** @var Problem $problem */
                $problem = $form->get('problem')->getData();
                /** @var Language $language */
                $language = $form->get('language')->getData();
                /** @var UploadedFile[] $files */
                $files      = $form->get('code')->getData();
                if (!is_array($files)) {
                    $files = [$files];
                }
                $entryPoint = $form->get('entry_point')->getData() ?: null;
                $submission = $this->submissionService->submitSolution($team, $problem->getProbid(), $contest,
                                                                       $language, $files, null, $entryPoint, null, null,
                                                                       $message);

                if ($submission) {
                    $this->dj->auditlog('submission', $submission->getSubmitid(), 'added', 'via teampage',
                                                     null, $contest->getCid());
                    $this->addFlash('success',
                                    '<strong>Submission done!</strong> Watch for the verdict in the list below.');
                } else {
                    $this->addFlash('danger', $message);
                }
                return $this->redirectToRoute('team_index');
            }
        }

        $data = ['form' => $form->createView()];

        if ($request->isXmlHttpRequest()) {
            return $this->render('@DOMJudge/team/submit_modal.html.twig', $data);
        } else {
            return $this->render('@DOMJudge/team/submit.html.twig', $data);
        }
    }

    /**
     * @Route("/submission/{submitId}", name="team_submission", requirements={"submitId": "\d+"})
     * @param Request $request
     * @param int     $submitId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function viewAction(Request $request, int $submitId)
    {
        $verificationRequired = (bool)$this->dj->dbconfig_get('verification_required', false);;
        $showCompile      = $this->dj->dbconfig_get('show_compile', 2);
        $showSampleOutput = $this->dj->dbconfig_get('show_sample_output', 0);
        $user             = $this->dj->getUser();
        $team             = $user->getTeam();
        $contest          = $this->dj->getCurrentContest($team->getTeamid());
        /** @var Judging $judging */
        $judging = $this->em->createQueryBuilder()
            ->from('DOMJudgeBundle:Judging', 'j')
            ->join('j.submission', 's')
            ->join('s.contest_problem', 'cp')
            ->join('cp.problem', 'p')
            ->join('s.language', 'l')
            ->select('j', 's', 'cp', 'p', 'l')
            ->andWhere('j.submitid = :submitId')
            ->andWhere('j.valid = 1')
            ->andWhere('s.team = :team')
            ->setParameter(':submitId', $submitId)
            ->setParameter(':team', $team)
            ->getQuery()
            ->getOneOrNullResult();

        // Update seen status when viewing submission
        if ($judging && $judging->getSubmission()->getSubmittime() < $contest->getEndtime() && (!$verificationRequired || $judging->getVerified())) {
            $judging->setSeen(true);
            $this->em->flush();
        }

        /** @var Testcase[] $runs */
        $runs = [];
        if ($showSampleOutput && $judging && $judging->getResult() !== 'compiler-error') {
            $runs = $this->em->createQueryBuilder()
                ->from('DOMJudgeBundle:Testcase', 't')
                ->join('t.testcase_content', 'tc')
                ->leftJoin('t.judging_runs', 'jr', Join::WITH, 'jr.judging = :judging')
                ->leftJoin('jr.judging_run_output', 'jro')
                ->select('t', 'jr', 'tc', 'jro')
                ->andWhere('t.problem = :problem')
                ->setParameter(':judging', $judging)
                ->setParameter(':problem', $judging->getSubmission()->getProblem())
                ->orderBy('t.rank')
                ->getQuery()
                ->getResult();
        }

        $data = [
            'judging' => $judging,
            'verificationRequired' => $verificationRequired,
            'showCompile' => $showCompile,
            'showSampleOutput' => $showSampleOutput,
            'runs' => $runs,
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('@DOMJudge/team/submission_modal.html.twig', $data);
        } else {
            return $this->render('@DOMJudge/team/submission.html.twig', $data);
        }
    }

    /**
     * @Route("/{submission}/edit-source", name="jury_submission_edit_source")
     * @param Request $request
     * @param Submission $submission
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editorAction(Request $request, Submission $submission)
//        int $probId, string $langId)
//    @Route("/submission/source/{probId}/{langId}", name="code_editor", requirements={"probId": "\d+", "langId": "\S+"})
    {
//        /** @var Language $languages */
        $languages = $this->em->getRepository(Language::class)->findBy(['allowSubmit' => true]);
//
//        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find(1);
//
//        return $this->render('@DOMJudge/team/submission_source.html.twig', [
//            'problem' => $problem,
//            'languages' => $languages,
//            'curLang' => $langId,
//        ]);


//        if (!$this->dj->getUser()->getTeam() || !$this->dj->checkrole('team')) {
//            $this->addFlash('danger', 'You cannot re-submit code without being a team.');
//            return $this->redirectToLocalReferrer($this->router, $request, $this->generateUrl('jury_submission',
//                ['submitId' => $submission->getSubmitid()]));
//        }

//        /** @var SubmissionFileWithSourceCode[] $files */
//        $files = $this->em->createQueryBuilder()
//            ->from('DOMJudgeBundle:SubmissionFileWithSourceCode', 'file')
//            ->select('file')
//            ->andWhere('file.submission = :submission')
////            ->setParameter(':submission', $submission)
//            ->orderBy('file.rank')
//            ->getQuery()
//            ->getResult();

//        $data = [
//            'problem' => $submission->getProblem(),
//            'language' => $submission->getLanguage(),
//            'entry_point' => $submission->getEntryPoint(),
//        ];
//
//        $data = [
//            'problem' => $problem,
//            'language' => $languages,
////            'entry_point' => $submission->getEntryPoint(),
//        ];

//        foreach ($files as $file) {
//            $data['source' . $file->getRank()] = $file->getSourcecode();
//        }

//        $formBuilder = $this->createFormBuilder($data)
        $formBuilder = $this->createFormBuilder()
            ->add('problem', EntityType::class, [
                'class' => 'DOMJudgeBundle\Entity\Problem',
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er)  {
                    return $er->createQueryBuilder('p')
                        ->join('p.contest_problems', 'cp')
                        ->andWhere('cp.allowSubmit = 1')
                        ->andWhere('cp.contest = :contest')
//                        ->setParameter(':contest', $submission->getContest())
                        ->orderBy('p.name');
                },
            ])
            ->add('language', EntityType::class, [
                'class' => 'DOMJudgeBundle\Entity\Language',
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('lang')
                        ->andWhere('lang.allowSubmit = 1')
                        ->orderBy('lang.name');
                }
            ])
            ->add('entry_point', TextType::class, [
                'label' => 'Optional entry point',
                'required' => false,
            ])
            ->add('submit', SubmitType::class);

//        foreach ($files as $file) {
//            $formBuilder->add('source' . $file->getRank(), TextareaType::class);
//        }

        $form = $formBuilder->getForm();
//
//        // Handle the form if it is submitted
        $form->handleRequest($request);
//        if ($form->isSubmitted() && $form->isValid()) {
//            $submittedData = $form->getData();
//
//            /** @var UploadedFile[] $filesToSubmit */
//            $filesToSubmit = [];
//            $tmpdir        = $this->dj->getDomjudgeTmpDir();
//            foreach ($files as $file) {
//                if (!($tmpfname = tempnam($tmpdir, "edit_source-"))) {
//                    throw new ServiceUnavailableHttpException(null, "Could not create temporary file.");
//                }
//                file_put_contents($tmpfname, $submittedData['source' . $file->getRank()]);
//                $filesToSubmit[] = new UploadedFile($tmpfname, $file->getFilename(), null, null, null, true);
//            }
//
//            $team = $this->dj->getUser()->getTeam();
//            /** @var Language $language */
//            $language   = $submittedData['language'];
//            $entryPoint = $submittedData['entry_point'];
//            if ($language->getRequireEntryPoint() && $entryPoint === null) {
//                $entryPoint = '__auto__';
//            }
//            $submittedSubmission = $this->submissionService->submitSolution(
//                $team,
//                $submittedData['problem'],
////                $submission->getContest(),
//                $language,
//                $filesToSubmit,
////                $submission->getOriginalSubmission() ?? $submission,
//                $entryPoint,
//                null,
//                null
////                $message
//            );
//
//            foreach ($filesToSubmit as $file) {
//                unlink($file->getRealPath());
//            }

//            if (!$submission) {
//                $this->addFlash('danger', $message);
//                return $this->redirectToRoute('jury_submission', ['submitId' => $submission->getSubmitid()]);
//            }

//            return $this->redirectToRoute('jury_submission', ['submitId' => $submittedSubmission->getSubmitid()]);
//        }

        $submission = new Submission();
//        $submission->submitid = 1;
        return $this->render('@DOMJudge/team/submission_edit_source.html.twig', [
            'submission' => $submission,
            'submission' => null,
//            'files' => $files,
            'form' => $form->createView(),
//            'selected' => $request->query->get('rank'),
            'selected' => null,
        ]);



    }
}
