<?php

namespace App\Service;

use App\Entity\Question;
use App\Entity\Study;
use Doctrine\ORM\EntityManagerInterface;

class StudyService
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function createStudy(?Study $study, ?array $questionsData)
    {
        if ($study !== null) {
            $this->entityManager->persist($study);
            $this->entityManager->flush();

            if ($questionsData !== null) {
                foreach ($questionsData as $questionData) {
                    $question = new Question();
                    $question->setStudy($study);
                    $question->setPosition($questionData['position']);
                    $question->setContent($questionData['content']);
                    $question->setType($questionData['type']);
                    $question->setCreatedAt(new \DateTime());
                    $question->setUpdatedAt(new \DateTime());

                    $this->entityManager->persist($question);
                }
            }

            $this->entityManager->flush();
        }
    }



}
