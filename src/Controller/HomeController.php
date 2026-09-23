<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\PositionRepository;
use App\Repository\TagRepository;
use App\Service\PositionVisibility;
use App\Service\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        PositionRepository $positions,
        TagRepository $tags,
        StatisticsService $statistics,
        PositionVisibility $visibility,
    ): Response {
        $user = $this->getUser();
        $forRecruiters = $user instanceof User && $user->isRecruiter();
        $visibleIds = $visibility->visibleIds($user instanceof User ? $user : null);

        return $this->render('home/index.html.twig', [
            'latest' => $positions->findLatest(8, $visibleIds),
            'popular' => $positions->findPopular(5, $visibleIds),
            'tags' => $tags->cloudWeights($forRecruiters),
            'stats' => $statistics->snapshot(),
        ]);
    }
}
