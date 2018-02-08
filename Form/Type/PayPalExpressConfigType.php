<?php

namespace Plugin\PayPalExpress\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PayPalExpressConfigType extends AbstractType
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('api_user', 'text', array(
                'constraints' => array(
                    new Assert\NotBlank(),
                ),
            ))
            ->add('api_password', 'text', array(
                'constraints' => array(
                    new Assert\NotBlank(),
                ),
            ))
            ->add('api_signature', 'text', array(
                'constraints' => array(
                    new Assert\NotBlank(),
                ),
            ))
            ->add('use_express_btn', 'checkbox', array(
                'label' => false,
                'required' => false,
            ))
            ->add('logo', 'file', array(
                'label' => false,
                'required' => false,
                'mapped' => false,
                'constraints' => array(
                    new Assert\File(array(
                        'mimeTypes' => array('image/jpeg', 'image/gif', 'image/png'),
                        'mimeTypesMessage' => 'ショップロゴ画像で許可されている形式は、jpg・gif・pngです。',
                    )),
                ),
            ))
            ->add('corporate_logo', 'hidden', array(
                'required' => false,
            ))
            ->add('border_color', 'text', array(
                'label' => false,
                'required' => false,
            ))
            ->add('use_sandbox', 'checkbox', array(
                'label' => false,
                'required' => false,
            ))
            /*
            ->add('paypal_logo', 'textarea', array(
                'label' => 'HTMLコード',
            ))
            */
            ->add('paypal_logo', 'choice', array(
                'choices' => array(
                    '1' => $this->app['config']['paypal_express_paypal_logo_1'],
                    '2' => $this->app['config']['paypal_express_paypal_logo_2'],
                    '3' => $this->app['config']['paypal_express_paypal_logo_3'],
                ),
                'expanded' => true,
                'multiple' => false,
                'required'    => false,
                'empty_value' => false,
            ))
            ->add('in_context', 'choice', array(
                'choices' => array(
                    '1' => '使用する',
                    '2' => '使用しない'
                ),
                'expanded' => true,
                'multiple' => false,
                'required'    => false,
                'empty_value' => false,
            ));

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Plugin\PayPalExpress\Entity\PayPalExpress',
        ));
    }

    public function getName()
    {
        return 'paypalexpress_config';
    }
}
