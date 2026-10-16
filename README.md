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