<?php

namespace Plugin\PayPalExpress\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PayPalExpressDeliveryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $deliveries = $options['deliveries'];

        $builder
            ->add('delivery', 'entity', array(
                'class' => 'Eccube\Entity\Delivery',
                'property' => 'name',
                'expanded' => true,
                'multiple' => false,
                'choices' => $deliveries,
                'constraints' => array(
                    new Assert\NotBlank(),
                ),
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'deliveries' => array(),
        ));

    }

    public function getName()
    {
        return 'paypalexpress_delivery';
    }
}
