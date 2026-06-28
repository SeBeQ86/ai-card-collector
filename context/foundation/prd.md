---
project: "AI Card Collector"
version: 1
status: draft
created: 2026-06-24
context_type: greenfield
product_type: web-app
target_scale:
  users: small
  qps: low
  data_volume: small
timeline_budget:
  mvp_weeks: 3
  hard_deadline: 2026-07-05
  after_hours_only: false
---

## Vision & Problem Statement

A card collector hunting specific language editions — Portuguese, Japanese, Thai — across multiple regions faces a decision-paralysis problem the moment the list grows past a handful of cards: no tool tells them which card to chase first, what contact history exists with sellers, or where the price ceiling stands relative to available offers.

The insight driving this product: existing tools (Cardmarket, spreadsheets, notes apps) model what you *have*, not what you're *actively hunting*. The language-edition angle — a niche Cardmarket surfaces poorly — and the seller-communication friction (polite outreach to foreign sellers in their language) are both invisible to mainstream tool builders.

## User & Persona

### Primary persona

Adult card collector (for self or a child) who searches for specific language versions of cards. Reaches for this product when their wanted-list grows past what a notebook or spreadsheet can hold and they start losing track of which cards are real priorities, what they've already offered to sellers, and what price limits they set.

One user. Auth exists for data protection, not multi-tenancy.

## Success Criteria

### Primary
- The end-to-end flow works: log in → add a card with all fields → app computes a difficulty score and priority → generate a seller message in English or Portuguese → card appears on the wanted list with its priority and status visible.

### Secondary
- The priority score feels intuitively correct: when the collector views the scored list, the top card genuinely feels like the most urgent one to chase (not an arbitrary number).

### Guardrails
- No card data is ever visible to anyone other than the authenticated owner of that data.
- Adding a card always persists correctly — no silent data loss of price limits, statuses, or notes.

## User Stories

### US-01: Collector adds a wanted card and sees its priority

- **Given** a logged-in collector
- **When** they fill in and save a new wanted card (name, language, source country, price limit, status = Searching, note)
- **Then** the card appears on the wanted list with a computed difficulty score and priority rank

#### Acceptance Criteria
- Name, language, price limit, and status are required before save
- Difficulty score is computed automatically on save — no manual trigger
- Card is immediately visible on the list after save, sorted by priority

### US-02: Collector generates a seller message

- **Given** a logged-in collector viewing a card's details
- **When** they choose "Generate seller message" and select a language (English or Portuguese)
- **Then** a pre-filled message appears with the card's name, language edition, and price limit substituted in, ready to copy

#### Acceptance Criteria
- Message appears immediately when a language is selected — no loading or waiting
- Both English and Portuguese are available as language choices
- Message text is copyable with one action

## Functional Requirements

### Authentication

- FR-001: Collector can log in with email and password. Priority: must-have
  > Socrates: Counter-argument considered: "could skip auth and run locally behind an obscure URL." Resolution: rejected — auth is essential even for a personal tool hosted on any server; security by obscurity is not a guardrail.
- FR-002: Collector can log out. Priority: must-have
  > Socrates: No counter-argument raised; follows directly from FR-001.

### Wanted cards

- FR-003: Collector can add a wanted card with name, language, source country/region (optional), price limit, status, and note. Priority: must-have
  > Socrates: Counter-argument accepted: source country/region is optional — not every card has a known region; scoring degrades gracefully when absent. Required fields: name, language, price limit, status.
- FR-004: Collector can edit any field of an existing wanted card. Priority: must-have
  > Socrates: No counter-argument raised; edit is the standard complement to add.
- FR-005: Collector can delete a wanted card. Priority: must-have
  > Socrates: No counter-argument raised; delete is needed to keep the list clean as cards are acquired or abandoned.
- FR-006: Collector can view the full wanted list. Priority: must-have
  > Socrates: No counter-argument raised; the list is the core product surface.

### Status management

- FR-007: Collector can set a card's status by selecting from: Searching / Contacted / Offer received / Acquired / Abandoned. Priority: must-have
  > Socrates: Counter-argument considered: "collapse Contacted and Offer received — too similar." Resolution: rejected — the distinction between initial contact and an actual offer is a meaningful negotiation-phase signal for the scorer.

### Priority & scoring

- FR-008: System automatically computes a difficulty score for each card based on language, status, price limit, and time since added. Priority: must-have
  > Socrates: Counter-argument considered: "auto-scoring adds complexity; manual sort is simpler." Resolution: rejected — auto-scoring is the domain rule that distinguishes this product from a spreadsheet. This is the non-trivial behavior.
- FR-009: Collector can view cards sorted by priority/difficulty score. Priority: must-have
  > Socrates: Counter-argument considered: "old Searching cards could get buried." Resolution: moot — time since added is already an input to FR-008, so long-standing unresolved cards naturally bubble up.

### Seller message generator

- FR-010: Collector can generate a seller message for a card in English. Priority: must-have
  > Socrates: No counter-argument raised; English is the baseline for international seller communication.
- FR-011: Collector can generate a seller message for a card in Portuguese. Priority: must-have
  > Socrates: Counter-argument considered: "ship English only in MVP, add Portuguese in v2." Resolution: rejected — PT/BR sellers are the primary real-world use case; Portuguese is core, not an enhancement.
- FR-012: Collector can refresh market prices for all active cards via a single "Odśwież ceny" button that calls the TCGdex API for every card with a linked api_card_id; the difficulty score is recomputed after each refresh. Priority: should-have
- FR-013: Collector can edit seller message templates per locale (EN, DE, FR, ES, PT, JA) stored in the database; custom templates use {{token}} placeholders and override the built-in PHP fallbacks at render time. Priority: should-have

## Non-Functional Requirements

- The app is usable on the collector's primary browser and device without any installation step.
- Any operation the collector initiates that takes longer than two seconds shows continuous visible progress (not a frozen screen).

## Business Logic

The system rates how hard it is to acquire each card based on language rarity, current pursuit status, how long it has been on the list, and whether the asking price is realistic — and surfaces the hardest-to-find cards first.

The rule consumes four user-facing inputs: (1) language edition of the card — non-English editions (Japanese, Portuguese, Thai, etc.) score as harder to source; (2) current status in the hunt lifecycle — cards still at Searching score harder than those at Contacted or Offer received; (3) price limit relative to typical market price — a very low price cap makes acquisition harder; (4) time since the card was added — the longer a card sits unresolved, the higher its urgency. The output is a difficulty score that determines the card's position in the sorted wanted list. The collector encounters it as a ranked list where the most urgent, hardest-to-get cards appear at the top.

## Access Control

Single user; email + password login. One role: collector. All app features are gated behind authentication. An unauthenticated request to any gated route redirects to the login page. No sign-up flow in MVP — account is seeded directly (single known user).

## Non-Goals

- No Cardmarket or marketplace API integration — price data and availability are entered manually by the collector; automated scraping or price lookup is out of scope.
- No AI-generated seller messages — the message generator uses fixed templates populated from card fields; no language-model capability and no external service dependency.
- No public profiles or sharing between users — the wanted list is private to the single authenticated user; no discovery, community, or export-to-public features.
- No native mobile app — the product is a browser-based web app; it may be mobile-responsive but there is no iOS or Android build.

## Open Questions

1. **Timeline vs. deadline gap** — The estimated MVP scope is 3 weeks; the hard deadline is July 5, 2026 (11 days from today). If implementation reveals the full 11-FR list cannot ship by then, which FRs are deferral candidates? Owner: collector. Block: no (shapes implementation plan, not PRD).
