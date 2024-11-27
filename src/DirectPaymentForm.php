<?php

namespace Cloudcogs\Woocommerce\Gateway\PowerTranz;

use SkyVerge\WooCommerce\PluginFramework\v5_15_1 as Framework;

class DirectPaymentForm extends Framework\SV_WC_Payment_Gateway_Payment_Form
{
    /**
     * Render a test amount input field that can be used to override the order total
     * when using the gateway in sandbox mode. The order total can then be set to
     * various amounts to simulate various authorization/settlement responses
     */
    public function render_payment_form_description()
    {
        parent::render_payment_form_description();

        /*
        if ($this->get_gateway()->is_test_environment() && ! is_add_payment_method_page()) {
            $id = 'wc-' . $this->get_gateway()->get_id_dasherized() . '-test-amount';

            ?>
            <p class="form-row">
                <label for="<?php echo esc_attr($id); ?>">Test Amount
                    <span style="font-size: 10px;" class="description">
                        - Enter a test amount or leave blank to use the order total.
                    </span>
                </label>
                <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>" />
            </p>
            <?php
        }
        */
    }

    public function get_payment_form_description_html()
    {
        $description = '';

        if ($this->get_gateway()->get_description()) {
            $description .= '<p>' . wp_kses_post($this->get_gateway()->get_description()) . '</p>';
        }

        if ($this->get_gateway()->is_test_environment()) {
            /* translators: Test mode refers to the current software environment */
            echo '<p style="font-weight: bold; text-align: center;">'
                 .esc_html__('*** TEST MODE ENABLED ***', 'woocommerce-plugin-framework') . '</p>';
        }

        /**
         * Payment Gateway Payment Form Description.
         *
         * Filters the HTML rendered for payment form description.
         *
         * @since 4.0.0
         *
         * @param string $html
         * @param Framework\SV_WC_Payment_Gateway_Payment_Form $this payment form instance
         */
        return apply_filters('wc_' . $this->get_gateway()->get_id() . '_payment_form_description', $description, $this);
    }

    public function render_payment_fields()
    {
        $fieldsetId = 'wc-' . $this->get_gateway()->get_id_dasherized() . '-credit-card-form';
        ?>
        <style>
            #<?php echo $fieldsetId; ?> {
                border:1px solid #000000;
                padding-block-end: unset;
            }
            .payment_method_<?= $this->get_gateway()->get_id(); ?> {
                padding-left: 0px !important;
            }
            #pf-overlay {
                position: fixed;
                background-color: rgba(255,255,255,0.95);
                background-image: url("<?= get_site_url()."/wp-content/plugins/".Plugin::TEXT_DOMAIN."/assets/fac-visa-mc.png"; ?>");
                background-repeat: no-repeat;
                background-position: center;
                text-align: center;
                top: 0;
                z-index: 1000;
            }
            #pf-overlay p {
                height: 100%;
                justify-content: center;
                align-items: center;
                display: flex;
                margin-top: 50px;
                font-weight: bolder;
            }
        </style>
        <?php
        parent::render_payment_fields();
    }

    public function render_fieldset_end()
    {
        parent::render_fieldset_end();

        $fieldsetId = 'wc-' . $this->get_gateway()->get_id_dasherized() . '-credit-card-form';
        ?>

        <script>
            (function(){
                jQuery("form[name='checkout']").off();
                jQuery("#place_order").click(function(e) {
                    jQuery(this).hide();

                    let o = jQuery("<div id='pf-overlay'><p>Please wait...</p></div>")
                        .width(window.innerWidth).height(window.innerHeight);

                    jQuery("body").prepend(o);

                    return true;
/*
                    let f = jQuery("#<?= $fieldsetId; ?>");
                    let h = f.outerHeight(true);
                    let w = f.outerWidth(true);

                    let i = jQuery("<iframe style='border:0px;' frameborder='0' id='payment_frame' name='payment_frame' scrolling='auto'>")
                        .width(w).height(h);
                    let o = jQuery("<div id='pf-overlay'><p>Please wait...</p></div>")
                        .width(w).height(h);

                    f.children('div').first().hide();
                    f.append(o,i);

                    jQuery("form[name='checkout']").attr('target','payment_frame');

                    let cid = setInterval(()=>{
                        if (jQuery("#payment_frame").get(0).contentDocument) {
                            jQuery("#pf-overlay").fadeOut();
                        }
                    },1000);

                    setTimeout(()=>{
                        clearInterval(cid);
                        jQuery("#pf-overlay").fadeOut();
                    },5000);
*/

                });
            }());
        </script>

        <?php
    }
}
