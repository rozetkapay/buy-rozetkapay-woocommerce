/**
 * RozetkaPay Payparts Admin JavaScript
 * Відформатована та покращена версія
 */

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('RozetkaPay Admin JS завантажено');
    
    // Показ/приховування додаткових налаштувань залежно від стилю
    $('#woocommerce_rp_payparts_display_style').on('change', function() {
        var style = $(this).val();
        var $info = $(this).closest('tr').find('.description');
        
        switch (style) {
            case 'slider':
                $info.html('Сучасний інтерактивний повзунок для вибору терміну розстрочки');
                break;
            case 'list':
                $info.html('Компактний список варіантів оплати');
                break;
            default:
                $info.html('Класичні картки з варіантами оплати');
                break;
        }
    });
    
    // Валідація налаштувань
    // Валідація налаштувань ЛИШЕ для секції Payparts
    (function() {
        var urlParams = new URLSearchParams(window.location.search);
        var section = urlParams.get('section') || '';
        // Запускаємо валідацію тільки якщо ми в секції rp_payparts І є потрібні поля
        if (section === 'rp_payparts' && $('#woocommerce_rp_payparts_api_user').length) {
            $('form').on('submit', function() {
                var testMode = $('#woocommerce_rp_payparts_test_mode').is(':checked');
                var apiUser = $('#woocommerce_rp_payparts_api_user').val();
                var apiPass = $('#woocommerce_rp_payparts_api_pass').val();
                if (!testMode && (!apiUser || !apiPass)) {
                    alert('Для продакшн-режиму необхідно вказати API користувача та пароль');
                    return false;
                }
                return true;
            });
        }
    })();

    // Копіювання Callback URL (якщо поле існує)
    setTimeout(function() {
        var $callbackCode = $('code:contains("/wc-api/rp_payparts_callback")');
        if ($callbackCode.length && !$callbackCode.next('.rp-copy-btn').length) {
            var $copyBtn = $('<button type="button" class="button-secondary rp-copy-btn" style="margin-left: 10px;">📋 Копіювати</button>');
            $callbackCode.after($copyBtn);
            
            $copyBtn.on('click', function() {
                // Створюємо тимчасове текстове поле для копіювання
                var tempInput = $('<input>');
                $('body').append(tempInput);
                tempInput.val($callbackCode.text()).select();
                
                try {
                    document.execCommand('copy');
                    var originalText = $copyBtn.html();
                    $copyBtn.html('✅ Скопійовано!').addClass('rp-copied');
                    
                    setTimeout(function() {
                        $copyBtn.html(originalText).removeClass('rp-copied');
                    }, 2000);
                } catch (err) {
                    alert('Скопіюйте URL вручну: ' + $callbackCode.text());
                }
                
                tempInput.remove();
            });
        }
    }, 1000);
    
    // Покращена валідація полів
    $('#woocommerce_rp_payparts_min_amount, #woocommerce_rp_payparts_max_amount').on('blur', function() {
        var minAmount = parseFloat($('#woocommerce_rp_payparts_min_amount').val()) || 0;
        var maxAmount = parseFloat($('#woocommerce_rp_payparts_max_amount').val()) || 0;
        
        if (minAmount > 0 && maxAmount > 0 && minAmount >= maxAmount) {
            alert('Мінімальна сума повинна бути меншою за максимальну суму');
            $(this).focus();
        }
    });
    
    // Підказки для полів банків і термінів
    $('#woocommerce_rp_payparts_allowed_banks').on('focus', function() {
        if (!$(this).data('placeholder-shown')) {
            $(this).attr('placeholder', 'Наприклад: rozetkapay,privatbank,monobank');
            $(this).data('placeholder-shown', true);
        }
    });
    
    $('#woocommerce_rp_payparts_allowed_terms').on('focus', function() {
        if (!$(this).data('placeholder-shown')) {
            $(this).attr('placeholder', 'Наприклад: 3,6,12,24');
            $(this).data('placeholder-shown', true);
        }
    });
    
    // ===== ДОДАТКОВІ ПОКРАЩЕННЯ =====
    
    // Інтерактивність списків банків
    $(document).on('click', '.rp-bank-item', function() {
        var bankCode = $(this).find('code').text();
        var $bankField = $('#woocommerce_rp_payparts_allowed_banks');
        
        if ($bankField.length) {
            var currentValue = $bankField.val();
            var banks = currentValue ? currentValue.split(',') : [];
            
            // Прибираємо пробіли
            banks = banks.map(function(bank) { return bank.trim(); });
            
            // Додаємо банк якщо його немає, прибираємо якщо є
            var index = banks.indexOf(bankCode);
            if (index > -1) {
                banks.splice(index, 1);
                $(this).removeClass('rp-selected');
            } else {
                banks.push(bankCode);
                $(this).addClass('rp-selected');
            }
            
            // Оновлюємо поле
            $bankField.val(banks.join(',')).trigger('input');
        }
    });
    
    // Інтерактивність списків термінів
    $(document).on('click', '.rp-term-item', function() {
        var termCode = $(this).find('code').text();
        var $termField = $('#woocommerce_rp_payparts_allowed_terms');
        
        if ($termField.length) {
            var currentValue = $termField.val();
            var terms = currentValue ? currentValue.split(',') : [];
            
            // Прибираємо пробіли та перетворюємо в числа
            terms = terms.map(function(term) { return term.trim(); });
            
            // Додаємо термін якщо його немає, прибираємо якщо є
            var index = terms.indexOf(termCode);
            if (index > -1) {
                terms.splice(index, 1);
                $(this).removeClass('rp-selected');
            } else {
                terms.push(termCode);
                $(this).addClass('rp-selected');
            }
            
            // Сортуємо терміни
            terms = terms
                .filter(function(term) { return term !== ''; })
                .sort(function(a, b) { return parseInt(a) - parseInt(b); });
            
            // Оновлюємо поле
            $termField.val(terms.join(',')).trigger('input');
        }
    });
    
    // Підсвічування вибраних елементів під час завантаження
    function highlightSelectedItems() {
        // Підсвічуємо вибрані банки
        var selectedBanks = $('#woocommerce_rp_payparts_allowed_banks').val();
        if (selectedBanks) {
            var banks = selectedBanks.split(',').map(function(bank) { return bank.trim(); });
            $('.rp-bank-item').each(function() {
                var bankCode = $(this).find('code').text();
                if (banks.indexOf(bankCode) > -1) {
                    $(this).addClass('rp-selected');
                }
            });
        }
        
        // Підсвічуємо вибрані терміни
        var selectedTerms = $('#woocommerce_rp_payparts_allowed_terms').val();
        if (selectedTerms) {
            var terms = selectedTerms.split(',').map(function(term) { return term.trim(); });
            $('.rp-term-item').each(function() {
                var termCode = $(this).find('code').text();
                if (terms.indexOf(termCode) > -1) {
                    $(this).addClass('rp-selected');
                }
            });
        }
    }
    
    // Запускаємо підсвічування після завантаження
    setTimeout(highlightSelectedItems, 500);
    
    // Валідація банків у реальному часі
    $('#woocommerce_rp_payparts_allowed_banks').on('input', function() {
        var value = $(this).val();
        var validBanks = ['abank', 'monobank', 'privatbank', 'rozetkapay', 'izibank'];
        var $feedback = $(this).siblings('.rp-field-feedback');
        
        if ($feedback.length === 0) {
            $feedback = $('<div class="rp-field-feedback"></div>');
            $(this).after($feedback);
        }
        
        if (value.trim() === '') {
            $feedback.removeClass('error success').html('');
            $('.rp-bank-item').removeClass('rp-selected');
            return;
        }
        
        var banks = value.split(',').map(function(bank) { return bank.trim(); });
        var invalidBanks = banks.filter(function(bank) {
            return bank !== '' && validBanks.indexOf(bank) === -1;
        });
        
        if (invalidBanks.length > 0) {
            $feedback
                .addClass('error')
                .removeClass('success')
                .html('❌ Невідомі банки: <strong>' + invalidBanks.join(', ') + '</strong>');
        } else {
            var validBanksList = banks.filter(function(b) { return b !== ''; });
            $feedback
                .addClass('success')
                .removeClass('error')
                .html('✅ Вибрано банків: <strong>' + validBanksList.length + '</strong> (' + validBanksList.join(', ') + ')');
        }
        
        // Оновлюємо підсвічування
        highlightSelectedItems();
    });
    
    // Валідація термінів у реальному часі
    $('#woocommerce_rp_payparts_allowed_terms').on('input', function() {
        var value = $(this).val();
        var $feedback = $(this).siblings('.rp-field-feedback');
        
        if ($feedback.length === 0) {
            $feedback = $('<div class="rp-field-feedback"></div>');
            $(this).after($feedback);
        }
        
        if (value.trim() === '') {
            $feedback.removeClass('error success').html('');
            $('.rp-term-item').removeClass('rp-selected');
            return;
        }
        
        var terms = value.split(',').map(function(term) { return parseInt(term.trim()); });
        var invalidTerms = terms.filter(function(term) {
            return isNaN(term) || term < 2 || term > 25;
        });
        
        if (invalidTerms.length > 0) {
            $feedback
                .addClass('error')
                .removeClass('success')
                .html('❌ Некоректні терміни (допустимі: 2–25 місяців)');
        } else {
            var validTerms = terms
                .filter(function(term) { return !isNaN(term); })
                .sort(function(a, b) { return a - b; });
            $feedback
                .addClass('success')
                .removeClass('error')
                .html('✅ Вибрано термінів: <strong>' + validTerms.length + '</strong> (' + validTerms.join(', ') + ' міс.)');
        }
        
        // Оновлюємо підсвічування
        highlightSelectedItems();
    });
    
    console.log('RozetkaPay Admin JS ініціалізовано з покращеннями');
});
