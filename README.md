Включи расширение vector (это нужно сделать один раз при первом запуске):
CREATE EXTENSION IF NOT EXISTS vector;

Создание миграций силами контейнера
docker exec -it generator_php php spark make:migration Create_My_Table

Выполнение миграций
docker exec -it generator_php php spark migrate

Генерация моделей
docker exec -it generator_php php spark make:model ProfessionModel

Используем ollama pull bge-m3 для нарезки pdf в вектор

Воркер для обработки запросов к ИИ на создание тестов
запускать в отдельной вкладке
docker exec -it generator_php php spark ai:worker


Запуск парсера таблиц pdf
Пошаговое руководство по ручному запуску и дебагу:

docker exec -it generator_tabula_worker php spark rag:run-tabula
docker exec -it generator_php php spark rag:run-enrich-worker

Создайте у себя на компьютере (хосте) папку tabula_bin прямо в корне вашего проекта (рядом с папками php, tabula_worker и файлом docker-compose.yml) и скачайте туда рабочий файл:
1. Скачайте tabula-1.0.5-jar-with-dependencies.jar через ваш браузер.
2. Положите его в созданную папку под именем tabula.jar. Путь должен получиться следующим: ./tabula_bin/tabula.jar.

Проверьте работоспособность Java-архива напрямую в контейнере:
docker exec -it generator_tabula_worker java -jar /opt/tabula_worker/bin/tabula.jar --version
