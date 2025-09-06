# PowerTranz 3DS2 - WooCommerce Payment Gateway

A WooCommerce Payment Gateway plugin for First Atlantic Commerce (PowerTranz) 3DS2.

## Description

This plugin integrates the PowerTranz 3DS2 payment gateway with WooCommerce, allowing customers to securely pay using their credit cards via First Atlantic Commerce's PowerTranz payment processing service.

The plugin supports EMV 3D-Secure versions 2.x with fallback to 3DS version 1.0, as well as support for non-3DS enabled cards.

## Features

- Credit card processing with PowerTranz
- Support for 3D Secure 2.0 (3DS2)
- Address Verification Service (AVS)
- IP Geolocation for enhanced security
- Risk management data display in order details
- Support for:
  - Credit card authorization and capture
  - Credit card charging
  - Refunds and voids
  - Detailed customer decline messages

## Requirements

- PHP 7.4 or higher (PHP 8.x recommended)
- WordPress 5.2 or higher
- WooCommerce 4.9.2 or higher
- SSL certificate (HTTPS) for your website

## Installation

1. Upload the plugin files to the `/wp-content/plugins/cc-woocommerce-gateway-powertranz` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to WooCommerce > Settings > Payments to configure the payment gateway.

## Configuration

1. Navigate to WooCommerce > Settings > Payments.
2. Click on "PowerTranz 3DS2 - WooCommerce Payment Gateway" to configure the settings.
3. Enter your PowerTranz credentials:
   - For Production: PowerTranzId and PowerTranzPassword
   - For Sandbox: Sandbox PowerTranzId and Sandbox PowerTranzPassword
4. Configure 3D Secure settings (recommended to keep enabled).
5. Optionally configure Address Verification (AVS).
6. For IP Geolocation, sign up at [ipgeolocation.io](https://ipgeolocation.io) and enter your API key.
7. Save changes.

## Supported Payment Methods

- Visa
- MasterCard

## Versioning

This plugin follows [Semantic Versioning](https://semver.org/). The version format is `MAJOR.MINOR.PATCH`:

- MAJOR version for incompatible API changes
- MINOR version for functionality added in a backward compatible manner
- PATCH version for backward compatible bug fixes

Current version: 0.2.1

## Support

For support, please visit:
- [Documentation](https://github.com/cloudcogsio/woocommerce-omnipay-powertranz-3ds2-gateway/wiki)
- [Issue Tracker](https://github.com/cloudcogsio/woocommerce-omnipay-powertranz-3ds2-gateway/issues)

## License

This plugin is licensed under the GNU General Public License v3.0. See the LICENSE file for details.

## Credits

- Developed by [cloudcogs.io](https://www.cloudcogs.io/)
- Built on the SkyVerge WooCommerce Plugin Framework
- Uses the Omnipay PowerTranz 3DS2 Gateway package