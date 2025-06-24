<?php

if (!function_exists('dd')) {
    function dd(...$vars)
    {
        $backtrace = debug_backtrace();
        $caller = array_shift($backtrace);

        $typeColors = [
            'string'   => '#009900',
            'integer'  => '#0000cc',
            'double'   => '#0000cc',
            'boolean'  => '#cc0000',
            'array'    => '#990099',
            'object'   => '#ff6600',
            'NULL'     => '#666666',
            'resource' => '#ff3399',
            'unknown'  => '#333333'
        ];

        echo '<div style="background: #f5f5f5; padding: 20px; margin: 10px; border-radius: 5px; border: 1px solid #ddd; font-family: monospace; font-size: 14px;">';
        echo '<h3 style="color: #333; margin-top: 0;">Debug Dump</h3>';
        echo '<p style="color: #666;">Called in: ' . $caller['file'] . ' on line ' . $caller['line'] . '</p>';

        foreach ($vars as $var) {
            $type = gettype($var);
            $typeInfo = '';
            $color = $typeColors[$type] ?? $typeColors['unknown'];

            // Дополнительная информация
            if ($type === 'object') {
                $typeInfo = ' (class: ' . get_class($var) . ')';
                try {
                    $reflection = new ReflectionClass($var);
                    $typeInfo .= ', methods: ' . count($reflection->getMethods());
                } catch (ReflectionException $e) {
                    $typeInfo .= ', [reflection failed]';
                }
            } elseif ($type === 'array') {
                $typeInfo = ' (count: ' . count($var) . ')';
            } elseif ($type === 'resource') {
                $typeInfo = ' (type: ' . get_resource_type($var) . ')';
            }

            echo '<div style="margin-bottom: 15px;">';
            echo '<p style="margin: 0 0 5px 0; font-weight: bold; color: #555;">';
            echo 'Type: <span style="color: ' . $color . ';">' . $type . $typeInfo . '</span>';
            echo '</p>';

            echo '<pre style="background: #fff; padding: 10px; border-radius: 3px; border: 1px solid #ddd; color: #333; margin: 0; overflow: auto;">';

            // Обработка циклических ссылок
            try {
                $output = @var_export($var, true);
                if ($output === false) {
                    throw new Exception('var_export failed (likely circular reference)');
                }
                highlight_string("<?php\n" . $output);
            } catch (Exception $e) {
                // Fallback для циклических ссылок
                echo '<div style="color: #cc0000;">⚠ Could not export variable (circular reference detected)</div>';
                ob_start();
                var_dump($var);
                $dump = ob_get_clean();
                echo htmlspecialchars($dump);
            }

            echo '</pre>';
            echo '</div>';
        }

        echo '</div>';

        die(1);
    }
}