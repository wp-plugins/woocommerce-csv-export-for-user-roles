<?php
/*
  Plugin Name: Woocommerce Order Report Snapshot
  Plugin URI: https://clickersonline.com.au/
  Description: Use this tool to generate a snapshot of the customer orders made within the selected date range. This will generate a CSV file which contains an overview of the orders made by what user role.
  Version: 1.0
  Author: clickersonline
  Author URI: https://clickersonline.com.au/
  License: GPLv2 or later
  License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'WOO_EXPORT_CSV_PLUGIN_DIR' ) )
    define( 'WOO_EXPORT_CSV_PLUGIN_DIR', untrailingslashit( dirname( __FILE__ ) ) );

function woo_export_csv_get_version(){
	if (!function_exists( 'get_plugins' ) )
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	$plugin_folder = get_plugins( '/' . plugin_basename( dirname( __FILE__ ) ) );
	$plugin_file = basename( ( __FILE__ ) );
	return $plugin_folder[$plugin_file]['Version'];
}

function woo_export_csv_init(){

}
add_action('init', 'woo_export_csv_init');

/* Admin Option + CSS*/
add_action( 'admin_init', 'register_plugin_styles' );
add_action( 'init', 'csvfileconvert' );
add_action('admin_menu', 'woo_export_csv_admin_menu');

