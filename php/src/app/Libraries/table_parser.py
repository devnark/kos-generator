import sys
import pdfplumber

def pdf_to_flat_table_strings(pdf_path):
    output_lines = []

    with pdfplumber.open(pdf_path) as pdf:
        for page_num, page in enumerate(pdf.pages, 1):
            # Извлекаем все таблицы на текущей странице
            tables = page.extract_tables()

            for table in tables:
                # Если таблица пустая или в ней нет строк с данными — пропускаем
                if not table or len(table) < 2:
                    continue

                # Извлекаем заголовки колонок (первая строка таблицы)
                # Очищаем от переносов строк и лишних пробелов
                headers = [str(cell).replace('\n', ' ').strip() if cell else f"Колонка_{i}" for i, cell in enumerate(table[0])]

                # Обрабатываем каждую строку с данными, начиная со второй
                for row_idx, row in enumerate(table[1:], 1):
                    # Очищаем ячейки текущей строки
                    cells = [str(cell).replace('\n', ' ').strip() if cell else "нет данных" for cell in row]

                    # Пропускаем полностью пустые строки
                    if not any(cell != "нет данных" for cell in cells):
                        continue

                    # Собираем строку в плоский формат: "Заголовок1 = Значение1, Заголовок2 = Значение2..."
                    row_parts = []
                    for h, c in zip(headers, cells):
                        row_parts.append(f'"{h}" = "{c}"')

                    flat_row_string = ", ".join(row_parts)

                    # Формируем итоговую текстовую запись для ИИ
                    output_lines.append(
                        f"[Данные таблицы на странице {page_num}, строка {row_idx}]: {flat_row_string}."
                    )

    return "\n".join(output_lines)

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python3 table_parser.py <path_to_pdf>")
        sys.exit(1)

    pdf_file_path = sys.argv[1]
    result = pdf_to_flat_table_strings(pdf_file_path)
    print(result)
