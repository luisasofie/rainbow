# EXT:rainbow - Make the TYPO3 backend yours! 

## Features

- Replaces the default TYPO3 backend blue with a default, vibrant hot-pink colour scheme
- Colours the topbar and sidebar in a vivid rose pink
- Tints every content area, panel, card, and context menu in soft blush pink
- Styles the login screen — button, highlight, and panel background — in matching pink
- Works in both light and dark mode (dark mode surfaces render as warm plum)
- No configuration, no database records — install and it just works
- Enables you to choose your prefered color for the backend layout

## How it Works

The TYPO3 14 backend colour system is driven by two root CSS custom properties:

- `--token-color-primary-base` — source of all primary-derived colours (buttons, badges, active states, focus rings, and the scaffold header/sidebar gradient)
- `--token-color-neutral-base` — source of the full neutral scale from which every surface colour is derived via `hsl(from <base> h s <lightness>%)`

EXT:rainbow overrides both at `:root` level and lets the cascade do the rest. No individual component colours are patched.

## Installation

Install via Composer:

```bash
composer req luisasofie/rainbow
```

Then activate the extension:

```bash
vendor/bin/typo3 extension:activate rainbow
```

Or install and activate via the TYPO3 Extension Manager.

## Requirements

- TYPO3 14.x
- `typo3/cms-backend`
- `typo3/cms-core`

## Credits

This extension was created by Luisa Sofie Faßbender in 2026.

[Find more TYPO3 extensions](https://extensions.typo3.org) that help deliver value in TYPO3 projects.
