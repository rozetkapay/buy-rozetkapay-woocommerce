jQuery(document).ready(function($) {
    'use strict';
    
    var isUpdating = false; // Прапорець для запобігання циклічним оновленням
    
    // Оновлюємо список банків під час зміни кошика
    $(document.body).on('updated_cart_totals updated_checkout', function() {
        // Запобігаємо циклічному оновленню
        if (isUpdating) {
            return;
        }
        
        // Перевіряємо, чи є наш спосіб оплати на сторінці
        if ($('#payment_method_rp_payparts').length && $('#payment_method_rp_payparts').is(':checked')) {
            isUpdating = true;
            
            // Невелика затримка перед оновленням
            setTimeout(function() {
                $('body').trigger('update_checkout');
                
                // Скидаємо прапорець через 2 секунди
                setTimeout(function() {
                    isUpdating = false;
                }, 2000);
            }, 100);
        }
    });
    
    // Оновлюємо лише під час зміни способу оплати на наш
    $(document).on('change', 'input[name="payment_method"]', function() {
        if ($(this).val() === 'rp_payparts') {
            isUpdating = true;
            
            setTimeout(function() {
                $('body').trigger('update_checkout');
                
                setTimeout(function() {
                    isUpdating = false;
                }, 2000);
            }, 100);
        }
    });
    
    // Стежимо за зміною кількості товарів лише на сторінці кошика
    if ($('body').hasClass('woocommerce-cart')) {
        $(document).on('change', '.qty', function() {
            // Лише якщо обрано наш спосіб оплати
            if ($('#payment_method_rp_payparts').is(':checked')) {
                setTimeout(function() {
                    $('body').trigger('update_checkout');
                }, 1000);
            }
        });
    }
});
