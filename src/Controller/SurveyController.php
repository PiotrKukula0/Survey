<?php

namespace App\Controller;

use App\Entity\Options;
use App\Entity\Study;
use App\Entity\Question;
use App\Service\StudyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class SurveyController extends AbstractController
{
    private StudyService $studyService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        StudyService $studyService,
        EntityManagerInterface $entityManager
    ) {
        $this->studyService = $studyService;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/index", name="survey_index")
     */
    public function index(): Response
    {
        $studies = $this->entityManager->getRepository(Study::class)->findAll();

        return $this->render('index.html.twig', [
            'studies' => $studies,
        ]);
    }

    /**
     * @Route("/survey/edit/{id}", name="survey_edit", methods={"GET", "POST"})
     */
    public function edit($id, Request $request): Response
    {
        $survey = $this->entityManager->getRepository(Study::class)->find($id);

        if (!$survey) {
            throw $this->createNotFoundException('Badanie o podanym identyfikatorze nie istnieje.');
        }

        if ($request->isMethod('POST')) {
            $survey->setName($request->request->get('name'));
            $survey->setStatus($request->request->get('status'));

            $this->entityManager->flush();

            return $this->redirectToRoute('survey_index');
        }

        return $this->render('survey_edit.html.twig', [
            'survey' => $survey,
        ]);
    }

    /**
     * @Route("/survey/delete/{id}", name="survey_delete", methods={"GET"})
     */
    public function deleteSurvey(int $id): Response
    {
        $survey = $this->entityManager->getRepository(Study::class)->find($id);

        if (!$survey) {
            throw $this->createNotFoundException('Badanie o podanym identyfikatorze nie istnieje.');
        }

        $questions = $survey->getQuestions();

        foreach ($questions as $question) {
            $options = $question->getOptions();

            foreach ($options as $option) {
                $this->entityManager->remove($option);
            }

            $this->entityManager->remove($question);
        }

        $this->entityManager->remove($survey);
        $this->entityManager->flush();

        return $this->redirectToRoute('survey_index');
    }


    /**
     * @Route("/survey/create", name="create_survey")
     */
    public function createSurvey(Request $request, EntityManagerInterface $entityManager): Response
    {
        $study = new Study();

        $form = $this->createFormBuilder($study)
            ->add('name')
            ->add('status')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $study->setCreatedAt(new \DateTime());
            $study->setUpdatedAt(new \DateTime());

            $entityManager->persist($study);
            $entityManager->flush();

            $studyId = $study->getId();

            return $this->redirectToRoute('survey_questions', ['id' => $studyId]);
        }

        return $this->render('survey/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    /**
     * @Route("/survey/questions/{id}", name="survey_questions", methods={"GET"})
     */
    public function questions(int $id): Response
    {
        $questions = $this->entityManager->getRepository(Question::class)->findBy(['surveyId' => $id]);
        $study = $this->entityManager->getRepository(Study::class)->find($id);
        $surveyId = $study->getId();
        return $this->render('survey/questions.html.twig', [
            'questions' => $questions,
            'surveyId' => $surveyId,
            'study' => $study,
            'id' => $id,
        ]);
    }

    /**
     * @Route("/survey/question-edit/{surveyId}/{questionId}", name="question_edit", methods={"GET", "POST"})
     */
    public function editQuestion(Request $request, int $surveyId, int $questionId): Response
    {
        $question = $this->entityManager->getRepository(Question::class)->find($surveyId);
        $question = $this->entityManager->getRepository(Question::class)->find($questionId);


        if (!$question) {
            throw $this->createNotFoundException('Question not found');
        }

        if ($request->isMethod('POST')) {
            $question->setContent($request->request->get('content'));
            $question->setType($request->request->get('type'));
            $question->setPosition($request->request->get('position'));

            $this->entityManager->flush();

            return $this->redirectToRoute('survey_questions', ['id' => $question->getSurveyId()]);
        }

        return $this->render('survey/edit_question.html.twig', [
            'question' => $question,
        ]);
    }

    /**
     * @Route("/survey/question-create/{id}", name="question_create", methods={"GET", "POST"})
     */
    public function createQuestion(Request $request, int $id): Response
    {
        $study = $this->entityManager->getRepository(Study::class)->find($id);

        if (!$study) {
            throw $this->createNotFoundException('Badanie o podanym ID nie istnieje.');
        }

        $position = $request->request->get('position');
        if (!is_numeric($position)) {
            $position = 0;
        }

        $question = new Question();
        $question->setPosition((int) $position);
        $question->setCreatedAt(new \DateTime());
        $question->setUpdatedAt(new \DateTime());
        $question->setStudy($study);

        if ($request->isMethod('POST')) {
            $content = $request->request->get('content');
            $type = $request->request->get('type');

            $question->setContent($content);
            $question->setType($type);

            $this->entityManager->persist($question);
            $this->entityManager->flush();

            return $this->redirectToRoute('question_options', [
                'surveyId' => $study->getId(),
                'questionId' => $question->getId(),
            ]);
        }

        return $this->render('survey/create_question.html.twig', [
            'question' => $question,
        ]);
    }



    /**
     * @Route("/survey/question-options/{surveyId}/{questionId}", name="question_options")
     */
    public function questionOptions(Request $request, int $surveyId, int $questionId): Response
    {
        $question = $this->entityManager->getRepository(Question::class)->find($questionId);

        if (!$question) {
            throw $this->createNotFoundException('Question not found');
        }

        $options = $question->getOptions(); // Pobieranie opcji odpowiedzi dla pytania

        return $this->render('survey/question_options.html.twig', [
            'surveyId' => $surveyId,
            'question' => $question,
            'options' => $options,
        ]);
    }

    /**
     * @Route("/survey/question-option-edit/{surveyId}/{questionId}/{optionId}", name="edit_option")
     */
    public function editOption(Request $request, int $surveyId, int $questionId, int $optionId): Response
    {
        $option = $this->entityManager->getRepository(Options::class)->find($optionId);

        if (!$option) {
            throw $this->createNotFoundException('Option not found');
        }

        if ($request->isMethod('POST')) {

            $value = $request->request->get('value');
            $content = $request->request->get('content');


            $option->setValue($value);
            $option->setContent($content);

            $this->entityManager->flush();

            $this->addFlash('success', 'Opcja odpowiedzi została zaktualizowana.');

            return $this->redirectToRoute('question_options', [
                'surveyId' => $surveyId,
                'questionId' => $questionId
            ]);
        }

        return $this->render('survey/edit_option.html.twig', [
            'surveyId' => $surveyId,
            'questionId' => $questionId,
            'optionId' => $optionId,
            'option' => $option,
        ]);
    }

    /**
     * @Route("/survey/delete-option/{surveyId}/{questionId}/{optionId}", name="delete_option")
     */
    public function deleteOption(int $surveyId, int $questionId, int $optionId): Response
    {

        $option = $this->entityManager->getRepository(Options::class)->find($optionId);

        if (!$option) {
            throw $this->createNotFoundException('Option not found');
        }

        $this->entityManager->remove($option);
        $this->entityManager->flush();

        $this->addFlash('success', 'Opcja odpowiedzi została usunięta.');

        return $this->redirectToRoute('question_options', [
            'surveyId' => $surveyId,
            'questionId' => $questionId
        ]);
    }

    /**
     * @Route("/survey/question-option-create/{surveyId}/{questionId}", name="create_option", methods={"GET", "POST"})
     */
    public function createQuestionOption(Request $request, int $surveyId, int $questionId): Response
    {
        $question = $this->entityManager->getRepository(Question::class)->find($questionId);

        if (!$question) {
            throw $this->createNotFoundException('Question not found');
        }

        if ($request->isMethod('POST')) {
            $optionValue = $request->request->get('optionValue');
            $optionText = $request->request->get('optionText');

            $option = new Options();
            $option->setValue($optionValue);
            $option->setContent($optionText);
            $option->setQuestion($question);
            $option->setCreatedAt(new \DateTime());
            $option->setUpdatedAt(new \DateTime());

            $this->entityManager->persist($option);
            $this->entityManager->flush();

            return $this->redirectToRoute('question_options', [
                'surveyId' => $surveyId,
                'questionId' => $questionId,
            ]);
        }

        return $this->render('survey/create_option.html.twig', [
            'surveyId' => $surveyId,
            'questionId' => $questionId,
        ]);
    }

    public function editQuestionOption(Request $request, int $surveyId, int $questionId, int $optionId): Response
    {

        $question = $this->entityManager->getRepository(Question::class)->findQuestionById($questionId);


        $option = $this->entityManager->getRepository(Options::class)->findOptionById($optionId);


        if ($option->getQuestion() !== $question) {


            return $this->redirectToRoute('question_options', [
                'surveyId' => $surveyId,
                'questionId' => $questionId,
            ]);
        }

        if ($request->isMethod('POST')) {
            $optionValue = $request->request->get('option_value');
            $optionText = $request->request->get('option_text');


            $option->setOptionValue($optionValue);
            $option->setOptionText($optionText);
            $this->entityManager->flush();


            return $this->redirectToRoute('question_options', [
                'surveyId' => $surveyId,
                'questionId' => $questionId,
            ]);
        }

        return $this->render('survey/edit_option.html.twig', [
            'option' => $option,
            'surveyId' => $surveyId,
            'questionId' => $questionId,
        ]);
    }

    public function showSurvey(Request $request, int $id): Response
    {
        $survey = $this->entityManager->find(Study::class, $id);

        if (!$survey) {
            throw new NotFoundHttpException('Badanie nie istnieje.');
        }

        $status = $survey->getStatus();

        $questions = $this->entityManager->createQuery('
            SELECT q
            FROM App\Entity\Question q
            WHERE q.study = :survey
            ORDER BY q.id ASC
        ')
            ->setParameter('survey', $survey)
            ->getResult();

        return $this->render('survey/show.html.twig', [
            'survey' => $survey,
            'status' => $status,
            'questions' => $questions,
        ]);
    }


    /**
     * @Route("/survey/questions/{id}/delete", name="question_delete", methods={"GET"})
     */
    public function delete(Request $request, int $id): Response
    {
        $question = $this->entityManager->getRepository(Question::class)->find($id);

        if (!$question) {
            throw $this->createNotFoundException('Question not found');
        }

        $this->entityManager->remove($question);
        $this->entityManager->flush();

        $surveyId = $question->getSurveyId();

        return $this->redirectToRoute('survey_questions', ['id' => $surveyId]);
    }

}
