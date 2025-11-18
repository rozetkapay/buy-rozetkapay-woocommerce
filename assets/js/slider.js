jQuery(document).ready(function($) {
    console.log('SLIDER: Скрипт завантажено');
    
    setTimeout(function() {
        if (typeof rozetkapay_slider_data === 'undefined') {
            console.log('SLIDER: Дані не знайдено');
            return;
        }
        
        var data = rozetkapay_slider_data;
        var currentBank = Object.keys(data.banks)[0];
        var $slider = $('#rp-slider-range');
        
        console.log('SLIDER: Дані завантажені, поточний банк:', currentBank);
        
        // Мінімальні періоди для банків
var bankMinPeriods = {
    'abank': 2,              // ✅ ДОДАНО
    'monobank': 3,
    'izibank': 3,
    'privatbank': 2,
    'rozetkapay': 2
    // 'fuib': 3,            // ✅ ВИДАЛЕНО
        };

        // Перевірка валідності періоду для банку
        function isTermValidForBank(bankSlug, term) {
            var minPeriod = bankMinPeriods[bankSlug] || 3;
            return term >= minPeriod;
        }
        
        // Отримати доступні періоди для банку
        function getAvailableTermsForBank(bankSlug) {
            var bankData = data.banks[bankSlug];
            if (!bankData || !bankData.terms) return [];
            
            var availableTerms = [];
            for (var term in bankData.terms) {
                var termNum = parseInt(term);
                if (isTermValidForBank(bankSlug, termNum)) {
                    availableTerms.push(termNum);
                }
            }
            
            return availableTerms.sort(function(a, b) { return a - b; });
        }
        
        // Функція оновлення слайдера З ВАЛІДАЦІЄЮ
        function updateSlider() {
            var sliderElement = document.getElementById('rp-slider-range');
            var termIndex = parseInt(sliderElement.value) || 0;
            var term = data.terms[termIndex];
            var bankData = data.banks[currentBank];
            
            console.log('SLIDER: Оновлення — банк:', currentBank, 'період:', term, 'Валідний:', isTermValidForBank(currentBank, term));
            
            // КРИТИЧНО ВАЖЛИВО: перевіряємо доступність періоду для поточного банку
            if (bankData && bankData.terms && bankData.terms[term] && isTermValidForBank(currentBank, term)) {
                var monthlyPayment = Math.round(parseFloat(data.cart_total) / term);
                var optionKey = bankData.terms[term];
                
                // Оновлення елементів
                var monthsElement = document.querySelector('.rp-slider-months');
                var paymentElement = document.querySelector('.rp-slider-payment');
                var hiddenInput = document.querySelector('input[name="rp_payparts_option"]');
                
                if (monthsElement) {
                    monthsElement.textContent = term;
                }
                
                if (paymentElement) {
                    paymentElement.textContent = monthlyPayment + ' грн/міс';
                }
                
                if (hiddenInput) {
                    hiddenInput.value = optionKey;
                }
                
                // Оновлюємо активні мітки
                $('.rp-slider-mark-label').removeClass('active');
                $('.rp-slider-mark-label').eq(termIndex).addClass('active');
                
                console.log('SLIDER: Оновлено на:', term, 'міс. =', monthlyPayment, 'грн/міс');
            } else {
                // НЕДОСТУПНИЙ ПЕРІОД — автоматично перемикаємо на найближчий доступний
                console.log('SLIDER: Період', term, 'недоступний для банку', currentBank);
                
                // Знаходимо найближчий доступний період
                var availableTerms = getAvailableTermsForBank(currentBank);
                
                if (availableTerms.length > 0) {
                    // Шукаємо найближчий доступний період (більший або рівний поточному)
                    var nearestTerm = availableTerms.find(function(t) { return t >= term; });
                    if (!nearestTerm) {
                        // Якщо немає більшого — беремо максимальний доступний
                        nearestTerm = availableTerms[availableTerms.length - 1];
                    }
                    
                    var nearestIndex = data.terms.indexOf(nearestTerm);
                    
                    if (nearestIndex !== -1 && nearestIndex !== termIndex) {
                        console.log('SLIDER: Автоперемикання на найближчий доступний період:', nearestTerm);
                        sliderElement.value = nearestIndex;
                        updateSlider(); // Рекурсивно оновлюємо
                        return;
                    }
                }
                
                console.log('SLIDER: Не знайдено валідних періодів для банку', currentBank);
            }
        }
        
        // Функція оновлення інформації про банк
        function updateBankInfo(bankSlug) {
            var bankData = data.banks[bankSlug];
            if (!bankData) return;
            
            console.log('SLIDER: Оновлення інформації про банк:', bankSlug);
            
            // Оновлюємо назву банку
            $('.rp-current-bank-name').text(bankData.name);
            
            // Оновлюємо логотип банку
            var $bankLogo = $('.rp-current-bank-info .rp-bank-logo img');
            var $bankFallback = $('.rp-current-bank-info .rp-bank-fallback');
            
            if ($bankLogo.length) {
                var newLogoUrl = data.plugin_url + 'assets/images/' + bankSlug + '-logo.png';
                $bankLogo.attr('src', newLogoUrl);
                $bankLogo.attr('alt', bankData.name + ' Logo');
                
                // Показуємо зображення та ховаємо fallback
                $bankLogo.show();
                $bankFallback.hide();
                
                // Обробник помилки завантаження логотипа
                $bankLogo.off('error').on('error', function() {
                    console.log('SLIDER: Не вдалося завантажити логотип для', bankSlug);
                    $(this).hide();
                    $bankFallback.show();
                    
                    // Оновлюємо текст fallback
                    var fallbackText = bankData.name.substring(0, 2).toUpperCase();
                    $bankFallback.text(fallbackText);
                });
            }
            
            // Додатково оновлюємо fallback
            if ($bankFallback.length) {
                var fallbackText = bankData.name.substring(0, 2).toUpperCase();
                $bankFallback.text(fallbackText);
            }
            
            console.log('SLIDER: Інформацію про банк оновлено для:', bankData.name);
        }
        
        // Функція валідації позиції слайдера — блокує недопустимі значення
        function validateSliderPosition(newValue) {
            var termIndex = parseInt(newValue);
            var term = data.terms[termIndex];
            
            if (!isTermValidForBank(currentBank, term)) {
                console.log('SLIDER: Заблоковано недопустимий період', term, 'для банку', currentBank);
                
                // Знаходимо найближчу валідну позицію
                var availableTerms = getAvailableTermsForBank(currentBank);
                if (availableTerms.length > 0) {
                    var nearestTerm = availableTerms[0]; // Беремо мінімальний доступний
                    var nearestIndex = data.terms.indexOf(nearestTerm);
                    
                    if (nearestIndex !== -1) {
                        console.log('SLIDER: Корекція до валідного періоду:', nearestTerm);
                        return nearestIndex;
                    }
                }
                
                return false; // Блокуємо зміну
            }
            
            return termIndex; // Дозволяємо зміну
        }
        
        // Обробка зміни банку (ОНОВЛЕНА ВЕРСІЯ)
        $(document).on('click', '.rp-bank-option', function(e) {
            e.preventDefault();
            
            var $option = $(this);
            var newBank = $option.find('input[type="radio"]').val();
            
            console.log('SLIDER: Клік по банку:', newBank);
            
            if (newBank !== currentBank) {
                // Прибираємо активний клас у всіх банків
                $('.rp-bank-option').removeClass('active');
                
                // Додаємо активний клас до обраного банку
                $option.addClass('active');
                $option.find('input[type="radio"]').prop('checked', true);
                
                // Зберігаємо старий банк для логування
                var oldBank = currentBank;
                currentBank = newBank;
                
                // Оновлюємо інформацію про банк
                updateBankInfo(currentBank);
                
                // ВАЖЛИВО: перевіряємо поточний обраний період
                var sliderElement = document.getElementById('rp-slider-range');
                var currentTermIndex = parseInt(sliderElement.value);
                var currentTerm = data.terms[currentTermIndex];
                
                console.log('SLIDER: Перемикання з', oldBank, 'на', newBank, '- поточний період:', currentTerm);
                
                // Якщо період недоступний для нового банку — перемикаємо
                if (!isTermValidForBank(currentBank, currentTerm)) {
                    console.log('SLIDER: Поточний період', currentTerm, 'недоступний для нового банку', currentBank);
                    
                    // Знаходимо мінімальний доступний період для нового банку
                    var availableTerms = getAvailableTermsForBank(currentBank);
                    
                    if (availableTerms.length > 0) {
                        var minAvailableTerm = availableTerms[0];
                        var newIndex = data.terms.indexOf(minAvailableTerm);
                        
                        if (newIndex !== -1) {
                            console.log('SLIDER: Автоперемикання на період:', minAvailableTerm, 'індекс:', newIndex);
                            sliderElement.value = newIndex;
                        }
                    }
                }
                
                // Оновлюємо слайдер
                updateSlider();
                
                console.log('SLIDER: Банк змінено на:', currentBank);
            }
        });
        
        // Моніторинг слайдера з валідацією
        var lastRawValue = document.getElementById('rp-slider-range').value;
        setInterval(function() {
            var sliderElement = document.getElementById('rp-slider-range');
            var currentRawValue = sliderElement.value;
            
            if (currentRawValue !== lastRawValue) {
                console.log('SLIDER: Значення змінюється:', lastRawValue, '→', currentRawValue);
                
                var validatedValue = validateSliderPosition(currentRawValue);
                
                if (validatedValue !== false && validatedValue !== parseInt(currentRawValue)) {
                    // Коригуємо позицію
                    sliderElement.value = validatedValue;
                    currentRawValue = validatedValue.toString();
                } else if (validatedValue === false) {
                    // Блокуємо зміну — повертаємо старе значення
                    sliderElement.value = lastRawValue;
                    currentRawValue = lastRawValue;
                }
                
                lastRawValue = currentRawValue;
                updateSlider();
            }
        }, 50);
        
        // Події слайдера з валідацією
        var sliderElement = document.getElementById('rp-slider-range');
        if (sliderElement) {
            // Обробник input з валідацією
            sliderElement.addEventListener('input', function() {
                console.log('SLIDER: Подія input:', this.value);
                
                var validatedValue = validateSliderPosition(this.value);
                if (validatedValue !== false) {
                    if (validatedValue !== parseInt(this.value)) {
                        this.value = validatedValue;
                    }
                    updateSlider();
                } else {
                    // Блокуємо недопустиме значення
                    this.value = lastRawValue;
                }
            });
            
            // Обробник change з валідацією
            sliderElement.addEventListener('change', function() {
                console.log('SLIDER: Подія change:', this.value);
                
                var validatedValue = validateSliderPosition(this.value);
                if (validatedValue !== false) {
                    if (validatedValue !== parseInt(this.value)) {
                        this.value = validatedValue;
                    }
                    updateSlider();
                } else {
                    // Блокуємо недопустиме значення
                    this.value = lastRawValue;
                }
            });
        }
        
        // Кліки по мітках з валідацією
        $(document).on('click', '.rp-slider-mark-label', function() {
            var index = $('.rp-slider-mark-label').index(this);
            var term = data.terms[index];
            
            console.log('SLIDER: Клік по мітці:', index, 'період:', term);
            
            // Перевіряємо валідність обраного періоду
            if (isTermValidForBank(currentBank, term)) {
                var sliderElement = document.getElementById('rp-slider-range');
                sliderElement.value = index;
                updateSlider();
            } else {
                console.log('SLIDER: Клік по мітці заблоковано — недопустимий період', term, 'для банку', currentBank);
                
                // Візуальна індикація недоступності
                var $mark = $(this);
                $mark.addClass('rp-invalid-term');
                setTimeout(function() {
                    $mark.removeClass('rp-invalid-term');
                }, 1000);
            }
        });
        
        // Початкова ініціалізація
        console.log('SLIDER: Старт ініціалізації...');
        
        // Перевіряємо початковий банк і період
        var sliderElement = document.getElementById('rp-slider-range');
        var initialTermIndex = parseInt(sliderElement.value) || 0;
        var initialTerm = data.terms[initialTermIndex];
        
        console.log('SLIDER: Початковий банк:', currentBank, 'період:', initialTerm);
        
        // Якщо початковий період недоступний — перемикаємо
        if (!isTermValidForBank(currentBank, initialTerm)) {
            var availableTerms = getAvailableTermsForBank(currentBank);
            if (availableTerms.length > 0) {
                var firstValidTerm = availableTerms[0];
                var firstValidIndex = data.terms.indexOf(firstValidTerm);
                if (firstValidIndex !== -1) {
                    console.log('SLIDER: Корекція початкового періоду до:', firstValidTerm);
                    sliderElement.value = firstValidIndex;
                }
            }
        }
        
        updateBankInfo(currentBank);
        updateSlider();
        
        console.log('SLIDER: Ініціалізацію завершено');
        
    }, 1000);
});
