<?php

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests']);

return PhpCsFixer\Config::create()->setRules([
    '@PSR2' => true,
    'no_whitespace_before_comma_in_array' => true,
    'whitespace_after_comma_in_array' => true,
    'blank_line_after_opening_tag' => true,
    'no_empty_statement' => true,
    'simplified_null_return' => false,
    'no_extra_consecutive_blank_lines' => true,
    'function_typehint_space' => true,
    'new_with_braces' => true,
    'no_blank_lines_after_phpdoc' => true,
    'phpdoc_indent' => true,
    'phpdoc_align' => true,
    'no_mixed_echo_print' => true,
    'self_accessor' => true,
    'no_trailing_comma_in_singleline_array' => true,
    'single_quote' => true,
    'single_blank_line_before_namespace' => true,
    'cast_spaces' => true,
    'no_unused_imports' => true,
    'no_unneeded_control_parentheses' => true,
    'ordered_imports' => true,
    'no_short_echo_tag' => true,
    'return_type_declaration' => true,
    'array_syntax' => ['syntax' => 'short'],
    'header_comment' => ['header' => '', 'location' => 'after_open'],
    'dir_constant' => true,
    'hash_to_slash_comment' => true,
    'no_useless_else' => true,
    'short_scalar_cast' => true,
    'method_separation' => true,
    'phpdoc_scalar' => true,
    'ternary_to_null_coalescing' => true
//    'protected_to_private' => true,
])->setFinder($finder)->setRiskyAllowed(true);
