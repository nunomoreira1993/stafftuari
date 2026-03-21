(function() {
  'use strict';
  
  /**
   * External dependencies
   */
  const settings = window.wc.wcSettings.getSetting('lusopay_cofi_data', {});
  const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('Lusopay Cofidis Pay', 'lusopay_cofi');
  const defaultLabel = window.wp.i18n.__('Lusopay Cofidis Pay', 'lusopay_cofi');
  const description = window.wp.htmlEntities.decodeEntities(settings.description || 'Use este método de pagamento');
  const maxInstallments = window.wp.htmlEntities.decodeEntities(settings.maxInstallments) || window.wp.i18n.__('Até 12 prestações sem juros.', 'lusopay_cofi');

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

    const cofidisLink = React.createElement('a', {
      href: 'https://www.cofidis.pt/cofidispay',
      target: '_blank',
      style: { display: 'inline', marginLeft: '5px' },
    }, '+info');

    const maxInstallmentsDiv = React.createElement('div', {
      className: 'max-installments',
      style: { fontWeight: 'bold', marginBottom: '5px' },
    }, maxInstallments);

    return React.createElement('div', {
      className: 'lusopay-cofidispay-content',
    }, maxInstallmentsDiv, description, cofidisLink);
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

  const CofidisPay = {
    name: 'lusopay_cofi',
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

  window.wc.wcBlocksRegistry.registerPaymentMethod(CofidisPay);
  console.log('Loading Cofidis Pay Block');
})();