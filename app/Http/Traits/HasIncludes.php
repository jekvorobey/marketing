<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

trait HasIncludes
{
    protected function getIncludes(Request $request): array
    {
        $includes = [];
        $parts = explode(',', $request->get('include', ''));
        foreach ($parts as $part)
        {
            $words = explode('.', $part, 2);

            if (count($words) === 2) {
                $relation = $words[0];
                $field = $words[1];
            } else {
                $relation = $part;
                $field = '*';
            }

            if (!isset($includes[$relation])) {
                $includes[$relation] = [];
            }

            if (!in_array($field, $includes[$relation])) {
                $includes[$relation][] = $field;
            }
        }
        return $includes;
    }

}
