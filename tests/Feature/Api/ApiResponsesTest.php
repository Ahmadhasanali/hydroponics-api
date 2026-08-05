<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Traits\ApiResponses;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiResponsesTest extends TestCase
{
    private function controller(): object
    {
        return new class
        {
            use ApiResponses;

            public function callSuccess(mixed $data, string $message, int $code)
            {
                return $this->successResponse($data, $message, $code);
            }

            public function callError(string $message, int $code, array $errors)
            {
                return $this->errorResponse($message, $code, $errors);
            }

            public function callPaginated(LengthAwarePaginator $paginator, string $message)
            {
                return $this->paginatedResponse($paginator, $message);
            }
        };
    }

    private function paginator(Collection $items, int $perPage, int $currentPage = 1): LengthAwarePaginator
    {
        return new LengthAwarePaginator($items->forPage($currentPage, $perPage), $items->count(), $perPage, $currentPage);
    }

    #[Test]
    public function success_response_has_the_correct_envelope(): void
    {
        $response = $this->controller()->callSuccess(['id' => 1], 'Created', 201);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame([
            'success' => true,
            'data' => ['id' => 1],
            'message' => 'Created',
        ], $response->getData(true));
    }

    #[Test]
    public function success_response_uses_defaults_when_only_data_is_provided(): void
    {
        $response = $this->controller()->callSuccess(['foo' => 'bar'], '', 200);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'success' => true,
            'data' => ['foo' => 'bar'],
            'message' => '',
        ], $response->getData(true));
    }

    #[Test]
    public function error_response_casts_errors_to_an_object(): void
    {
        $response = $this->controller()->callError('Validation failed', 422, ['email' => ['The email field is required.']]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => ['email' => ['The email field is required.']],
        ], $response->getData(true));
    }

    #[Test]
    public function error_response_defaults_errors_to_empty_object(): void
    {
        $response = $this->controller()->callError('Not found', 404, []);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'message' => 'Not found',
        ], array_intersect_key($response->getData(true), ['success' => 1, 'message' => 1]));
        $this->assertStringContainsString('"errors":{}', $response->getContent());
    }

    #[Test]
    public function paginated_response_exposes_meta_structure(): void
    {
        $items = collect([['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]]);
        $response = $this->controller()->callPaginated($this->paginator($items, 2, 1), 'Samples');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'success' => true,
            'data' => [['id' => 1], ['id' => 2]],
            'meta' => [
                'current_page' => 1,
                'last_page' => 2,
                'per_page' => 2,
                'total' => 4,
            ],
            'message' => 'Samples',
        ], $response->getData(true));
    }
}
