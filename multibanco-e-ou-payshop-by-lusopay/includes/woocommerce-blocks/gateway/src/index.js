/**
 * External dependencies
 */

const settings_multibanco = window.wc.wcSettings.getSetting('lusopaygateway_data', {});
const defaultLabel_multibanco = window.wp.i18n.__('Lusopay Multibanco', 'lusopaygateway');
const label_multibanco = window.wp.htmlEntities.decodeEntities(settings_multibanco.title) || window.wp.i18n.__('Lusopay Multibanco', 'lusopaygateway');
var description = React.createElement('p', null, window.wp.htmlEntities.decodeEntities(settings_multibanco.description || 'Use this method for payment'));
const description_lusopaygateway = window.wp.htmlEntities.decodeEntities(settings_multibanco.description || settings_multibanco.description);


/**
 * Content component
 *
 * @param {*} props Props from payment API.
 */
const ContentMultibanco = (props) => {
	// Empty component with no content
	return React.createElement(
    'div',
    { className: 'lusopaygateway-content' },
    description_lusopaygateway ? description_lusopaygateway : null
  );

  };
  

/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */

const LabelMultibanco = (props) => {
	var icon = React.createElement('img', {
	  src: settings_multibanco.img,
	  style: {
		display: 'inline',
		marginLeft: '5px', // Adjust the value as needed
	  },
	});
	var span = React.createElement('span', {
	  className: 'wc-block-components-payment-method-label wc-block-components-payment-method-label--with-icon',
	}, window.wp.htmlEntities.decodeEntities(settings_multibanco.title) || defaultLabel_multibanco, icon);
	return span;
  };

  const Multibanco = {
	name: 'lusopaygateway',
	label: React.createElement(LabelMultibanco, null),
	content: React.createElement(ContentMultibanco, null),
	edit: React.createElement(ContentMultibanco, null),
	icons: null,
	canMakePayment: () => true,
	ariaLabel: label_multibanco,
	supports: {
	  features: settings_multibanco.supports,
	},
  };
  
  window.wc.wcBlocksRegistry.registerPaymentMethod(Multibanco);
