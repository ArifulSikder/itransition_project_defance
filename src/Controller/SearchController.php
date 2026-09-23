<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\SearchType;
use App\Repository\CurriculumVitaeRepository;
use App\Repository\SearchDocumentRepository;
use App\Service\CvAssembler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search')]
    public function index(Request $request, SearchDocumentRepository $documents, CurriculumVitaeRepository $cvs, CvAssembler $assembler): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $type = (string) $request->query->get('type', '');
        $hits = $q === '' ? [] : $documents->fullTextSearch($q);
        $user = $this->getUser();
        $canSeeCvs = $user instanceof User && $user->isRecruiter();

        $visible = [];
        $cvIds = [];
        foreach ($hits as $hit) {
            if ($type !== '' && $hit['type'] !== $type) {
                continue;
            }
            if ($hit['type'] === SearchType::Cv->value && !$canSeeCvs) {
                continue;
            }
            if ($hit['type'] === SearchType::User->value && !$canSeeCvs && !($user instanceof User && (int) $hit['entity_id'] === $user->getId())) {
                continue;
            }
            if ($hit['type'] === SearchType::Attribute->value && !($user instanceof User && $user->isRecruiter())) {
                continue;
            }
            if ($hit['type'] === SearchType::Cv->value) {
                $cvIds[] = (int) $hit['entity_id'];
            }
            $visible[] = $hit;
        }

        $likes = [];
        if ($cvIds !== []) {
            foreach ($assembler->visibleToRecruiters($cvs->findPublishedByIds($cvIds)) as $cv) {
                $likes[(int) $cv->getId()] = $cv->getLikeCount();
            }
        }

        $final = [];
        foreach ($visible as $hit) {
            if ($hit['type'] === SearchType::Cv->value) {
                $id = (int) $hit['entity_id'];
                if (!isset($likes[$id])) {
                    continue;
                }
                $hit['likes'] = $likes[$id];
            }
            $final[] = $hit;
        }

        return $this->render('search/index.html.twig', [
            'q' => $q,
            'hits' => $final,
        ]);
    }
}
