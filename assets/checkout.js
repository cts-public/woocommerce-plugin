const crypay_data = window.wc.wcSettings.getSetting( 'crypay_data', {} );
const crypay_label = window.wp.htmlEntities.decodeEntities( crypay_data.title )
    || window.wp.i18n.__( 'My Gateway', 'crypay' );
const crypay_content = ( crypay_data ) => {
    return window.wp.htmlEntities.decodeEntities( crypay_data.description || '' );
};
const Crypay = {
    name: 'crypay',
    label: crypay_label,
    content: Object( window.wp.element.createElement )( crypay_content, null ),
    edit: Object( window.wp.element.createElement )( crypay_content, null ),
    canMakePayment: () => true,
    placeOrderButtonLabel: window.wp.i18n.__( 'Continue', 'crypay' ),
    ariaLabel: crypay_label,
    supports: {
        features: crypay_data.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Crypay );