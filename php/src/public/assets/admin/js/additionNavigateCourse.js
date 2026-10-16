$(document).ready(function () {
    var $filterForm = $('#filterForm');
    var $datalist = $('#datalistOptions');
    var $searchInput = $('#customSearchInput');
    var $categorySelect = $('#searchCategory');
    var $sectionSelect = $('#searchSection');

    var courseId = $filterForm.data('course-id');
    var searchUrl = $filterForm.data('search-url');

    // БЕРЕМ БАЗОВЫЙ URL НАПРЯМУЮ ИЗ ФОРМЫ (он уже отрендерен сервером как http://kos-home.local/admin/course/2850/)
    var baseUrl = $filterForm.attr('action');
    // На всякий случай гарантируем, что в конце есть слеш
    if (!baseUrl.endsWith('/')) {
        baseUrl += '/';
    }

    var serverData = [];
    var tempValue = "";
    var isClearing = false;

    // 1. Загрузка данных
    $.ajax({
        url: searchUrl,
        method: 'GET',
        data: { course_id: courseId },
        dataType: 'json',
        success: function (response) {
            serverData = response.quest_numbers;
            renderDatalist();
        },
        error: function (xhr, status, error) {
            console.error("Ошибка загрузки данных:", error);
        }
    });

    // 2. Функция отрисовки datalist
    function renderDatalist() {
        var selectedCategory = $categorySelect.val();
        var selectedSection = $sectionSelect.val();

        $datalist.empty();

        serverData.forEach(function (item) {
            var matchCategory = true;
            if (selectedCategory === 'demo') {
                matchCategory = (parseInt(item.is_demo) === 1);
            } else if (selectedCategory === 'not_filled') {
                matchCategory = (parseInt(item.not_filled) === 1);
            }

            var matchSection = (selectedSection === 'all' || !item.section || item.section === selectedSection);

            if (matchCategory && matchSection) {
                var $option = $('<option>')
                    .val(item.quest_sort)
                    .attr('data-id', item.quest_id);
                $datalist.append($option);
            }
        });
    }

    // 3. Обработчики изменений в селектах (без очистки инпута)
    $categorySelect.on('change', function () {
        renderDatalist();
    });

    $sectionSelect.on('change', function () {
        renderDatalist();
    });

    // 4. Логика фокуса/клика для инпута
    $searchInput.on('focus click', function () {
        var $input = $(this);
        if (isClearing) return;

        if ($input.val() !== "") {
            tempValue = $input.val();
        }

        isClearing = true;
        $input.val('');

        setTimeout(function () {
            $input.trigger('input');
            isClearing = false;
        }, 1);
    });

    $searchInput.on('blur', function () {
        var $input = $(this);
        setTimeout(function () {
            if ($input.val() === "" && tempValue !== "") {
                $input.val(tempValue);
            }
        }, 200);
    });

    $searchInput.on('input', function () {
        var val = $(this).val();
        $datalist.find('option').each(function () {
            if ($(this).val() === val) {
                tempValue = val;
            }
        });
    });

    // 5. НАДЕЖНЫЙ РАСЧЕТ ССЫЛКИ ПРИ НАЖАТИИ НА ПОИСК
    // 5. НАДЕЖНЫЙ РАСЧЕТ ССЫЛКИ И ПЕРЕХОД БЕЗ ЗНАКА ВОПРОСА
    $filterForm.on('submit', function (e) {
        e.preventDefault(); // Полностью блокируем стандартный сабмит GET-формы, чтобы убрать "?"

        var currentInputValue = $searchInput.val();
        var foundId = "";

        // Ищем ID в текущем отфильтрованном списке datalist
        $datalist.find('option').each(function () {
            if ($(this).val() === currentInputValue) {
                foundId = $(this).attr('data-id');
            }
        });

        if (foundId) {
            // Склеиваем чистый базовый URL из формы и ID вопроса
            var targetUrl = baseUrl + "quest/" + foundId;

            // Перенаправляем пользователя напрямую по чистой ссылке
            window.location.href = targetUrl;
        } else {
            alert("Выбранный номер вопроса недоступен с текущими фильтрами или введен некорректно.");
        }
    });
});
