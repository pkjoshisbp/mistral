<?php

namespace App\Services\Widget;

use App\Models\Organization;

interface OrganizationWidgetBehavior
{
    public function supports(Organization $organization): bool;

    public function preferredFaqMatch(Organization $organization, string $message): ?array;

    public function isRelatedFollowUp(
        string $message,
        ?string $lastUserMessage,
        ?string $lastAssistantMessage,
        array $previousContextPayloads,
        ?array $pendingFollowUpState
    ): bool;

    public function enrichFollowUpSearchQuery(
        string $message,
        ?string $lastUserMessage,
        ?string $lastAssistantMessage,
        bool $isAffirmativeFollowUp
    ): ?string;

    public function shouldSkipFaqPolish(?array $match): bool;

    public function shouldSuppressPromotionResponse(string $message): bool;

    public function answerFamilyLabels(string $text): array;

    public function catalogBudgetResponse(
        Organization $organization,
        string $message,
        string $searchQuery,
        array $orderedResults = []
    ): ?string;
}
