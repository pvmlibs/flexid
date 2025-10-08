<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$config = new Config();

return $config
    ->setParallelConfig(new PhpCsFixer\Runner\Parallel\ParallelConfig(6, 20))
    ->setRiskyAllowed(true)
    ->setRules([
        '@PhpCsFixer' => true,
        '@PSR12' => true,
        '@Symfony' => true,

        // Migrations
        '@PHP81Migration' => true,
        '@PHPUnit100Migration:risky' => true,

        // Custom
        'phpdoc_array_type' => true,
        'no_unused_imports' => true,
        'single_space_around_construct' => true,
        'control_structure_braces' => true,
        'control_structure_continuation_position' => true,
        'declare_parentheses' => true,
        'no_multiple_statements_per_line' => true,
        'braces_position' => true,
        'statement_indentation' => true,
        'no_extra_blank_lines' => true,
        'concat_space' => ['spacing' => 'one'],
        'type_declaration_spaces' => true,
        'heredoc_to_nowdoc' => true,
        'protected_to_private' => true,
        'increment_style' => ['style' => 'post'],
        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'no_multi_line',
        ],
        'class_attributes_separation' => [
            'elements' => [
                'trait_import' => 'none',
            ],
        ],
        'trailing_comma_in_multiline' => [
            'after_heredoc' => true, 'elements' => ['arguments', 'array_destructuring', 'arrays', 'match', 'parameters'],
        ],
        'normalize_index_brace' => true,
        'spaces_inside_parentheses' => true,
        'not_operator_with_successor_space' => true,
        'single_line_comment_style' => [
            'comment_types' => ['hash'],
        ],
        'yoda_style' => false,
        'use_arrow_functions' => true,
        'void_return' => true,
        'php_unit_test_class_requires_covers' => false,
    ])
    ->setFinder(
        Finder::create()
            ->in(__DIR__ . '/src')
            ->in(__DIR__ . '/tests'),
    )
    ->setCacheFile(__DIR__ . '/cache/php-cs-fixer/php_cs.cache');
