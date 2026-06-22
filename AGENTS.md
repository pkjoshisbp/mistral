# Repository Architecture Rules

## Organization-Specific Widget Behavior

- Do not add organization names, slugs, business rules, FAQ exceptions, query phrases, or tenant-specific routing branches to `laravel/app/Http/Controllers/WidgetController.php`.
- `WidgetController` must remain organization-agnostic and limited to shared widget orchestration.
- Put tenant-specific behavior behind `App\Services\Widget\OrganizationWidgetBehaviorRegistry`.
- Add each tenant's rules in a dedicated class under `laravel/app/Services/Widget/Behaviors/`.
- A tenant behavior may own preferred FAQ matching, query enrichment, follow-up continuity, response-routing overrides, and optional response-polish decisions.
- When fixing a tenant issue, first decide whether the behavior is universally correct. Only universal behavior belongs in shared controller/service logic.
- Add focused tests for the tenant behavior class. Do not encode tenant names or examples in global controller tests.
