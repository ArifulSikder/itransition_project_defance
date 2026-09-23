<?php

namespace App\Form;

use App\Entity\Position;
use App\Enum\PositionLevel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PositionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class)
            ->add('company', TextType::class, ['required' => false])
            ->add('level', EnumType::class, ['class' => PositionLevel::class, 'choice_label' => fn (PositionLevel $l) => $l->label()])
            ->add('shortDescription', TextareaType::class)
            ->add('publicAccess', CheckboxType::class, ['required' => false, 'label' => 'Public (all authenticated candidates)'])
            ->add('maxProjects', IntegerType::class)
            ->add('expectedVersion', HiddenType::class, ['mapped' => false])
            ->add('templateJson', HiddenType::class, ['mapped' => false])
            ->add('rulesJson', HiddenType::class, ['mapped' => false])
            ->add('tagsJson', HiddenType::class, ['mapped' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Position::class]);
    }
}
