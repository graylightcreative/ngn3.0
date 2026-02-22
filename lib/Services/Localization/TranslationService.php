<?php
namespace NGN\Lib\Services\Localization;

/**
 * Translation Service - NGN 3.0 Global Reach
 * Handles dynamic i18n translation and locale management.
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class TranslationService
{
    private $pdo;
    private $locale;
    private $translations = [];

    public function __construct(Config $config, string $locale = 'en')
    {
        $this->pdo = ConnectionFactory::read($config);
        $this->locale = $locale;
        $this->loadTranslations();
    }

    /**
     * Get Translated String
     */
    public function t(string $key, array $placeholders = []): string
    {
        $text = $this->translations[$key] ?? $key;
        
        foreach ($placeholders as $k => $v) {
            $text = str_replace("{{$k}}", $v, $text);
        }
        
        return $text;
    }

    /**
     * Set Current Locale
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
        $this->loadTranslations();
    }

    private function loadTranslations(): void
    {
        // Future: Load from database or JSON files per locale
        $this->translations = [
            'en' => [
                'welcome' => 'Welcome to the Independent Empire',
                'payout_ready' => 'Your royalty payout of {amount} is ready.'
            ],
            'es' => [
                'welcome' => 'Bienvenido al Imperio Independiente',
                'payout_ready' => 'Tu pago de regalías de {amount} está listo.'
            ]
        ][$this->locale] ?? [];
    }
}
