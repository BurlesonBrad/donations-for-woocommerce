<?php
/**
 * Plugin Name: Donations for WooCommerce
 * Description: Easily accept donations of varying amounts through your WooCommerce store.
 * Version: 1.0.3
 * Author: Potent Plugins
 * Author URI: http://potentplugins.com/?utm_source=donations-for-woocommerce&utm_medium=link&utm_campaign=wp-plugin-credit-link
 * License: GNU General Public License version 2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html
 */

// Add Istructions link in plugins list
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'hm_wcdon_action_links');
function hm_wcdon_action_links($links) {
	array_unshift($links, '<a href="'.esc_url(get_admin_url(null, 'index.php?page=hm_wcdon')).'">Instructions</a>');
	return $links;
}

// Add admin page
add_action('admin_menu', 'hm_wcdon_admin_menu');
function hm_wcdon_admin_menu() {
	add_dashboard_page('Donations for WooCommerce', '', 'activate_plugins', 'hm_wcdon', 'hm_wcdon_admin_page');
}

// Display admin page
function hm_wcdon_admin_page() {
	echo('
		<div class="wrap">
			<h2>Donations for WooCommerce</h2>
			<h3>Usage Instructions</h3>
			<p>Simply create a new WooCommerce product for each type of donation you would like to accept. Under <em>Product Data</em>, set the product type to &quot;Donation&quot;. Optionally, set the default donation amount in the <em>General</em> section. You\'ll probably also want to ensure that product reviews are disabled in the <em>Advanced</em> section. That\'s all!</p>
	');
	$potent_slug = 'donations-for-woocommerce';
	include(__DIR__.'/plugin-credit.php');
	echo('
		</div>
	');
}

// Disable price display in frontend for Donation products
add_filter('woocommerce_get_price_html', 'hm_wcdon_get_price_html', 10, 2);
function hm_wcdon_get_price_html($price, $product) {
	if ($product->product_type == 'donation')
		return (is_admin() ? 'Variable' : '');
	else
		return $price;
}

// Add amount field before add to cart button
add_action('woocommerce_before_add_to_cart_button', 'hm_wcdon_before_add_to_cart_button');
function hm_wcdon_before_add_to_cart_button() {
	global $product;
	if ($product->product_type == 'donation') {
		echo('<span class="wc-donation-amount">
				<label for="donation_amount_field">Amount:</label>
				<input type="number" name="donation_amount" id="donation_amount_field" size="5" min="0" step="0.01" value="'.number_format($product->price, 2).'" />
			</span>');
	}
}

// Add Donation product type option
add_filter('product_type_selector', 'hm_wcdon_product_type_selector');
function hm_wcdon_product_type_selector($productTypes) {
	$productTypes['donation'] = 'Donation';
	return $productTypes;
}

// Hide all but the General and Advanced product data tabs for Donation products
add_filter('woocommerce_product_data_tabs', 'hm_wcdon_product_data_tabs', 10, 1);
function hm_wcdon_product_data_tabs($tabs) {
	foreach ($tabs as $tabId => $tabData) {
		if ($tabId != 'general' && $tabId != 'advanced')
			$tabs[$tabId]['class'][] = 'hide_if_donation';
	}
	return $tabs;
}

// Create the WC_Product_Donation class
add_filter('plugins_loaded', 'hm_wcdon_create_product_type');
function hm_wcdon_create_product_type() {
	if (!class_exists('WC_Product_Simple'))
		return;
	class WC_Product_Donation extends WC_Product_Simple {
		function __construct($product) {
			parent::__construct($product);
			$this->product_type = 'donation';
		}
		function is_sold_individually() { return true; }
		function is_taxable() { return false; }
		function needs_shipping() { return false; }
		function add_to_cart_text() { return 'Donate'; }
		function add_to_cart_url() { return get_permalink($this->id); }
	}
}

// Add the default amount field to the General product data tab
add_filter('woocommerce_product_options_general_product_data', 'hm_wcdon_product_options_general');
function hm_wcdon_product_options_general() {
	global $thepostid;
	echo('<div class="options-group show_if_donation">');
	woocommerce_wp_text_input(array('id' => 'donation_default_amount', 'label' => 'Default amount', 'value' => get_post_meta($thepostid, '_price', true), 'data_type' => 'price'));
	echo('</div>');
}

// Save the default donation amount
add_action('woocommerce_process_product_meta_donation', 'hm_wcdon_process_product_meta');
function hm_wcdon_process_product_meta($productId) {
	$price = ($_POST['donation_default_amount'] === '') ? '' : wc_format_decimal($_POST['donation_default_amount']);
	update_post_meta($productId, '_price', $price);
	update_post_meta($productId, '_regular_price', $price);
}

// Process donation amount when a Donation product is added to the cart
add_filter('woocommerce_add_cart_item', 'hm_wcdon_add_cart_item');
function hm_wcdon_add_cart_item($item) {
	if ($item['data']->product_type == 'donation') {
		if (isset($_POST['donation_amount']) && is_numeric($_POST['donation_amount']) && $_POST['donation_amount'] > 0)
			$item['donation_amount'] = $_POST['donation_amount']*1;
		else
			$item['donation_amount'] = 0;
		$item['data']->price = $item['donation_amount'];
	}
	return $item;
}

// Use the Simple product type's add to cart button for Donation products
add_action('woocommerce_donation_add_to_cart', 'hm_wcdon_add_to_cart_template');
function hm_wcdon_add_to_cart_template() {
	do_action('woocommerce_simple_add_to_cart');
}

// Set Donation product price when loading the cart
add_filter('woocommerce_get_cart_item_from_session', 'hm_wcdon_get_cart_item_from_session');
function hm_wcdon_get_cart_item_from_session($session_data) {
	if ($session_data['data']->product_type == 'donation' && isset($session_data['donation_amount']))
			$session_data['data']->price = $session_data['donation_amount'];
	return $session_data;
}

// Add the donation amount field to the cart display
add_filter('woocommerce_cart_item_price', 'hm_wcdon_cart_item_price', 10, 3);
function hm_wcdon_cart_item_price($price, $cart_item, $cart_item_key) {
	return ($cart_item['data']->product_type == 'donation' ? 
				'<input type="number" name="donation_amount_'.$cart_item_key.'" size="5" min="0" step="0.01" value="'.$cart_item['data']->price.'" />' :
				$price);
}

// Process donation amount fields in cart updates
add_filter('woocommerce_update_cart_action_cart_updated', 'hm_wcdon_update_cart');
function hm_wcdon_update_cart($cart_updated) {
	foreach (WC()->cart->get_cart() as $key => $cartItem) {
		if ($cartItem['data']->product_type == 'donation' && isset($_POST['donation_amount_'.$key])
				&& is_numeric($_POST['donation_amount_'.$key]) && $_POST['donation_amount_'.$key] > 0 && $_POST['donation_amount_'.$key] != $cartItem['data']->price) {
			$cartItem['donation_amount'] = $_POST['donation_amount_'.$key]*1;
			$cartItem['data']->price = $cartItem['donation_amount'];
			WC()->cart->cart_contents[$key] = $cartItem;
			$cart_updated = true;
		}
	}
	return $cart_updated;
}
?>