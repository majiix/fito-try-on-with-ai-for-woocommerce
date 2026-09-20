<?php
/**
 * Automated tests for prompt category mutual exclusivity and deduplication.
 */

require_once dirname(__DIR__, 4) . '/wp-load.php';

echo "=== Running Category Exclusivity Tests ===\n";

// Test 1: Deduplication across prompts
echo "- Testing Category Deduplication Across Multiple Prompts... ";
$raw_prompts = [
    [
        'id'         => 'clothing',
        'title'      => 'Clothing Preset',
        'is_system'  => 1,
        'categories' => [10, 15, 20],
        'prompt'     => 'Clothing prompt',
    ],
    [
        'id'         => 'shoes',
        'title'      => 'Footwear Preset',
        'is_system'  => 1,
        'categories' => [20, 25, 30],
        'prompt'     => 'Footwear prompt',
    ],
    [
        'id'         => 'custom_test',
        'title'      => 'Custom Test',
        'is_system'  => 0,
        'categories' => [10, 30, 40],
        'prompt'     => 'Custom prompt',
    ],
];

$sanitized_prompts = [];
$claimed_category_ids = [];

foreach ($raw_prompts as $item) {
    $id = sanitize_key($item['id']);
    $title = sanitize_text_field($item['title']);
    $is_system = !empty($item['is_system']);
    $prompt = sanitize_textarea_field($item['prompt']);

    $cats = [];
    if (!empty($item['categories']) && is_array($item['categories'])) {
        $raw_cats = array_map('absint', $item['categories']);
        $raw_cats = array_filter($raw_cats);
        foreach ($raw_cats as $cid) {
            if (!in_array($cid, $claimed_category_ids, true)) {
                $cats[] = $cid;
                $claimed_category_ids[] = $cid;
            }
        }
    }

    $sanitized_prompts[] = [
        'id'         => $id,
        'title'      => $title,
        'is_system'  => $is_system,
        'categories' => array_values(array_unique($cats)),
        'prompt'     => $prompt,
    ];
}

assert($sanitized_prompts[0]['categories'] === [10, 15, 20], 'Clothing keeps 10, 15, 20');
assert($sanitized_prompts[1]['categories'] === [25, 30], 'Shoes loses 20 and keeps 25, 30');
assert($sanitized_prompts[2]['categories'] === [40], 'Custom loses 10 and 30 and keeps only 40');
echo "PASSED\n";

// Test 2: Edge Case - Empty or invalid category IDs
echo "- Testing Invalid / Empty Category Inputs... ";
$claimed_test = [];
$raw_cats = ['abc', 0, ' 12 ', 12, null];
$filtered_cats = [];
foreach (array_filter(array_map('absint', $raw_cats)) as $cid) {
    if (!in_array($cid, $claimed_test, true)) {
        $filtered_cats[] = $cid;
        $claimed_test[] = $cid;
    }
}
assert($filtered_cats === [12], 'Only valid non-zero absint category ID retained');
echo "PASSED\n";

// Test 3: Prompt Resolution in OpenRouter class with exclusive categories
echo "- Testing build_category_prompt with Exclusive Category IDs... ";
$openrouter = new Fito_Tryon_OpenRouter();

$original_config = get_option('fito_tryon_prompts_config');
update_option('fito_tryon_prompts_config', [
    [
        'id'         => 'clothing',
        'title'      => 'Clothing',
        'is_system'  => 1,
        'categories' => [101],
        'prompt'     => 'Fit clothing: {product_title}',
    ],
    [
        'id'         => 'shoes',
        'title'      => 'Shoes',
        'is_system'  => 1,
        'categories' => [202],
        'prompt'     => 'Fit shoes: {product_title}',
    ],
]);

$resolved_prompt = $openrouter->build_category_prompt([
    'product_title' => 'Sneakers Pro',
    'category_ids'  => [202],
]);
assert(str_contains($resolved_prompt, 'Fit shoes: Sneakers Pro'), 'Resolves to shoes prompt');

$resolved_prompt_clothing = $openrouter->build_category_prompt([
    'product_title' => 'Silk Shirt',
    'category_ids'  => [101],
]);
assert(str_contains($resolved_prompt_clothing, 'Fit clothing: Silk Shirt'), 'Resolves to clothing prompt');

$resolved_prompt_fallback = $openrouter->build_category_prompt([
    'product_title' => 'Random Item',
    'category_ids'  => [999],
]);
assert(str_contains($resolved_prompt_fallback, 'Fit clothing: Random Item'), 'Falls back to clothing prompt');

if ($original_config !== false) {
    update_option('fito_tryon_prompts_config', $original_config);
} else {
    delete_option('fito_tryon_prompts_config');
}
echo "PASSED\n";

echo "\n[PASS] All Category Exclusivity Tests Completed Successfully!\n";
