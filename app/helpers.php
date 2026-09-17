<?php

if (! function_exists('currency_symbol')) {
    function currency_symbol(): string
    {
        return config('currency.symbol', '₨');
    }
}

if (! function_exists('currency_code')) {
    function currency_code(): string
    {
        return config('currency.code', 'PKR');
    }
}

if (! function_exists('money')) {
    /**
     * Format an amount with the system currency symbol (₨).
     */
    function money(float|int|string|null $amount, int $decimals = 2): string
    {
        if ($amount === null || $amount === '') {
            return currency_symbol() . '0' . ($decimals > 0 ? '.' . str_repeat('0', $decimals) : '');
        }

        return currency_symbol() . number_format((float) $amount, $decimals);
    }
}

if (! function_exists('media_url')) {
    /**
     * Resolve a stored public disk path or absolute URL for display.
     */
    function media_url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}

if (! function_exists('voice_asset')) {
    /**
     * Public voice file URL with cache-busting query (desktop + web toast).
     */
    function voice_asset(string $file): string
    {
        $file = ltrim($file, '/');
        if (! str_starts_with($file, 'voice/')) {
            $file = 'voice/'.$file;
        }

        $path = public_path($file);
        $version = is_file($path) ? (string) filemtime($path) : (string) time();

        return asset($file).'?v='.$version;
    }
}

if (! function_exists('percent_change')) {
    /**
     * Percent change between two numbers; null when prior is zero and current is zero.
     */
    function percent_change(float|int $current, float|int $previous): ?float
    {
        $current = (float) $current;
        $previous = (float) $previous;

        if ($previous == 0.0) {
            return $current == 0.0 ? 0.0 : 100.0;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }
}
