<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    private ?array $token = null;
    private ?string $message = null;

    /**
     * Create a new resource instance with token.
     */
    public static function withToken($user, array $token, ?string $message = null): self
    {
        $resource = new self($user);
        $resource->token = $token;
        $resource->message = $message;
        return $resource;
    }

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $data = [
            'user' => new UserResource($this->resource),
        ];

        if ($this->token) {
            $data['token'] = $this->token;
        }

        if ($this->message) {
            $data['message'] = $this->message;
        }

        return $data;
    }
}
