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
 * Uses GeneralUtility::makeInstance() instead of constructor injection so
 * that the class does not need to be registered in the Symfony DI container.
 * This avoids a chicken-and-egg problem during composer dump-autoload where
 * the DI scanner calls class_exists() before the autoload map is written.
 */
final class UserColorMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $color = trim((string)(($GLOBALS['BE_USER'] ?? null)?->uc['rainbow_primaryColor'] ?? ''));

        if ($this->isValidHexColor($color)) {
            GeneralUtility::makeInstance(PageRenderer::class)->addCssInlineBlock(
                'rainbow-user-color',
                $this->buildCss($color),
                null,
                false,
                true
            );
        }

        return $handler->handle($request);
    }

    /**
     * Accepts #RGB and #RRGGBB (no alpha).
     */
    private function isValidHexColor(string $color): bool
    {
        return (bool)preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $color);
    }

    /**
     * Builds the inline CSS using the same three overrides as rainbow.css,
     * but driven by the user-chosen hex colour.
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
