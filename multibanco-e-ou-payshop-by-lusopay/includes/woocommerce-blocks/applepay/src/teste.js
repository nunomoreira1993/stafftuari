(function() {
  'use strict';
  
  /**
   * External dependencies
   */
  const settings = window.wc.wcSettings.getSetting('lusopay_applepay_data', {});
  const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('Lusopay Apple Pay', 'lusopay_applepay');
  const defaultLabel = window.wp.i18n.__('Lusopay Apple Pay', 'lusopay_applepay');
  const description = window.wp.htmlEntities.decodeEntities(settings.description || 'Use este método de pagamento');

  /**
   * Content component
   */
  const Content = (props) => {
    const { eventRegistration, emitResponse } = props;
    const { onPaymentProcessing } = eventRegistration;

    React.useEffect(() => {
      const unsubscribe = onPaymentProcessing(async () => {
        return {
          type: emitResponse.responseTypes.SUCCESS,
          meta: {
            paymentMethodData: {}
          },
        };
      });

      return () => {
        unsubscribe();
      };
    }, [emitResponse.responseTypes.SUCCESS, onPaymentProcessing]);

    return React.createElement('div', {
      className: 'lusopay-applepay-content',
    }, description);
  };

  /**
   * Label component
   */
  const Label = () => {
    const icon = React.createElement('img', {
      src: settings.img,
      style: { display: 'inline', marginLeft: '5px' },
    });

    const span = React.createElement('span', {
      className: 'wc-block-components-payment-method-label wc-block-components-payment-method-label--with-icon',
    }, window.wp.htmlEntities.decodeEntities(settings.title) || defaultLabel, icon);

    return span;
  };

  const ApplePay = {
    name: 'lusopay_applepay',
    label: React.createElement(Label, null),
    content: React.createElement(Content, null),
    edit: React.createElement(Content, null),
    icons: null,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
      features: settings.supports,
    },
  };

  window.wc.wcBlocksRegistry.registerPaymentMethod(ApplePay);
  console.log('Loading Apple Pay Block');
})();