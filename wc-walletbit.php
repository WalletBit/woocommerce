<?php
/*
Plugin Name: WooCommerce WalletBit
Plugin URI: https://walletbit.com/shop/woocommerce
Description: Extends WooCommerce with an bitcoin gateway.
Version: 1.0
Author: Kris
Author URI: https://walletbit.com/shop/woocommerce
 
Copyright: © 2013 WalletBit.
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/
add_action('plugins_loaded', 'woocommerce_walletbit_init', 0);

function woocommerce_walletbit_init()
{
	if (!class_exists('WC_Payment_Gateway'))
	{
		return;
	}

	/**
	* Add the Gateway to WooCommerce
	**/
	function woocommerce_add_walletbit_gateway($methods)
	{
		$methods[] = 'WC_WalletBit';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'woocommerce_add_walletbit_gateway');

	class WC_WalletBit extends WC_Payment_Gateway
	{
		public function __construct()
		{
			$this->id = 'walletbit';
			$this->icon = plugins_url( 'images/bitcoin.png', __FILE__ );
			$this->medthod_title = 'WalletBit';
			$this->has_fields = false;

			$this->init_form_fields();
			$this->init_settings();

			$this->title          = $this->settings['title'];
			$this->description    = $this->settings['description'];
			$this->email          = $this->settings['email'];
			$this->token          = $this->settings['token'];
			$this->securityword   = $this->settings['securityword'];
			$this->debug          = $this->settings['debug'];

			$this->liveurl = 'https://walletbit.com/pay';

			$this->msg['message'] = "";
			$this->msg['class'] = "";

			add_action('init', array(&$this, 'check_walletbit_response'));
			add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
			add_action('woocommerce_receipt_walletbit', array(&$this, 'receipt_page'));

            // Valid for use.
            $this->enabled = ( 'yes' == $this->settings['enabled'] ) && !empty( $this->email ) && !empty( $this->token ) && !empty( $this->securityword ) && $this->is_valid_for_use();

            // Checking if email is not empty.
            $this->email == '' ? add_action( 'admin_notices', array( &$this, 'email_missing_message' ) ) : '';

            // Checking if app_secret is not empty.
            $this->token == '' ? add_action( 'admin_notices', array( &$this, 'token_missing_message' ) ) : '';

            // Checking if app_secret is not empty.
            $this->securityword == '' ? add_action( 'admin_notices', array( &$this, 'securityword_missing_message' ) ) : '';
		}

		public function is_valid_for_use()
		{
			// bitcoin can be used in any country in any currency
			//if ( !in_array( get_woocommerce_currency() , array( 'BTC', 'USD' ) ) ) {
			//    return false;
			//}

			return true;
		}

		function init_form_fields()
		{
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'walletbit' ),
					'type' => 'checkbox',
					'label' => __( 'Enable WalletBit', 'walletbit' ),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __( 'Title', 'walletbit' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'walletbit' ),
					'default' => __( 'WalletBit', 'walletbit' )
				),
				'description' => array(
					'title' => __( 'Description', 'walletbit' ),
					'type' => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'walletbit' ),
					'default' => __( 'Pay with Bitcoin', 'walletbit' )
				),
				'email' => array(
					'title' => __( 'WalletBit Email', 'walletbit' ),
					'type' => 'text',
					'description' => __( 'Please enter your WalletBit Merchant Email', 'walletbit' ) . ' ' . sprintf( __( 'You can to get this information in: %sWalletBit Account%s.', 'walletbit' ), '<a href="https://walletbit.com/businesstools/IPN" target="_blank">', '</a>' ),
					'default' => ''
				),
				'token' => array(
					'title' => __( 'WalletBit Token', 'walletbit' ),
					'type' => 'text',
					'description' => __( 'Please enter your WalletBit Token', 'walletbit' ) . ' ' . sprintf( __( 'You can to get this information in: %sWalletBit Account%s.', 'walletbit' ), '<a href="https://walletbit.com/businesstools/IPN" target="_blank">', '</a>' ),
					'default' => ''
				),
				'securityword' => array(
					'title' => __( 'WalletBit Security Word', 'walletbit' ),
					'type' => 'text',
					'description' => __( 'Please enter your WalletBit Security Word', 'walletbit' ) . ' ' . sprintf( __( 'You can to get this information in: %sWalletBit Account%s.', 'walletbit' ), '<a href="https://walletbit.com/businesstools/IPN" target="_blank">', '</a>' ),
					'default' => ''
				),
				'debug' => array(
					'title' => __( 'Debug Log', 'walletbit' ),
					'type' => 'checkbox',
					'label' => __( 'Enable logging', 'walletbit' ),
					'default' => 'no',
					'description' => __( 'Log WalletBit events, such as API requests, inside <code>woocommerce/logs/walletbit.txt</code>', 'walletbit'  ),
				)
			);
		}

		public function admin_options()
		{
			?>
			<h3><?php _e('WalletBit Checkout', 'walletbit');?></h3>

			<?php if ( empty( $this->email ) ) : ?>
				<div id="wc_get_started">
					<span class="main"><?php _e('Get started with WalletBit Checkout', 'walletbit'); ?></span>
					<span><a href="https://walletbit.com/shop/woocommerce">WalletBit Checkout</a> <?php _e('provides a secure way to collect and transmit bitcoin to your payment gateway.', 'walletbit'); ?></span>

					<p><a href="https://walletbit.com/signup" target="_blank" class="button button-primary"><?php _e('Join for free', 'walletbit'); ?></a> <a href="https://walletbit.com/shop/woocommerce" target="_blank" class="button"><?php _e('Learn more about WooCommerce and WalletBit', 'walletbit'); ?></a></p>

				</div>
			<?php else : ?>
				<p><a href="https://walletbit.com/shop/woocommerce">WalletBit Checkout</a> <?php _e('provides a secure way to collect and transmit bitcoin to your payment gateway.', 'walletbit'); ?></p>
			<?php endif; ?>

			<table class="form-table">
				<?php $this->generate_settings_html(); ?>
			</table>
			<?php
		}

		/**
		*  There are no payment fields for walletbit, but we want to show the description if set.
		**/
		function payment_fields()
		{
			if ($this->description)
				echo wpautop(wptexturize($this->description));
		}

		/**
		* Receipt Page
		**/
		function receipt_page($order)
		{
			echo '<p>'.__('Thank you for your order, please click the button below to pay with WalletBit.', 'walletbit').'</p>';
			echo $this->generate_walletbit_form($order);
		}

		/**
		* Generate walletbit button link
		**/
		public function generate_walletbit_form($order_id)
		{
			global $woocommerce;

			$order = &new woocommerce_order($order_id);

			$item_names = array();

			if (sizeof($order->get_items()) > 0) : foreach ($order->get_items() as $item) :
				if ($item['qty']) $item_names[] = $item['name'] . ' x ' . $item['qty'];
			endforeach; endif;

			$item_name = sprintf( __('Order %s' , 'woocommerce'), $order->get_order_number() ) . " - " . implode(', ', $item_names);

			$walletbit_args = array(
				'token' => $this->token,
				'item_name' => $item_name,
				'amount' => number_format($order->order_total, 2, '.', ''),
				'currency' => get_woocommerce_currency(),
				'returnurl' => esc_url($this->get_return_url($order)),
				'cancelurl' => esc_url($order->get_cancel_order_url()),
				'additional' => 'email=' . $order->billing_email . '|custom=' . $order_id . '|invoice=' . $order->order_key
			);

			$walletbit_args = apply_filters( 'woocommerce_walletbit_args', $walletbit_args );

			$walletbit_args_array = array();

			foreach($walletbit_args as $key => $value)
			{
				$walletbit_args_array[] = '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
			}

			return '<form action="' . $this->liveurl . '" method="post" id="walletbit_payment_form">
			' . implode('', $walletbit_args_array) . '
			<input type="submit" class="button-alt" id="submit_walletbit_payment_form" value="'.__('Pay via WalletBit', 'walletbit').'" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">'.__('Cancel order &amp; restore cart', 'walletbit').'</a>
			<script type="text/javascript">
			jQuery(function(){
			jQuery("body").block(
			{
				message: "<img src=\"' . $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to WalletBit Payment Gateway to make the payment.', 'walletbit').'",
				overlayCSS:
				{
					background: "#fff",
					opacity: 0.6
				},
				css: {
					padding:        20,
					textAlign:      "center",
					color:          "#555",
					border:         "3px solid #aaa",
					backgroundColor:"#fff",
					cursor:         "wait",
					lineHeight:"32px"
				}
			});
			jQuery("#submit_walletbit_payment_form").click();});</script>
			</form>';
		}

		/**
		* Process the payment and return the result
		**/
		function process_payment($order_id)
		{
			$order = &new woocommerce_order($order_id);
			return array(
				'result' => 'success',
				'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
			);
		}

		/**
		* Check for valid walletbit server callback
		**/
		function check_walletbit_response()
		{
			if ($_GET['walletbit_callback'] == true)
			{
				global $woocommerce;

				$str =
				$_POST["merchant"].":".
				$_POST["customer_email"].":".
				$_POST["amount"].":".
				$this->securityword;

				$hash = strtoupper(hash('sha256', $str));

				// proccessing payment only if hash is valid
				if (isset($_POST['type']) && strtolower($_POST['type']) == 'cancel' && $_POST["merchant"] == $this->email && $_POST["encrypted"] == $hash)
				{
					$order_id = intval($_POST['custom']);
					$order_key = $_POST['invoice'];

					$order = new woocommerce_order($order_id);

					// Put this order on-hold for manual checking
					$order->update_status( 'cancelled', sprintf( __( 'IPN: Received cancel notification from WalletBit.', 'woocommerce' ), '' ) );
				}
				else
				{
					$str =
					$_POST["merchant"].":".
					$_POST["customer_email"].":".
					$_POST["amount"].":".
					$_POST["batchnumber"].":".
					$_POST["txid"].":".
					$_POST["address"].":".
					$this->securityword;

					$hash = strtoupper(hash('sha256', $str));

					// proccessing payment only if hash is valid
					if ($_POST["merchant"] == $this->email && $_POST["encrypted"] == $hash && $_POST["status"] == 1)
					{
						$order_id = intval($_POST['custom']);
						$order_key = $_POST['invoice'];

						$order = new woocommerce_order($order_id);

						if ($order->order_key !== $order_key)
						{
							if ($this->debug=='yes') $this->log->add( 'walletbit', 'Error: Order Key does not match invoice.' );
						}
						else
						{
							if ($order->status == 'completed')
							{
								if ($this->debug=='yes') $this->log->add( 'walletbit', 'Aborting, Order #' . $order_id . ' is already complete.' );
							}
							else
							{
								// Validate Amount
								$amount = number_format($_POST['amount'] * $_POST['rate'], 2, '.', '');
								$total = number_format($order->get_total(), 2, '.', '');

								if ($amount >= $total)
								{
									// Payment completed
									$order->add_order_note( __('IPN: Payment completed notification from WalletBit', 'woocommerce') );
									$order->payment_complete();

									if ($this->debug=='yes') $this->log->add( 'walletbit', 'Payment complete.' );
								}
								else
								{
									if ($this->debug == 'yes')
									{
										$this->log->add( 'walletbit', 'Payment error: Amounts do not match (gross ' . $amount . ')' );
									}

									// Put this order on-hold for manual checking
									$order->update_status( 'on-hold', sprintf( __( 'IPN: Validation error, amounts do not match (gross %s).', 'woocommerce' ), $amount ) );
								}
							}
						}
					}
				}

				print '1';
			}
		}

        /**
         * Adds error message when not configured the email.
         *
         * @return string Error Mensage.
         */
        public function email_missing_message() {
            $message = '<div class="error">';
                $message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should enter your Email in WalletBit configuration. %sClick here to configure!%s' , 'wcwalletbit' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&amp;tab=payment_gateways">', '</a>' ) . '</p>';
            $message .= '</div>';

            echo $message;
        }

        /**
         * Adds error message when not configured the token.
         *
         * @return String Error Mensage.
         */
        public function token_missing_message() {
            $message = '<div class="error">';
                $message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should enter your Token in WalletBit configuration. %sClick here to configure!%s' , 'wcwalletbit' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&amp;tab=payment_gateways">', '</a>' ) . '</p>';
            $message .= '</div>';

            echo $message;
        }

        /**
         * Adds error message when not configured the security word.
         *
         * @return String Error Mensage.
         */
        public function securityword_missing_message() {
            $message = '<div class="error">';
                $message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should enter your Security Word in WalletBit configuration. %sClick here to configure!%s' , 'wcwalletbit' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&amp;tab=payment_gateways">', '</a>' ) . '</p>';
            $message .= '</div>';

            echo $message;
        }
	}
}