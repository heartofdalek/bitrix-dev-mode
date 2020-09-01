### Мастер для "деактивации" битрикс
Цель решения: настроить копию битрикс под разработку. <br> 
Включено два варианта исполнения: мастер и скрипт для запуска из консоли.

#### Использование мастера
1. Распокавать файлы в `/bitrix/wizards/{Код партнера}/devoptions/`
2. Запустить из административного интерфейса (Список мастеров)

#### Использование консольного скрипта
1. Расположить папку `/tbdn_dev` в корне проекта
2. Перейти в папку решения
3. запустить скрипт php index.php <br>
   Параметры:
    - k - удалить лицензиноный ключ (необязательный флаг) Ключ сохранится в /bitrix/license_ley.php.dev
    - p - закрыть доступ к публичной части (необязательный флаг)
    - s - в качестве параметра передается им сервера, он же используется для установки адреса сайта в папке "/" (необязательный параметр)
	   Например dev.bx-site.ru
    - t - Передается название сайта в логотипе (для bx24)
    - docroot - Указать директорию в качестве DOCUMENT_ROOT

Пример вызова из консоли: <br>
	`php index.php -k -p -s=dev.bx-site.ru --docroot=/home/bitrix/www` - закроет публичную часть, удалит ключ, установить адрес сайта и домен


##### Ненастравиваемый функционал:
1. Включить опцию "Для разработки"
2. Отключить резервное копирование
3. Отключить кэширование
4. Очистить кэш
5. В папку `/bitrix/php_interface` кладет файл `custom_mail.php`. Нужно его подключить, для перенаправления почты в файл
6. Деактивирует почтовые ящики