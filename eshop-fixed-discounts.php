<?php defined('ABSPATH') or die("No direct access allowed");
/*
* Plugin Name:   eShop Fixed Discounts
* Plugin URI:	 http://usestrict.net/2013/12/eshop-fixed-discounts-free-plugin/
* Description:   Provide fixed discounts depending on cart total
* Version:       1.0
* Author:        Vinny Alves
* Author URI:    http://www.usestrict.net
*
* License:       GNU General Public License, v2 (or newer)
* License URI:  http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* Copyright (C) 2013 www.usestrict.net, released under the GNU General Public License.
*/

class eShopFixedDiscounts
{
	const VERSION = '1.0';

	public $domain = 'eshop-coupons-plus';
	public static $instance;
	
	protected $env;
	
	private $nonce_name = 'eshop_fixed_discounts_nonce';
	private $total;
	private $disc_type;
	private $discount;	
	
	public function __construct()
	{
		$this->set_env();
		
		if (is_admin())
			$this->admin_init();
		else
		{	
			add_filter('eshop_is_discountable', array(&$this, 'is_discountable'), 1, 1);
			add_action('wp_footer', array(&$this,'maybe_add_cart_js'));
		}
	}

	
	public static function bootstrap()
	{
		if (! isset(self::$instance))
			self::$instance = new self();
		
		return self::$instance;
	}
	
	
	protected function set_env()
	{
		if ( isset($this->env) )
			return;
	
		$this->env = (object) array();
	
		$this->env->base_dir = trailingslashit(dirname(__FILE__));
		$this->env->inc_dir = $this->env->base_dir . 'includes/';
		$this->env->url     = plugins_url('', __FILE__);
		$this->env->js_url  = $this->env->url . '/includes/assets/js/';
	}
	
	
	
	public function admin_init()
	{
		if (isset($_POST) && isset($_POST['action']) && 'save_eshop_metaboxes_general' === $_POST['action'] && 
			isset($_POST['eshop-action-status'])     && 'Discounts' === $_POST['eshop-action-status'])
		{
			add_action('admin_init', array(&$this, 'capture_form_edit'), 1, 0);
		}
		
		add_action('admin_head-settings_page_eshop-settings', array(&$this, 'maybe_add_admin_js'), 1000);
	}
	
	public function maybe_add_admin_js()
	{
		if (isset($_GET['mstatus']) && 'Discounts' === $_GET['mstatus'])
		{
			$opts = maybe_unserialize(get_option($this->domain.'-fixed_discounts', array(1=>'%', 2=>'%', 3=>'%')),true);
			
			wp_enqueue_script($this->domain . '-fixed-discounts', $this->env->js_url . 'fixed_discounts.js', array('jquery'));
			wp_localize_script($this->domain . '-fixed-discounts', 'eshop_fixed_discounts', array(
																						'nonce_name' => $this->nonce_name,
																						'nonce'		 => wp_create_nonce($this->nonce_name), 
																						'opts'       => $opts,
																						'is_admin'   => true)
			);
		}
	}
	
	public function capture_form_edit()
	{
		check_admin_referer( $this->nonce_name, $this->nonce_name );
		
		$opts = array();
		foreach (array(1,2,3) as $i)
		{
			$opts[$i] = $_POST['usc_fixed_discount_type_'.$i];
		}
		
		update_option($this->domain.'-fixed_discounts', $opts);
	}
	
	
	public function is_discountable($percent)
	{
		global $eshopoptions;
		
		if (! isset($this->total))
			$this->total = calculate_total();
		
		$my_opts = maybe_unserialize(get_option($this->domain.'-fixed_discounts', array(1=>'%', 2=>'%', 3=>'%')),true);
		
		for ($x=1;$x<=3;$x++)
		{
			if(isset($eshopoptions['discount_spend'.$x]) && $eshopoptions['discount_spend'.$x] != '')
			{
				$edisc[$eshopoptions['discount_spend'.$x]]['val'] = $eshopoptions['discount_value'.$x];
				$edisc[$eshopoptions['discount_spend'.$x]]['type'] = $my_opts[$x];
			}
		}
		
		$discount = 0;
		$disc_type = '%';
		if(isset($edisc) && is_array($edisc))
		{
			ksort($edisc);
			foreach ($edisc as $amt => $ary)
			{
				if($amt <= $this->total)
				{
					$this->discount  = $ary['val'];
					$this->disc_type = $ary['type'];
				}
			}
		}

		$retval = 0;
		
		if ($this->discount > 0)
		{
			$retval = ('%' === $this->disc_type) ? $this->discount : $this->discount / $this->total * 100; 
		} 
		
		return ('$' === $this->disc_type && $this->discount >= $this->total) ? 0 : $retval;
	}
	
	
	public function get_discount_info($info=array())
	{
		if (isset($this->discount))
			$info['discount'] = $this->discount;
		
		if (isset($this->disc_type))
			$info['disc_type'] = $this->disc_type;
		
		return $info;
	}
	
	
	public function maybe_add_cart_js()
	{
		global $eshopoptions, $post;
		
		$checkout_page_id = (int) $eshopoptions['checkout'];
		$cart_page_id     = (int) $eshopoptions['cart'];
		
		if ($post->ID !== $checkout_page_id && $post->ID !== $cart_page_id)
			return;
		
		if ('$' !== $this->disc_type)
			return;
		
		wp_enqueue_script($this->domain . '-fixed-discounts', $this->env->js_url . 'fixed_discounts.js', array('jquery'));
		wp_localize_script($this->domain . '-fixed-discounts', 'eshop_fixed_discounts', 
				array('is_admin'   => false, 
					  'lang' => array('discount_applied' => '<small>(' . sprintf(__('Including Discount of <span>%s%s</span>','eshop'),
									   $eshopoptions['currency_symbol'], number_format_i18n(round($this->discount, 2),2)).')</small>')));
	}
	
}


$usc_eShopFixedDiscounts = eShopFixedDiscounts::bootstrap();


/* End of file eshop-fixed-discounts.php */
/* Location: eshop-fixed-discounts/eshop-fixed-discounts.php */