<?php
$form_fields = array(
  'enabled' => array(
      'title'       => 'Enable/Disable',
      'label'       => 'Enable Gateway for Qpay on WooCommerce',
      'type'        => 'checkbox',
      'description' => '',
      'default'     => 'no'
  ),
  'title' => array(
      'title'       => 'Title',
      'type'        => 'text',
      'description' => 'This controls the title which the user sees during checkout.',
      'default'     => 'Credit Card',
      'desc_tip'    => true,
  ),
  'description' => array(
      'title'       => 'Description',
      'type'        => 'textarea',
      'description' => 'This controls the description which the user sees during checkout.',
      'default'     => 'Pay with your credit card via our super-cool payment gateway.',
  ),
  'testmode' => array(
      'title'       => 'Test mode',
      'label'       => 'Enable Test Mode',
      'type'        => 'checkbox',
      'description' => 'Place the payment gateway in test mode using test API keys.',
      'default'     => 'yes',
      'desc_tip'    => true,
  ),
  'test_publishable_key' => array(
      'title'       => 'Test Publishable Key',
      'type'        => 'text'
  ),
  'test_private_key' => array(
      'title'       => 'Test Private Key',
      'type'        => 'password',
  ),
  'publishable_key' => array(
      'title'       => 'Live Publishable Key',
      'type'        => 'text'
  ),
  'private_key' => array(
      'title'       => 'Live Private Key',
      'type'        => 'password'
  )
);

?>
