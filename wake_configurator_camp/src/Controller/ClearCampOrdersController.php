<?php

namespace Drupal\wake_configurator_camp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * clear order of camp which are in draft more than 30 mins.
 */
class ClearCampOrdersController extends ControllerBase {

  public function __construct() {

  }

  public function content() {
    $order_ids            = array();
    $current_datetime     = date("d-m-Y H:i:s");
    $current_datetime_str = strtotime($current_datetime);
    $compare_datetime     = $current_datetime_str - (30 * 60); // subtract 30 mins from current time
    


    $select_old_orders = db_query("SELECT order_id, created, changed FROM commerce_order WHERE type='configurator_camp' AND state='draft' AND created < ".$compare_datetime." AND changed < ".$compare_datetime);
    foreach ($select_old_orders as $key => $res_old_orders) {
      $order =  \Drupal::entityTypeManager()->getStorage('commerce_order')->load($res_old_orders->order_id);
      $order_ids[] = $order->id();
      $order->delete();
    }
    if(count($order_ids) > 0){
      $imploade_order_ids = implode(',', $order_ids);
    }else{
      $imploade_order_ids = 0;
    }
    return array(
      '#type' => 'markup',
      '#markup' => $this->t('Deleted Order Ids: '.$imploade_order_ids),
    );
  }
}
