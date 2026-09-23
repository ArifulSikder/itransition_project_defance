<?php

namespace App\Service;

use App\Entity\AttributeDefinition;
use App\Entity\CurriculumVitae;
use App\Entity\Position;
use App\Entity\SearchDocument;
use App\Entity\User;
use App\Enum\SearchType;
use App\Repository\CurriculumVitaeRepository;
use App\Repository\SearchDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SearchIndexer
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SearchDocumentRepository $documents,
        private readonly CurriculumVitaeRepository $cvs,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function syncOwner(User $user): void
    {
        $this->indexUser($user);
        if ($user->getId() === null) {
            return;
        }

        $this->syncCvs($this->cvs->findForOwner($user));
    }

    public function indexPosition(Position $position): void
    {
        $body = implode(' ', array_filter([
            $position->getTitle(),
            $position->getCompany(),
            $position->getLevel()->label(),
            $position->getShortDescription(),
        ]));

        $this->upsert(SearchType::Position, (int) $position->getId(), $position->getTitle(), $body, $this->urls->generate('position_show', ['id' => $position->getId()]));
    }

    public function indexUser(User $user): void
    {
        $body = implode(' ', array_filter([
            $user->getFirstName(),
            $user->getLastName(),
            $user->getEmail(),
            $user->getLocation(),
        ]));

        $this->upsert(SearchType::User, (int) $user->getId(), $user->getDisplayName(), $body, $this->urls->generate('profile_show', ['id' => $user->getId()]));
    }

    public function indexAttribute(AttributeDefinition $attribute): void
    {
        $body = implode(' ', array_filter([
            $attribute->getName(),
            $attribute->getCategory()->label(),
            $attribute->getDescription(),
        ]));

        $this->upsert(SearchType::Attribute, (int) $attribute->getId(), $attribute->getName(), $body, $this->urls->generate('attribute_index'));
    }

    public function indexCv(CurriculumVitae $cv): void
    {
        $this->syncCvs([$cv]);
    }

    /** @param list<CurriculumVitae> $cvs */
    public function syncCvs(array $cvs): void
    {
        $ids = [];
        foreach ($cvs as $cv) {
            if ($cv->getId()) {
                $ids[] = (int) $cv->getId();
            }
        }

        $existing = $this->documents->findForTargets(SearchType::Cv, $ids);
        foreach ($cvs as $cv) {
            $id = (int) $cv->getId();
            if (!$cv->isPublished()) {
                if (isset($existing[$id])) {
                    $this->em->remove($existing[$id]);
                }
                continue;
            }

            $document = $existing[$id] ?? new SearchDocument();
            $owner = $cv->getOwner();
            $position = $cv->getPosition();
            $parts = [$owner->getDisplayName(), $owner->getLocation(), $position->getTitle(), $position->getCompany()];
            foreach ($owner->getAttributeValues() as $value) {
                $parts[] = $value->getAttribute()->getName();
                $parts[] = $value->getRawValue();
            }
            foreach ($owner->getProjects() as $project) {
                $parts[] = $project->getName();
                $parts[] = $project->getDescription();
                foreach ($project->getTags() as $tag) {
                    $parts[] = $tag->getName();
                }
            }

            $document->setType(SearchType::Cv);
            $document->setEntityId($id);
            $document->setTitle($owner->getDisplayName().' — '.$position->getTitle());
            $document->setBody(implode(' ', array_filter($parts)));
            $document->setRoute($this->urls->generate('cv_show', ['id' => $id]));
            $this->em->persist($document);
        }
    }

    public function remove(SearchType $type, int $entityId): void
    {
        $document = $this->documents->findOneByTarget($type, $entityId);
        if ($document) {
            $this->em->remove($document);
        }
    }

    private function upsert(SearchType $type, int $entityId, string $title, string $body, string $route): void
    {
        $document = $this->documents->findOneByTarget($type, $entityId) ?? new SearchDocument();
        $document->setType($type);
        $document->setEntityId($entityId);
        $document->setTitle($title);
        $document->setBody($body);
        $document->setRoute($route);
        $this->em->persist($document);
    }
}
