<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Services\CancellationService;
use App\Services\CaptureService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EventController extends Controller
{
    public function store(
        Request $request,
        CaptureService $captureService,
        CancellationService $cancellationService,
    ): JsonResponse {
        $rules = [
            'id' => ['required', 'string', 'max:64'],
            'type' => ['required', 'string', 'in:capture,cancellation'],
            'authorization_id' => ['required', 'string'],
            'occurred_at' => [
                'required',
                'string',
                'date_format:Y-m-d\TH:i:s\Z',
            ],
        ];

        if ($request->input('type') === 'capture') {
            $rules = array_merge($rules, [
                'amount_cents' => [
                    'required',
                    function (
                        string $attribute,
                        mixed $value,
                        Closure $fail,
                    ): void {
                        if (! is_int($value)) {
                            $fail(
                                'The amount cents field must be an integer.',
                            );
                        }
                    },
                    'min:1',
                    'max:100000000',
                ],

                'currency' => [
                    'required',
                    'string',
                    'in:BRL',
                ],

                'sequence' => [
                    'required',
                    function (
                        string $attribute,
                        mixed $value,
                        Closure $fail,
                    ): void {
                        if (! is_int($value)) {
                            $fail(
                                'The sequence field must be an integer.',
                            );
                        }
                    },
                    'min:1',
                ],

                'final' => [
                    'required',
                    function (
                        string $attribute,
                        mixed $value,
                        Closure $fail,
                    ): void {
                        if (! is_bool($value)) {
                            $fail(
                                'The final field must be a boolean.',
                            );
                        }
                    },
                ],
            ]);
        }

        $validated = $request->validate($rules);

        if ($validated['type'] === 'capture') {
            return response()->json(
                $captureService->process($validated),
            );
        }

        return response()->json(
            $cancellationService->process($validated),
        );
    }
}
