<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\Form;

use App\Recruitment\Domain\JobApplication\CvText;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ApplyRequest>
 */
final class ApplyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Full name',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Phone',
                'required' => false,
                'attr' => ['autocomplete' => 'tel', 'placeholder' => '+34 600 123 456'],
            ])
            ->add('cv', TextareaType::class, [
                'label' => 'CV',
                'empty_data' => '',
                'help' => 'Paste your CV as plain text: experience, skills, education…',
                'attr' => [
                    'rows' => 14,
                    'class' => 'font-mono',
                    'data-char-counter-target' => 'input',
                    'data-action' => 'char-counter#update',
                    'maxlength' => CvText::MAX_LENGTH,
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'help' => 'Anything else you want the team to know (availability, links…).',
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ApplyRequest::class]);
    }
}
