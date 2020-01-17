<?php

namespace Drupal\wake_configurator_camp\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderItemEvent;
use Drupal\commerce\Context;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce\PurchasableEntityInterface;



/**
 * Performs stock transactions on order and order item events.
 */
class WakeOrderItemEventSubsciber implements EventSubscriberInterface {

  // protected $entityTypeManager;
  
  public function __construct() {
      // $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Performs a stock transaction when an order item is deleted.
   *
   * @param \Drupal\commerce_order\Event\OrderItemEvent $event
   *   The order item event.
   */
  public function onOrderItemDelete(OrderItemEvent $event) {
    $item = $event->getOrderItem();
    $order_id  = $item->getOrderId();
    $order =  \Drupal::entityTypeManager()->getStorage('commerce_order')->load($order_id);
    if ($order && !in_array($order->getState()->value, ['draft', 'canceled'])) {
      $order_type = $order->bundle();
      if($order_type == 'configurator_camp'){
        $entity = $item->getPurchasedEntity();
        if (!$entity) {
              return;
        }
        $purchased_entity_id = $item->getPurchasedEntityId(); 
        $item_quantity = round($item->getQuantity()); 
        
        $select_camp_order_items = db_query("SELECT entity_id FROM camp_stock__field_camp_id WHERE field_camp_id_target_id=".$purchased_entity_id);
        foreach ($select_camp_order_items as $key => $res_camp_order_items) {
            $stock_entity =  \Drupal::entityTypeManager()->getStorage('camp_stock')->load($res_camp_order_items->entity_id);
            $stock_value = '';
            $stock_value = $stock_entity->get('field_campstock_level')->value;
            if($stock_value != ''){
              $new_stock_value = $stock_value+$item_quantity;
              if($new_stock_value >0){
                $stock_entity->set('field_campstock_level', $new_stock_value);
                $stock_entity->save();
              }
            }
        }
        
      }
    }
    
  }

  public function onOrderDelete(OrderEvent $event) {
    $order = $event->getOrder();
    $order_id = $order->id(); 
    // $order_id  = $item->getOrderId();
    // $order =  \Drupal::entityTypeManager()->getStorage('commerce_order')->load($order_id);
    if ($order && !in_array($order->getState()->value, ['draft', 'canceled'])) {
      $order_type = $order->bundle();
      if($order_type == 'configurator_camp' && isset($order_id) && $order_id != ''){
        $select_camp_order_items = db_query("SELECT csci.entity_id, coi.quantity FROM camp_stock__field_camp_id AS csci INNER JOIN commerce_order_item AS coi ON coi.purchased_entity = csci.field_camp_id_target_id WHERE coi.order_id=".$order_id);
        foreach ($select_camp_order_items as $key => $res_camp_order_items) {
        // print  $res_camp_order_items->entity_id; print "<br>";
          $item_quantity = round($res_camp_order_items->quantity);
          $stock_entity =  \Drupal::entityTypeManager()->getStorage('camp_stock')->load($res_camp_order_items->entity_id);
          $stock_value = '';
          $stock_value = $stock_entity->get('field_campstock_level')->value;
          if($stock_value != ''){
            $new_stock_value = $stock_value+$item_quantity;
            if($new_stock_value >0){
              $stock_entity->set('field_campstock_level', $new_stock_value);
              $stock_entity->save();
            }
          }
        }
      }
    }
    return true;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      // State change events fired on workflow transitions from state_machine.
      // 'commerce_order.place.post_transition' => ['onOrderPlace', -100],
      // 'commerce_order.cancel.post_transition' => ['onOrderCancel', -100],
      // Order storage events dispatched during entity operations in
      // CommerceContentEntityStorage.
      // ORDER_UPDATE handles new order items since ORDER_ITEM_INSERT doesn't.
      // OrderEvents::ORDER_UPDATE => ['onOrderUpdate', -100],
      OrderEvents::ORDER_PREDELETE => ['onOrderDelete', -100],
      // OrderEvents::ORDER_ITEM_UPDATE => ['onOrderItemUpdate', -100],
      OrderEvents::ORDER_ITEM_DELETE => ['onOrderItemDelete', -100],
    ];
    return $events;
  }


}
