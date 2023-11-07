<?php

namespace App\Observers\Discount;

use App\Models\Discount\Discount;
use App\Models\Discount\DiscountCondition;
use App\Models\Discount\DiscountConditionGroup;
use Greensight\CommonMsa\Dto\RoleDto;
use Greensight\CommonMsa\Dto\UserDto;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;

class DiscountConditionObserver
{
    /**
     * Handle the DiscountCondition "created" event.
     *
     * @param  DiscountCondition  $createdCondition
     * @return void
     */
    public function created(DiscountCondition $createdCondition)
    {
        if ($createdCondition->type == DiscountCondition::CUSTOMER) {
            $serviceNotificationService = app(ServiceNotificationService::class);

            /** @var UserService $userService */
            $userService = app(UserService::class);

            /** @var CustomerService $customerService */
            $customerService = app(CustomerService::class);

            collect($createdCondition->getCustomerIds())
                ->unique()
                ->map(function ($customer) use ($customerService) {
                    return $customerService->customers(
                        $customerService->newQuery()
                            ->setFilter('id', $customer)
                    )->first();
                })
                ->filter()
                ->map(function ($user) use ($userService) {
                    return $userService->users(
                        $userService->newQuery()
                            ->setFilter('id', $user->user_id)
                    )->first();
                })
                ->filter()
                ->filter(function (UserDto $userDto) {
                    return array_key_exists(RoleDto::ROLE_SHOWCASE_REFERRAL_PARTNER, $userDto->roles);
                })
                ->each(function (UserDto $userDto) use ($serviceNotificationService, $createdCondition) {
                    if ($createdCondition->discount->value_type == Discount::DISCOUNT_VALUE_TYPE_PERCENT) {
                        $type = '%';
                    } else {
                        $type = ' руб.';
                    }
                    $discount = $createdCondition->conditionGroup->discount;
                    $serviceNotificationService->send($userDto->id, 'sotrudnichestvouroven_personalnoy_skidki_izmenen', [
                        'LVL_DISCOUNT' => sprintf('%s%s', $discount->value, $type),
                        'CUSTOMER_NAME' => $userDto->first_name,
                    ]);
                });
        }

        if (
            $createdCondition->type !== DiscountCondition::DISCOUNT_SYNERGY ||
            empty($createdCondition->condition[DiscountCondition::FIELD_SYNERGY] ?? [])
        ) {
            return;
        }

        $discountIds = $createdCondition->condition[DiscountCondition::FIELD_SYNERGY];
        $discounts = Discount::with('conditionGroups.conditions')
            ->whereIn('id', $discountIds)
            ->get();

        /** @var Discount $discount */
        foreach ($discounts as $discount) {
            $added = false;

            foreach ($discount->conditionGroups as $conditionGroup) {
                $synergyCondition = $conditionGroup->conditions
                    ->firstWhere('type', DiscountCondition::DISCOUNT_SYNERGY);

                if ($synergyCondition) {
                    $synergy = collect($synergyCondition->condition[DiscountCondition::FIELD_SYNERGY] ?? [])
                        ->push($createdCondition->conditionGroup->discount_id)
                        ->unique()
                        ->values()
                        ->toArray();

                    $synergyCondition->setSynergy($synergy);
                    $synergyCondition->saveQuietly();
                    $added = true;
                    break;
                }
            }

            if (!$added) {
                /** @var DiscountConditionGroup $conditionGroup */
                $conditionGroup = $discount->conditionGroups()->create();

                $condition = new DiscountCondition();
                $condition->type = DiscountCondition::DISCOUNT_SYNERGY;
                $condition->setSynergy([$createdCondition->conditionGroup->discount_id]);
                $condition->discount_id = $discount->id; //deprecated
                $condition->discount_condition_group_id = $conditionGroup->id;
                $condition->saveQuietly();
            }
        }
    }

    /**
     * Handle the DiscountCondition "deleted" event.
     *
     * @param  DiscountCondition  $deletedCondition
     * @return void
     */
    public function deleted(DiscountCondition $deletedCondition)
    {
        if ($deletedCondition->type !== DiscountCondition::DISCOUNT_SYNERGY) {
            return;
        }

        if (!$deletedCondition->conditionGroup) {
            return;
        }

        $discountId = $deletedCondition->conditionGroup->discount_id;

        $conditions = DiscountCondition::query()
            ->where('type', DiscountCondition::DISCOUNT_SYNERGY)
            ->whereJsonContains('condition->synergy', $discountId)
            ->orWhere(function ($builder) use ($discountId) {
                return $builder->whereJsonContains('condition->synergy', $discountId);
            })
            ->get();

        /** @var DiscountCondition $condition */
        foreach ($conditions as $condition) {
            $synergy = $condition->condition[DiscountCondition::FIELD_SYNERGY];

            if (($key = array_search($discountId, $synergy)) !== false) {
                unset($synergy[$key]);
                $synergy = array_values($synergy);
                if (empty($synergy)) {
                    if ($condition->conditionGroup && $condition->conditionGroup->conditions->containsOneItem()) {
                        $condition->conditionGroup->delete();
                    }
                    $condition->deleteQuietly();
                } else {
                    $condition->condition = array_merge(
                        $condition->condition,
                        [DiscountCondition::FIELD_SYNERGY => $synergy]
                    );
                    $condition->saveQuietly();
                }
            }
        }
    }
}
