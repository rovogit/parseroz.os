# parseroz v1.0.1-alpha

Получение данных о товарах Ozon с использованием Symfony Panther.

## Требования

- PHP 8.0+
- Composer
- ChromeDriver
- Symfony Panther

## Установка

1. Установите зависимости через Composer
2. В классе OzonParser укажите правильный путь к $chromeDriverPath:

    - Windows
     '/../../chromedriver/win/chromedriver.exe'

    - Linux
    '/../../chromedriver/linux/chromedriver'
3. Доступный url для отображения формы запроса - /