(function($) {
    $.fn.quizConstructor = function(options) {
        // Настройки по умолчанию
        var settings = $.extend({
            minOptions: 2,
            multipleCorrect: false,
            maxWidth: '800px',
            readOnly: false, // ИСПРАВЛЕНО: Новый параметр для отключения редактирования и отправки
            initialData: null, // ИСПРАВЛЕНО: Объект для передачи готовых данных {question: "...", options: [...], correctOptionIndexes: [...]}
            onSave: function(data) { console.log('Данные сохранены:', data); }
        }, options);

        return this.each(function() {
            var $container = $(this);

            // Используем переданные данные, если они есть
            var initQuestion = '';
            var initOptions = [];
            var initCorrect = [];

            if (settings.initialData) {
                initQuestion = settings.initialData.question || '';
                initOptions = settings.initialData.options || [];
                initCorrect = settings.initialData.correctOptionIndexes || [];
            }

            var groupName = 'quiz_correct_' + Math.random().toString(36).substr(2, 9);

            var $card = $('<div>').addClass('card shadow-sm mx-auto').css('max-width', settings.maxWidth);
            var $cardBody = $('<div>').addClass('card-body p-4');

            // Меняем заголовок в зависимости от режима readOnly
            var titleText = settings.readOnly ? 'Просмотр вопроса (Только чтение)' : 'Конструктор вопроса';
            var $title = $('<h5>').addClass('card-title mb-4 text-primary fs-4').text(titleText);

            // Группа вопроса
            var $questionGroup = $('<div>').addClass('mb-4');
            var $questionLabel = $('<label>').addClass('form-label fw-semibold fs-5').text('Вопрос');

            var $questionInput = $('<textarea>').attr({
                class: 'form-control form-control-lg',
                id: 'quiz-question',
                placeholder: 'Введите текст вопроса',
                rows: 2,
                required: true,
                disabled: settings.readOnly // Блокируем поле, если readOnly
            }).val(initQuestion).css({
                'resize': 'none',
                'overflow-y': 'hidden',
                'min-height': '50px'
            });

            $questionInput.on('input', function() {
                $(this).css('height', 'auto');
                $(this).css('height', this.scrollHeight + 'px');
            });

            $questionGroup.append($questionLabel).append($questionInput);

            // Группа вариантов ответов
            var $optionsGroup = $('<div>').addClass('mb-4');

            var labelText = settings.multipleCorrect
                ? 'Варианты ответов (отметьте правильные варианты зелеными галочками)'
                : 'Варианты ответов (отметьте ОДИН правильный вариант зеленой галочкой)';
            if (settings.readOnly) labelText = 'Варианты ответов (правильные отмечены зеленым)';

            var $optionsLabel = $('<label>').addClass('form-label fw-semibold fs-5').text(labelText);
            var $optionsContainer = $('<div>').attr('id', 'options-container').addClass('d-flex flex-column gap-2');

            // Создаем кнопку добавления только если НЕ readOnly
            var $addOptionBtn = settings.readOnly
                ? null
                : $('<button>').attr('type', 'button').addClass('btn btn-outline-success btn-sm mt-3 px-3').text('+ Добавить вариант');

            $optionsGroup.append($optionsLabel).append($optionsContainer);
            if ($addOptionBtn) $optionsGroup.append($addOptionBtn);

            // Создаем кнопку сохранения только если НЕ readOnly
            var $saveQuizBtn = settings.readOnly
                ? null
                : $('<button>').attr('type', 'button').addClass('btn btn-primary w-100 py-2 fs-5 fw-bold').text('Сохранить вопрос');

            $cardBody.append($title).append($questionGroup).append($optionsGroup);
            if ($saveQuizBtn) $cardBody.append($saveQuizBtn);

            $card.append($cardBody);
            $container.empty().append($card);

            function addOptionInput(value, isChecked) {
                var val = value || '';
                var uniqueId = 'correct_opt_' + Math.random().toString(36).substr(2, 9);

                var $row = $('<div>').addClass('input-group option-item align-items-start');
                var inputType = settings.multipleCorrect ? 'checkbox' : 'radio';

                var $checkInput = $('<input>').attr({
                    type: inputType,
                    name: groupName,
                    id: uniqueId,
                    class: 'btn-check quiz-correct-input',
                    autocomplete: 'off',
                    disabled: settings.readOnly // Блокируем галочку, если readOnly
                });

                var $checkLabel = $('<label>').attr({
                    for: uniqueId
                }).addClass('btn btn-outline-secondary px-3').text('✓').css('min-height', '38px');

                // Задаем начальное состояние галочки и её цвет
                if (isChecked) {
                    $checkInput.prop('checked', true);
                    $checkLabel.removeClass('btn-outline-secondary').addClass('btn-success');
                }

                $checkInput.on('change', function() {
                    if (!settings.multipleCorrect) {
                        $optionsContainer.find('label.btn').removeClass('btn-success').addClass('btn-outline-secondary');
                    }

                    if ($(this).prop('checked')) {
                        $checkLabel.removeClass('btn-outline-secondary').addClass('btn-success');
                    } else {
                        $checkLabel.removeClass('btn-success').addClass('btn-outline-secondary');
                    }
                });

                var $input = $('<textarea>').attr({
                    class: 'form-control quiz-option',
                    placeholder: 'Вариант ответа',
                    rows: 1,
                    disabled: settings.readOnly // Блокируем текстовое поле ответа, если readOnly
                }).val(val).css({
                    'resize': 'none',
                    'overflow-y': 'hidden',
                    'min-height': '38px'
                });

                $input.on('input', function() {
                    $(this).css('height', 'auto');
                    $(this).css('height', this.scrollHeight + 'px');
                });

                // Кнопку удаления создаем только если НЕ readOnly
                var $deleteBtn = settings.readOnly
                    ? null
                    : $('<button>').attr('type', 'button').addClass('btn btn-outline-danger delete-option-btn px-3').text('✕').css('min-height', '38px');

                $row.append($checkInput).append($checkLabel).append($input);
                if ($deleteBtn) $row.append($deleteBtn);

                if ($deleteBtn) {
                    $deleteBtn.on('click', function() {
                        if ($optionsContainer.children('.option-item').length > settings.minOptions) {
                            var wasChecked = $checkInput.prop('checked');
                            $row.remove();

                            if (wasChecked && !settings.multipleCorrect) {
                                var $firstInput = $optionsContainer.find('.quiz-correct-input').first();
                                $firstInput.prop('checked', true).trigger('change');
                            }
                        } else {
                            alert('Должно быть как минимум ' + settings.minOptions + ' варианта ответа!');
                        }
                    });
                }

                $optionsContainer.append($row);

                // Подгоняем высоту под переданный текст сразу после добавления
                if (settings.readOnly) {
                    $input.css('height', $input[0].scrollHeight + 'px');
                }
            }

            // Отрисовка полей
            if (initOptions.length > 0) {
                // Если переданы готовые данные, строим поля по ним
                for (var i = 0; i < initOptions.length; i++) {
                    var isCorrect = initCorrect.indexOf(i) !== -1;
                    addOptionInput(initOptions[i], isCorrect);
                }
            } else {
                // Иначе создаем пустые дефолтные поля для нового вопроса
                for (var i = 0; i < settings.minOptions; i++) {
                    addOptionInput();
                }
            }

            // Авто-высота для поля вопроса, если загружен готовый текст
            if (settings.readOnly && initQuestion) {
                $questionInput.css('height', $questionInput[0].scrollHeight + 'px');
            }

            if ($addOptionBtn) {
                $addOptionBtn.on('click', function() {
                    addOptionInput();
                });
            }

            if ($saveQuizBtn) {
                $saveQuizBtn.on('click', function() {
                    var questionText = $.trim($questionInput.val());
                    var options = [];
                    var correctIndexes = [];

                    $optionsContainer.children('.option-item').each(function(index) {
                        var $item = $(this);
                        var val = $.trim($item.find('.quiz-option').val());

                        if (val) {
                            options.push(val);
                            if ($item.find('.quiz-correct-input').prop('checked')) {
                                correctIndexes.push(options.length - 1);
                            }
                        }
                    });

                    if (!questionText) {
                        $questionInput.addClass('is-invalid').focus();
                        alert('Пожалуйста, введите текст вопроса.');
                        return;
                    } else {
                        $questionInput.removeClass('is-invalid').addClass('is-valid');
                    }

                    if (options.length < settings.minOptions) {
                        alert('Пожалуйста, заполните как минимум ' + settings.minOptions + ' варианта ответа.');
                        return;
                    }

                    if (correctIndexes.length === 0) {
                        alert('Пожалуйста, укажите хотя бы один правильный вариант ответа, нажав на галочку «✓» слева!');
                        return;
                    }

                    var resultData = {
                        question: questionText,
                        options: options,
                        correctOptionIndexes: correctIndexes
                    };

                    settings.onSave(resultData);
                    $container.trigger('quiz:saved', [resultData]);
                });
            }
        });
    };
})(jQuery);
