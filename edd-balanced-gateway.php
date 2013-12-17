<?php
/**
 * Plugin Name:		Easy Digital Downloads - Balanced Gateway
 * Plugin URI:		https://easydigitaldownloads.com/extension/balanced-payment-gateway
 * Description:		Adds a payment gateway for balancedpayments.com
 * Version:			1.0.1
 * Author:			Daniel J Griffiths
 * Author URI:		http://ghost1227.com
 */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;


if( !class_exists( 'EDD_Balanced_Gateway' ) ) {

	class EDD_Balanced_Gateway {

		private static $instance;

		/**
		 * Get active instance
		 *
		 * @since		1.0.0
		 * @access		public
		 * @static
		 * @return		object self::$instance
		 */
		public static function get_instance() {
			if( !self::$instance )
				self::$instance = new EDD_Balanced_Gateway();

			return self::$instance;
		}


		/**
		 * Class constructor
		 *
		 * @since		1.0.0
		 * @access		public
		 * @return		void
		 */
		public function __construct() {
			if( !defined( 'BALANCED_PLUGIN_DIR' ) )
				define( 'BALANCED_PLUGIN_DIR', dirname( __FILE__ ) );

			if( !defined( 'BALANCED_PLUGIN_URL' ) )
				define( 'BALANCED_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

			if( !defined( 'BALANCED_PLUGIN_VER' ) )
				define( 'BALANCED_PLUGIN_VER', '1.0.1' );

			// Load our custom updater
			if( !class_exists( 'EDD_License' ) )
				include( BALANCED_PLUGIN_DIR . '/includes/EDD_License_Handler.php' );

			$this->init();
		}


		/**
		 * Run action and filter hooks
		 *
		 * @since		1.0.0
		 * @access		private
		 * @return		void
		 */
		private function init() {
			// Make sure EDD is active
			if( !class_exists( 'Easy_Digital_Downloads' ) ) return;

			global $edd_options;

			// Internationalization
			add_action( 'init', array( $this, 'textdomain' ) );

			// Register settings
			add_filter( 'edd_settings_gateways', array( $this, 'settings' ), 1 );

			// Handle licensing
			$license = new EDD_License( __FILE__, 'Balanced Gateway', BALANCED_PLUGIN_VER, 'Daniel J Griffiths' );

			// Enqueue scripts
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'wp_head', array( $this, 'balanced_js_init' ) );

			// Add the gateway
			add_filter( 'edd_payment_gateways', array( $this, 'register_gateway' ) );

			// Process payment
			add_action( 'edd_gateway_balanced', array( $this, 'process_payment' ) );

            // Display errors
			add_action( 'edd_after_cc_fields', array( $this, 'errors_div' ), 999 );
		}


		/**
		 * Internationalization
		 *
		 * @since		1.0.0
		 * @access		public
		 * @static
		 * @return		void
		 */
		public static function textdomain() {
			// Set filter for language directory
			$lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
			$lang_dir = apply_filters( 'edd_balanced_gateway_lang_dir', $lang_dir );

			// Load translations
			load_plugin_textdomain( 'edd-balanced-gateway', false, $lang_dir );
		}


		/**
		 * Add settings
		 *
		 * @since		1.0.0
		 * @access		public
		 * @param		array $settings The existing plugin settings
		 * @return		array
		 */
		public function settings( $settings ) {
			$balanced_gateway_settings = array(
				array(
					'id'	=> 'edd_balanced_settings',
					'name'	=> '<strong>' . __( 'Balanced Payments Settings', 'edd-balanced-gateway' ) . '</strong>',
					'desc'	=> __( 'Configure your Balanced Payments Gateway settings', 'edd-balanced-gateway' ),
					'type'	=> 'header'
				),
				array(
					'id'	=> 'edd_balanced_api_key',
					'name'	=> __( 'API Secret Key', 'edd-balanced-gateway' ),
					'desc'	=> __( 'Enter your Balanced Payments API Secret Key (<a href="https://dashboard.balancedpayments.com/">Dashboard</a>)', 'edd-balanced-gateway' ),
					'type'	=> 'text'
				),
				array(
					'id'	=> 'edd_balanced_marketplace_uri',
					'name'	=> __( 'Marketplace URI', 'edd-balanced-gateway' ),
					'desc'	=> __( 'Enter your marketplace URI (<a href="https://dashboard.balancedpayments.com/">Dashboard</a>)', 'edd-balanced-gateway' ),
					'type'	=> 'text'
				)
			);

			return array_merge( $settings, $balanced_gateway_settings );
		}


		/**
		 * Enqueue necessary scripts
		 *
		 * @since		1.0.0
		 * @access		public
		 * @return		void
		 */
		public function enqueue_scripts() {
			if( edd_is_checkout() && edd_is_gateway_active( 'balanced' ) ) {

				wp_enqueue_script( 'balanced', 'https://js.balancedpayments.com/v1/balanced.js', array( 'jquery' ) );
				wp_enqueue_script( 'edd-balanced-js', BALANCED_PLUGIN_URL . 'assets/js/edd-balanced.js', array( 'jquery', 'balanced' ), time() );
			
			}
		}


		/**
		 * Initialize balanced.js
		 *
		 * @since		1.0.0
		 * @access		public
		 * @global		array $edd_options
		 * @return		void
		 */
		public function balanced_js_init() {
			global $edd_options;

			if( edd_is_checkout() && edd_is_gateway_active( 'balanced' ) ) {
				if( isset( $edd_options['edd_balanced_marketplace_uri'] ) && $edd_options['edd_balanced_marketplace_uri'] != '' )
					echo '<script type="text/javascript">balanced.init(\'' . $edd_options['edd_balanced_marketplace_uri'] . '\')</script>';
			}
		}


		/**
		 * Register our new gateway
		 *
		 * @since		1.0.0
		 * @access		public
		 * @param		array $gateways The current gateway list
		 * @return		array $gateways The updated gateway list
		 */
		public function register_gateway( $gateways ) {
			$gateways['balanced'] = array(
				'admin_label'		=> 'Balanced Payments',
				'checkout_label'	=> __( 'Credit Card', 'edd-balanced-gateway' )
			);

			return $gateways;
		}


		/**
		 * Process payment submission
		 *
		 * @since		1.0.0
		 * @access		public
		 * @global		array $edd_options
		 * @param		array $purchase_data The data for a specific purchase
		 * @return		void
		 */
		public function process_payment( $purchase_data ) {
			global $edd_options;

			$errors = edd_get_errors();

			if( !$errors ) {

				// We need composer autoloader...
				require_once( BALANCED_PLUGIN_DIR . '/includes/libraries/vendor/autoload.php' );

				Httpful\Bootstrap::init();
				RESTful\Bootstrap::init();
				Balanced\Bootstrap::init();

				Balanced\Settings::$api_key = $edd_options['edd_balanced_api_key'];

				$customer = new Balanced\Customer();
				$customer->save();
				$customer->addCard( $_POST['balancedToken'] );

                try{
                    $amount = number_format( $purchase_data['price'] * 100, 0 );
					$result = $customer->debit( edd_sanitize_amount( $amount ) );
                } catch( Exception $e ) {
                    edd_record_gateway_error( __( 'Balanced Error', 'edd-balanced-gateway' ), print_r( $e, true ), 0 );
                    edd_set_error( 'card_declined', __( 'Your card was declined!', 'edd-balanced-gateway' ) );
                    edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
                }

                if( $result->status == 'succeeded' ) {
                    $payment_data = array(
                        'price'         => $purchase_data['price'],
                        'date'          => $purchase_data['date'],
                        'user_email'    => $purchase_data['user_email'],
                        'purchase_key'  => $purchase_data['purchase_key'],
                        'currency'      => $edd_options['currency'],
                        'downloads'     => $purchase_data['downloads'],
                        'cart_details'  => $purchase_data['cart_details'],
                        'user_info'     => $purchase_data['user_info'],
                        'status'        => 'pending'
                    );

                    $payment = edd_insert_payment( $payment_data );

                    if( $payment ) {
                        edd_insert_payment_note( $payment, sprintf( __( 'Balanced Transaction ID: %s', 'edd-balanced-gateway' ), $result->id ) );
                        edd_update_payment_status( $payment, 'publish' );
                        edd_send_to_success_page();
                    } else {
                        edd_set_error( 'authorize_error', __( 'Error: your payment could not be recorded. Please try again.', 'edd-balanced-gateway' ) );
                        edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
                    }
                } else {
                    wp_die( $result->description, $result->status );
                }
            } else {
                edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
            }
		}


		/**
		 * Output form errors
		 *
		 * @since		1.0.0
		 * @access		public
		 * @return		void
		 */
		public function errors_div() {
			echo '<div id="edd-balanced-payment-errors"></div>';
		}
	}
}


function edd_balanced_gateway_load() {
	$edd_balanced_gateway = new EDD_Balanced_Gateway();
}
add_action( 'plugins_loaded', 'edd_balanced_gateway_load' );
