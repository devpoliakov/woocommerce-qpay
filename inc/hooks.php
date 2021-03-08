<?php 


// test additional fields

add_action( 'woocommerce_admin_order_data_after_billing_address', 'qpay_editable_order_meta_billing' );    
function qpay_editable_order_meta_billing( $order ){    
  $qpay_uid = get_post_meta( $order->get_id(), 'qpay_uid', true );
  ?> 
  <div class=" uid qpay">
    <p<?php if( !$qpay_uid ) echo ' class="none_set"' ?>>
      <strong>Preferred Contact Method:</strong>
      <?php echo ( $qpay_uid ) ? $qpay_uid : '' ?>
    </p>
  </div>
  <?php
}
 
 /*
add_action( 'woocommerce_process_shop_order_meta', 'qpay_save_billing_details' );
 
function qpay_save_billing_details( $ord_id ){
  update_post_meta( $ord_id, 'qpay_uid', wc_clean( $_POST[ 'qpay_uid' ] ) );
}
*/