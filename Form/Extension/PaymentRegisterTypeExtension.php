<?php
namespace Plugin\PayPalExpress\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class PaymentRegisterTypeExtension extends AbstractTypeExtension
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            /*
            ->add('payment_paypal_logo', 'textarea', array(
                'label' => 'HTMLコード',
                'mapped' => false,
            ))
            */
            ->add('payment_paypal_logo', 'choice', array(
                'choices' => array(
                    '1' => $this->app['config']['paypal_express_payment_paypal_logo_1'],
                    '2' => $this->app['config']['paypal_express_payment_paypal_logo_2'],
                    '3' => $this->app['config']['paypal_express_payment_paypal_logo_3'],
                ),
                'expanded' => true,
                'multiple' => false,
                'required'    => false,
                'empty_value' => false,
                'mapped' => false,
            ));
    }

    public function getExtendedType()
    {
        return 'payment_register';
    }
}