<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthorizationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:64'],
            'card_token' => ['required', 'string'],
            'amount_cents' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_int($value)) {
                        $fail('The amount cents field must be an integer.');
                    }
                },
                'min:1',
                'max:100000000',
            ],
            'currency' => ['required', 'string', 'in:BRL'],
            'mcc' => ['required', 'string', 'regex:/^\d{4}$/'],
            'merchant' => ['required', 'array'],
            'merchant.name' => ['required', 'string'],
            'merchant.city' => ['required', 'string'],
            'merchant.country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'occurred_at' => [
                'required',
                'string',
                'date_format:Y-m-d\TH:i:s\Z',
            ],
        ]);

        return response()->json(['received' => true, 'id' => $validated['id']]);
    }
}
