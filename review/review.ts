/**
 * AI Card Collector — local code review agent
 * Usage: git diff | npx tsx review.ts
 * Usage: npx tsx review.ts --file diff.txt
 * Requires: ANTHROPIC_API_KEY env var
 */

import { generateObject } from "ai";
import { createAnthropic } from "@ai-sdk/anthropic";
import { readFileSync } from "node:fs";
import { SYSTEM_PROMPT, REVIEW_SCHEMA, type Review } from "./review-schema.ts";

const MODEL = "claude-haiku-4-5-20251001"; // fast + cheap for review

async function readDiff(): Promise<string> {
  // --file flag: read from file instead of stdin
  const fileIdx = process.argv.indexOf("--file");
  if (fileIdx !== -1 && process.argv[fileIdx + 1]) {
    return readFileSync(process.argv[fileIdx + 1], "utf8");
  }

  // stdin (git diff | npx tsx review.ts)
  if (!process.stdin.isTTY) {
    const chunks: Buffer[] = [];
    for await (const chunk of process.stdin) chunks.push(chunk as Buffer);
    return Buffer.concat(chunks).toString("utf8");
  }

  // no input — use sample diff for testing
  return SAMPLE_DIFF;
}

async function reviewDiff(diff: string): Promise<Review> {
  if (!diff.trim()) {
    throw new Error("Empty diff — nothing to review");
  }

  const anthropic = createAnthropic({
    apiKey: process.env.ANTHROPIC_API_KEY,
  });

  const { object, usage } = await generateObject({
    model: anthropic(MODEL),
    schema: REVIEW_SCHEMA,
    system: SYSTEM_PROMPT,
    prompt: `Zrecenzuj ten diff:\n\n${diff}`,
    maxTokens: 1024,
  });

  // log token usage to stderr (doesn't pollute JSON stdout)
  console.error(`Tokens: ${usage.promptTokens} in / ${usage.completionTokens} out`);

  return object;
}

function formatOutput(review: Review): string {
  const verdict = review.verdict === "pass" ? "✅ PASS" : "❌ FAIL";
  const scores = [
    `Poprawność:    ${review.implementationCorrectness}/10`,
    `Idiomatyczność: ${review.idiomaticity}/10`,
    `Złożoność:     ${review.complexity}/10`,
    `Testy:         ${review.testRiskCoverage}/10`,
    `Bezpieczeństwo: ${review.securitySafety}/10`,
  ].join("\n");

  return `${verdict}\n\n${scores}\n\n${review.summary}`;
}

// --- entry point ---
const diff = await readDiff();
try {
  const review = await reviewDiff(diff);

  // JSON to stdout (machine-readable, for CI)
  if (process.argv.includes("--json")) {
    console.log(JSON.stringify(review, null, 2));
  } else {
    console.log(formatOutput(review));
  }

  // exit 1 on fail (CI can use this as gate)
  if (review.verdict === "fail") process.exit(1);
} catch (err) {
  console.error("Review failed:", err instanceof Error ? err.message : err);
  process.exit(2);
}

// --- sample diff for testing without git ---
const SAMPLE_DIFF = `
diff --git a/public/card-add.php b/public/card-add.php
index abc1234..def5678 100644
--- a/public/card-add.php
+++ b/public/card-add.php
@@ -1,4 +1,4 @@
-<?php
+<?php declare(strict_types=1);

 require_once __DIR__ . '/../src/bootstrap.php';

@@ -68,7 +68,12 @@ if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $targetPrice  = $raw['target_price']  !== '' ? (float) $raw['target_price']  : null;
     $offerPrice   = $raw['offer_price']   !== '' ? (float) $raw['offer_price']   : null;

-    $score = CardScorer::calculate($language, $status, $targetPrice, $offerPrice, 0);
+    // Extract age calculation to helper method (Faza 2 from plan.md)
+    $createdAt = null; // new card has no history
+    $ageInDays = $createdAt !== null
+        ? CardScorer::ageInDays($createdAt)
+        : 0;
+    $score = CardScorer::calculate($language, $status, $targetPrice, $offerPrice, $ageInDays);

     $data = [
         'name'                => $name,
`;
