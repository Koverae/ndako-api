<?php

namespace App\Services;

use App\Models\Company\Company;
use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\App\Handlers\AppManagerHandler;
use Koverae\KoveraeBilling\Models\Plan;
use App\Events\CompanyProvisioned;
use App\Jobs\InstallDefaultModules;
use Throwable;

class OnboardingService
{
    /**
     * Check if a company website already exists.
     */
    public function websiteExists(string $url): bool
    {
        return Company::where('website', $url)->exists();
    }

    /**
     * Provision a full company onboarding flow.
     *
     * Flow:
     * 1. Create a new team for the user
     * 2. Resolve correct billing plan
     * 3. Create subscription under the team
     * 4. Create company linked to team and user
     * 5. Install default modules (async job)
     * 6. Update user profile + assign role & permissions
     * 7. Fire domain event for further listeners
     *
     * @return array{
     *     company: Company,
     *     subscription: mixed,
     *     plan_tag: string
     * }
     *
     * @throws Throwable
     */
    public function provision(
        User $user,
        string $name,
        string $type,
        int $languageId,
        int $currencyId,
        int $rooms,
        string $city,
        int $countryId,
        ?string $website,
        string $role,
        string $billingCycle = 'monthly'
    ): array {
        try {
            return DB::transaction(function () use (
                $user,
                $name,
                $type,
                $languageId,
                $currencyId,
                $rooms,
                $city,
                $countryId,
                $website,
                $role,
                $billingCycle
            ) {
                // 1) Create team
                $team = Team::create([
                    'user_id' => $user->id,
                    'uuid'    => (string) Str::uuid(),
                ]);

                // 2) Resolve plan
                $planTag = $this->resolvePlanTag($rooms, $billingCycle);
                $plan = Plan::getByTag($planTag);

                if (!$plan) {
                    throw new \RuntimeException("Plan not found for tag: {$planTag}");
                }

                // 3) Subscription
                $subscription = $team->newSubscription(
                    'main',
                    $plan,
                    'Main subscription',
                    'Customer main subscription',
                    null,
                    'free'
                );

                // 4) Create company
                $company = Company::create([
                    'team_id'             => $team->id,
                    'owner_id'            => $user->id,
                    'name'                => $name,
                    'website'             => $website,
                    'city'                => $city,
                    'country_id'          => $countryId,
                    'industry'            => $type,
                    'size'                => $rooms,
                    'primary_interest'    => 'manage_my_business',
                    'default_currency_id' => $currencyId,
                ]);

                // 5) Install modules async
                InstallDefaultModules::dispatch($company->id, $user->id);

                // 6) Update user profile
                $user->update([
                    'company_id'         => $company->id,
                    'current_company_id' => $company->id,
                    'language_id'        => $languageId,
                ]);

                $user->assignRole($role);
                $user->givePermissionTo('manage_kover_subscription');

                // 7) Fire domain event
                CompanyProvisioned::dispatch($company, $subscription, $user);

                return [
                    'company'      => $company,
                    'subscription' => $subscription,
                    'plan_tag'     => $planTag,
                ];
            });
        } catch (Throwable $e) {
            Log::error('Onboarding failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
            ]);
            throw $e;
        }
    }

    /**
     * Resolve correct plan tag based on rooms and billing cycle.
     */
    private function resolvePlanTag(int $rooms, string $billingCycle = 'yearly'): string
    {
        $billing = in_array($billingCycle, ['monthly', 'yearly']) ? $billingCycle : 'monthly';

        if ($rooms <= 20) {
            return "starter-{$billing}";
        }

        // You can add enterprise logic here later
        return "spark-{$billing}";
    }
}
