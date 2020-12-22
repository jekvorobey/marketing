<?php

namespace App\Http\Traits;

use Greensight\CommonMsa\Models\AbstractModel;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

trait HasUsers
{
    protected function extractIdsFromResult($result, array $fields): array
    {
        $values = [];

        if ($result instanceof AbstractModel)
        {
            foreach (array_keys($fields) as $field)
                $values[] = $result->getAttribute($field . '_id');
        }
        elseif ($result instanceof LengthAwarePaginator)
        {
            foreach ($result->items() as $item) {
                foreach (array_keys($fields) as $field)
                    $values[] = $item->getAttribute($field . '_id');
            }
        }

        return array_values(array_unique(array_filter($values)));
    }

    protected function extractColumnsFromObject($obj, array $columns): ?array
    {
        if (!$obj) {
            return $obj;
        }

        if (in_array('*', $columns))
            return $obj->toArray();

        $response = [];

        foreach ($columns as $column)
            $response[$column] = $obj->$column ?? null;

        return $response;
    }

    protected function assignUsersToResult(array $users, $result, array $fields): void
    {
        if ($result instanceof AbstractModel)
        {
            foreach ($fields as $field => $columns) {
                $user_id = $result->getAttribute($field . '_id');
                $result->$field = $this->extractColumnsFromObject($users[$user_id] ?? null, $columns);
            }
        }
        elseif ($result instanceof LengthAwarePaginator)
        {
            foreach ($result->items() as $item) {
                foreach ($fields as $field => $columns) {
                    $user_id = $item->getAttribute($field . '_id');
                    $item->$field = $this->extractColumnsFromObject($users[$user_id] ?? null, $columns);
                }
            }
        }
    }

    /**
     * Параметр $fields - ассоциативный массив:
     *  - ключ - запрошенная связь, имя колонки в базе формируется как '{ключ}_id'
     *  - значение - массив необходимых полей пользователя, если содержит '*' - в результате будут все доступные поля
     *
     * пример:
     * [
     *      'user' => ['id', 'full_name', 'email', 'login']
     *      'creator' => ['short_name']
     * ]
     *
     * @param AbstractModel|LengthAwarePaginator $result
     * @param array $fields
     */
    protected function attachUsers($result, array $fields): void
    {
        $userIds = $this->extractIdsFromResult($result, $fields);

        $users = $this->fetchUsersByIds($userIds);

        $this->assignUsersToResult($users, $result, $fields);
    }

    protected function attachCustomerUsers($result, array $customer_fields): void
    {
        $customerIds = $this->extractIdsFromResult($result, $customer_fields);

        $customers = $this->fetchCustomerUsersIds($customerIds);

        $userIds = array_values(array_filter(array_unique($customers)));

        $users = $this->fetchUsersByIds($userIds);

        foreach ($customers as $customerId => $userId)
            $customers[$customerId] = $users[$userId] ?? null;

        $this->assignUsersToResult($customers, $result, $customer_fields);
    }

    protected function fetchUsersByIds(array $userIds): array
    {
        $users = [];

        if (empty($userIds))
            return $users;

        /**
         * @var UserService $userService
         */
        $userService = resolve(UserService::class);

        $userQuery = $userService->newQuery()->setFilter('id', $userIds);

        foreach ($userService->users($userQuery) as $user) {
            $users[$user->id] = $user;
        };

        return $users;
    }

    protected function fetchCustomerUsersIds(array $customerIds): array
    {
        if (empty($customerIds))
            return [];

        /**
         * @var CustomerService $customerService
         */
        $customerService = resolve(CustomerService::class);

        $customerQuery = $customerService->newQuery()->setFilter('id', $customerIds);

        $customers = [];

        foreach ($customerService->customers($customerQuery) as $customer) {
            $customers[$customer->id] = $customer->user_id;
        }

        return $customers;
    }
}
