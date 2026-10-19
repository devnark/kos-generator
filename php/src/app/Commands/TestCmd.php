<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestCmd extends BaseCommand
{
//docker exec -it generator_php tail -F /tmp/curl_debug.log
//docker exec -it generator_php php spark test:cmd
//docker exec -it generator_php php spark ai:worker
//docker exec -it generator_php php spark cache:clear
    protected $group = 'Test';
    protected $name = 'test:cmd';
    protected $description = 'Тестирование задумок';

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        set_time_limit(0);
        CLI::write("Запуск теста...\n", "green");

$responseBody = <<<HEAD
{"id":"gen_2223391052767956993","object":"chat.completion","created":1792401747,"model":"qwen/qwen-2.5-72b-instruct","choices":[{"index":0,"message":{"role":"assistant","content":"```json\n{\n  \"question\": \"Какой косметический препарат используется для смягчения и увлажнения кутикулы, боковых валиков и кожи кистей?\",\n  \"options\": [\n    \"Гель для закрепления и придания блеска\",\n    \"Масло для кутикулы\",\n    \"Скраб для кожи стоп\",\n    \"Крем для тела\",\n    \"Лак для ногтей\"\n  ],\n  \"correct\": [\"Масло для кутикулы\"],\n  \"rationale\": \"Согласно Национальному стандарту РФ ГОСТ Р 72319-2025 (страница 14), для смягчения и увлажнения кутикулы, боковых валиков и кожи кистей используется косметический препарат, который в данном контексте наиболее вероятно является маслом для кутикулы.\"\n}\n```"},"finish_reason":"stop","logprobs":null}],"usage":{"prompt_tokens":2417,"completion_tokens":220,"total_tokens":2637,"completion_tokens_details":{"reasoning_tokens":0,"audio_tokens":0,"image_tokens":0,"accepted_prediction_tokens":null,"rejected_prediction_tokens":null},"prompt_tokens_details":{"cached_tokens":0,"cache_write_tokens":0,"audio_tokens":0,"video_tokens":0},"server_tool_use":null,"cost_rub":0.11435258,"cost":0.11435258,"plugins":null}}
HEAD;

$responseBody2 = <<<HEAD
{
  "id": "gen-1792373567-zKmPXmxtiQNVA7RMHlEt",
  "model": "qwen/qwen-2.5-72b-instruct",
  "choices": [
    {
      "index": 0,
      "message": {
        "role": "assistant",
        "content": "```json\n{\n  \"question\": \"Укажите время исполнения услуги по укреплению/выравниванию натуральных ногтей гелем/полигелем/акригелем с подготовительными и заключительными этапами работ.\",\n  \"options\": [\n    \"60-80 минут\",\n    \"70-90 минут\",\n    \"90-110 минут\",\n    \"Нанесение парафиновой маски\",\n    \"Применение размягчителя\"\n  ],\n  \"correct\": [\"70-90 минут\"],\n  \"rationale\": \"Согласно Национальному стандарту РФ ГОСТ Р 72319-2025, страница 12, время исполнения услуги по укреплению/выравниванию натуральных ногтей гелем/полигелем/акригелем составляет 70-90 минут с подготовительными и заключительными этапами работ.\"\n}\n```"
      },
      "finishReason": "stop",
      "logprobs": null
    }
  ],
  "usage": {
    "promptTokens": 2323,
    "completionTokens": 229,
    "totalTokens": 2552,
    "cost": 0.11074341,
    "promptTokensDetails": {
      "cachedTokens": 0,
      "cacheWriteTokens": 0,
      "audioTokens": 0,
      "videoTokens": 0
    },
    "completionTokensDetails": {
      "reasoningTokens": 0,
      "audioTokens": 0,
      "imageTokens": 0
    },
    "costDetails": {
      "promptUsd": 0.00083628,
      "completionsUsd": 0.0000916
    }
  },
  "metadata": {}
}
HEAD;

$responseBody3 = <<<HEAD
{
  "id": "gen-1792397562-bQzqu2pk9S0sVJlkE69k",
  "model": "qwen/qwen-2.5-72b-instruct",
  "choices": [
    {
      "index": 0,
      "message": {
        "role": "assistant",
        "content": "```json\n{\n  \"question\": \"Какой косметический препарат используется для питания и увлажнения кутикулы, боковых валиков и кожи кистей на последнем этапе услуги по укреплению/выравниванию ногтей?\",\n  \"options\": [\n    \"Гель для закрепления и придания блеска\",\n    \"Крем для рук\",\n    \"Размягчитель кутикулы\",\n    \"Парафиновая маска\",\n    \"Препарат для снятия липкого слоя\"\n  ],\n  \"correct\": [\"Крем для рук\"],\n  \"rationale\": \"Согласно Национальному стандарту РФ ГОСТ Р 72319-2025, на последнем этапе услуги по укреплению/выравниванию ногтей используется косметический препарат для питания и увлажнения кутикулы, боковых валиков и кожи кистей (страница 14).\"\n}\n```"
      },
      "finishReason": "stop",
      "logprobs": null
    }
  ],
  "usage": {
    "promptTokens": 2435,
    "completionTokens": 234,
    "totalTokens": 2669,
    "cost": 0.11579434,
    "promptTokensDetails": {
      "cachedTokens": 0,
      "cacheWriteTokens": 0,
      "audioTokens": 0,
      "videoTokens": 0
    },
    "completionTokensDetails": {
      "reasoningTokens": 0,
      "audioTokens": 0,
      "imageTokens": 0
    },
    "costDetails": {
      "promptUsd": 0.0008766,
      "completionsUsd": 0.0000936
    }
  },
  "metadata": {}
}
HEAD;

        $responseBody5 = <<<HEAD
{
  "id": "gen-1792401736-IBARp1kieL0g57jfmpJB",
  "model": "qwen/qwen-2.5-72b-instruct",
  "choices": [
    {
      "index": 0,
      "message": {
        "role": "assistant",
        "content": "```json\n{\n  \"question\": \"Какой косметический препарат используется для смягчения и увлажнения кутикулы, боковых валиков и кожи кистей?\",\n  \"options\": [\n    \"Гель для закрепления и придания блеска\",\n    \"Масло для кутикулы\",\n    \"Скраб для кожи стоп\",\n    \"Крем для тела\",\n    \"Лак для ногтей\"\n  ],\n  \"correct\": [\"Масло для кутикулы\"],\n  \"rationale\": \"Согласно Национальному стандарту РФ ГОСТ Р 72319-2025 (страница 14), для смягчения и увлажнения кутикулы, боковых валиков и кожи кистей используется косметический препарат, который в данном контексте наиболее вероятно является маслом для кутикулы.\"\n}\n```"
      },
      "finishReason": "stop",
      "logprobs": null
    }
  ],
  "usage": {
    "promptTokens": 2417,
    "completionTokens": 220,
    "totalTokens": 2637,
    "cost": 0.11435258,
    "promptTokensDetails": {
      "cachedTokens": 0,
      "cacheWriteTokens": 0,
      "audioTokens": 0,
      "videoTokens": 0
    },
    "completionTokensDetails": {
      "reasoningTokens": 0,
      "audioTokens": 0,
      "imageTokens": 0
    },
    "costDetails": {
      "promptUsd": 0.00087012,
      "completionsUsd": 0.000088
    }
  },
  "metadata": {}
}
HEAD;
        $responseBody6 = <<<HEAD
{
  "id": "gen-1792403953-tNcVNcdmgbQvEzMFK0IS",
  "model": "qwen/qwen-2.5-72b-instruct",
  "choices": [
    {
      "index": 0,
      "message": {
        "role": "assistant",
        "content": "```json\n{\n  \"question\": \"Какой косметический препарат применяется для питания и увлажнения кутикулы, боковых валиков и кожи кистей на заключительном этапе услуги по укреплению/выравниванию ногтей?\",\n  \"options\": [\n    \"Гель для закрепления и придания блеска\",\n    \"Скраб для кожи стоп\",\n    \"Маска для кожи стоп\",\n    \"Косметическое средство (крем/масло/лосьон)\",\n    \"Размягчитель для кутикулы\"\n  ],\n  \"correct\": [\"Косметическое средство (крем/масло/лосьон)\"],\n  \"rationale\": \"Согласно Национальному стандарту РФ ГОСТ Р 72319-2025, на странице 14, на заключительном этапе услуги по укреплению/выравниванию ногтей применяется косметическое средство (крем/масло/лосьон) для питания и увлажнения кутикулы, боковых валиков и кожи кистей.\"\n}\n```"
      },
      "finishReason": "stop",
      "logprobs": null
    }
  ],
  "usage": {
    "promptTokens": 2563,
    "completionTokens": 272,
    "totalTokens": 2835,
    "cost": 0.12310817,
    "promptTokensDetails": {
      "cachedTokens": 0,
      "cacheWriteTokens": 0,
      "audioTokens": 0,
      "videoTokens": 0
    },
    "completionTokensDetails": {
      "reasoningTokens": 0,
      "audioTokens": 0,
      "imageTokens": 0
    },
    "costDetails": {
      "promptUsd": 0.00092268,
      "completionsUsd": 0.0001088
    }
  },
  "metadata": {}
}
HEAD;
        $responseBody7 = <<<HEAD
{
  "id": "gen-1792404508-H5PGUPbBrIoaANjTtf1Y",
  "model": "qwen/qwen-2.5-72b-instruct",
  "choices": [
    {
      "index": 0,
      "message": {
        "role": "assistant",
        "content": "```json\n{\n  \"question\": \"Укажите, сколько времени требуется для выполнения услуги по укреплению/выравниванию натуральных ногтей гелем/полигелем/акригелем с подготовительными и заключительными этапами?\",\n  \"options\": [\"60-90 минут\", \"Нанесение парафиновой маски\", \"Срезание кутикулы кусачками\", \"Применение размягчителя\", \"Массаж кистей с кремом\"],\n  \"correct\": [\"60-90 минут\"],\n  \"rationale\": \"Согласно Национальному стандарту РФ ГОСТ Р 72319-2025, время исполнения услуги по укреплению/выравниванию натуральных ногтей гелем/полигелем/акригелем составляет 70-90 минут с подготовительными и заключительными этапами работ (страница 12).\"\n}\n```"
      },
      "finishReason": "stop",
      "logprobs": null
    }
  ],
  "usage": {
    "promptTokens": 2412,
    "completionTokens": 235,
    "totalTokens": 2647,
    "cost": 0.11485385,
    "promptTokensDetails": {
      "cachedTokens": 0,
      "cacheWriteTokens": 0,
      "audioTokens": 0,
      "videoTokens": 0
    },
    "completionTokensDetails": {
      "reasoningTokens": 0,
      "audioTokens": 0,
      "imageTokens": 0
    },
    "costDetails": {
      "promptUsd": 0.00086832,
      "completionsUsd": 0.000094
    }
  },
  "metadata": {}
}
HEAD;
        $responseBody8 = <<<HEAD
{
  "id": "gen-1792371886-y45DcW8VVVfLR2V2MvI6",
  "model": "qwen/qwen-2.5-72b-instruct",
  "choices": [
    {
      "index": 0,
      "message": {
        "role": "assistant",
        "content": "К сожалению, вы не предоставили конкретный тест для проверки. Пожалуйста, предоставьте тест в формате, указанном в ваших правилах, чтобы я мог провести аудит.\n\nПример ожидаемого формата:\n```json\n{\n  \"question\": \"Какие услуги включает классический маникюр?\",\n  \"type\": \"multi\",\n  \"options\": [\n    \"Обрезной маникюр\",\n    \"Препаратный маникюр\",\n    \"Аппаратный маникюр\",\n    \"Комбинированный маникюр\",\n    \"Уход за кожей кистей и ногтями\",\n    \"СПА уход за кожей кистей и ногтями\",\n    \"Косметический массаж кистей\"\n  ],\n  \"correct\": [\n    \"Обрезной маникюр\",\n    \"Препаратный маникюр\",\n    \"Аппаратный маникюр\",\n    \"Комбинированный маникюр\"\n  ]\n}\n```\n\nПосле предоставления теста, я смогу провести проверку на наличие смыслового обмана, синонимов и логических ошибок."
      },
      "finishReason": "stop",
      "logprobs": null
    }
  ],
  "usage": {
    "promptTokens": 1870,
    "completionTokens": 272,
    "totalTokens": 2142,
    "cost": 0.09333248,
    "promptTokensDetails": {
      "cachedTokens": 0,
      "cacheWriteTokens": 0,
      "audioTokens": 0,
      "videoTokens": 0
    },
    "completionTokensDetails": {
      "reasoningTokens": 0,
      "audioTokens": 0,
      "imageTokens": 0
    },
    "costDetails": {
      "promptUsd": 0.0006732,
      "completionsUsd": 0.0001088
    }
  },
  "metadata": {}
}
HEAD;
        $responseBody9 = <<<HEAD
{
  "id": "gen-1792373022-CDfM8OEBm6VNT3rqjTKG",
  "model": "qwen/qwen-2.5-72b-instruct",
  "choices": [
    {
      "index": 0,
      "message": {
        "role": "assistant",
        "content": "{\n  \"question\": \"Выберите один правильный вариант ответа. Какой препарат используется для питания и увлажнения кутикулы, боковых валиков и кожи кистей в процессе укрепления/выравнивания ногтей?\",\n  \"options\": [\n    \"Гель для закрепления и придания блеска\",\n    \"Косметический препарат для питания и увлажнения\",\n    \"Дегидратор (обезжириватель)\",\n    \"Средство для снятия липкого слоя\",\n    \"Размягчитель для кутикулы\"\n  ],\n  \"correct\": [\"Косметический препарат для питания и увлажнения\"],\n  \"rationale\": \"Согласно Национальному стандарту РФ ГОСТ Р 72319-2025, на странице 14 указано, что для питания и увлажнения кутикулы, боковых валиков и кожи кистей используется косметический препарат.\"\n}"
      },
      "finishReason": "stop",
      "logprobs": null
    }
  ],
  "usage": {
    "promptTokens": 2608,
    "completionTokens": 235,
    "totalTokens": 2843,
    "cost": 0.12327526,
    "promptTokensDetails": {
      "cachedTokens": 0,
      "cacheWriteTokens": 0,
      "audioTokens": 0,
      "videoTokens": 0
    },
    "completionTokensDetails": {
      "reasoningTokens": 0,
      "audioTokens": 0,
      "imageTokens": 0
    },
    "costDetails": {
      "promptUsd": 0.00093888,
      "completionsUsd": 0.000094
    }
  },
  "metadata": {}
}
HEAD;
        $responseBody10 = <<<HEAD
{"id":"gen_2223418014320693249","object":"chat.completion","created":1792408171,"model":"qwen/qwen-2.5-72b-instruct","choices":[{"index":0,"message":{"role":"assistant","content":"```json\n{\n  \"question\": \"Укажите, сколько времени требуется для предоставления услуги по укреплению/выравниванию натуральных ногтей гелем/полигелем/акригелем с подготовительными и заключительными этапами работ?\",\n  \"options\": [\n    \"Нанесение парафиновой маски\",\n    \"Срезание кутикулы кусачками\",\n    \"Применение размягчителя\",\n    \"70-90 минут\",\n    \"Нанесение маски на кожу стоп\"\n  ],\n  \"correct\": [\"70-90 минут\"],\n  \"rationale\": \"Согласно Национальному стандарту РФ ГОСТ Р 72319-2025, время исполнения услуги по укреплению/выравниванию натуральных ногтей гелем/полигелем/акригелем составляет 70-90 минут с подготовительными и заключительными этапами работ (страница 12).\"\n}\n```"},"finish_reason":"stop","logprobs":null}],"usage":{"prompt_tokens":2472,"completion_tokens":245,"total_tokens":2717,"completion_tokens_details":{"reasoning_tokens":0,"audio_tokens":0,"image_tokens":0,"accepted_prediction_tokens":null,"rejected_prediction_tokens":null},"prompt_tokens_details":{"cached_tokens":0,"cache_write_tokens":0,"audio_tokens":0,"video_tokens":0},"server_tool_use":null,"cost_rub":0.11790924,"cost":0.11790924,"plugins":null}}
HEAD;

        $responseBody11 = <<<HEAD
{"id":"gen_2223421523761565697","object":"chat.completion","created":1792409008,"model":"qwen/qwen-2.5-72b-instruct","choices":[{"index":0,"message":{"role":"assistant","content":{ "question": "Укажите, какое средство используется для нанесения на ногтевую пластину перед укреплением/выравниванием натуральных ногтей гелем/полигелем/акригелем?", "options": ["Дегидратор (обезжириватель)", "Масло для кутикулы", "Гель для закрепления и придания блеска", "Парафиновая маска", "Размягчитель для кутикулы"], "correct": ["Дегидратор (обезжириватель)"], "rationale": "Согласно Национальному стандарту РФ ГОСТ Р 72319-2025, на странице 14, перед укреплением/выравниванием натуральных ногтей гелем/полигелем/акригелем необходимо очистить и обезжирить ногтевую пластину с помощью дегидратора (обезжиривателя).}},"finish_reason":"stop","logprobs":null}],"usage":{"prompt_tokens":2366,"completion_tokens":257,"total_tokens":2623,"completion_tokens_details":{"reasoning_tokens":0,"audio_tokens":0,"image_tokens":0,"accepted_prediction_tokens":null,"rejected_prediction_tokens":null},"prompt_tokens_details":{"cached_tokens":0,"cache_write_tokens":0,"audio_tokens":0,"video_tokens":0},"server_tool_use":null,"cost_rub":0.11392769,"cost":0.11392769,"plugins":null}}
HEAD;

        $responseBody12 = <<<HEAD
{
  "id": "gen-1792397562-bQzqu2pk9S0sVJlkE69k",
  "model": "qwen/qwen-2.5-72b-instruct",
  "choices": [
    {
      "index": 0,
      "message": {
        "role": "assistant",
        "content": "```json\n{\n  \"question\": \"Какой косметический препарат используется для питания и увлажнения кутикулы, боковых валиков и кожи кистей на последнем этапе услуги по укреплению/выравниванию ногтей?\",\n  \"options\": [\n    \"Гель для закрепления и придания блеска\",\n    \"Крем для рук\",\n    \"Размягчитель кутикулы\",\n    \"Парафиновая маска\",\n    \"Препарат для снятия липкого слоя\"\n  ],\n  \"correct\": [\"Крем для рук\"],\n  \"rationale\": \"Согласно Национальному стандарту РФ ГОСТ Р 72319-2025, на последнем этапе услуги по укреплению/выравниванию ногтей используется косметический препарат для питания и увлажнения кутикулы, боковых валиков и кожи кистей (страница 14).\"\n}\n```"
      },
      "finishReason": "stop",
      "logprobs": null
    }
  ],
  "usage": {
    "promptTokens": 2435,
    "completionTokens": 234,
    "totalTokens": 2669,
    "cost": 0.11579434,
    "promptTokensDetails": {
      "cachedTokens": 0,
      "cacheWriteTokens": 0,
      "audioTokens": 0,
      "videoTokens": 0
    },
    "completionTokensDetails": {
      "reasoningTokens": 0,
      "audioTokens": 0,
      "imageTokens": 0
    },
    "costDetails": {
      "promptUsd": 0.0008766,
      "completionsUsd": 0.0000936
    }
  },
  "metadata": {}
}
HEAD;

$responseBody13 =
<<<HEAD
{"model":"qwen2.5:7b","created_at":"2026-10-19T19:59:47.8351703Z","message":{"role":"assistant","content":"```json\n{\n  \"valid\": false,\n  \"error_reason\": \"Тест содержит смысловой обман и логическую ошибку.\",\n  \"valid_reason\": \"Варианты '1-2 пары', '5-6 пар', '7-8 пар' и '9-10 пар' являются синонимами '3-4 пары', так как они указывают на близкие значения. В тестах на знание норм и правил обычно используются точные значения, а не интервалы, которые могут быть интерпретированы как синонимы.\",\n  \"valid_not_enough_data\": \"Не было предоставлена дополнительная информация, позволяющая уточнить интервалы запаса постельного белья.\"\n}\n```\n\n### Пояснение к проверке:\n1. **Смысловой обман**: Варианты ответов '1-2 пары', '5-6 пар', '7-8 пар' и '9-10 пар' могут быть интерпретированы как синонимы '3-4 пары', так как они указывают на близкие значения. Это создает путаницу для участников теста.\n2. **Логическая ошибка**: В документе указано именно интервал '3-4 пары', но в тесте не указан конкретный интервал, что может вызвать сомнения.\n\nДля улучшения теста рекомендуется указать точный интервал или использовать другие варианты ответов, которые не будут рассматриваться как синонимы."},"done":true,"done_reason":"stop","total_duration":6899273400,"load_duration":6142700,"prompt_eval_count":1946,"prompt_eval_cached_count":27,"prompt_eval_duration":528879000,"eval_count":348,"eval_duration":6302257000}
HEAD;

$responseBody14 =
<<<HEAD
{"model":"qwen2.5:7b","created_at":"2026-10-19T20:55:16.2422039Z","message":{"role":"assistant","content":"{\n  \"question\": \"Какое количество запаса чистого постельного белья необходимо иметь проживающим согласно правилам?\",\n  \"options\": [\n    \"В объеме, позволяющем обеспечить его смену раз в неделю\",\n    \"В объеме, позволяющем обеспечить его смену в соответствии с пунктом 7.19 настоящих правил\",\n    \"Достаточно одного комплекта на одного проживающего\",\n    \"Нет определенных требований к запасу белья\",\n    \"В объеме, позволяющем обеспечить его смену в соответствии с местными обычаями\"\n  ],\n  \"correct\": [\"В объеме, позволяющем обеспечить его смену в соответствии с пунктом 7.19 настоящих правил\"],\n  \"rationale\": \"Обоснование со ссылкой на страницу 40 в документе: \\\"проживающих необходимо иметь запас чистого постельного белья в объеме, позволяющем обеспечить его смену в соответствии спунктом 7.19 настоящих правил. (в ред.Постановления Главного государственного санитарного врача РФ от 20.03.2024 N 2)\\\"\"\n}"},"done":true,"done_reason":"stop","total_duration":5631460900,"load_duration":2452400,"prompt_eval_count":2265,"prompt_eval_cached_count":1549,"prompt_eval_duration":219971000,"eval_count":285,"eval_duration":5183492000}
HEAD;


        $responseBody = $responseBody14;

        //docker exec -it generator_php php spark test:cmd
        try {
            $responseJson = $this->convertStringJsonToArray($responseBody);
        }catch (\Exception $e){
            echo "<br>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>> START >>>>>>>>>>>>>>>>>>>>>>>>><br>";
            echo "<br>FILE: " . __FILE__ . " ::: LINE: " . __LINE__ . "<br>";
            var_dump($e->getMessage());
            echo "<br>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>> END >>>>>>>>>>>>>>>>>>>>>>>>>>><br>";
            die();

        }

        echo "<br>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>> START >>>>>>>>>>>>>>>>>>>>>>>>><br>";
        echo "<br>FILE: " . __FILE__ . " ::: LINE: " . __LINE__ . "<br>";
        var_dump($responseJson);
        var_dump("END of TEST");
        echo "<br>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>> END >>>>>>>>>>>>>>>>>>>>>>>>>>><br>";
        die();


        CLI::write("\nКонец", "green");
    }


    private function convertStringJsonToArray($responseBody) : array {
        $buffer = $responseBody;
        // Вырезаем кусок json и заменяем его на плейсхолдер
        if (preg_match('/```json(.*?)```/s', $responseBody, $matches)) {
            $fullBlock = $matches[0]; // Весь блок: ```json ... ```
            $jsonBlock = $matches[1]; // Только блок: ```json ... ```

            $jsonBlock = $this->clearJson($jsonBlock);

            // Заменяем весь найденный блок в исходном тексте на ###
            $responseBody = str_replace($fullBlock, '#%#%#', $responseBody);
            $responseBody = str_replace('#%#%#', $jsonBlock, $responseBody);
            $responseBody = str_replace('#%#%#', $jsonBlock, $responseBody);
            $responseBody = str_replace('"{', '{', $responseBody);
            $responseBody = str_replace('}"', '}', $responseBody);
        }else {
            $responseBody = $this->clearJson($responseBody);
            $responseBody = str_replace('"{', '{', $responseBody);
            $responseBody = str_replace('}"', '}', $responseBody);
        }

        if (empty(json_decode($responseBody, true))) {
            throw new \Exception("Bad JSON: $buffer");
        }

        return json_decode($responseBody, true);
    }

    private function clearJson($stringJson) :string
    {
        $responseBody = '';
        if (isset($stringJson)){
            $content = str_replace('\"', '"', $stringJson);
            $content = str_replace('\n', '', $content);
            $responseBody = preg_replace('/\s+/u', ' ', $content);
        }
        $responseBody = trim($responseBody, ' "\'');

        return $responseBody;
    }
}
