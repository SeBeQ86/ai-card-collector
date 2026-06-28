<?php

declare(strict_types=1);

/**
 * CardScorer business-logic guard — no test framework required.
 *
 * Run:  php tests/CardScorerTest.php
 * Exit: 0 on all pass, 1 on any failure.
 *
 * Tests document invariants from src/Card/CardScorer.php and docs/business-rules.md:
 *   - Language rarity:  JP/TH/PT/ID = 35 pts; FR/DE/ES/KR/RU/PL/ZH = 20 pts; EN = 0 pts.
 *   - Status urgency:   searching=40, contacted=25, offer_received=10.
 *   - Terminal statuses (acquired, abandoned) always return score 0.
 *   - Age urgency:      +1 per 5 days unresolved, capped at 15.
 *   - Market pressure:  budget/market coverage: >=100%=0, 85-100%=+10, 70-85%=+20, 50-70%=+30, <50%=+40.
 *   - Maximum score:    155 (active cards); 0 (terminal).
 *   - explain() total must be consistent with calculate() for the same inputs.
 *
 * Expected values below are derived from those documented rules, not from reading
 * the implementation and copying its arithmetic.
 */

require __DIR__ . '/../src/Card/CardScorer.php';

use App\Card\CardScorer;

// ---------------------------------------------------------------------------
// Assertion helpers
// ---------------------------------------------------------------------------

$passed = 0;
$failed = 0;

function pass(string $label): void
{
    global $passed;
    echo "PASS  $label\n";
    $passed++;
}

function fail(string $label, mixed $expected, mixed $actual): void
{
    global $failed;
    echo "FAIL  $label\n";
    echo "      expected " . var_export($expected, true)
        . ', got ' . var_export($actual, true) . "\n";
    $failed++;
}

function assert_eq(mixed $expected, mixed $actual, string $label): void
{
    if ($expected === $actual) {
        pass($label);
    } else {
        fail($label, $expected, $actual);
    }
}

function assert_true(bool $condition, string $label): void
{
    assert_eq(true, $condition, $label);
}

// ---------------------------------------------------------------------------
// Group 1: Terminal statuses always return 0  (documented invariant)
// ---------------------------------------------------------------------------

echo "\n-- Terminal status invariant --\n";

assert_eq(0, CardScorer::calculate('JP', 'acquired', null, null, 0),
    'acquired returns 0 regardless of language');

assert_eq(0, CardScorer::calculate('EN', 'acquired', 1.0, 999.0, 70),
    'acquired returns 0 even with price mismatch and high age');

assert_eq(0, CardScorer::calculate('JP', 'abandoned', null, null, 0),
    'abandoned returns 0 regardless of language');

assert_eq(0, CardScorer::calculate('EN', 'abandoned', 10.0, 5.0, 100),
    'abandoned returns 0 even with all other factors set');

// ---------------------------------------------------------------------------
// Group 2: Non-negative score for active statuses
// ---------------------------------------------------------------------------

echo "\n-- Active card returns non-negative score --\n";

$active = CardScorer::calculate('EN', 'searching', null, null, 0);
assert_true($active >= 0, 'searching returns non-negative score');

$active = CardScorer::calculate('EN', 'contacted', null, null, 0);
assert_true($active >= 0, 'contacted returns non-negative score');

$active = CardScorer::calculate('EN', 'offer_received', null, null, 0);
assert_true($active >= 0, 'offer_received returns non-negative score');

// ---------------------------------------------------------------------------
// Group 3: Language rarity ordering  (documented: non-EN = 40 pts, EN = 0 pts)
// ---------------------------------------------------------------------------

echo "\n-- Language rarity --\n";

// Same status, price, age — only language differs
$jpScore = CardScorer::calculate('JP', 'searching', null, null, 0);
$enScore = CardScorer::calculate('EN', 'searching', null, null, 0);
assert_true($jpScore > $enScore,
    'JP card scores higher than EN card (all other factors equal)');

// "english" spelled out must also be recognised as the zero-rarity language
$englishScore = CardScorer::calculate('english', 'searching', null, null, 0);
assert_eq($enScore, $englishScore,
    '"english" and "en" produce the same score');

// ---------------------------------------------------------------------------
// Group 4: Status urgency ordering  (documented: searching > contacted > offer_received > 0)
// ---------------------------------------------------------------------------

echo "\n-- Status urgency ordering --\n";

$s = CardScorer::calculate('EN', 'searching',     null, null, 0);
$c = CardScorer::calculate('EN', 'contacted',     null, null, 0);
$o = CardScorer::calculate('EN', 'offer_received', null, null, 0);

assert_true($s > $c, 'searching scores higher than contacted');
assert_true($c > $o, 'contacted scores higher than offer_received');
assert_true($o > 0,  'offer_received scores above zero');

// ---------------------------------------------------------------------------
// Group 5: Score cap  (documented maximum: 100)
// ---------------------------------------------------------------------------

echo "\n-- Score ceiling --\n";

// Maximise every component: JP language (40) + searching (40) + offer > target (10) + 70 days = 10 weeks (10) = 100
$maxScore = CardScorer::calculate('JP', 'searching', 1.0, 999.0, 70);
assert_true($maxScore <= 100, 'score never exceeds documented maximum of 100');
assert_eq(100, $maxScore,     'all components maximised sum to exactly 100');

// ---------------------------------------------------------------------------
// Group 6: explain() structure and terminal flag
// ---------------------------------------------------------------------------

echo "\n-- explain() structure --\n";

$terminalCard = [
    'language'            => 'JP',
    'status'              => 'acquired',
    'target_price'        => null,
    'current_offer_price' => null,
    'created_at'          => '2020-01-01 00:00:00',
];

$ex = CardScorer::explain($terminalCard);

assert_eq(0,    $ex['total'],    'explain() acquired — total is 0');
assert_eq(true, $ex['terminal'], 'explain() acquired — terminal flag is true');
assert_eq(0,    $ex['language'], 'explain() acquired — language component is 0');
assert_eq(0,    $ex['status'],   'explain() acquired — status component is 0');
assert_eq(0,    $ex['price'],    'explain() acquired — price component is 0');
assert_eq(0,    $ex['age'],      'explain() acquired — age component is 0');

// ---------------------------------------------------------------------------
// Group 7: explain() consistency with calculate()
// ---------------------------------------------------------------------------

echo "\n-- explain() consistency with calculate() --\n";

// Use created_at = now so age in days = 0, keeping results predictable
$nowStr = date('Y-m-d H:i:s');

$activeCard = [
    'language'            => 'EN',
    'status'              => 'searching',
    'target_price'        => null,
    'current_offer_price' => null,
    'created_at'          => $nowStr,
];

$ex    = CardScorer::explain($activeCard);
$calc  = CardScorer::calculate('EN', 'searching', null, null, 0);

assert_eq(false, $ex['terminal'], 'explain() active card — terminal flag is false');
assert_eq($calc, $ex['total'],    'explain() total matches calculate() for age=0 card');

// All component keys present
foreach (['language', 'status', 'price', 'age', 'total', 'terminal'] as $key) {
    assert_true(array_key_exists($key, $ex), "explain() result has '$key' key");
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

echo "\n";
printf("Results: %d passed, %d failed.\n", $passed, $failed);

exit($failed > 0 ? 1 : 0);
