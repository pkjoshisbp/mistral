# Org-Neutral Semantic Authoring Guidelines

Use this for every organization (healthcare, ecommerce, education, SaaS, etc.) to improve retrieval without keyword stuffing.

## 1) Content shape per record
- **Entity (required):** The primary thing users ask for (plan, test, product, service, policy).
- **Description (required):** 1-3 plain sentences with factual details.
- **Context fields (recommended):** category, plan type, billing period, pricing/currency, limits, requirements, availability.
- **Keywords (optional):** only concise user-intent terms; avoid repeating the same phrase many times.

## 2) Good pattern
Example:
- `Starter Plan – $9/month.`
- `Designed for individuals and small teams.`

Better semantic version (no stuffing):
- Entity: `starter plan`
- Description: `Most affordable subscription for individuals and small teams. Monthly billing with essential features.`
- Keywords (optional): `entry level, basic plan, beginner, low cost monthly`

Enterprise example:
- Entity: `enterprise plan`
- Description: `Built for large organizations and high-volume usage with advanced controls and dedicated support.`
- Keywords (optional): `corporate, business, company, large team, scale`

## 3) Synonym strategy (recommended)
- Add **intent synonyms**, not repeated duplicates.
- Keep keywords compact (typically 6-16 useful terms).
- Prefer phrase diversity: role (`corporate buyer`), size (`large team`), objective (`high volume`), commercial intent (`pricing`, `monthly`).

## 4) System behavior (already implemented)
- Ingestion auto-enriches metadata with:
  - `entity` / `primary_entity`
  - `keywords` / `search_keywords`
  - `semantic_terms` / `semantic_text`
- Search and reranking use entity + keyword + semantic fields together.
- Query intent (pricing/enterprise/demo/etc.) adjusts ranking without hardcoded per-question patches.

## 5) Data quality rules
- Do not paste unrelated terms in one row.
- Keep one primary entity per row when possible.
- If query is ambiguous, add KB rows instead of hacks in response logic.
- Update rows with real commercial terms users actually ask.

## 6) Optional AI-assisted expansion
- If your team prefers, generate synonyms from the description using an LLM.
- Keep human review in the loop before publishing to avoid noisy terms.
- Suggested target: 8-20 high-quality semantic terms per row.
- Runtime toggle for ingestion-time LLM expansion (Laravel env):
  - `AI_SEMANTIC_ENRICH_WITH_LLM=true` to enable
  - `AI_SEMANTIC_ENRICH_WITH_LLM=false` (default) for deterministic-only enrichment
