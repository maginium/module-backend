<?php

declare(strict_types=1);

use Maginium\Framework\Component\Module;

/**
 * Registers multiple modules based on a list of namespaces and their respective paths.
 *
 * This script registers each module by iterating over an associative array of module namespaces
 * and their corresponding directory paths. The `Module::register` method is called
 * for each module to register it within the application.
 *
 * @param array $extensions An associative array where each key is a fully-qualified module namespace
 *                           (e.g., 'Maginium_Framework'), and the value is the absolute file path
 *                           to the module's directory (e.g., __DIR__).
 */
$extensions = [
    // Backend modules
    'Maginium_Backend' => __DIR__ . '/Core',
    'Maginium_ScopeHint' => __DIR__ . '/ScopeHint',
    'Maginium_DisableInlineEditing' => __DIR__ . '/DisableInlineEditing',
];

// Register each module using the provided extensions list.
Module::registerModules($extensions);
