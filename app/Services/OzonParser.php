<?php

namespace App\Services;

use Exception;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\DomCrawler\Crawler;

class OzonParser
{
    private Client $client;
    private ?string $sku;

    public function __construct(string $url)
    {
        $this->sku = $this->extractSkuFromUrl($url);
        if (!$this->sku) {
            throw new Exception("Неверный URL Ozon. Пример правильного URL: https://www.ozon.ru/product/12345678/");
        }

        $chromeDriverPath = realpath(__DIR__ . '/../../chromedriver/win/chromedriver.exe');
        if (!$chromeDriverPath) {
            throw new Exception("ChromeDriver не найден. Убедитесь, что файл chromedriver.exe находится по указанному пути");
        }

        $this->client = Client::createChromeClient(
            $chromeDriverPath,
            [
                '--headless=new',
                '--disable-blink-features=AutomationControlled',
                '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ],
            [
                'webServerDir'             => __DIR__ . '/../../public',
                'connection_timeout_in_ms' => 30000,
                'request_timeout_in_ms'    => 60000
            ]
        );
    }

    public function parse(): array
    {
        try {
            $url = "https://www.ozon.ru/product/{$this->sku}/";
            $this->client->request('GET', $url);

            // Основные ожидания
            $this->client->waitForVisibility('[data-widget="webProductHeading"]', 10);
            $this->client->waitForVisibility('[data-widget="webPrice"]', 5);
            $this->scrollPage();

            // Пытаемся найти и кликнуть кнопку "Перейти к описанию"
            $this->clickDescriptionButton();

            // Дополнительное ожидание для описания и характеристик
            try {
                $this->client->waitForVisibility('[data-widget="webDescription"]', 5);
                $this->client->waitForVisibility('[data-widget="webCharacteristics"]', 5);
            } catch (Exception $e) {
                // Продолжаем, даже если не все элементы загрузились
            }

            $crawler = $this->client->getCrawler();

            return [
                'title'           => $this->extractTitle($crawler),
                'category'        => $this->extractCategory($crawler),
                'type'            => $this->extractType($crawler),
                'country'         => $this->extractCountry($crawler),
                'article'         => $this->extractArticle($crawler),
                'price'           => $this->extractPrice($crawler),
                'images'          => $this->extractImages($crawler),
                'description'     => $this->extractDescription($crawler),
                'characteristics' => $this->extractCharacteristics($crawler),
                'sku'             => $this->sku,
                'url'             => $url
            ];
        } catch (Exception $e) {
            throw new Exception("Ошибка парсинга: " . $e->getMessage());
        } finally {
            $this->client->quit();
        }
    }

    private function clickDescriptionButton(): void
    {
        try {
            // Ждем появления блока с короткими характеристиками
            $this->client->waitForVisibility('[data-widget="webShortCharacteristics"]', 5);

            // Прокручиваем к блоку, чтобы кнопка была видимой
            $this->client->executeScript('document.querySelector(\'[data-widget="webShortCharacteristics"]\').scrollIntoView()');
            sleep(1);

            // Ищем кнопку "Перейти к описанию" и кликаем
            $buttonSelector = '[data-widget="webShortCharacteristics"] [title="Перейти к описанию"]';
            if ($this->client->getCrawler()->filter($buttonSelector)->count() > 0) {
                $this->client->getMouse()->clickTo($buttonSelector);
                sleep(2); // Даем время на загрузку контента после клика
            }
        } catch (Exception $e) {
            // Если не удалось кликнуть, продолжаем без этого
        }
    }

    private function clickDescriptionTab(Crawler $crawler): void
    {
        try {
            $tabSelectors = [
                'button[aria-label="Описание"]',
                'button:contains("Описание")',
                '.tabs-item:contains("Описание")',
                'a[href*="description"]'
            ];

            foreach ($tabSelectors as $selector) {
                if ($crawler->filter($selector)->count() > 0) {
                    $this->client->getMouse()->clickTo($selector);
                    sleep(2); // Даем время на загрузку
                    break;
                }
            }
        } catch (Exception $e) {
            // Если не удалось кликнуть, продолжаем
        }
    }

    private function cleanHtmlToText(string $html): string
    {
        // Удаляем скрипты и стили
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // Заменяем теги br на переносы строк
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

        // Удаляем все остальные теги
        $text = strip_tags($html);

        // Удаляем лишние пробелы и переносы
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Удаляем технические тексты
        $text = preg_replace('/Скрыть (описание|характеристики)/i', '', $text);

        return $text;
    }

    private function extractSkuFromUrl(string $url): ?string
    {
        if (preg_match('/ozon\.ru\/product\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function scrollPage(): void
    {
        $scrollScript = <<<JS
        window.scrollTo(0, document.body.scrollHeight / 3);
        setTimeout(() => window.scrollTo(0, document.body.scrollHeight * 2/3), 800);
        setTimeout(() => window.scrollTo(0, document.body.scrollHeight), 1600);
        JS;
        $this->client->executeScript($scrollScript);
        sleep(2);
    }

    private function extractTitle(Crawler $crawler): string
    {
        $selectors = [
            '[data-widget="webProductHeading"] h1',
            'h1[slot="title"]',
            'h1.tsHeadline500Medium',
            'h1'
        ];

        foreach ($selectors as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                return trim($crawler->filter($selector)->text());
            }
        }

        return 'Название не найдено';
    }

    private function extractPrice(Crawler $crawler): string
    {
        try {
            // Основные селекторы для цены с Ozon Картой
            $selectors = [
                '[data-widget="webPrice"] .m8o_27', // Новый селектор для цены с картой
                '[data-widget="webPrice"] [class*="price"]', // Общий селектор
                '.o9m_27 .m8o_27', // Альтернативный путь
                '.webPrice .final-price' // Резервный вариант
            ];

            foreach ($selectors as $selector) {
                if ($crawler->filter($selector)->count() > 0) {
                    $price = trim($crawler->filter($selector)->text());
                    // Очищаем цену от лишних символов
                    $price = preg_replace('/[^\d]/u', '', $price);
                    return $price ? $price . ' ₽' : 'Цена не найдена';
                }
            }

            // Альтернативный поиск через JavaScript
            $jsPrice = $this->client->executeScript('
            const priceElement = document.querySelector(\'[data-widget="webPrice"] .m8o_27\') ||
                                document.querySelector(\'.o9m_27 .m8o_27\');
            return priceElement ? priceElement.innerText.trim() : "";
        ');

            if (!empty($jsPrice)) {
                $jsPrice = preg_replace('/[^\d]/u', '', $jsPrice);
                return $jsPrice ? $jsPrice . ' ₽' : 'Цена не найдена';
            }

            // Если не нашли цену с картой, попробуем найти любую цену
            if ($crawler->filter('[data-widget="webPrice"]')->count() > 0) {
                $priceText = $crawler->filter('[data-widget="webPrice"]')->text();
                if (preg_match('/\d+[\s ]*₽/u', $priceText, $matches)) {
                    return trim(str_replace(' ', ' ', $matches[0]));
                }
            }
        } catch (Exception $e) {
            // Логирование ошибки
        }

        return 'Цена не найдена';
    }

    private function extractCategory(Crawler $crawler): string
    {
        try {
            if ($crawler->filter('[data-widget="breadCrumbs"]')->count() > 0) {
                $categories = $crawler->filter('[data-widget="breadCrumbs"] a')->each(function (Crawler $node) {
                    return trim($node->text());
                });

                // Фильтруем пустые элементы и преобразуем массив в строку
                $filteredCategories = array_filter($categories, function ($item) {
                    return !empty($item) && strtolower($item) !== 'главная';
                });

                return implode(' > ', $filteredCategories);
            }
        } catch (Exception $e) {
            // Логируем ошибку при необходимости
        }

        return 'Категория не найдена';
    }

    private function extractType(Crawler $crawler): string
    {
        $characteristics = $this->extractCharacteristics($crawler);
        foreach ($characteristics as $char) {
            if (mb_stripos($char['name'], 'тип') !== false) {
                return $char['value'];
            }
        }

        return 'Тип не указан';
    }

    private function extractCountry(Crawler $crawler): string
    {
        $characteristics = $this->extractCharacteristics($crawler);
        foreach ($characteristics as $char) {
            if (mb_stripos($char['name'], 'страна') !== false) {
                return $char['value'];
            }
        }

        return 'Страна не указана';
    }

    private function extractArticle(Crawler $crawler): string
    {
        if ($crawler->filter('[data-widget="webDetailSKU"]')->count() > 0) {
            $text = $crawler->filter('[data-widget="webDetailSKU"]')->text();
            if (preg_match('/\d+/', $text, $matches)) {
                return $matches[0];
            }
        }

        return $this->sku; // Возвращаем SKU из URL, если не нашли артикул
    }

    private function extractDescription(Crawler $crawler): string
    {
        try {
            // 1. Попробуем кликнуть на таб "Описание", если есть
            $this->clickDescriptionTab($crawler);

            // 2. Основные селекторы
            $mainSelectors = [
                '[data-widget="webDescription"]',
                '[data-widget="webFullDescription"]',
                '.description-text',
                '#section-description',
                '.product-description',
                '.item-description-text',
                '.ozon-desc'
            ];

            foreach ($mainSelectors as $selector) {
                if ($crawler->filter($selector)->count() > 0) {
                    $html = $crawler->filter($selector)->html();
                    $text = $this->cleanHtmlToText($html);
                    if (!empty($text)) {
                        return $text;
                    }
                }
            }

            // 3. Поиск по структуре страницы (новый дизайн Ozon)
            if ($crawler->filter('.vk5_27')->count() > 0) {
                $characteristics = $crawler->filter('.vk5_27')->first()->html();
                if (strpos($characteristics, 'Описание') !== false) {
                    $text = $this->cleanHtmlToText($characteristics);
                    if (!empty($text)) {
                        return $text;
                    }
                }
            }

            // 4. Поиск по тексту на странице
            $pageText = $crawler->filter('body')->text();
            if (preg_match('/Описание(.+?)(Характеристики|Отзывы|$)/s', $pageText, $matches)) {
                $text = trim(preg_replace('/\s+/', ' ', $matches[1]));
                if (!empty($text)) {
                    return $text;
                }
            }

            // 5. Альтернативный поиск через JavaScript
            $jsDescription = $this->client->executeScript('
            const descElement = document.querySelector(\'[data-widget="webDescription"]\') || 
                              document.querySelector(\'.description-text\');
            return descElement ? descElement.innerText : "";
        ');

            if (!empty(trim($jsDescription))) {
                return trim($jsDescription);
            }
        } catch (Exception $e) {
            // Ошибки
        }

        return 'Описание не найдено';
    }

    private function extractCharacteristics(Crawler $crawler): array
    {
        $characteristics = [];
        $selectors = [
            '[data-widget="webCharacteristics"] .vk5_27', // Новый дизайн
            '.characteristics .e1p3',                     // Старый дизайн
            '.ui-p6 .ui-p7'                              // Альтернативный вариант
        ];

        foreach ($selectors as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                $crawler->filter($selector)->each(function (Crawler $node) use (&$characteristics) {
                    try {
                        $name = $node->filter('dt')->text('');
                        $value = $node->filter('dd')->text('');
                        if ($name && $value) {
                            $characteristics[] = [
                                'name'  => trim($name),
                                'value' => trim($value)
                            ];
                        }
                    } catch (Exception $e) {
                        // Пропускаем проблемные элементы
                    }
                });
                break;
            }
        }

        return $characteristics;
    }

    private function extractImages(Crawler $crawler): array
    {
        $images = [];
        try {
            $this->client->waitForVisibility('[data-widget="webGallery"] img, .ui-p0 img, .gallery img', 5);
            sleep(1); // Дополнительное время для загрузки изображений

            $selectors = [
                '[data-widget="webGallery"] img[src]',
                '.ui-p0 img[src]',
                '.gallery img[src]'
            ];

            foreach ($selectors as $selector) {
                if ($crawler->filter($selector)->count() > 0) {
                    $crawler->filter($selector)->each(function (Crawler $node) use (&$images) {
                        $src = $node->attr('src');
                        if ($src) {
                            // Корректная замена размера изображения
                            $src = str_replace(['wc50', 'wc100', 'wc1000', 'wc10000'], 'wc1000', $src);
                            $images[] = $src;
                        }
                    });
                    break;
                }
            }
        } catch (Exception $e) {
            // Ошибки
        }

        return array_values(array_unique(array_filter($images)));
    }
}