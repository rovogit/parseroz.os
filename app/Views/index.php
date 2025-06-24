<!DOCTYPE html>
<html lang="ru">
<head>
    <title>parseroz alpha</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
        input[type="text"] { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
        button { padding: 10px 20px; background: #005BFF; color: white; border: none; cursor: pointer; }
        .result { margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px; }
        .section { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .images { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
        .images img { max-width: 150px; max-height: 150px; object-fit: contain; border: 1px solid #ddd; }
        .char-list { margin: 0; padding: 0; list-style: none; }
        .char-list li { padding: 5px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
<h1>parseroz v1.0.1-alpha</h1>

<form method="post" action="/parse">
    <label for="url">URL товара:</label>
    <input type="text" id="url" name="url"
           placeholder="https://www.ozon.ru/product/123456789/"
           value="https://www.ozon.ru/product/"
           required>
    <button type="submit">Отправить запрос</button>
</form>

<?php if (isset($result)): ?>
    <div class="result">
        <?php if (isset($result['error'])): ?>
            <div class="error">
                <strong>Ошибка:</strong> <?= htmlspecialchars($result['error']) ?>
            </div>
        <?php else: ?>
            <div class="section">
                <h2><?= htmlspecialchars($result['title']) ?></h2>
                <p><strong>Категория:</strong> <?= htmlspecialchars($result['category']) ?></p>
                <p><strong>Тип:</strong> <?= htmlspecialchars($result['type']) ?></p>
                <p><strong>Страна:</strong> <?= htmlspecialchars($result['country']) ?></p>
                <p><strong>Артикул:</strong> <?= htmlspecialchars($result['sku']) ?></p>
                <p><strong>Цена:</strong> <?= htmlspecialchars($result['price']) ?></p>
            </div>

            <div class="section">
                <h3>Изображения (<?= count($result['images']) ?>)</h3>
                <div class="images">
                    <?php foreach ($result['images'] as $image): ?>
                        <a href="<?= htmlspecialchars($image) ?>">
                            <img src="<?= htmlspecialchars($image) ?>" alt="Изображение товара">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="section">
                <h3>Описание</h3>
                <p><?= nl2br(htmlspecialchars($result['description'])) ?></p>
            </div>

            <div class="section">
                <h3>Характеристики</h3>
                <ul class="char-list">
                    <?php foreach ($result['characteristics'] as $char): ?>
                        <li>
                            <strong><?= htmlspecialchars($char['name']) ?>:</strong>
                            <?= htmlspecialchars($char['value']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
</body>
</html>