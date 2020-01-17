<?php
namespace Drupal\wake_configurator\Plugin\WebformHandler;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\webformSubmissionInterface;


/**
 * Form submission handler.
 *
 * @WebformHandler(
 *   id = "coti_config_form_handler",
 *   label = @Translation("Coti config form handler"),
 *   category = @Translation("Form Handler"),
 *   description = @Translation("Doing something extra with form submissions"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class CotiConfigHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

        $values = $webform_submission->getData();

        $form_state_values = $form_state->getValues();

        /** adding condition because submit need to work only when final submit of the form done and user needs to redirect to cart **/
        if(isset($form['actions']['preview_prev']) && $form['actions']['submit'] && isset($form_state_values['in_draft'])){
            // deleting old orders of this customer that is in cart(draft stage)
            $select_old_coti_orders = db_query("SELECT order_id FROM commerce_order WHERE type='configurator' AND state='draft' AND uid=".$values['oi_enduser_1']);
            foreach ($select_old_coti_orders as $key => $res_old_coti_orders) {
              $old_order =  \Drupal::entityTypeManager()->getStorage('commerce_order')->load($res_old_coti_orders->order_id);
              if($old_order){
                $old_order->delete();
              }
            }   
            if(isset($values['oi_coti_1']) && trim($values['oi_coti_1']) !=''){
             
            }else{
              $values['oi_taxe_1'] = '';
            }                     
            $unit_price_product_values  = array('1'=>array('oi_variation_id'=>$values['oi_coti_1'], 'oi_enduser'=>$values['oi_enduser_1']),
              '2'=>array('oi_variation_id'=>$values['oi_taxe_1'], 'oi_enduser'=>$values['oi_enduser_1']),
              '3'=>array('oi_variation_id'=>$values['oi_coti_2'], 'oi_enduser'=>$values['oi_enduser_2']),
              '4'=>array('oi_variation_id'=>$values['oi_coti_3'], 'oi_enduser'=>$values['oi_enduser_3']),
              '5'=>array('oi_variation_id'=>$values['oi_coti_4'], 'oi_enduser'=>$values['oi_enduser_4']),
              '6'=>array('oi_variation_id'=>$values['oi_coti_5'], 'oi_enduser'=>$values['oi_enduser_5']),
              '7'=>array('oi_variation_id'=>$values['oi_coti_6'], 'oi_enduser'=>$values['oi_enduser_6']),
              '8'=>array('oi_variation_id'=>$values['oi_coti_7'], 'oi_enduser'=>$values['oi_enduser_7']),
              '9'=>array('oi_variation_id'=>$values['oi_coti_8'], 'oi_enduser'=>$values['oi_enduser_8']),
              '10'=>array('oi_variation_id'=>$values['oi_coti_9'], 'oi_enduser'=>$values['oi_enduser_9']),
              '11'=>array('oi_variation_id'=>$values['oi_badge'], 'oi_enduser'=>$values['oi_enduser_10']),
              '12'=>array('oi_variation_id'=>$values['oi_badge2'], 'oi_enduser'=>$values['oi_enduser_11']),
              '13'=>array('oi_variation_id'=>$values['oi_local'], 'oi_enduser'=>$values['oi_enduser_12']),
              '14'=>array('oi_variation_id'=>$values['oi_local2'], 'oi_enduser'=>$values['oi_enduser_13']),
              '15'=>array('oi_variation_id'=>$values['oi_team1'], 'oi_enduser'=>$values['oi_enduser_14']),
              '16'=>array('oi_variation_id'=>$values['oi_team2'], 'oi_enduser'=>$values['oi_enduser_15']),
              '17'=>array('oi_variation_id'=>$values['oi_team3'], 'oi_enduser'=>$values['oi_enduser_16']),
              '18'=>array('oi_variation_id'=>$values['oi_tshirt1'], 'oi_enduser'=>$values['oi_enduser_1']),
              '19'=>array('oi_variation_id'=>$values['oi_tshirt2'], 'oi_enduser'=>$values['oi_enduser_1']),
              '20'=>array('oi_variation_id'=>$values['oi_polo1'], 'oi_enduser'=>$values['oi_enduser_1']));
            
            // print "<pre>"; print_r($unit_price_product_values); die();

            /** adding all selected products to cart **/
            $customer_account = \Drupal\user\Entity\User::load($values['oi_enduser_1']);


            $store_id = 1;
            $order_type = 'configurator';

            $entity_manager = \Drupal::entityManager();
            $cart_manager   = \Drupal::service('commerce_cart.cart_manager');
            $cart_provider  = \Drupal::service('commerce_cart.cart_provider');

            $store = $entity_manager->getStorage('commerce_store')->load($store_id); 
            $cart = $cart_provider->getCart($order_type, $store, $customer_account);

              
            if (!$cart) {
                $cart = $cart_provider->createCart($order_type, $store, $customer_account);
            }
            // print "<pre>"; print_r($cart); die();

            foreach($unit_price_product_values as $field_key=>$res_product_values){
              if(isset($res_product_values['oi_variation_id']) && $res_product_values['oi_variation_id'] != ''){
                $variation_id = $res_product_values['oi_variation_id'];
                $enduser_id   = $res_product_values['oi_enduser'];

                $product_variation = $entity_manager->getStorage('commerce_product_variation')->load($variation_id);

                $order_item = $entity_manager->getStorage('commerce_order_item')->create(array(
                  'type' => 'configurator',
                  'purchased_entity' => (string) $variation_id,
                  'quantity' => 1,
                  'unit_price' => $product_variation->getPrice(),
                  'field_commerce_enduser' => (string) $enduser_id,
                ));

                $order_item->save();
                $cart_manager->addOrderItem($cart, $order_item);
              }
            }
                $user_roles = \Drupal::currentUser()->getRoles();
                $role_has_all_access = 'accueil';

                /** if accueil is adding the form then getting current user id from url **/
                if((in_array($role_has_all_access, $user_roles)) && (isset($_GET['member'])) && $_GET['member'] !=''){
                  $tax_variation_id = 0;
                  $select_tax_variation_id = db_query("SELECT variation_id FROM commerce_product_variation_field_data WHERE sku='TAX_SW'");
                  foreach ($select_tax_variation_id as $key => $res_tax_variation_id) {
                    $tax_variation_id = $res_tax_variation_id->variation_id;
                  }
                  if($tax_variation_id != 0){
                    $tax_product_variation = $entity_manager->getStorage('commerce_product_variation')->load($tax_variation_id);
                    $enduser_id = $_GET['member'];

                    $order_item = $entity_manager->getStorage('commerce_order_item')->create(array(
                      'type' => 'configurator',
                      'purchased_entity' => (string) $tax_variation_id,
                      'quantity' => 1,
                      'unit_price' => $tax_product_variation->getPrice(),
                      'field_commerce_enduser' => (string) $enduser_id,
                    ));

                    $order_item->save();
                    $cart_manager->addOrderItem($cart, $order_item);    
                  }              
                }
            // print "<pre>"; print_r($values); die();
        }
        
        return true;
 }
}   
?>