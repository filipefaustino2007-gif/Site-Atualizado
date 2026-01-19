<?php

function getAvailableLanguages(): array
{
    return [
        'pt' => 'Português ',
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
        'it' => 'Italiano',
        'nl' => 'Nederlands',
        'gr' => 'Deutsch',
        'da' => 'Dansk',
        'ge' => 'Ελληνικά',
        'fi' => 'Suomi',
        'se' => 'Svenska',
        'cz' => 'Čeština',
        'sk' => 'Slovenčina',
        'sl' => 'Slovenščina',
        'et' => 'Eesti',
        'hr' => 'Magyar',
        'lt' => 'Latviešu',
        'li' => 'Lietuvių',
        'mt' => 'Malta',
        'pl' => 'Polski',
        'bg' => 'български',
    ];
}

function getLanguageFlags(): array
{
    return [
        'pt' => '../img/portugal.svg',
        'en' => '../img/uk.svg',
        'es' => '../img/spain.svg',
        'fr' => '../img/france.svg',
        'it' => '../img/italy.svg',
        'nl' => '../img/netherlands.svg',
        'gr' => '../img/germany.svg',
        'da' => '../img/denmark.svg',
        'ge' => '../img/greece.svg',
        'fi' => '../img/finland.svg',
        'se' => '../img/sweden.svg',
        'cz' => '../img/czech.svg',
        'sk' => '../img/slovakia.svg',
        'sl' => '../img/slovenija.svg',
        'et' => '../img/estonia.svg',
        'hr' => '../img/hungary.svg',
        'lt' => '../img/latvian.svg',
        'li' => '../img/lithuania.svg',
        'mt' => '../img/malta.svg',
        'pl' => '../img/poland.svg',
        'bg' => '../img/bulgaria.svg',
    ];
}

function resolveLanguageCode(?string $requested): string
{
    $available = getAvailableLanguages();

    if ($requested !== null && array_key_exists($requested, $available)) {
        return $requested;
    }

    return 'pt';
}

function buildRedirectTarget(?string $referer): string
{
    // Fallback para a raiz do site
    if (!$referer) {
        return '/index.php';
    }

    $parts = parse_url($referer);
    $path = $parts['path'] ?? '/index.php';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';

    // Segurança: não permitimos submeter ../ no path
    if (str_contains($path, '..')) {
        return '/index.php';
    }

    // Garantir que o path começa com /
    if ($path === '' || $path === '/') {
        $target = '/index.php';
    } else {
        $target = $path;
    }

    return $target . $query;
}
