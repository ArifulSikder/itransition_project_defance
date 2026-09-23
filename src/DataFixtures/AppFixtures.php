<?php

namespace App\DataFixtures;

use App\Entity\AttributeDefinition;
use App\Entity\CurriculumVitae;
use App\Entity\CvLike;
use App\Entity\DiscussionPost;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\PositionAttribute;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AttributeCategory;
use App\Enum\AttributeType;
use App\Enum\FilterOperator;
use App\Enum\PositionLevel;
use App\Repository\TagRepository;
use App\Service\AttributeValueHelper;
use App\Service\SearchIndexer;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly AttributeValueHelper $values,
        private readonly TagRepository $tags,
        private readonly SearchIndexer $indexer,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $builtins = $this->builtins($manager);
        $library = $this->library($manager);
        $attributes = $builtins + $library;

        $manager->flush();

        $admin = $this->user($manager, 'Ada', 'Admin', 'admin@cvhub.test', ['ROLE_ADMIN', 'ROLE_RECRUITER', 'ROLE_CANDIDATE']);
        $recruiter = $this->user($manager, 'Rita', 'Recruiter', 'recruiter@cvhub.test', ['ROLE_RECRUITER']);
        $john = $this->user($manager, 'John', 'Smith', 'john@cvhub.test', ['ROLE_CANDIDATE'], 'Warsaw');
        $lee = $this->user($manager, 'Lee', 'Morris', 'lee@cvhub.test', ['ROLE_CANDIDATE'], 'Barcelona');
        $paul = $this->user($manager, 'Paul', 'King', 'paul@cvhub.test', ['ROLE_CANDIDATE'], 'Lisbon');
        $manager->flush();

        $this->fill($john, $attributes, [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'location' => 'Warsaw',
            'ielts' => '7.5',
            'gpa' => '4.2',
            'remote' => '1',
            'presentation' => 'Advanced',
            'english' => 'C1',
        ]);
        $this->fill($lee, $attributes, [
            'first_name' => 'Lee',
            'last_name' => 'Morris',
            'location' => 'Barcelona',
            'ielts' => '8.0',
            'gpa' => '3.8',
            'remote' => '1',
            'presentation' => 'Intermediate',
            'english' => 'C2',
        ]);
        $this->fill($paul, $attributes, [
            'first_name' => 'Paul',
            'last_name' => 'King',
            'location' => 'Lisbon',
            'ielts' => '6.5',
            'gpa' => '3.1',
            'remote' => '0',
            'presentation' => 'Beginner',
            'english' => 'B2',
        ]);

        $this->project($manager, $john, 'Payments platform', 'PHP, Symfony, PostgreSQL, Docker', '2022-01-01', '2024-06-01');
        $this->project($manager, $john, 'Analytics dashboard', 'TypeScript, React, PostgreSQL', '2023-03-01', null);
        $this->project($manager, $lee, 'QA automation suite', 'PHP, Cypress, Docker', '2021-05-01', '2023-12-01');
        $this->project($manager, $paul, 'Infrastructure as code', 'Terraform, AWS, Docker', '2020-01-01', '2022-01-01');

        $ba = $this->position($manager, 'Business Analyst', 'Acme', PositionLevel::Middle, true, [$attributes['first_name'], $attributes['last_name'], $attributes['english'], $attributes['gpa'], $attributes['presentation']], [], ['PostgreSQL', 'React']);
        $ds = $this->position($manager, 'Data Scientist', 'Nordic Labs', PositionLevel::Senior, false, [$attributes['first_name'], $attributes['last_name'], $attributes['ielts'], $attributes['gpa']], [
            [$attributes['ielts'], FilterOperator::GreaterThan, '7.0'],
        ], ['PostgreSQL']);
        $qa = $this->position($manager, 'QA Engineer', 'Helix', PositionLevel::Senior, true, [$attributes['first_name'], $attributes['last_name'], $attributes['english'], $attributes['remote']], [], ['Cypress', 'Docker']);
        $devops = $this->position($manager, 'DevOps Engineer', 'Orbit', PositionLevel::Junior, true, [$attributes['first_name'], $attributes['last_name'], $attributes['remote']], [], ['Docker', 'AWS']);

        $cvJohn = $this->cv($manager, $john, $ba, true);
        $cvLee = $this->cv($manager, $lee, $qa, true);
        $this->cv($manager, $paul, $qa, false);

        $like = (new CvLike())->setCv($cvJohn)->setRecruiter($recruiter);
        $manager->persist($like);
        $cvJohn->setLikeCount(1);

        $post = (new DiscussionPost())->setPosition($ba)->setAuthor($recruiter)->setContent('Please highlight stakeholder workshops in your projects.');
        $manager->persist($post);
        $reply = (new DiscussionPost())->setPosition($ba)->setAuthor($john)->setContent('Will do — I added the payments platform notes.');
        $manager->persist($reply);

        $manager->flush();

        foreach ([$admin, $recruiter, $john, $lee, $paul] as $user) {
            $this->indexer->indexUser($user);
        }
        foreach ($attributes as $attribute) {
            $this->indexer->indexAttribute($attribute);
        }
        foreach ([$ba, $ds, $qa, $devops] as $position) {
            $this->indexer->indexPosition($position);
        }
        $this->indexer->indexCv($cvJohn);
        $this->indexer->indexCv($cvLee);
        $manager->flush();
    }

    /** @return array<string, AttributeDefinition> */
    private function builtins(ObjectManager $manager): array
    {
        $defs = [
            ['first_name', 'First Name', AttributeCategory::Personal, AttributeType::String],
            ['last_name', 'Last Name', AttributeCategory::Personal, AttributeType::String],
            ['location', 'Location', AttributeCategory::Personal, AttributeType::String],
            ['personal_photo', 'Personal Photo', AttributeCategory::Personal, AttributeType::Image],
        ];
        $map = [];
        foreach ($defs as [$code, $name, $category, $type]) {
            $attribute = (new AttributeDefinition())
                ->setCode($code)
                ->setName($name)
                ->setCategory($category)
                ->setType($type)
                ->setBuiltin(true)
                ->setDescription('Built-in profile field. Recruiters can add it to a template but cannot delete it.');
            $manager->persist($attribute);
            $map[$code] = $attribute;
        }

        return $map;
    }

    /** @return array<string, AttributeDefinition> */
    private function library(ObjectManager $manager): array
    {
        $rows = [
            ['ielts', 'IELTS Score', AttributeCategory::Certification, AttributeType::Numeric, null, 0, 9],
            ['gpa', 'GPA', AttributeCategory::Education, AttributeType::Numeric, null, 0, 5],
            ['english', 'English Level', AttributeCategory::Language, AttributeType::Choice, ['A2', 'B1', 'B2', 'C1', 'C2']],
            ['presentation', 'Presentation Skills', AttributeCategory::SoftSkill, AttributeType::Choice, ['Beginner', 'Intermediate', 'Advanced']],
            ['remote', 'Remote Work Availability', AttributeCategory::Preference, AttributeType::Boolean, null],
            ['summary', 'Professional Summary', AttributeCategory::Personal, AttributeType::Text, null],
        ];
        $map = [];
        foreach ($rows as $row) {
            [$code, $name, $category, $type, $options, $min, $max] = array_pad($row, 7, null);
            $attribute = (new AttributeDefinition())
                ->setCode($code)
                ->setName($name)
                ->setCategory($category)
                ->setType($type)
                ->setOptions($options)
                ->setDescription('Reusable library attribute.');
            if ($min !== null) {
                $attribute->setMinValue($min)->setMaxValue($max);
            }
            $manager->persist($attribute);
            $map[$code] = $attribute;
        }

        return $map;
    }

    /** @param list<string> $roles */
    private function user(ObjectManager $manager, string $first, string $last, string $email, array $roles, string $location = 'Remote'): User
    {
        $user = (new User())
            ->setFirstName($first)
            ->setLastName($last)
            ->setEmail($email)
            ->setRoles($roles)
            ->setLocation($location)
            ->setEmailVerified(true);
        $user->setPassword($this->hasher->hashPassword($user, 'password'));
        $manager->persist($user);

        return $user;
    }

    /** @param array<string, AttributeDefinition> $attributes */
    private function fill(User $user, array $attributes, array $values): void
    {
        foreach ($values as $code => $raw) {
            if (isset($attributes[$code])) {
                $this->values->write($user, $attributes[$code], $raw);
            }
        }
    }

    private function project(ObjectManager $manager, User $owner, string $name, string $tagList, string $from, ?string $to): void
    {
        $project = (new Project())
            ->setOwner($owner)
            ->setName($name)
            ->setStartedOn(new \DateTimeImmutable($from))
            ->setEndedOn($to ? new \DateTimeImmutable($to) : null)
            ->setDescription("**$name**\n\nHands-on work with ".strtolower($tagList).'.');
        foreach (explode(', ', $tagList) as $nameTag) {
            $project->addTag($this->tags->findOrCreate($nameTag));
        }
        $manager->persist($project);
    }

    /**
     * @param list<AttributeDefinition> $template
     * @param list<array{0: AttributeDefinition, 1: FilterOperator, 2: string}> $rules
     * @param list<string> $tags
     */
    private function position(ObjectManager $manager, string $title, string $company, PositionLevel $level, bool $public, array $template, array $rules, array $tags): Position
    {
        $position = (new Position())
            ->setTitle($title)
            ->setCompany($company)
            ->setLevel($level)
            ->setPublicAccess($public)
            ->setShortDescription("We are hiring a $title to join $company.")
            ->setMaxProjects(3);
        foreach ($template as $i => $attribute) {
            $slot = (new PositionAttribute())->setAttribute($attribute)->setSortOrder($i);
            $position->addTemplateAttribute($slot);
        }
        foreach ($rules as [$attribute, $operator, $value]) {
            $rule = (new PositionAccessRule())->setAttribute($attribute)->setOperator($operator)->setCompareValue($value);
            $position->addAccessRule($rule);
        }
        foreach ($tags as $tag) {
            $position->addProjectTag($this->tags->findOrCreate($tag));
        }
        $manager->persist($position);

        return $position;
    }

    private function cv(ObjectManager $manager, User $owner, Position $position, bool $published): CurriculumVitae
    {
        $cv = (new CurriculumVitae())->setOwner($owner)->setPosition($position)->setPublished($published);
        $position->setCvCount($position->getCvCount() + 1);
        $manager->persist($cv);

        return $cv;
    }
}
