<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addUserSetting(
    'rainbow_primaryColor',
    [
        'label' => 'LLL:EXT:rainbow/Resources/Private/Language/locallang.xlf:user_settings.rainbow_primaryColor',
        'config' => [
            'type' => 'color',
        ],
    ],
    'after:colorScheme'
);
