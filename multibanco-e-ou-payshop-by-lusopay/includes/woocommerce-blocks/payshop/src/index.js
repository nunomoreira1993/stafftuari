/**
 * External dependencies
 */
const settings_payshop = window.wc.wcSettings.getSetting('lusopay_payshop_data', {});
const defaultLabel_payshop = window.wp.i18n.__('lusopay Payshop', 'lusopay_payshop');
const label_payshop = window.wp.htmlEntities.decodeEntities(settings_payshop.title) || window.wp.i18n.__('lusopay Payshop', 'lusopay_payshop');
var description = React.createElement('p', null, window.wp.htmlEntities.decodeEntities(settings_payshop.description || 'Use this method for payment'));
/**
 * Content component
 *
 * @param {*} props Props from payment API.
 */

const ContentPayshop = (props) => {
	// Empty component with no content
	return React.createElement(
    'div',
    { className: 'lusopay_payshop-content' },
    description_lusopaygateway ? description_lusopaygateway : null
  );

  };
/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const LabelPayshop = (props) => {
    var icon = React.createElement('img', {
        src: settings_payshop.img,
        style: {
            display: 'inline',
            marginLeft: '5px', // Adjust the value as needed
        },
    });
    var span = React.createElement('span', {
        className: 'wc-block-components-payment-method-label wc-block-components-payment-method-label--with-icon',
    }, window.wp.htmlEntities.decodeEntities(settings_payshop.title) || defaultLabel_payshop, icon);
    return span;
};
const Payshop = {
    name: 'lusopay_payshop',
    label: React.createElement(LabelPayshop, null),
    content: React.createElement(ContentPayshop, null),
    edit: React.createElement(ContentPayshop, null),
    icons: null,
    canMakePayment: () => true,
    ariaLabel: label_payshop,
    supports: {
        features: settings_payshop.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(Payshop);