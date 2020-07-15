<?php
/**
 * This file is part of Stripe4
 *
 * Copyright(c) Akira Kurozumi <info@a-zumi.net>
 *
 * https://a-zumi.net
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\Stripe4\Form\Extension;


use Doctrine\ORM\EntityRepository;
use Eccube\Entity\Order;
use Eccube\Form\Type\Shopping\OrderType;
use Plugin\Stripe4\Entity\Team;
use Plugin\Stripe4\Form\Type\TeamType;
use Plugin\Stripe4\Service\Method\CreditCard;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;

class CreditCardExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['skip_add_form']) {
            return;
        }

        $builder
            ->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
                /** @var FormBuilderInterface $form */
                $form = $event->getForm();
                /** @var Order $order */
                $order = $event->getData();

                if ($order->getPayment()->getMethodClass() === CreditCard::class) {
                    $form
                        ->add('stripe_payment_intent_id', HiddenType::class, [
                            'mapped' => true,
                            'constraints' => [
                                new NotBlank()
                            ]
                        ])
                        ->add('is_saving_card', ChoiceType::class, [
                            'mapped' => false,
                            'choices' => [
                                'カード情報を保存する' => true,
                            ],
                            'expanded' => true,
                            'multiple' => true
                        ]);

                    if ($Customer = $order->getCustomer()) {
                        $form
                            ->add('stripe_customer', HiddenType::class, [
                                'mapped' => false,
                                'data' => $order->getCustomer()->getTeams() ? $order->getCustomer()->getTeams()->first()->getStripeCustomerId() : ''
                            ])
                            ->add('cards', EntityType::class, [
                                'mapped' => false,
                                'required' => false,
                                'class' => Team::class,
                                'query_builder' => function(EntityRepository $er) use($Customer) {
                                    return $er->createQueryBuilder("t")
                                        ->where("t.Customer = :Customer")
                                        ->setParameter("Customer", $Customer);
                                },
                                'choice_label' => function(Team $team) {
                                    return $team->getStripePaymentMethodId();
                                },
                                'choice_value' => function(?Team $team) {
                                    return $team ? $team->getStripePaymentMethodId() : '';
                                },
                                'expanded' => true,
                                'multiple' => false,
                                'placeholder' => false
                            ]);
                    }
                }
            });
    }

    /**
     * @inheritDoc
     */
    public function getExtendedType()
    {
        // TODO: Implement getExtendedType() method.
        return OrderType::class;
    }
}
