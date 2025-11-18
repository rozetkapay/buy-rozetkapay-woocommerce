/**
 * RozetkaPay Payparts Frontend JavaScript
 * Версія: 2.0.0
 */

(function($) {
    'use strict';

    // Головний об’єкт плагіна
    const RPPayparts = {
        
        /**
         * Ініціалізація
         */
        init: function() {
            this.bindEvents();
            this.updateCalculations();
        },

        /**
         * Прив'язка подій
         */
        bindEvents: function() {
            // Оновлення розрахунків при зміні кошика
            $(document.body).on('updated_cart_totals updated_checkout', this.updateCalculations.bind(this));
            
            // Обробка вибору опції оплати
            $(document).on('change', 'input[name="rp_payparts_option"]', this.handleOptionChange.bind(this));
            
            // Обробка вибору методу оплати
            $(document).on('change', 'input[name="payment_method"]', this.handlePaymentMethodChange.bind(this));
            
            // Покращена взаємодія з опціями
            $(document).on('click', '.rp-payparts-option', this.handleOptionClick.bind(this));
            
            // Анімація елементів
            this.animateElements();
        },

        /**
         * Оновлення розрахунку платежів
         */
        updateCalculations: function() {
            const $container = $('.rp-payparts-container');
            if (!$container.length) return;

            const cartTotal = this.getCartTotal();
            if (!cartTotal) return;

            // Оновлення щомісячних платежів по кожній опції
            $('.rp-payparts-option').each(function() {
                const $option = $(this);
                const optionValue = $option.find('input[type="radio"]').val();
                
                if (!optionValue) return;
                
                const parts = optionValue.split('|');
                if (parts.length !== 2) return;
                
                const term = parseInt(parts[1]);
                const monthlyPayment = Math.round(cartTotal / term);
                
                const $details = $option.find('.rp-option-details');
                $details.html('<strong>' + RPPayparts.formatPrice(monthlyPayment) + ' грн/міс</strong>');
            });

            // Оновлення повідомлення в кошику
            this.updateCartMessage(cartTotal);
        },

        /**
         * Отримати поточну суму кошика
         */
        getCartTotal: function() {
            // Отримання із фрагментів WooCommerce
            if (typeof wc_cart_fragments_params !== 'undefined') {
                const fragments = JSON.parse(sessionStorage.getItem(wc_cart_fragments_params.fragment_name) || '{}');
                if (fragments && fragments.cart_total) {
                    const matches = fragments.cart_total.match(/[\d,]+\.?\d*/);
                    if (matches) {
                        return parseFloat(matches[0].replace(/,/g, ''));
                    }
                }
            }

            // Резервний метод — читання з DOM
            const $total = $('.cart-subtotal .woocommerce-Price-amount, .order-total .woocommerce-Price-amount').last();
            if ($total.length) {
                const totalText = $total.text().replace(/[^\d.,]/g, '');
                return parseFloat(totalText.replace(',', ''));
            }

            return 0;
        },

        /**
         * Оновити повідомлення в кошику
         */
        updateCartMessage: function(cartTotal) {
            const $message = $('.rp-payparts-cart-message');
            if (!$message.length) return;

            // Отримання мін/макс термінів із доступних опцій
            const terms = [];
            $('.rp-payparts-option input[type="radio"]').each(function() {
                const optionValue = $(this).val();
                const parts = optionValue.split('|');
                if (parts.length === 2) {
                    terms.push(parseInt(parts[1]));
                }
            });

            if (terms.length === 0) return;

            const minTerm = Math.min(...terms);
            const maxTerm = Math.max(...terms);
            const minPayment = Math.round(cartTotal / maxTerm);
            const maxPayment = Math.round(cartTotal / minTerm);

            // Оновлення тексту повідомлення
            let messageText = $message.text();
            messageText = messageText.replace(/від\s[\d\s]+\sдо\s[\d\s]+\sгрн/, 
                'від ' + this.formatPrice(minPayment) + ' до ' + this.formatPrice(maxPayment) + ' грн');
            
            $message.text(messageText);
        },

        /**
         * Обробка зміни опції
         */
        handleOptionChange: function(e) {
            const $option = $(e.target).closest('.rp-payparts-option');
            
            // Прибираємо selected з усіх
            $('.rp-payparts-option').removeClass('rp-selected');
            
            // Додаємо клас до обраної опції
            $option.addClass('rp-selected');
            
            // Тригер події
            $(document.body).trigger('rp_payparts_option_changed', [$(e.target).val()]);
        },

        /**
         * Обробка зміни методу оплати
         */
        handlePaymentMethodChange: function(e) {
            const selectedMethod = $(e.target).val();
            
            if (selectedMethod === 'rp_payparts') {
                // Оновити розрахунок після вибору метода
                setTimeout(() => {
                    this.updateCalculations();
                }, 100);
            }
        },

        /**
         * Обробка кліку по опції
         */
        handleOptionClick: function(e) {
            const $option = $(e.currentTarget);
            const $radio = $option.find('input[type="radio"]');
            
            if (!$radio.prop('checked')) {
                $radio.prop('checked', true).trigger('change');
            }
        },

        /**
         * Форматування ціни з пробілами
         */
        formatPrice: function(amount) {
            return Math.round(amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        },

        /**
         * Анімація елементів при завантаженні
         */
        animateElements: function() {
            // Анімація контейнера
            $('.rp-payparts-container').each(function(index) {
                const $container = $(this);
                $container.css({
                    'opacity': '0',
                    'transform': 'translateY(20px)'
                });
                
                setTimeout(() => {
                    $container.animate({
                        'opacity': 1,
                        'transform': 'translateY(0)'
                    }, 300);
                }, index * 100);
            });

            // Анімація опцій
            $('.rp-payparts-option').each(function(index) {
                const $option = $(this);
                setTimeout(() => {
                    $option.addClass('rp-animate-in');
                }, index * 50);
            });
        },

        /**
         * Відобразити стан завантаження
         */
        showLoading: function() {
            $('.rp-payparts-container').addClass('rp-loading');
            $('.rp-option-details').html('<span class="rp-calc-note">' + 
                (rpPayparts.strings?.calculating || 'Розраховуємо...') + '</span>');
        },

        /**
         * Приховати стан завантаження
         */
        hideLoading: function() {
            $('.rp-payparts-container').removeClass('rp-loading');
        },

        /**
         * Обробка помилок
         */
        handleError: function(message) {
            console.error('RozetkaPay Payparts Помилка:', message);
            
            $('.rp-option-details').html('<span class="rp-calc-note rp-error">' + 
                (rpPayparts.strings?.error || 'Помилка розрахунку') + '</span>');
        }
    };

    // Розширена анімація
    const animationCSS = `
        <style>
        .rp-animate-in {
            animation: rpSlideInUp 0.4s ease-out forwards;
        }
        
        @keyframes rpSlideInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .rp-payparts-option {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .rp-payparts-option:hover {
            transform: translateY(-2px);
        }
        
        .rp-payparts-option.rp-selected {
            transform: translateY(-1px) scale(1.02);
        }
        
        .rp-error {
            color: #dc2626 !important;
        }
        
        .rp-loading .rp-option-details {
            opacity: 0.6;
        }
        </style>
    `;
    
    // Додаємо стилі
    $('head').append(animationCSS);

    // Розширені функції
    const AdvancedFeatures = {
        
        /**
         * Додаємо підказки
         */
        addTooltips: function() {
            $('.rp-term-badge').each(function() {
                const $badge = $(this);
                const term = parseInt($badge.text());
                let tooltip = '';
                
                if (term <= 6) {
                    tooltip = 'Короткостроковий план — швидке погашення';
                } else if (term <= 12) {
                    tooltip = 'Оптимальний план — баланс суми та тривалості';
                } else {
                    tooltip = 'Довгостроковий план — мінімальні щомісячні платежі';
                }
                
                $badge.attr('title', tooltip);
            });
        },

        /**
         * Додаємо логотипи банків
         */
        addBankLogos: function() {
            $('.rp-bank-name').each(function() {
                const $bankName = $(this);
                const bankSlug = $bankName.text().toLowerCase().replace(/\s+/g, '');
                
                const logoUrl = rpPayparts.assetsUrl + 'images/banks/' + bankSlug + '.png';
                const $logo = $('<img class="rp-bank-logo" src="' + logoUrl + '" alt="' + $bankName.text() + '">');
                
                $logo.on('error', function() {
                    $(this).hide();
                });
                
                $bankName.prepend($logo);
            });
        },

        /**
         * Додаємо індикатори прогресу
         */
        addProgressIndicators: function() {
            $('.rp-payparts-option').each(function() {
                const $option = $(this);
                const optionValue = $option.find('input[type="radio"]').val();
                const parts = optionValue.split('|');
                
                if (parts.length !== 2) return;
                
                const term = parseInt(parts[1]);
                const maxTerm = 24;
                const progressPercent = (term / maxTerm) * 100;
                
                const $progress = $('<div class="rp-term-progress"><div class="rp-term-progress-bar" style="width: ' + progressPercent + '%"></div></div>');
                $option.find('.rp-option-details').append($progress);
            });
        }
    };

    // Ініціалізація при готовності документа
    $(document).ready(function() {
        RPPayparts.init();
        
        if (typeof rpPayparts !== 'undefined' && rpPayparts.enableAdvanced) {
            AdvancedFeatures.addTooltips();
            AdvancedFeatures.addBankLogos();
            AdvancedFeatures.addProgressIndicators();
        }
    });

    // Експортуємо у глобальну область
    window.RPPayparts = RPPayparts;
    window.RPPaypartsAdvanced = AdvancedFeatures;

})(jQuery);

/**
 * Vanilla JS fallback для середовищ без jQuery
 */
if (typeof jQuery === 'undefined') {
    console.warn('RozetkaPay Payparts: jQuery не знайдено, використовується fallback на чистому JS');
    
    document.addEventListener('DOMContentLoaded', function() {
        const options = document.querySelectorAll('.rp-payparts-option');
        
        options.forEach(function(option) {
            option.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                if (radio && !radio.checked) {
                    radio.checked = true;
                    
                    options.forEach(opt => opt.classList.remove('rp-selected'));
                    this.classList.add('rp-selected');
                }
            });
        });
        
        // Проста анімація
        options.forEach(function(option, index) {
            setTimeout(function() {
                option.style.opacity = '1';
                option.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });
}
