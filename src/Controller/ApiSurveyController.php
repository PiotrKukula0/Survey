<?php

namespace App\Controller;

use App\Entity\Question;
use App\Entity\Study;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ApiSurveyController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/api/v1/survey/{id}", name="api_survey_get", methods={"GET"})
     */
    public function getSurvey(int $id): JsonResponse
    {
        $survey = $this->entityManager->getRepository(Study::class)->find($id);

        if (!$survey || $survey->getStatus() === 'Edycja' || $survey->getStatus() === 'Testowanie') {
            throw $this->createNotFoundException('Badanie nie istnieje lub jest w trybie edycji/testowania.');
        }

        $surveyData = [
            'id' => $survey->getId(),
            'name' => $survey->getName(),
            'status' => $survey->getStatus(),
            'created' => $survey->getCreatedAt()->format('Y-m-d'),
            'updated' => $survey->getUpdatedAt()->format('Y-m-d'),
        ];

        return new JsonResponse($surveyData);
    }

    /**
     * @Route("/api/v1/survey-questions/{id}", name="api_survey_questions_get", methods={"GET"})
     */
    public function getSurveyQuestions(int $id): JsonResponse
    {
        $survey = $this->entityManager->getRepository(Study::class)->find($id);

        if (!$survey || $survey->getStatus() === 'Edycja' || $survey->getStatus() === 'Testowanie') {
            throw $this->createNotFoundException('Badanie nie istnieje lub jest w trybie edycji/testowania.');
        }

        $questions = $survey->getQuestions();

        $questionsData = [];

        foreach ($questions as $question) {
            $questionData = [
                'id' => $question->getId(),
                'surveyId' => $question->getStudy()->getId(),
                'position' => $question->getPosition(),
                'title' => $question->getContent(),
                'type' => $question->getType(),
                'created' => $question->getCreatedAt()->format('Y-m-d'),
                'updated' => $question->getUpdatedAt()->format('Y-m-d'),
            ];

            $questionsData[] = $questionData;
        }

        return new JsonResponse($questionsData);
    }

    /**
     * @Route("/api/v1/survey-questions-options/{surveyId}/{questionId}", name="api_survey_question_options_get", methods={"GET"})
     */
    public function getSurveyQuestionOptions(int $surveyId, int $questionId): JsonResponse
    {
        $survey = $this->entityManager->getRepository(Study::class)->find($surveyId);

        if (!$survey || $survey->getStatus() === 'Edycja' || $survey->getStatus() === 'Testowanie') {
            throw $this->createNotFoundException('Badanie nie istnieje lub jest w trybie edycji/testowania.');
        }

        $question = $this->entityManager->getRepository(Question::class)->find($questionId);

        if (!$question || $question->getStudy() !== $survey) {
            throw $this->createNotFoundException('Pytanie nie istnieje lub nie należy do tego badania.');
        }

        $options = $question->getOptions();

        $optionsData = [];

        foreach ($options as $option) {
            $optionData = [
                'id' => $option->getId(),
                'questionId' => $option->getQuestion()->getId(),
                'value' => $option->getValue(),
                'title' => $option->getContent(),
                'created' => $option->getCreatedAt()->format('Y-m-d'),
                'updated' => $option->getUpdatedAt()->format('Y-m-d'),
            ];

            $optionsData[] = $optionData;
        }

        return new JsonResponse($optionsData);
    }
}
