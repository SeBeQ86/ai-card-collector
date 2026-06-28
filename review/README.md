# Review Agent — AI Card Collector

Local code review agent using Vercel AI SDK + Claude Haiku.

## Setup

```bash
cd review/
npm install
```

Set env var:
```bash
# Windows PowerShell
$env:ANTHROPIC_API_KEY = "sk-ant-..."
```

## Usage

```bash
# Review current working changes
git diff | npx tsx review.ts

# Review staged changes
git diff --cached | npx tsx review.ts

# Review last commit
git diff HEAD~1 | npx tsx review.ts

# Test with built-in sample diff (no git needed)
npx tsx review.ts

# JSON output (for CI)
git diff | npx tsx review.ts --json
```

## Exit codes

- `0` — verdict: pass
- `1` — verdict: fail (CI can use as merge gate)
- `2` — agent error (API key missing, network, etc.)

## Model

Uses `claude-haiku-4-5-20251001` — fast and cheap for review tasks.
Swap in `review.ts:MODEL` for any model available on Anthropic API.
