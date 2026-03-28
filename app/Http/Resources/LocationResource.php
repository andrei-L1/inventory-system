<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'location_type_id' => $this->location_type_id,
            'location_type' => $this->whenLoaded('locationType', function () {
                return $this->locationType->name;
            }),
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', function () {
                return [
                    'id' => $this->parent->id,
                    'name' => $this->parent->name,
                ];
            }),
            'default_receive_location_id' => $this->default_receive_location_id,
            'default_receive_location' => $this->whenLoaded('defaultReceiveLocation', function () {
                return [
                    'id' => $this->defaultReceiveLocation->id,
                    'name' => $this->defaultReceiveLocation->name,
                ];
            }),
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