function csvfileconvert(){
    //echo $resultOrder;
    if(sanitize_text_field($_GET['woo_export_csv']) == 'csv'){
        $filename = 'woo_export_csv_' . date( 'Y-m-d' ) . '.csv';

        header( 'Content-Description: File Transfer' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ), true );
        global $wpdb;
        if(sanitize_text_field($_GET['woo_export_csv']) == 'csv'){
            $start_date = date( 'Y-m-d', strtotime( sanitize_text_field($_GET['start_date']) ));
            $end_date = date( 'Y-m-d', strtotime( sanitize_text_field($_GET['end_date']) ));
            //echo $start_date;
            //echo $end_date;die;
            //echo "<pre>";
            $sql2 = "
                SELECT posts.ID as order_id,
                posts.post_parent as parent_id,
                meta__order_total.meta_value as total_sales,
                meta__order_shipping.meta_value as total_shipping,
                meta__order_tax.meta_value as total_tax,
                meta__order_shipping_tax.meta_value as total_shipping_tax,
                posts.post_date as post_date
                FROM {$wpdb->prefix}posts AS posts
                LEFT JOIN {$wpdb->prefix}postmeta AS meta__order_total ON posts.ID = meta__order_total.post_id
                LEFT JOIN {$wpdb->prefix}postmeta AS meta__user_id ON posts.ID = meta__user_id.post_id
                LEFT JOIN {$wpdb->prefix}postmeta AS meta__order_shipping ON posts.ID = meta__order_shipping.post_id
                LEFT JOIN {$wpdb->prefix}postmeta AS meta__order_tax ON posts.ID = meta__order_tax.post_id
                LEFT JOIN {$wpdb->prefix}postmeta AS meta__order_shipping_tax ON posts.ID = meta__order_shipping_tax.post_id
                LEFT JOIN {$wpdb->prefix}posts AS parent ON posts.post_parent = parent.ID
                WHERE posts.post_type IN ( 'shop_order_refund','shop_order' )
                AND posts.post_status IN ( 'wc-completed','wc-processing','wc-on-hold')
                AND ( parent.post_status IN ( 'wc-completed','wc-processing','wc-on-hold') OR parent.ID IS NULL )
                AND	posts.post_date >= '$start_date'
                AND	posts.post_date <= '$end_date 23:59:59'
                AND meta__order_total.meta_key = '_order_total'
                AND meta__order_shipping.meta_key = '_order_shipping'
                AND meta__order_tax.meta_key = '_order_tax'
                AND meta__order_shipping_tax.meta_key = '_order_shipping_tax'
                GROUP BY posts.ID
                ORDER BY post_date ASC
            ";
            //echo $sql2;
            $result = $wpdb->get_results($sql2);

            //print_r($result);

            $orderArr = array();

            foreach($result as $order){
                $currentOrder = new WC_Order($order->order_id);

                $user_id = $currentOrder->get_user_id();

                if($user_id){
                    $user = new WP_User( $user_id );
                }
                else{
                    $currentParentOrder = new WC_Order($order->parent_id);
                    $user_id = $currentParentOrder->get_user_id();
                    if($user_id){
                        $user = new WP_User( $user_id );
                    }
                }

                if(!isset($orderArr[$user->roles[0]]['soldItems'])){
                    $orderArr[$user->roles[0]]['soldItems'] = 0;
                }
                $orderArr[$user->roles[0]]['soldItems'] += floatval($currentOrder->get_item_count());

                if(!isset($orderArr[$user->roles[0]]['totalOrder'])){
                    $orderArr[$user->roles[0]]['totalOrder'] = 0;
                }
                $orderArr[$user->roles[0]]['totalOrder']++;

                if(!isset($orderArr[$user->roles[0]]['shippingAmount'])){
                    $orderArr[$user->roles[0]]['shippingAmount'] = 0;
                }
                $orderArr[$user->roles[0]]['shippingAmount'] += (floatval($order->total_shipping));

                $coupon_sql =
                    "
                SELECT meta__coupon_amount.meta_value as coupon_discount
                FROM {$wpdb->prefix}woocommerce_order_items AS meta__coupon
                LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS meta__coupon_amount ON meta__coupon_amount.order_item_id = meta__coupon.order_item_id
                WHERE meta__coupon.order_id = $order->order_id
                AND meta__coupon.order_item_type = 'coupon'
                AND meta__coupon_amount.meta_key = 'discount_amount'
                ";
                $coupon_result = $wpdb->get_results($coupon_sql);

                if(empty($coupon_result)){
                    $coupon_amount = 0;
                }
                else{
                    $coupon_amount = $coupon_result[0]->coupon_discount;
                }

                if(!isset($orderArr[$user->roles[0]]['coupon'])){
                    $orderArr[$user->roles[0]]['coupon'] = 0;
                }
                $orderArr[$user->roles[0]]['coupon'] += $coupon_amount;

                if(!isset($orderArr[$user->roles[0]]['grosssale'])){
                    $orderArr[$user->roles[0]]['grosssale'] = 0;
                }
                $orderArr[$user->roles[0]]['grosssale'] += (floatval($order->total_sales));

                if(!isset($orderArr[$user->roles[0]]['netsale'])){
                    $orderArr[$user->roles[0]]['netsale'] = 0;
                }
                $orderArr[$user->roles[0]]['netsale'] += (floatval($order->total_sales) - floatval($order->total_tax)- floatval($order->total_shipping)- floatval($order->total_shipping_tax));

                if(!isset($orderArr[$user->roles[0]]['refund'])){
                    $orderArr[$user->roles[0]]['refund'] = 0;
                }
            }
            $sql_refunded = "
                SELECT meta__order_total.meta_value as total_refund,
                meta__order_shipping.meta_value as total_shipping,
                meta__order_tax.meta_value as total_tax,
                meta__order_shipping_tax.meta_value as total_shipping_tax,
                meta__user_id.meta_value as user_id
                FROM {$wpdb->prefix}posts AS posts
                LEFT JOIN {$wpdb->prefix}postmeta AS meta__order_total ON posts.ID = meta__order_total.post_id
                LEFT JOIN {$wpdb->prefix}postmeta AS meta__user_id ON posts.ID = meta__user_id.post_id
                LEFT JOIN {$wpdb->prefix}postmeta AS meta__order_shipping ON posts.ID = meta__order_shipping.post_id
                LEFT JOIN {$wpdb->prefix}postmeta AS meta__order_tax ON posts.ID = meta__order_tax.post_id
                LEFT JOIN {$wpdb->prefix}postmeta AS meta__order_shipping_tax ON posts.ID = meta__order_shipping_tax.post_id
                WHERE posts.post_type IN ( 'shop_order' )
                AND posts.post_status IN ( 'wc-refunded')
                AND	posts.post_date >= '$start_date'
                AND	posts.post_date <= '$end_date 23:59:59'
                AND meta__order_total.meta_key = '_order_total'
                AND meta__order_shipping.meta_key = '_order_shipping'
                AND meta__order_tax.meta_key = '_order_tax'
                AND meta__order_shipping_tax.meta_key = '_order_shipping_tax'
                AND meta__user_id.meta_key = '_customer_user'
                GROUP BY posts.ID
                ORDER BY post_date ASC
            ";

            $result_refunded = $wpdb->get_results($sql_refunded);

            foreach($result_refunded as $refunded){
                $user_id = $refunded->user_id;
                if($user_id){
                    $user = new WP_User( $user_id );
                }
                $orderArr[$user->roles[0]]['refund'] += floatval($refunded->total_refund);
            }
            //print_r($result_refunded);
            //print_r($orderArr);
            $resultOrder = "Customer Role,Date,Number of items sold,Number of orders,Coupon amount,Refund amount,Shipping amount,Gross Sales amount,Net Sales amount\n";
            $i = 1;
            foreach($orderArr as $key=>$singleOrder){
                $resultOrder .="$key,$start_date - $end_date,$singleOrder[soldItems],$singleOrder[totalOrder],$singleOrder[coupon],$singleOrder[refund],$singleOrder[shippingAmount],$singleOrder[grosssale],$singleOrder[netsale]";
                if(count($orderArr) != $i++){
                    $resultOrder .="\n";
                }
            }
        echo $resultOrder;
        exit;
        }
    }
}
function register_plugin_styles() {
    wp_register_style( 'woo-export-csv-admin-css', plugins_url( '/' . basename(dirname(__FILE__)) . '/css/woo-export-csv-admin.css'), array(), '1.0', 'all' ) ;
    wp_register_style( 'woo-export-csv-ui-style-css', plugins_url( '/' . basename(dirname(__FILE__)) . '/css/jquery-ui.min.css'), array(), '1.11.2', 'all' ) ;
    wp_register_script('woo-export-csv-ui-js', plugins_url( '/' . basename(dirname(__FILE__)) . '/js/datepicker.min.js'), array( 'jquery' ), '1.11.2'  );
}

function woo_export_csv_admin_menu() {
    $page = add_submenu_page('woocommerce', 'Order Export', 'Order Export', 'manage_options', 'order-export', 'woo_export_csv_page');
    add_action( 'admin_print_styles-' . $page, 'my_plugin_admin_styles' );
}

function my_plugin_admin_styles() {
    wp_enqueue_style( 'woo-export-csv-admin-css' );
    wp_enqueue_style( 'woo-export-csv-ui-style-css' );
    wp_enqueue_script('woo-export-csv-ui-js');
}

function woo_export_csv_page(){
    require_once( ABSPATH . 'wp-content/plugins/' . basename(dirname(__FILE__)) . '/export.php' );
}

/* Admin Option + CSS*/
function woo_export_csv_link($link) {
    $woo_export_csv_link = '<a href="admin.php?page=order-export">Export</a>';
    array_unshift($link, $woo_export_csv_link);
    return $link;
}

$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'woo_export_csv_link' );


?>
