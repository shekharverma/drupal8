<?php
namespace Drupal\wake_configurator_camp\Plugin\WebformHandler;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\webformSubmissionInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_order\Adjustment;

/**
 * Form submission handler.
 *
 * @WebformHandler(
 *   id = "camp_config_form_handler",
 *   label = @Translation("Camp config form handler"),
 *   category = @Translation("Form Handler"),
 *   description = @Translation("Doing something extra with form submissions"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class CampConfigHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {


        /** adding condition because submit need to work only when final submit of the form done and user needs to redirect to cart **/
        $form_state_values = $form_state->getValues();
        if(isset($form['actions']['wizard_prev']) && $form['actions']['submit'] && isset($form_state_values['in_draft'])){
          $current_language = \Drupal::languageManager()->getCurrentLanguage(\Drupal\Core\Language\LanguageInterface::TYPE_INTERFACE)->getId();
          
          $values = $webform_submission->getData();

          $week_selected  = $values['select_week'];

            // deleting old orders of this customer of type camp that is in cart(draft stage)
          $select_old_camp_orders = db_query("SELECT order_id FROM commerce_order WHERE type='configurator_camp' AND state='draft' AND uid=".$values['o_customer']);
          foreach ($select_old_camp_orders as $key => $res_old_camp_orders) {
            $old_order =  \Drupal::entityTypeManager()->getStorage('commerce_order')->load($res_old_camp_orders->order_id);
            if($old_order){
              $old_order->delete();
            }
          }

          $unit_price_product_values  = array(
            '1'=>array('oi_variation_id'=>$values['oi_wbd_am_1'],'oi_enduser'=>$values['oi_enduser_1'], 'oi_week_number'=>$week_selected),
            '2'=>array('oi_variation_id'=>$values['oi_wbd_pm_1'],'oi_enduser'=>$values['oi_enduser_1'], 'oi_week_number'=>$week_selected),
            '3'=>array('oi_variation_id'=>$values['oi_mc_am_1'], 'oi_enduser'=>$values['oi_enduser_1'], 'oi_week_number'=>$week_selected),
            '4'=>array('oi_variation_id'=>$values['oi_wf_pm_1'], 'oi_enduser'=>$values['oi_enduser_1'], 'oi_week_number'=>$week_selected),
            '5'=>array('oi_variation_id'=>$values['oi_option_1'],'oi_enduser'=>$values['oi_enduser_1'], 'oi_week_number'=>$week_selected),
            '6'=>array('oi_variation_id'=>$values['oi_repas_1'], 'oi_enduser'=>$values['oi_enduser_1'], 'oi_week_number'=>$week_selected),
            '7'=>array('oi_variation_id'=>$values['oi_tshirt_1'],'oi_enduser'=>$values['oi_enduser_1'], 'oi_week_number'=>$week_selected)
            );
          
          $check_stock_products = array(
            '1'=>array('oi_variation_id'=>$values['oi_wbd_am_1']),
            '2'=>array('oi_variation_id'=>$values['oi_wbd_pm_1']),
            '3'=>array('oi_variation_id'=>$values['oi_mc_am_1']),
            '4'=>array('oi_variation_id'=>$values['oi_wf_pm_1'])
          );

          foreach ($check_stock_products as $key => $res_stock_products) {
            if(isset($res_stock_products['oi_variation_id']) && $res_stock_products['oi_variation_id'] !=''){

              $select_camp_stocks = db_query("SELECT cscl.field_campstock_level_value FROM camp_stock__field_camp_id AS csci INNER JOIN camp_stock__field_campstock_level AS cscl ON cscl.entity_id = csci.entity_id WHERE csci.bundle='camp_stock' AND csci.field_camp_id_target_id=".$res_stock_products['oi_variation_id']);

              foreach ($select_camp_stocks as $res_camp_stocks) {
                if($res_camp_stocks->field_campstock_level_value == 0){
                  if($current_language == 'en'){
                    drupal_set_message(t('You have to restart again as some camps are not available anymore'), 'status', TRUE);
                  }else if($current_language == 'fr'){
                    drupal_set_message(t('Vous devez refaire la configuration du stage, car des camps ne sont plus disponible'), 'status', TRUE);
                  }else{
                    drupal_set_message(t('You have to restart again as some camps are not available anymore'), 'status', TRUE);
                  }
                  $send_back = new \Symfony\Component\HttpFoundation\RedirectResponse('/form/config-camp');
                  $send_back->send();
                  exit();
                }
              } 
            }
          }

            /** adding all selected products to cart **/
          $customer_account = \Drupal\user\Entity\User::load($values['o_customer']);


          $store_id = 1;
          $order_type = 'configurator_camp';

          $entity_manager = \Drupal::entityManager();
          $cart_manager   = \Drupal::service('commerce_cart.cart_manager');
          $cart_provider  = \Drupal::service('commerce_cart.cart_provider');
          $store = $entity_manager->getStorage('commerce_store')->load($store_id); 
          $cart = $cart_provider->getCart($order_type, $store, $customer_account);

          if (!$cart) {
              $cart = $cart_provider->createCart($order_type, $store, $customer_account);
          }

          foreach($unit_price_product_values as $field_key=>$res_product_values){
            if(isset($res_product_values['oi_variation_id']) && $res_product_values['oi_variation_id'] != ''){
              $variation_id = $res_product_values['oi_variation_id'];
              $enduser_id   = $res_product_values['oi_enduser'];
              $week_number  = $res_product_values['oi_week_number'];

              $product_variation = $entity_manager->getStorage('commerce_product_variation')->load($variation_id);
              // print $product_variation->getPrice();

              $order_item = $entity_manager->getStorage('commerce_order_item')->create(array(
                'type' => 'configurator_camp',
                'purchased_entity' => (string) $variation_id,
                'quantity' => 1,
                'unit_price' => $product_variation->getPrice(),
                'field_commerce_enduser' => (string) $enduser_id,
                'field_order_week' => (string) $week_number,
              ));
              $order_item->save();
              $cart_manager->addOrderItem($cart, $order_item);    
            }
          }

          $user_roles = \Drupal::currentUser()->getRoles();
          $role_has_all_access = 'accueil';

          /** adding tax_sw tax if accueil is filling the form **/
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
                'type' => 'configurator_camp',
                'purchased_entity' => (string) $tax_variation_id,
                'quantity' => 1,
                'unit_price' => $tax_product_variation->getPrice(),
                'field_commerce_enduser' => (string) $enduser_id,
              ));

              $order_item->save();
              $cart_manager->addOrderItem($cart, $order_item);    
            }              
          }          

          /** apply discount on order total if discount exist **/
          $unit_price_field_values = array('oi_wbd_am_1'=>$values['oi_wbd_am_1'], 'oi_wbd_pm_1'=>$values['oi_wbd_pm_1'], 'oi_mc_am_1'=>$values['oi_mc_am_1'], 'oi_wf_pm_1'=>$values['oi_wf_pm_1'], 'oi_option_1'=>$values['oi_option_1'], 'oi_repas_1'=>$values['oi_repas_1'], 'oi_tshirt_1'=>$values['oi_tshirt_1']);

          $discount_unit_price_field_values = array();

          if($values['camp_am'] == 'WD' && $values['oi_wbd_am_1'] != ''){
              $discount_unit_price_field_values['oi_discount_wbd_am'] = $values['oi_discount_wbd_am'];
          }

          if($values['camp_pm'] == 'WD' && $values['oi_wbd_pm_1'] != ''){
              $discount_unit_price_field_values['oi_discount_wbd_pm'] = $values['oi_discount_wbd_pm'];
          }

          if($values['camp_am'] == 'MC' && $values['oi_mc_am_1'] != ''){
              $discount_unit_price_field_values['oi_discount_mc'] = $values['oi_discount_mc'];
          }
          
          if($values['camp_am'] == 'WD' && $values['oi_wbd_am_1'] != '' && $values['camp_pm'] == 'WF' && $values['oi_wf_pm_1'] != ''){
              $discount_unit_price_field_values['oi_discount_fdc'] = $values['oi_discount_fdc'];
          }

          $total_order_amount   = 0;
          $total_discount_amount  = 0;

          foreach($unit_price_field_values AS $field_key=>$res_unit_price_field_values){
            if($res_unit_price_field_values != ''){
              $get_variations_data = db_query("SELECT price__number FROM commerce_product_variation_field_data WHERE variation_id=".$res_unit_price_field_values." LIMIT 1");
              foreach ($get_variations_data as $res_variations_data) {
                  $total_order_amount+= $res_variations_data->price__number;
              } 
            } 
          }

          foreach($discount_unit_price_field_values AS $field_key=>$res_discount_unit_price_field_values){
            if($res_discount_unit_price_field_values != ''){
              $get_discount_data = db_query("SELECT field_discount_number FROM commerce_product_attribute_value__field_discount WHERE entity_id=".$res_discount_unit_price_field_values." LIMIT 1");
              foreach ($get_discount_data as $res_discount_data) {
                  $total_discount_amount+=  $res_discount_data->field_discount_number;
              } 
            } 
          } 

          if($total_discount_amount > 0){
            $get_last_camp_order_id = db_query("SELECT order_id FROM commerce_order WHERE type='configurator_camp' AND uid = ".$values['o_customer']." ORDER BY order_id DESC LIMIT 1");
            foreach ($get_last_camp_order_id as $res_last_camp_order_id) {
              $camp_order_id = $res_last_camp_order_id->order_id;
            }
           
            $adjustment_amount  = number_format($total_discount_amount,2,".","");
            $order_total        = number_format($total_order_amount,2,".","");

            if ($adjustment_amount > $order_total) {
              $adjustment_amount = $order_total;
            }

            $order =  \Drupal::entityTypeManager()->getStorage('commerce_order')->load($camp_order_id);
            /** if discount greater that  0 **/
            if($adjustment_amount > 0){ 
              $get_amount_format = new Price($adjustment_amount, 'CHF'); 
              $new_order_adjustment = new Adjustment([
                'type' => 'promotion',
                'label' => t('Discount'),
                'amount' => $get_amount_format->multiply('-1'),
                'source_id' => 7,
                'locked'=>1,
              ]);
              if($order->getAdjustments()){
                $order_adjustment = $order->getAdjustments(); 
                $order->removeAdjustment($order_adjustment[0]);              
              }
              $order->addAdjustment($new_order_adjustment);
              $order->save();
            }
          }
        }
        return true;
 }
}   
?>