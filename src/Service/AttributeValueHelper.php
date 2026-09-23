<?php

namespace App\Service;

use App\Entity\AttributeDefinition;
use App\Entity\AttributeValue;
use App\Entity\User;
use App\Enum\AttributeType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Symfony\Contracts\Translation\TranslatorInterface;

class AttributeValueHelper
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getOrCreate(User $owner, AttributeDefinition $definition): AttributeValue
    {
        $values = $owner->getAttributeValues();
        if ($values instanceof PersistentCollection && !$values->isInitialized()) {
            $values->initialize();
        }

        $existing = $owner->findAttributeValue($definition);
        if ($existing) {
            return $existing;
        }

        $value = new AttributeValue();
        $value->setOwner($owner);
        $value->setAttribute($definition);
        $owner->addAttributeValue($value);
        $this->em->persist($value);

        return $value;
    }

    public function write(User $owner, AttributeDefinition $definition, ?string $raw): AttributeValue
    {
        $value = $this->getOrCreate($owner, $definition);
        $value->setRawValue($raw === '' ? null : $raw);
        $this->syncBuiltin($owner, $definition, $value->getRawValue());

        return $value;
    }

    public function syncBuiltin(User $owner, AttributeDefinition $definition, ?string $raw): void
    {
        match ($definition->getCode()) {
            AttributeDefinition::BUILTIN_FIRST_NAME => $owner->setFirstName($raw),
            AttributeDefinition::BUILTIN_LAST_NAME => $owner->setLastName($raw),
            AttributeDefinition::BUILTIN_LOCATION => $owner->setLocation($raw),
            AttributeDefinition::BUILTIN_PHOTO => $owner->setPhotoUrl($raw),
            default => null,
        };
    }

    public function isFilled(?string $raw): bool
    {
        if ($raw === null) {
            return false;
        }

        $trimmed = trim($raw);

        return $trimmed !== '' && $trimmed !== '{"from":"","to":""}';
    }

    public function display(?string $raw, AttributeType $type): string
    {
        if (!$this->isFilled($raw)) {
            return '';
        }

        if ($type === AttributeType::Period) {
            $data = json_decode((string) $raw, true);
            if (is_array($data)) {
                return trim(($data['from'] ?? '').' — '.($data['to'] ?? ''));
            }
        }

        if ($type === AttributeType::Boolean) {
            return $raw === '1' || $raw === 'true' ? 'Yes' : 'No';
        }

        return (string) $raw;
    }

    public function sortKey(?string $raw, AttributeType $type): string
    {
        if (!$this->isFilled($raw)) {
            return '';
        }

        return match ($type) {
            AttributeType::Numeric => sprintf('%020.6f', (float) $raw),
            AttributeType::Boolean => in_array(mb_strtolower((string) $raw), ['1', 'true', 'yes', 'on'], true) ? '1' : '0',
            AttributeType::Period => (string) $raw,
            default => mb_strtolower((string) $raw),
        };
    }

    public function validate(AttributeDefinition $definition, ?string $raw): ?string
    {
        if (!$this->isFilled($raw)) {
            return null;
        }

        $type = $definition->getType();

        if (in_array($type, [AttributeType::String, AttributeType::Text], true)) {
            $len = mb_strlen((string) $raw);
            if ($definition->getMinLength() !== null && $len < $definition->getMinLength()) {
                return $this->translator->trans('validation.too_short');
            }
            if ($definition->getMaxLength() !== null && $len > $definition->getMaxLength()) {
                return $this->translator->trans('validation.too_long');
            }
            $pattern = $definition->getPattern();
            if ($pattern && @preg_match('/'.$pattern.'/', (string) $raw) !== 1) {
                return $this->translator->trans('validation.pattern');
            }
        }

        if ($type === AttributeType::Numeric) {
            if (!is_numeric($raw)) {
                return $this->translator->trans('validation.numeric');
            }
            $n = (float) $raw;
            if ($definition->getMinValue() !== null && $n < $definition->getMinValue()) {
                return $this->translator->trans('validation.min');
            }
            if ($definition->getMaxValue() !== null && $n > $definition->getMaxValue()) {
                return $this->translator->trans('validation.max');
            }
        }

        return null;
    }
}
