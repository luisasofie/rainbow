<?php

declare(strict_types=1);

namespace LuisaSofie\Rainbow\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Injects a per-user primary colour into every backend page.
 *
 * Runs after authentication so $GLOBALS['BE_USER'] is available.
 * Adds an inline <style> block that overrides the three CSS custom
 * properties rainbow.css sets globally, giving each user their own theme.
 *
 * Before using the chosen colour it is passed through ensureAccessible(),
 * which darkens it (HSL lightness only, hue and saturation are preserved)
 * until it achieves WCAG AA 4.5:1 contrast against white.  This guarantees
 * button labels remain readable regardless of what the user picks.
 *
 * Uses GeneralUtility::makeInstance() instead of constructor injection so
 * that the class does not need to be registered in the Symfony DI container.
 * This avoids a chicken-and-egg problem during composer dump-autoload where
 * the DI scanner calls class_exists() before the autoload map is written.
 */
final class UserColorMiddleware implements MiddlewareInterface
{
    /** WCAG AA minimum contrast ratio for text on a coloured background. */
    private const WCAG_AA = 4.5;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $color = trim((string)(($GLOBALS['BE_USER'] ?? null)?->uc['rainbow_primaryColor'] ?? ''));

        if ($this->isValidHexColor($color)) {
            $accessible = $this->ensureAccessible($color);
            GeneralUtility::makeInstance(PageRenderer::class)->addCssInlineBlock(
                'rainbow-user-color',
                $this->buildCss($accessible),
                null,
                false,
                true
            );
        }

        return $handler->handle($request);
    }

    // -------------------------------------------------------------------------
    // Accessibility
    // -------------------------------------------------------------------------

    /**
     * Returns the colour unchanged if it already achieves WCAG_AA contrast
     * against white (#fff).  Otherwise reduces HSL lightness via binary search
     * until it does, then returns the darkened hex value.
     *
     * Hue and saturation are always preserved so the colour "feels" the same.
     */
    private function ensureAccessible(string $hex): string
    {
        [$r, $g, $b] = $this->hexToRgb($hex);

        if ($this->contrastWithWhite($r, $g, $b) >= self::WCAG_AA) {
            return $hex;
        }

        [$h, $s, $l] = $this->rgbToHsl($r, $g, $b);

        // Binary-search for the highest lightness that still passes WCAG AA.
        $lo = 0.0;
        $hi = $l;
        for ($i = 0; $i < 24; $i++) {
            $mid = ($lo + $hi) / 2;
            [$r2, $g2, $b2] = $this->hslToRgb($h, $s, $mid);
            if ($this->contrastWithWhite($r2, $g2, $b2) >= self::WCAG_AA) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }

        [$r2, $g2, $b2] = $this->hslToRgb($h, $s, $lo);
        return sprintf('#%02x%02x%02x',
            (int)round($r2 * 255),
            (int)round($g2 * 255),
            (int)round($b2 * 255)
        );
    }

    /**
     * WCAG relative luminance of an sRGB triplet (values 0–1).
     * https://www.w3.org/TR/WCAG21/#dfn-relative-luminance
     */
    private function luminance(float $r, float $g, float $b): float
    {
        $lin = static function (float $c): float {
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
    }

    /** Contrast ratio between the given colour and pure white. */
    private function contrastWithWhite(float $r, float $g, float $b): float
    {
        return 1.05 / ($this->luminance($r, $g, $b) + 0.05);
    }

    // -------------------------------------------------------------------------
    // Colour-space helpers
    // -------------------------------------------------------------------------

    /** Hex string (#RGB or #RRGGBB) → [r, g, b] each in range 0–1. */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    /** RGB (0–1) → [h, s, l] each in range 0–1. */
    private function rgbToHsl(float $r, float $g, float $b): array
    {
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l   = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r      => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $g      => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h / 6, $s, $l];
    }

    /** HSL (0–1) → [r, g, b] each in range 0–1. */
    private function hslToRgb(float $h, float $s, float $l): array
    {
        if ($s === 0.0) {
            return [$l, $l, $l];
        }

        $q   = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p   = 2 * $l - $q;
        $hue = static function (float $t) use ($p, $q): float {
            if ($t < 0) {
                $t += 1;
            }
            if ($t > 1) {
                $t -= 1;
            }
            if ($t < 1 / 6) {
                return $p + ($q - $p) * 6 * $t;
            }
            if ($t < 1 / 2) {
                return $q;
            }
            if ($t < 2 / 3) {
                return $p + ($q - $p) * (2 / 3 - $t) * 6;
            }
            return $p;
        };

        return [$hue($h + 1 / 3), $hue($h), $hue($h - 1 / 3)];
    }

    // -------------------------------------------------------------------------
    // Validation & CSS generation
    // -------------------------------------------------------------------------

    /** Accepts #RGB and #RRGGBB (no alpha). */
    private function isValidHexColor(string $color): bool
    {
        return (bool)preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $color);
    }

    /**
     * Builds the inline CSS block.
     *
     * hsl(from <color> h calc(s * .47) 50%)  →  neutral surface base
     * hsl(from <color> h calc(s * .88) 38%)  →  sidebar / topbar background
     */
    private function buildCss(string $color): string
    {
        $c = htmlspecialchars($color, ENT_QUOTES, 'UTF-8');
        return <<<CSS
            :root {
                --token-color-primary-base: {$c};
                --token-color-neutral-base: hsl(from {$c} h calc(s * .47) 50%);
                --typo3-scaffold-header-bg: hsl(from {$c} h calc(s * .88) 38%);
                --typo3-scaffold-sidebar-bg: hsl(from {$c} h calc(s * .88) 38%);
                --bs-focus-ring-color: color-mix(in srgb, {$c}, transparent 75%);
            }
            .typo3-login {
                --typo3-login-highlight: {$c};
                --typo3-login-bg: color-mix(in srgb, {$c}, white 93%);
                --typo3-login-btn-bg: {$c};
                --typo3-login-btn-color: #fff;
            }
            CSS;
    }
}
