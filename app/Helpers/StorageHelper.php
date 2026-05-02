<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class StorageHelper
{
    /**
     * Verificar se é URL externa
     */
    private static function isExternalUrl($url): bool
    {
        if (empty($url)) return false;
        // Verifica se começa com http:// ou https://
        return preg_match('/^https?:\/\//', $url) === 1;
    }

    /**
     * Gerar URL correta para qualquer arquivo
     */
    public static function url($path)
    {
        if (empty($path)) {
            return null;
        }

        // ✅ Se for URL externa, retorna como está
        if (self::isExternalUrl($path)) {
            return $path;
        }

        // ✅ Se for caminho local (starts with storage/ ou fotos/)
        $cleanPath = ltrim($path, '/');

        // Remove 'storage/' do início se existir
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        }

        // Verificar se o arquivo existe no storage público
        if (Storage::disk('public')->exists($cleanPath)) {
            return asset('storage/' . $cleanPath);
        }

        // Fallback: avatar com iniciais via UI Avatars
        return null;
    }

    /**
     * Processar array de portfolio
     */
    public static function processPortfolio($portfolio)
    {
        if (empty($portfolio) || !is_array($portfolio)) {
            return [];
        }

        return array_map([self::class, 'url'], $portfolio);
    }

    /**
     * Processar foto de perfil
     */
    public static function processFoto($foto)
    {
        return self::url($foto);
    }
}
