jQuery(document).ready(function ($) {


    /* product IDs */

    var PRODUCTS = {

        workshop: 12816,
        conference: 12817,
        virtual: 12818,
        workshop_conference: 12670,
        workshop_virtual: 12672

    };


    /* prices */

    var PRICES = {

        12816: 50000,
        12817: 120000,
        12818: 40000,
        12670: 150000,
        12672: 70000

    };


    var uid = 0;


    /* steps */

    function setStep(n) {

        $('#nsa-step-1,#nsa-step-2,#nsa-step-3').hide();

        $('#nsa-step-' + n).show();

    }


    /* selection */

    function getSelection() {

        return {

            workshop: $('#org_workshop').is(':checked'),

            conference: $('#org_conference').is(':checked'),

            virtual: $('#org_virtual').is(':checked')

        }

    }


    /* product resolver */

    function getProductId(s) {

        if (s.workshop && s.conference)

            return PRODUCTS.workshop_conference;

        if (s.workshop && s.virtual)

            return PRODUCTS.workshop_virtual;

        if (s.workshop)

            return PRODUCTS.workshop;

        if (s.conference)

            return PRODUCTS.conference;

        if (s.virtual)

            return PRODUCTS.virtual;

        return null;

    }


    /* mutual exclusion */

    $('.org-conference-opt').change(function () {

        if ($(this).is(':checked'))

            $('.org-conference-opt').not(this).prop('checked', false);

        validateProduct();

        updatePrice();

    });


    $('.org-reg-check').change(function () {

        validateProduct();

        updatePrice();

    });


    function validateProduct() {

        var s = getSelection();

        if (!s.workshop && !s.conference && !s.virtual) {

            $('.org-reg-error').text('Select product').show();

            $('#btn-add-attendee').prop('disabled', true);

            return false;

        }

        if (s.conference && s.virtual) {

            $('.org-reg-error').text('Choose onsite OR virtual');

            $('#btn-add-attendee').prop('disabled', true);

            return false;

        }

        $('.org-reg-error').hide();

        $('#btn-add-attendee').prop('disabled', false);

        return true;

    }


    /* price preview */

    function updatePrice() {

        var s = getSelection();

        var pid = getProductId(s);

        var qty = $('.nsa-attendee-card').length;

        if (!pid) {

            $('#price-preview').text('Select product');

            return;

        }

        var price = PRICES[pid];

        var total = price * qty;

        $('#price-preview').text(

            '₦' + price.toLocaleString() +

            ' x ' + qty +

            ' = ₦' + total.toLocaleString()

        );

    }


    /* add attendee */

    $('#btn-add-attendee').click(function () {

        if (!validateProduct())

            return;

        uid++;

        var tpl = document.getElementById('attendee-card-tpl');

        var frag = document.importNode(tpl.content, true);

        var div = document.createElement('div');

        div.appendChild(frag);

        var html = div.innerHTML;

        html = html.replace('data-attendee-id=""', 'data-attendee-id="' + uid + '"');

        var card = $(html);

        card.find('.att-num').text(uid);

        card.find('input[type=radio]').attr('name', 'member_' + uid);

        $('#attendee-list').append(card);

        bindEvents(card);

        updateUI();

        updatePrice();

    });


    function bindEvents(card) {

        card.find('.btn-remove-attendee').click(function () {

            card.remove();

            updateUI();

            updatePrice();

        });

    }


    /* UI updates */

    function updateUI() {

        var count = $('.nsa-attendee-card').length;

        $('#attendee-empty').toggle(count === 0);

        $('#btn-to-checkout').prop('disabled', count === 0);

    }


    /* checkout */

    $('#btn-to-checkout').click(function () {

        var count = $('.nsa-attendee-card').length;

        var s = getSelection();

        var pid = getProductId(s);

        $.post(

            ajax_object.ajax_url,

            {

                action: 'add_to_cart_dynamic',

                product_id: pid,

                quantity: count,

                nonce: ajax_object.nonce

            },

            function () {

                $.post(

                    ajax_object.ajax_url,

                    {

                        action: 'load_wc_checkout',

                        nonce: ajax_object.nonce

                    },

                    function (resp) {

                        $('#checkout-container').html(resp.data.html);

                        $('#checkout-summary-label').text(

                            count + ' attendee(s)'

                        );

                        setStep(3);

                    }

                );

            }

        );

    });


    /* navigation */

    $('#btn-back-to-2').click(function () {

        setStep(2);

    });


    setStep(1);


});