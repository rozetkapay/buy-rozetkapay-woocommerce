jQuery(document).ready(function($) {
    'use strict';
    
    // Показуємо інструкцію, якщо є прапорець
    if (typeof rp_mobile_instruction !== 'undefined' && rp_mobile_instruction) {
        console.log('Показуємо інструкцію для банку:', rp_mobile_instruction.bank_name);
        
        var modal = $(`
            <div class="rp-mobile-instruction-modal" style="
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.8); z-index: 99999; display: flex;
                align-items: center; justify-content: center; animation: fadeIn 0.3s ease;
            ">
                <div class="rp-modal-content" style="
                    background: white; padding: 30px; border-radius: 12px;
                    max-width: 450px; width: 90%; text-align: center; 
                    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                    animation: slideIn 0.3s ease;
                ">
                    <div class="rp-modal-icon" style="
                        font-size: 64px; margin-bottom: 20px;
                        background: linear-gradient(45deg, #4CAF50, #45a049);
                        -webkit-background-clip: text; -webkit-text-fill-color: transparent;
                        background-clip: text; color: #4CAF50;
                    ">📱</div>
                    
                    <h3 style="
                        margin-bottom: 15px; color: #333; font-size: 24px;
                        font-weight: 600;
                    ">Підтвердіть оплату частинами</h3>
                    
                    <p style="
                        margin-bottom: 25px; color: #666; line-height: 1.5;
                        font-size: 16px;
                    ">
                        Перевірте свій смартфон та підтвердіть розстрочку 
                        в мобільному додатку <strong style="color: #333;">${rp_mobile_instruction.bank_name}</strong>
                    </p>
                    
                    <div class="rp-instruction-steps" style="
                        text-align: left; margin-bottom: 25px; 
                        background: #f8f9fa; padding: 20px; border-radius: 8px;
                    ">
                        <div style="margin-bottom: 10px; display: flex; align-items: center;">
                            <span style="
                                display: inline-block; width: 24px; height: 24px;
                                background: #007cba; color: white; border-radius: 50%;
                                text-align: center; line-height: 24px; margin-right: 10px;
                                font-size: 12px; font-weight: bold;
                            ">1</span>
                            Відкрийте додаток ${rp_mobile_instruction.bank_name}
                        </div>
                        <div style="margin-bottom: 10px; display: flex; align-items: center;">
                            <span style="
                                display: inline-block; width: 24px; height: 24px;
                                background: #007cba; color: white; border-radius: 50%;
                                text-align: center; line-height: 24px; margin-right: 10px;
                                font-size: 12px; font-weight: bold;
                            ">2</span>
                            Знайдіть push-повідомлення про розстрочку
                        </div>
                        <div style="display: flex; align-items: center;">
                            <span style="
                                display: inline-block; width: 24px; height: 24px;
                                background: #007cba; color: white; border-radius: 50%;
                                text-align: center; line-height: 24px; margin-right: 10px;
                                font-size: 12px; font-weight: bold;
                            ">3</span>
                            Підтвердіть операцію
                        </div>
                    </div>
                    
                    <div class="rp-modal-buttons" style="display: flex; gap: 10px; justify-content: center;">
                        <button class="rp-modal-close" style="
                            background: #007cba; color: white; border: none; 
                            padding: 12px 24px; border-radius: 6px; cursor: pointer;
                            font-size: 16px; font-weight: 500;
                            transition: background 0.2s ease;
                        ">Зрозуміло</button>
                        
                        <button class="rp-modal-later" style="
                            background: transparent; color: #666; border: 1px solid #ddd; 
                            padding: 12px 24px; border-radius: 6px; cursor: pointer;
                            font-size: 16px;
                        ">Закрити</button>
                    </div>
                    
                    <div class="rp-auto-close-timer" style="
                        margin-top: 15px; font-size: 12px; color: #999;
                    ">Автоматично закриється через <span class="rp-timer">15</span> сек.</div>
                </div>
            </div>
        `);
        
        // Додаємо CSS-анімації
        $('<style>').prop('type', 'text/css').html(`
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes slideIn {
                from { transform: translateY(-30px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            .rp-modal-close:hover {
                background: #005a87 !important;
            }
            .rp-modal-later:hover {
                background: #f5f5f5 !important;
            }
        `).appendTo('head');
        
        $('body').append(modal);
        
        // Закриття по кнопках
        $('.rp-modal-close, .rp-modal-later').on('click', function() {
            closeModal();
        });
        
        // Закриття по кліку на фон
        $('.rp-mobile-instruction-modal').on('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Функція закриття
        function closeModal() {
            modal.fadeOut(300, function() {
                $(this).remove();
            });
        }
        
        // Таймер автозакриття
        var timeLeft = 15;
        var timer = setInterval(function() {
            timeLeft--;
            $('.rp-timer').text(timeLeft);
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                closeModal();
            }
        }, 1000);
        
        // Очищаємо таймер під час закриття
        $('.rp-modal-close, .rp-modal-later').on('click', function() {
            clearInterval(timer);
        });
    }
});
