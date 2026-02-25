<?php

namespace Studio\Totem\Helpers;

use Illuminate\Support\HtmlString;

function columnSort(string $label, string $columnKey, bool $isDefault = false): HtmlString
{
    $icon = '';

    if (request()->has('sort_by')) {
        if (request()->input('sort_by') === $columnKey) {
            $icon = request()->input('sort_direction', 'asc') === 'asc'
                ? ' <span uk-icon="icon: triangle-up; ratio: 0.7"></span>'
                : ' <span uk-icon="icon: triangle-down; ratio: 0.7"></span>';
        }
    } elseif ($isDefault) {
        $icon = request()->input('sort_direction', 'asc') === 'asc'
            ? ' <span uk-icon="icon: triangle-up; ratio: 0.7"></span>'
            : ' <span uk-icon="icon: triangle-down; ratio: 0.7"></span>';
    }

    $order = 'asc';
    if (request()->has('sort_direction')) {
        $order = request()->input('sort_direction') === 'desc' ? 'asc' : 'desc';
    } elseif ($isDefault) {
        $order = 'desc';
    }

    $url = request()->fullUrlWithQuery([
        'sort_by' => $columnKey,
        'sort_direction' => $order,
    ]);

    return new HtmlString('<a href="'.$url.'">'.$label.$icon.'</a>');
}
