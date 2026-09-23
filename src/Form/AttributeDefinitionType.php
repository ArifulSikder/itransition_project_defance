<?php

namespace App\Form;

use App\Entity\AttributeDefinition;
use App\Enum\AttributeCategory;
use App\Enum\AttributeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttributeDefinitionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('category', EnumType::class, ['class' => AttributeCategory::class, 'choice_label' => fn (AttributeCategory $c) => $c->label()])
            ->add('type', EnumType::class, ['class' => AttributeType::class, 'choice_label' => fn (AttributeType $t) => $t->label()])
            ->add('description', TextareaType::class, ['required' => false])
            ->add('optionsText', TextType::class, [
                'mapped' => false,
                'required' => false,
                'help' => 'Comma-separated options for "One of many" attributes.',
            ])
            ->add('minLength', IntegerType::class, ['required' => false])
            ->add('maxLength', IntegerType::class, ['required' => false])
            ->add('pattern', TextType::class, ['required' => false, 'help' => 'Optional regex without slashes.'])
            ->add('minValue', NumberType::class, ['required' => false])
            ->add('maxValue', NumberType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AttributeDefinition::class]);
    }
}
