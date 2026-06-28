<?php

declare(strict_types=1);

/**
 * CardRepository integration tests — requires real MySQL.
 *
 * Run:  php tests/IntegrationTest.php
 * Exit: 0 on all pass, 1 on any failure.
 *
 * Env vars (fall back to config/app.php defaults):
 *   DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
 *
 * Tests cover:
 *   - CRUD lifecycle: create → find → update → delete
 *   - user_id scoping: user B cannot read or delete user A's card (IDOR guard)
 *   - listForUser ordering: highest score first
 */

$config = require dirname(__DIR__) . '/config/app.php';
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Card\CardRepository;
use App\Database\Connection;

// ── Helpers ───────────────────────────────────────────────────────────────────

$pass = 0;
$fail = 0;

function ok(string $label, bool $result): void
{
    global $pass, $fail;
    if ($result) {
        echo "  PASS  {$label}\n";
        $pass++;
    } else {
        echo "  FAIL  {$label}\n";
        $fail++;
    }
}

// ── Setup: two isolated test users ───────────────────────────────────────────

$pdo  = Connection::get($config['db']);
$repo = new CardRepository($pdo);

// Clean up any leftover data from a previous run
$pdo->exec("DELETE FROM users WHERE email IN ('inttest_a@ci.local', 'inttest_b@ci.local')");

$pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)")
    ->execute(['inttest_a@ci.local', password_hash('x', PASSWORD_BCRYPT)]);
$userA = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)")
    ->execute(['inttest_b@ci.local', password_hash('x', PASSWORD_BCRYPT)]);
$userB = (int) $pdo->lastInsertId();

// ── Tests ─────────────────────────────────────────────────────────────────────

echo "\nIntegrationTest — CardRepository\n";
echo str_repeat('-', 50) . "\n";

// 1. createForUser returns a positive ID
$cardId = $repo->createForUser($userA, [
    'name'                => 'Charizard',
    'api_card_id'         => 'base1-4',
    'language'            => 'EN',
    'country'             => null,
    'target_price'        => 150.00,
    'current_offer_price' => null,
    'purchase_price'      => null,
    'purchased_at'        => null,
    'source_url'          => null,
    'seller_name'         => null,
    'status'              => 'searching',
    'seller_contact'      => null,
    'notes'               => null,
    'image_url'           => null,
    'difficulty_score'    => 48,
]);
ok('createForUser returns positive ID', $cardId > 0);

// 2. findForUser retrieves the correct card for user A
$card = $repo->findForUser($userA, $cardId);
ok('findForUser returns card for correct user',  $card !== null);
ok('findForUser name matches',                   ($card['name']   ?? '') === 'Charizard');
ok('findForUser status matches',                 ($card['status'] ?? '') === 'searching');

// 3. user B cannot read user A's card (IDOR guard)
$crossCard = $repo->findForUser($userB, $cardId);
ok('findForUser returns null for wrong user_id', $crossCard === null);

// 4. listForUser returns only user A's cards
$listA = $repo->listForUser($userA);
$listB = $repo->listForUser($userB);
ok('listForUser returns at least 1 card for A',  count($listA) >= 1);
ok('listForUser returns 0 cards for B',          count($listB) === 0);
ok('listForUser result contains the created ID', in_array($cardId, array_column($listA, 'id'), false));

// 5. updateForUser modifies status and score
$updated = $repo->updateForUser($userA, $cardId, [
    'name'                => 'Charizard',
    'api_card_id'         => 'base1-4',
    'language'            => 'EN',
    'country'             => null,
    'target_price'        => 150.00,
    'current_offer_price' => 160.00,
    'purchase_price'      => null,
    'purchased_at'        => null,
    'source_url'          => null,
    'seller_name'         => null,
    'status'              => 'contacted',
    'seller_contact'      => null,
    'notes'               => null,
    'image_url'           => null,
    'difficulty_score'    => 55,
]);
ok('updateForUser returns true', $updated);

$afterUpdate = $repo->findForUser($userA, $cardId);
ok('updateForUser status persisted', ($afterUpdate['status'] ?? '') === 'contacted');
ok('updateForUser score persisted',  (int) ($afterUpdate['difficulty_score'] ?? -1) === 55);

// 6. user B cannot update user A's card
$crossUpdate = $repo->updateForUser($userB, $cardId, [
    'name' => 'HACKED', 'api_card_id' => null, 'language' => 'EN',
    'country' => null, 'target_price' => 0, 'current_offer_price' => null,
    'purchase_price' => null, 'purchased_at' => null,
    'source_url' => null, 'seller_name' => null, 'status' => 'abandoned',
    'seller_contact' => null, 'notes' => null, 'image_url' => null, 'difficulty_score' => 0,
]);
ok('updateForUser returns false for wrong user_id', !$crossUpdate);

$afterCrossAttempt = $repo->findForUser($userA, $cardId);
ok('card name unchanged after cross-user update attempt', ($afterCrossAttempt['name'] ?? '') === 'Charizard');

// 7. listForUser ordering: higher score card first
$highScoreId = $repo->createForUser($userA, [
    'name' => 'Blastoise', 'api_card_id' => null, 'language' => 'JP',
    'country' => null, 'target_price' => 200.00, 'current_offer_price' => null,
    'purchase_price' => null, 'purchased_at' => null,
    'source_url' => null, 'seller_name' => null, 'status' => 'searching',
    'seller_contact' => null, 'notes' => null, 'image_url' => null,
    'difficulty_score' => 120,
]);
$sorted = $repo->listForUser($userA);
ok('listForUser orders by difficulty_score DESC',
    count($sorted) >= 2 && (int) $sorted[0]['difficulty_score'] >= (int) $sorted[1]['difficulty_score']);

// 8. deleteForUser removes the card
$deleted = $repo->deleteForUser($userA, $highScoreId);
ok('deleteForUser returns true', $deleted);
ok('card no longer found after delete', $repo->findForUser($userA, $highScoreId) === null);

// 9. user B cannot delete user A's card
$crossDelete = $repo->deleteForUser($userB, $cardId);
ok('deleteForUser returns false for wrong user_id', !$crossDelete);
ok('card still exists after cross-user delete attempt', $repo->findForUser($userA, $cardId) !== null);

// ── Teardown ──────────────────────────────────────────────────────────────────

$pdo->exec("DELETE FROM users WHERE email IN ('inttest_a@ci.local', 'inttest_b@ci.local')");

// ── Result ────────────────────────────────────────────────────────────────────

echo str_repeat('-', 50) . "\n";
echo "  {$pass} passed, {$fail} failed\n\n";

exit($fail > 0 ? 1 : 0);
