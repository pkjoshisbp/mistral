<?php

namespace App\Services\Widget;

use App\Models\Organization;
use App\Services\Widget\Behaviors\IndianArtZoneWidgetBehavior;

class OrganizationWidgetBehaviorRegistry
{
    /** @var array<int, OrganizationWidgetBehavior> */
    private array $behaviors;

    public function __construct(IndianArtZoneWidgetBehavior $indianArtZoneBehavior)
    {
        $this->behaviors = [$indianArtZoneBehavior];
    }

    public function preferredFaqMatch(Organization $organization, string $message): ?array
    {
        return $this->behaviorFor($organization)?->preferredFaqMatch($organization, $message);
    }

    public function isRelatedFollowUp(
        Organization $organization,
        string $message,
        ?string $lastUserMessage,
        ?string $lastAssistantMessage,
        array $previousContextPayloads,
        ?array $pendingFollowUpState
    ): bool {
        return $this->behaviorFor($organization)?->isRelatedFollowUp(
            $message,
            $lastUserMessage,
            $lastAssistantMessage,
            $previousContextPayloads,
            $pendingFollowUpState
        ) ?? false;
    }

    public function enrichFollowUpSearchQuery(
        Organization $organization,
        string $message,
        ?string $lastUserMessage,
        ?string $lastAssistantMessage,
        bool $isAffirmativeFollowUp
    ): ?string {
        return $this->behaviorFor($organization)?->enrichFollowUpSearchQuery(
            $message,
            $lastUserMessage,
            $lastAssistantMessage,
            $isAffirmativeFollowUp
        );
    }

    public function shouldSkipFaqPolish(Organization $organization, ?array $match): bool
    {
        return $this->behaviorFor($organization)?->shouldSkipFaqPolish($match) ?? false;
    }

    public function shouldSuppressPromotionResponse(Organization $organization, string $message): bool
    {
        return $this->behaviorFor($organization)?->shouldSuppressPromotionResponse($message) ?? false;
    }

    public function answerFamilyLabels(Organization $organization, string $text): array
    {
        return $this->behaviorFor($organization)?->answerFamilyLabels($text) ?? [];
    }

    public function catalogBudgetResponse(
        Organization $organization,
        string $message,
        string $searchQuery,
        array $orderedResults = []
    ): ?string {
        return $this->behaviorFor($organization)?->catalogBudgetResponse(
            $organization,
            $message,
            $searchQuery,
            $orderedResults
        );
    }

    private function behaviorFor(Organization $organization): ?OrganizationWidgetBehavior
    {
        foreach ($this->behaviors as $behavior) {
            if ($behavior->supports($organization)) {
                return $behavior;
            }
        }

        return null;
    }
}
